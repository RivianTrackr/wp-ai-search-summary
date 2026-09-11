<?php
declare(strict_types=1);

namespace RivianTrackr\AISearchSummary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles search event logging, feedback recording, log purging, and statistics.
 */
class Analytics {

	private bool $logs_table_checked = false;
	private bool $logs_table_exists  = false;

	/**
	 * Get the logs table name.
	 */
	public static function get_logs_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'riviantrackr_logs';
	}

	/**
	 * Get the feedback table name.
	 */
	public static function get_feedback_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'riviantrackr_feedback';
	}

	/**
	 * Check if the logs table exists in the database.
	 */
	public function logs_table_is_available(): bool {
		if ( $this->logs_table_checked ) {
			return $this->logs_table_exists;
		}

		global $wpdb;
		$table_name = self::get_logs_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		$this->logs_table_checked = true;
		$this->logs_table_exists  = ( $result === $table_name );

		return $this->logs_table_exists;
	}

	/**
	 * Forget the cached table-existence check (after creating or repairing the table).
	 */
	public function reset_table_check(): void {
		$this->logs_table_checked = false;
		$this->logs_table_exists  = false;
	}

	/**
	 * Log a search event to the database.
	 *
	 * @param string      $search_query  The search query.
	 * @param int         $results_count Number of matching posts.
	 * @param int         $ai_success    Whether AI was successful (1/0).
	 * @param string      $ai_error      Error message if any.
	 * @param bool|int|null $cache_hit   Cache hit indicator (null, true/false, or 2 for session).
	 * @param int|null    $response_time_ms Response time in milliseconds.
	 * @param string|null $ai_model      Model ID used.
	 * @param bool        $anonymize     Whether to hash the query.
	 */
	public function log_search_event(
		string $search_query,
		int $results_count,
		int $ai_success,
		string $ai_error = '',
		$cache_hit = null,
		?int $response_time_ms = null,
		?string $ai_model = null,
		bool $anonymize = false
	): void {
		if ( empty( $search_query ) ) {
			return;
		}

		if ( ! $this->logs_table_is_available() ) {
			return;
		}

		global $wpdb;
		$table_name = self::get_logs_table_name();
		$now        = current_time( 'mysql' );

		if ( $anonymize ) {
			$search_query = hash( 'sha256', strtolower( trim( $search_query ) ) );
		}

		$sanitized_error = '';
		if ( ! empty( $ai_error ) ) {
			$sanitized_error = wp_strip_all_tags( $ai_error );
			$sanitized_error = sanitize_text_field( $sanitized_error );
			if ( function_exists( 'mb_substr' ) ) {
				$sanitized_error = mb_substr( $sanitized_error, 0, RIVIANTRACKR_ERROR_MAX_LENGTH, 'UTF-8' );
			} else {
				$sanitized_error = substr( $sanitized_error, 0, 500 );
			}
		}

		$data = array(
			'search_query'  => $search_query,
			'results_count' => $results_count,
			'ai_success'    => $ai_success ? 1 : 0,
			'ai_error'      => $sanitized_error,
			'created_at'    => $now,
		);

		$formats = array( '%s', '%d', '%d', '%s', '%s' );

		if ( $cache_hit !== null ) {
			$data['cache_hit'] = $cache_hit ? 1 : 0;
			$formats[]         = '%d';
		}

		if ( $response_time_ms !== null ) {
			$data['response_time_ms'] = $response_time_ms;
			$formats[]                = '%d';
		}

		if ( $ai_model !== null ) {
			$data['ai_model'] = sanitize_text_field( $ai_model );
			$formats[]        = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert( $table_name, $data, $formats );

		if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				'[RivianTrackr AI Search Summary] Failed to log search event: ' .
				$wpdb->last_error .
				' | Query: ' . substr( $search_query, 0, 50 )
			);
		}
	}

	/**
	 * Record user feedback.
	 *
	 * @param string $search_query The search query.
	 * @param bool   $helpful      Whether the summary was helpful.
	 * @param string $ip           Client IP address.
	 * @return bool|string True on success, 'duplicate' if already voted, false on error.
	 */
	public function record_feedback( string $search_query, bool $helpful, string $ip ) {
		global $wpdb;

		$table_name = self::get_feedback_table_name();
		$ip_hash    = hash( 'sha256', $ip . wp_salt( 'auth' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (search_query, helpful, ip_hash, created_at) VALUES (%s, %d, %s, %s)',
				$table_name,
				substr( $search_query, 0, 255 ),
				$helpful ? 1 : 0,
				$ip_hash,
				current_time( 'mysql' )
			)
		);

		if ( false === $result ) {
			return false;
		}

		if ( 0 === $wpdb->rows_affected ) {
			return 'duplicate';
		}

		return true;
	}

	/**
	 * Get feedback statistics.
	 *
	 * @return array{total_votes: int, helpful_count: int, not_helpful_count: int, helpful_rate: float}
	 */
	public function get_feedback_stats(): array {
		global $wpdb;

		$table_name = self::get_feedback_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
					COUNT(*) AS total_votes,
					SUM(helpful) AS helpful_count,
					COUNT(*) - SUM(helpful) AS not_helpful_count
				 FROM %i',
				$table_name
			)
		);

		$total   = $stats ? (int) $stats->total_votes : 0;
		$helpful = $stats ? (int) $stats->helpful_count : 0;

		return array(
			'total_votes'       => $total,
			'helpful_count'     => $helpful,
			'not_helpful_count' => $total - $helpful,
			'helpful_rate'      => $total > 0 ? round( ( $helpful / $total ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Purge logs older than specified number of days.
	 *
	 * @param int $days Number of days to keep.
	 * @return int|false Number of rows deleted, or false on failure.
	 */
	public function purge_old_logs( int $days = 30 ) {
		if ( ! $this->logs_table_is_available() ) {
			return false;
		}

		global $wpdb;
		$table_name  = self::get_logs_table_name();
		$cutoff_date = gmdate( 'Y-m-d H:i:s', time() - ( absint( $days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$table_name,
				$cutoff_date
			)
		);

		return $deleted;
	}

	/**
	 * Calculate success rate as a percentage.
	 *
	 * @param int $success_count Number of successes.
	 * @param int $total         Total count.
	 * @return int Percentage (0-100).
	 */
	public function calculate_success_rate( int $success_count, int $total ): int {
		if ( $total <= 0 ) {
			return 0;
		}
		return (int) round( ( $success_count / $total ) * 100 );
	}

	/**
	 * Get trending search keywords.
	 *
	 * @param int    $limit       Number of keywords to return.
	 * @param int    $time_period Time period value.
	 * @param string $time_unit   'hours' or 'days'.
	 * @return array Trending keywords with counts.
	 */
	public function get_trending_keywords( int $limit = 5, int $time_period = 24, string $time_unit = 'hours' ): array {
		if ( ! $this->logs_table_is_available() ) {
			return array();
		}

		global $wpdb;
		$table_name = self::get_logs_table_name();

		$seconds = ( 'days' === $time_unit ) ? $time_period * DAY_IN_SECONDS : $time_period * HOUR_IN_SECONDS;
		$since   = gmdate( 'Y-m-d H:i:s', time() - $seconds );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT search_query, COUNT(*) AS search_count
				 FROM %i
				 WHERE created_at >= %s
				   AND results_count > 0
				   AND ai_success = 1
				 GROUP BY search_query
				 ORDER BY search_count DESC
				 LIMIT %d',
				$table_name,
				$since,
				$limit
			)
		);

		return $results ? $results : array();
	}
}
