<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RivianTrackr\AISearchSummary\ApiHandler;

/**
 * Tests for model capability gating, output_config construction, and the
 * refusal stop-reason mapping in ApiHandler.
 */
class ApiHandlerCapabilitiesTest extends TestCase {

	private ApiHandler $handler;

	protected function setUp(): void {
		global $_wp_test_transients;
		$_wp_test_transients = array();
		$this->handler       = new ApiHandler();
	}

	private function normalize( array $api_data ): array {
		$method = new ReflectionMethod( ApiHandler::class, 'normalize_anthropic_response' );
		return $method->invoke( $this->handler, $api_data );
	}

	// --- Model ID parsing ---

	public function test_parses_current_and_dated_model_ids(): void {
		$this->assertSame( array( 'family' => 'sonnet', 'version' => 5.0 ), $this->handler->parse_model_id( 'claude-sonnet-5' ) );
		$this->assertSame( array( 'family' => 'haiku', 'version' => 4.5 ), $this->handler->parse_model_id( 'claude-haiku-4-5' ) );
		$this->assertSame( array( 'family' => 'sonnet', 'version' => 4.5 ), $this->handler->parse_model_id( 'claude-sonnet-4-5-20250929' ) );
		$this->assertSame( array( 'family' => 'opus', 'version' => 4.1 ), $this->handler->parse_model_id( 'claude-opus-4-1-20250805' ) );
		$this->assertSame( array( 'family' => 'sonnet', 'version' => 4.0 ), $this->handler->parse_model_id( 'claude-sonnet-4-20250514' ) );
		$this->assertSame( array( 'family' => 'fable', 'version' => 5.1 ), $this->handler->parse_model_id( 'claude-fable-5-1' ) );
	}

	public function test_legacy_and_unknown_ids_return_null(): void {
		$this->assertNull( $this->handler->parse_model_id( 'claude-3-5-haiku-20241022' ) );
		$this->assertNull( $this->handler->parse_model_id( 'gpt-4o' ) );
		$this->assertNull( $this->handler->parse_model_id( '' ) );
	}

	// --- Effort support ---

	public function test_effort_support_by_family_and_version(): void {
		$this->assertTrue( $this->handler->model_supports_effort( 'claude-opus-5' ) );
		$this->assertTrue( $this->handler->model_supports_effort( 'claude-opus-4-6' ) );
		$this->assertTrue( $this->handler->model_supports_effort( 'claude-opus-4-5-20251101' ) );
		$this->assertTrue( $this->handler->model_supports_effort( 'claude-sonnet-5' ) );
		$this->assertTrue( $this->handler->model_supports_effort( 'claude-sonnet-4-6' ) );
		$this->assertTrue( $this->handler->model_supports_effort( 'claude-fable-5-1' ) );

		$this->assertFalse( $this->handler->model_supports_effort( 'claude-haiku-4-5' ) );
		$this->assertFalse( $this->handler->model_supports_effort( 'claude-sonnet-4-5-20250929' ) );
		$this->assertFalse( $this->handler->model_supports_effort( 'claude-3-5-haiku-20241022' ) );
	}

	// --- Structured output support ---

	public function test_structured_output_support_by_family_and_version(): void {
		$this->assertTrue( $this->handler->model_supports_structured_output( 'claude-opus-5' ) );
		$this->assertTrue( $this->handler->model_supports_structured_output( 'claude-opus-4-8' ) );
		$this->assertTrue( $this->handler->model_supports_structured_output( 'claude-sonnet-5' ) );
		$this->assertTrue( $this->handler->model_supports_structured_output( 'claude-haiku-4-5' ) );
		$this->assertTrue( $this->handler->model_supports_structured_output( 'claude-fable-5-1' ) );

		$this->assertFalse( $this->handler->model_supports_structured_output( 'claude-opus-4-6' ) );
		$this->assertFalse( $this->handler->model_supports_structured_output( 'claude-sonnet-4-6' ) );
	}

	// --- output_config construction ---

	public function test_output_config_includes_effort_and_schema_when_supported(): void {
		$config = $this->handler->build_output_config( 'claude-sonnet-5', array( 'effort' => 'low' ) );

		$this->assertSame( 'low', $config['effort'] );
		$this->assertSame( 'json_schema', $config['format']['type'] );
		$this->assertSame( array( 'answer_html', 'results' ), $config['format']['schema']['required'] );
		$this->assertFalse( $config['format']['schema']['additionalProperties'] );
		$this->assertFalse( $config['format']['schema']['properties']['results']['items']['additionalProperties'] );
	}

	public function test_output_config_omits_effort_for_haiku_but_keeps_schema(): void {
		$config = $this->handler->build_output_config( 'claude-haiku-4-5', array( 'effort' => 'low' ) );

		$this->assertArrayNotHasKey( 'effort', $config );
		$this->assertArrayHasKey( 'format', $config );
	}

	public function test_output_config_is_empty_for_unsupported_model(): void {
		$this->assertSame( array(), $this->handler->build_output_config( 'claude-3-5-haiku-20241022', array( 'effort' => 'low' ) ) );
	}

	public function test_output_config_is_empty_after_model_rejected_it(): void {
		$key_method = new ReflectionMethod( ApiHandler::class, 'output_config_unsupported_key' );
		set_transient( $key_method->invoke( $this->handler, 'claude-sonnet-5' ), 1, 60 );

		$this->assertSame( array(), $this->handler->build_output_config( 'claude-sonnet-5', array( 'effort' => 'low' ) ) );
	}

	// --- Refusal handling ---

	public function test_normalize_maps_refusal_to_content_filter(): void {
		$normalized = $this->normalize( array(
			'content'     => array(),
			'stop_reason' => 'refusal',
		) );

		$this->assertSame( 'content_filter', $normalized['choices'][0]['finish_reason'] );
	}

	public function test_parse_reports_refusal_as_content_policy(): void {
		$normalized = $this->normalize( array(
			'content'     => array( array( 'type' => 'text', 'text' => 'I cannot help with that.' ) ),
			'stop_reason' => 'refusal',
		) );

		$error  = '';
		$result = $this->handler->parse_ai_content( $normalized, $error );

		$this->assertNull( $result );
		$this->assertStringContainsString( 'content policy', $error );
	}

	// --- Key tester guard ---

	public function test_key_tester_rejects_empty_key_without_network(): void {
		$result = $this->handler->test_anthropic_key( '' );
		$this->assertFalse( $result['success'] );
	}
}
