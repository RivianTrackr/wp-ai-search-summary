<?php
declare(strict_types=1);

namespace RivianTrackr\AISearchSummary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles communication with the Anthropic API.
 *
 * Includes prompt construction, request execution with retry logic,
 * and response normalization.
 */
class ApiHandler {

	/**
	 * Build the system prompt for AI search.
	 *
	 * @param string $site_name Site display name.
	 * @param string $site_desc Optional site description.
	 * @return string System message for the AI.
	 */
	public function build_system_prompt( string $site_name, string $site_desc = '' ): string {
		$desc_suffix = ! empty( $site_desc ) ? ', ' . $site_desc : '';

		return "You are the AI-powered search assistant built into {$site_name}{$desc_suffix}.
    You are part of the {$site_name} platform — users are reading your answers directly on the site. Speak as {$site_name}'s search assistant, not as a generic external AI.
    Use the provided posts as your entire knowledge base.
    Answer the user query based only on these posts.
    When referencing information, naturally attribute it to {$site_name} coverage (e.g. \"Based on {$site_name}'s reporting...\", \"As covered on {$site_name}...\", \"{$site_name} reported that...\"). Do not over-attribute — one or two natural references per answer is enough.
    Prefer newer posts over older ones when there is conflicting or overlapping information, especially for news, software updates, or product changes.
    If something is not covered, say that {$site_name} does not have that information yet instead of making something up.

    IMPORTANT: This is a one-way search interface - users cannot reply or provide clarification. Never ask follow-up questions, never ask the user to clarify, and never suggest they tell you more. Instead, provide the most comprehensive answer possible covering all likely interpretations of their query. If a query is ambiguous, briefly cover the most relevant possibilities.

    Always respond as a single JSON object using this structure:
    {
      \"answer_html\": \"HTML formatted summary answer for the user\",
      \"results\": [
         {
           \"id\": 123,
           \"title\": \"Post title\",
           \"url\": \"https://...\",
           \"excerpt\": \"Short snippet\",
           \"type\": \"post or page\"
         }
      ]
    }

    The results array should list up to 5 of the most relevant posts you used when creating the summary, so they can be shown as sources under the answer.";
	}

	/**
	 * Format posts into a text block for the AI prompt.
	 *
	 * @param array $posts Array of post data arrays.
	 * @return string Formatted text.
	 */
	public function format_posts_for_prompt( array $posts ): string {
		$text = '';
		foreach ( $posts as $p ) {
			$date = isset( $p['date'] ) ? $p['date'] : '';
			$text .= "ID: {$p['id']}\n";
			$text .= "Title: {$p['title']}\n";
			$text .= "URL: {$p['url']}\n";
			$text .= "Type: {$p['type']}\n";
			if ( $date ) {
				$text .= "Published: {$date}\n";
			}
			$text .= "Content: {$p['content']}\n";
			$text .= "-----\n";
		}
		return $text;
	}

	/**
	 * Call the Anthropic Claude API with retry logic.
	 *
	 * @param string $api_key   API key.
	 * @param string $model     Model ID.
	 * @param string $query     User search query.
	 * @param array  $posts     Posts data for context.
	 * @param array  $options   Plugin options.
	 * @return array Normalized API response or array with 'error' key.
	 */
	public function call_anthropic( string $api_key, string $model, string $query, array $posts, array $options ): array {
		if ( empty( $api_key ) ) {
			return array( 'error' => 'API key is missing. Please configure the plugin settings.' );
		}

		if ( empty( $model ) ) {
			return array( 'error' => 'No AI model is selected. Choose a model in the plugin settings.' );
		}

		$endpoint = 'https://api.anthropic.com/v1/messages';

		$posts_text = $this->format_posts_for_prompt( $posts );
		$site_name  = ! empty( $options['site_name'] ) ? $options['site_name'] : get_bloginfo( 'name' );
		$site_desc  = ! empty( $options['site_description'] ) ? $options['site_description'] : '';

		$system_message = $this->build_system_prompt( $site_name, $site_desc );
		$user_message   = "User search query: {$query}\n\nHere are the posts from the site (with newer posts listed first where possible):\n\n{$posts_text}";

		$configured_tokens = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : RIVIANTRACKR_MAX_TOKENS;

		// Note: no assistant-turn prefill here — Claude 4.6+ models reject
		// last-turn prefills with a 400. JSON output is enforced through
		// structured outputs (output_config.format) on models that support it
		// and requested via the system prompt everywhere; parse_ai_content()
		// still handles markdown fences or preamble via brace extraction.
		$body = array(
			'model'      => $model,
			'max_tokens' => $configured_tokens,
			'system'     => $system_message,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $user_message,
				),
			),
		);

		$output_config = $this->build_output_config( $model, $options );
		if ( ! empty( $output_config ) ) {
			$body['output_config'] = $output_config;
		}

		$args = array(
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => RIVIANTRACKR_ANTHROPIC_API_VERSION,
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => isset( $options['request_timeout'] ) ? (int) $options['request_timeout'] : RIVIANTRACKR_API_TIMEOUT,
		);

		$result = $this->execute_with_retry(
			function () use ( $endpoint, $args ) {
				return $this->make_anthropic_request( $endpoint, $args );
			}
		);

		// If the model rejects output_config (effort or JSON schema support
		// differs per model family), retry once without it and remember that
		// for a day so the extra round-trip is not repeated on every search.
		if ( ! empty( $output_config ) && isset( $result['error'], $result['_http_code'] ) && 400 === (int) $result['_http_code'] ) {
			set_transient( $this->output_config_unsupported_key( $model ), 1, 86400 );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RivianTrackr AI Search Summary] Model ' . $model . ' rejected output_config; retrying without it.' );
			}
			unset( $body['output_config'] );
			$args['body'] = wp_json_encode( $body );

			$result = $this->execute_with_retry(
				function () use ( $endpoint, $args ) {
					return $this->make_anthropic_request( $endpoint, $args );
				}
			);
		}

		unset( $result['_http_code'] );

		return $result;
	}

	/**
	 * Build the output_config block (effort + structured output schema) for a model.
	 *
	 * Effort is only sent to models that accept it (Opus 4.5+, Sonnet 4.6+,
	 * Fable/Mythos); Haiku 4.5 and older models reject the parameter. The JSON
	 * schema is only sent to models documented as supporting structured
	 * outputs. A model that returned HTTP 400 for output_config in the last
	 * day gets an empty config.
	 *
	 * @param string $model   Model ID.
	 * @param array  $options Plugin options (reads 'effort').
	 * @return array output_config array, or empty array when nothing applies.
	 */
	public function build_output_config( string $model, array $options ): array {
		if ( get_transient( $this->output_config_unsupported_key( $model ) ) ) {
			return array();
		}

		$config = array();

		$effort = isset( $options['effort'] ) ? (string) $options['effort'] : '';
		if ( $effort !== '' && $this->model_supports_effort( $model ) ) {
			$config['effort'] = $effort;
		}

		if ( $this->model_supports_structured_output( $model ) ) {
			$config['format'] = array(
				'type'   => 'json_schema',
				'schema' => $this->get_response_schema(),
			);
		}

		return $config;
	}

	/**
	 * JSON schema for the summary response, used with structured outputs.
	 *
	 * @return array JSON schema.
	 */
	public function get_response_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'answer_html' => array( 'type' => 'string' ),
				'results'     => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'      => array( 'type' => 'integer' ),
							'title'   => array( 'type' => 'string' ),
							'url'     => array( 'type' => 'string' ),
							'excerpt' => array( 'type' => 'string' ),
							'type'    => array( 'type' => 'string' ),
						),
						'required'             => array( 'id', 'title', 'url', 'excerpt', 'type' ),
						'additionalProperties' => false,
					),
				),
			),
			'required'             => array( 'answer_html', 'results' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Parse a Claude model ID into family and version.
	 *
	 * Handles current IDs (claude-sonnet-5, claude-haiku-4-5) and dated
	 * snapshots (claude-opus-4-1-20250805, claude-sonnet-4-20250514). Legacy
	 * "claude-3-5-haiku" style IDs return null.
	 *
	 * @param string $model Model ID.
	 * @return array{family: string, version: float}|null
	 */
	public function parse_model_id( string $model ): ?array {
		if ( ! preg_match( '/^claude-(opus|sonnet|haiku|fable|mythos)-(\d+)(?:-(\d{1,2}))?(?:-\d{8})?$/', $model, $m ) ) {
			return null;
		}

		$major = (int) $m[2];
		$minor = isset( $m[3] ) && $m[3] !== '' ? (int) $m[3] : 0;

		return array(
			'family'  => $m[1],
			'version' => (float) ( $major . '.' . $minor ),
		);
	}

	/**
	 * Whether a model accepts output_config.effort.
	 *
	 * @param string $model Model ID.
	 * @return bool
	 */
	public function model_supports_effort( string $model ): bool {
		$info = $this->parse_model_id( $model );
		if ( ! $info ) {
			return false;
		}

		switch ( $info['family'] ) {
			case 'fable':
			case 'mythos':
				return true;
			case 'opus':
				return $info['version'] >= 4.5;
			case 'sonnet':
				return $info['version'] >= 4.6;
			default:
				return false;
		}
	}

	/**
	 * Whether a model supports structured outputs (output_config.format).
	 *
	 * @param string $model Model ID.
	 * @return bool
	 */
	public function model_supports_structured_output( string $model ): bool {
		$info = $this->parse_model_id( $model );
		if ( ! $info ) {
			return false;
		}

		switch ( $info['family'] ) {
			case 'fable':
			case 'mythos':
				return true;
			case 'opus':
				return in_array( $info['version'], array( 4.1, 4.5, 4.8 ), true ) || $info['version'] >= 5;
			case 'sonnet':
				return $info['version'] >= 5;
			case 'haiku':
				return $info['version'] >= 4.5;
			default:
				return false;
		}
	}

	/**
	 * Transient key that records a model rejecting output_config.
	 *
	 * @param string $model Model ID.
	 * @return string Transient key.
	 */
	private function output_config_unsupported_key( string $model ): string {
		return 'riviantrackr_no_outcfg_' . substr( hash( 'sha256', $model ), 0, 24 );
	}

	/**
	 * Execute an API request with retry logic.
	 *
	 * @param callable $request_fn Function that returns a result array.
	 * @return array Normalized API response.
	 */
	private function execute_with_retry( callable $request_fn ): array {
		$max_retries = 2;
		$attempt     = 0;
		$last_error  = null;

		while ( $attempt <= $max_retries ) {
			$result = $request_fn();

			if ( isset( $result['success'] ) && $result['success'] ) {
				$data = $this->normalize_anthropic_response( $result['data'] );

				if ( $attempt > 0 ) {
					$data['_retry_count'] = $attempt;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( '[RivianTrackr AI Search Summary] Request succeeded after ' . $attempt . ' retry(ies)' );
					}
				}
				return $data;
			}

			$is_retryable = isset( $result['retryable'] ) && $result['retryable'];
			$last_error   = $result;

			if ( ! $is_retryable || $attempt >= $max_retries ) {
				break;
			}

			$delay = pow( 2, $attempt );
			sleep( $delay );
			$attempt++;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RivianTrackr AI Search Summary] Retry attempt ' . ( $attempt + 1 ) . ' after ' . $delay . 's delay' );
			}
		}

		$error_msg = $last_error['error'] ?? 'Unknown error occurred.';
		if ( $attempt > 0 ) {
			$error_msg .= ' (after ' . ( $attempt + 1 ) . ' attempts)';
		}

		$failure = array( 'error' => $error_msg );
		if ( isset( $last_error['code'] ) ) {
			$failure['_http_code'] = (int) $last_error['code'];
		}
		return $failure;
	}

	/**
	 * Normalize an Anthropic response to the internal chat-completion format
	 * consumed by parse_ai_content().
	 *
	 * @param array $api_data Raw Anthropic response.
	 * @return array Normalized format.
	 */
	private function normalize_anthropic_response( array $api_data ): array {
		$content_text = '';
		if ( ! empty( $api_data['content'] ) && is_array( $api_data['content'] ) ) {
			foreach ( $api_data['content'] as $block ) {
				if ( isset( $block['type'] ) && $block['type'] === 'text' && isset( $block['text'] ) ) {
					$content_text .= $block['text'];
				}
			}
		}

		$stop_reason   = isset( $api_data['stop_reason'] ) ? $api_data['stop_reason'] : 'end_turn';
		$finish_reason = 'stop';
		if ( $stop_reason === 'max_tokens' ) {
			$finish_reason = 'length';
		} elseif ( $stop_reason === 'refusal' ) {
			// Safety classifiers declined the request (HTTP 200). Any content
			// present is not guaranteed to match the schema.
			$finish_reason = 'content_filter';
		}

		return array(
			'choices' => array(
				array(
					'message' => array(
						'content' => $content_text,
						'refusal' => null,
					),
					'finish_reason' => $finish_reason,
				),
			),
		);
	}

	/**
	 * Make an HTTP request to Anthropic.
	 *
	 * @param string $endpoint API endpoint URL.
	 * @param array  $args     wp_remote_post args.
	 * @return array{success: bool, data?: array, error?: string, retryable?: bool}
	 */
	private function make_anthropic_request( string $endpoint, array $args ): array {
		$response = wp_safe_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $this->handle_connection_error( $response );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			// Anthropic uses 529 for overloaded
			if ( $code === 529 ) {
				return array(
					'success'   => false,
					'error'     => 'Anthropic API is temporarily overloaded. Please try again later.',
					'retryable' => true,
				);
			}
			return $this->handle_http_error( $code, $body, 'Anthropic' );
		}

		return $this->parse_json_response( $body );
	}

	/**
	 * Handle a WP_Error from wp_remote_post.
	 *
	 * @param \WP_Error $response WordPress error object.
	 * @return array Result array.
	 */
	private function handle_connection_error( \WP_Error $response ): array {
		$error_msg = $response->get_error_message();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[RivianTrackr AI Search Summary] API request error: ' . $error_msg );
		}

		$is_timeout    = strpos( $error_msg, 'cURL error 28' ) !== false || strpos( $error_msg, 'timed out' ) !== false;
		$is_connection = strpos( $error_msg, 'cURL error 6' ) !== false || strpos( $error_msg, 'resolve host' ) !== false;

		if ( $is_timeout ) {
			// Not retryable: the browser aborts after one request timeout, so
			// a retry would spend another full timeout (and another API call)
			// on a response nobody will see.
			return array(
				'success'   => false,
				'error'     => 'Request timed out. The AI service may be slow right now. Please try again.',
				'retryable' => false,
			);
		}
		if ( $is_connection ) {
			return array(
				'success'   => false,
				'error'     => 'Could not connect to AI service. Please check your internet connection.',
				'retryable' => true,
			);
		}

		return array(
			'success'   => false,
			'error'     => 'Could not connect to AI service. Please try again.',
			'retryable' => true,
		);
	}

	/**
	 * Handle an HTTP error response.
	 *
	 * @param int    $code     HTTP status code.
	 * @param string $body     Response body.
	 * @param string $provider Provider name for error messages.
	 * @return array Result array.
	 */
	private function handle_http_error( int $code, string $body, string $provider ): array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[RivianTrackr AI Search Summary] ' . $provider . ' HTTP error ' . $code . ' body: ' . $body );
		}

		if ( $code === 429 ) {
			return array(
				'success'   => false,
				'code'      => $code,
				'error'     => $provider . ' rate limit exceeded. Please try again in a few moments.',
				'retryable' => true,
			);
		}

		if ( $code >= 500 && $code < 600 ) {
			return array(
				'success'   => false,
				'code'      => $code,
				'error'     => $provider . ' service temporarily unavailable. Please try again later.',
				'retryable' => true,
			);
		}

		if ( $code === 401 ) {
			return array(
				'success'   => false,
				'code'      => $code,
				'error'     => 'Invalid ' . $provider . ' API key. Please check your plugin settings.',
				'retryable' => false,
			);
		}

		if ( $code === 400 ) {
			return array(
				'success'   => false,
				'code'      => $code,
				'error'     => $provider . ' rejected the request (HTTP 400). Check the selected model and plugin settings.',
				'retryable' => false,
			);
		}

		return array(
			'success'   => false,
			'code'      => $code,
			'error'     => 'AI service error. Please try again later.',
			'retryable' => false,
		);
	}

	/**
	 * Parse a JSON response body.
	 *
	 * @param string $body Response body.
	 * @return array Result array.
	 */
	private function parse_json_response( string $body ): array {
		$decoded = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RivianTrackr AI Search Summary] Failed to decode response: ' . json_last_error_msg() );
			}
			return array(
				'success'   => false,
				'error'     => 'Could not understand AI response. Please try again.',
				'retryable' => true,
			);
		}

		return array(
			'success' => true,
			'data'    => $decoded,
		);
	}

	/**
	 * Parse the AI content from a normalized API response.
	 *
	 * Handles JSON extraction, nested JSON unwrapping, and validation.
	 *
	 * @param array  $api_response Normalized API response.
	 * @param string $ai_error     Output: error message if parsing fails.
	 * @return array|null Parsed data or null on failure.
	 */
	public function parse_ai_content( array $api_response, string &$ai_error ): ?array {
		// Check for model refusal (explicit refusal field, or a "refusal"
		// stop reason mapped to content_filter during normalization).
		if ( ! empty( $api_response['choices'][0]['message']['refusal'] ) ) {
			$ai_error = 'The AI model declined to answer this query.';
			return null;
		}
		if ( ( $api_response['choices'][0]['finish_reason'] ?? '' ) === 'content_filter' ) {
			$ai_error = 'The response was filtered by content policy. Please try a different search.';
			return null;
		}

		// Get content from multiple possible locations
		$raw_content = null;
		if ( ! empty( $api_response['choices'][0]['message']['content'] ) ) {
			$raw_content = $api_response['choices'][0]['message']['content'];
		} elseif ( ! empty( $api_response['choices'][0]['text'] ) ) {
			$raw_content = $api_response['choices'][0]['text'];
		} elseif ( ! empty( $api_response['output'] ) ) {
			$raw_content = is_array( $api_response['output'] )
				? wp_json_encode( $api_response['output'] )
				: $api_response['output'];
		}

		if ( empty( $raw_content ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RivianTrackr AI Search Summary] Empty response. Full API response: ' . wp_json_encode( $api_response ) );
			}
			$finish_reason = $api_response['choices'][0]['finish_reason'] ?? 'unknown';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RivianTrackr AI Search Summary] Empty response with finish_reason: ' . $finish_reason );
			}
			if ( $finish_reason === 'content_filter' ) {
				$ai_error = 'The response was filtered by content policy. Please try a different search.';
			} elseif ( $finish_reason === 'length' ) {
				$ai_error = 'The response was truncated. Please try a simpler search.';
			} else {
				$ai_error = 'AI summary is not available for this search. Please try again.';
			}
			return null;
		}

		// Decode JSON content
		if ( is_array( $raw_content ) ) {
			$decoded = $raw_content;
		} else {
			$decoded = json_decode( $raw_content, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$first = strpos( $raw_content, '{' );
				$last  = strrpos( $raw_content, '}' );
				if ( $first !== false && $last !== false && $last > $first ) {
					$json_candidate = substr( $raw_content, $first, $last - $first + 1 );
					$decoded        = json_decode( $json_candidate, true );
				}
			}
		}

		if ( ! is_array( $decoded ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$raw_sample = is_string( $raw_content ) ? substr( $raw_content, 0, 2000 ) : wp_json_encode( $raw_content );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RivianTrackr AI Search Summary] Could not parse AI content. Raw content (first 2000 chars): ' . $raw_sample );
			}

			// A response cut off at the max_tokens limit produces invalid JSON.
			// Surface that specifically so it isn't mistaken for a service issue.
			$finish_reason = $api_response['choices'][0]['finish_reason'] ?? '';
			if ( $finish_reason === 'length' ) {
				$ai_error = 'The AI response was truncated before completing. Increase the Max Response Tokens setting.';
			} else {
				$ai_error = 'Could not parse AI response. The service may be experiencing issues.';
			}
			return null;
		}

		// Handle double-encoded JSON
		if ( isset( $decoded['answer_html'] ) && is_string( $decoded['answer_html'] ) ) {
			$inner = trim( $decoded['answer_html'] );
			if ( strlen( $inner ) > 0 && $inner[0] === '{' && strpos( $inner, '"answer_html"' ) !== false ) {
				$inner_decoded = json_decode( $inner, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $inner_decoded ) && isset( $inner_decoded['answer_html'] ) ) {
					$decoded = $inner_decoded;
				}
			}
		}

		if ( empty( $decoded['answer_html'] ) ) {
			$decoded['answer_html'] = '<p>AI summary did not return a valid answer.</p>';
		}

		if ( empty( $decoded['results'] ) || ! is_array( $decoded['results'] ) ) {
			$decoded['results'] = array();
		}

		return $decoded;
	}

	/**
	 * Test an Anthropic API key.
	 *
	 * @param string $api_key API key to test.
	 * @return array{success: bool, message: string}
	 */
	public function test_anthropic_key( string $api_key ): array {
		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => 'API key is empty.',
			);
		}

		// Minimal one-token request against the cheapest current model.
		$response = wp_safe_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => RIVIANTRACKR_ANTHROPIC_API_VERSION,
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'model'      => 'claude-haiku-4-5',
					'max_tokens' => 1,
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => 'Hi',
						),
					),
				) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Connection error: ' . $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 401 ) {
			return array(
				'success' => false,
				'message' => 'Invalid API key. Please check your Anthropic key and try again.',
			);
		}

		if ( $code === 403 ) {
			return array(
				'success' => false,
				'message' => 'API key lacks required permissions. Check your Anthropic Console settings.',
			);
		}

		if ( $code === 429 ) {
			return array(
				'success' => false,
				'message' => 'Rate limit exceeded. Your API key works but has hit rate limits.',
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$api_msg = $body['error']['message'] ?? ( 'HTTP ' . $code );
			return array(
				'success' => false,
				'message' => 'API error: ' . $api_msg,
			);
		}

		return array(
			'success' => true,
			'message' => 'Anthropic API key is valid and working!',
		);
	}
}
