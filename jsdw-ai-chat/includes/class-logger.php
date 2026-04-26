<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Logger {
	const LEVELS = array( 'debug', 'info', 'warning', 'error', 'critical' );

	/**
	 * @var JSDW_AI_Chat_DB
	 */
	private $db;

	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	public function __construct( JSDW_AI_Chat_DB $db, JSDW_AI_Chat_Settings $settings ) {
		$this->db       = $db;
		$this->settings = $settings;
	}

	public function mark_rest_ready() {
		$this->info( 'rest_runtime', 'REST runtime initialized.' );
	}

	public function log( $level, $event_type, $message, $context = array() ) {
		try {
			global $wpdb;
			if ( ! ( $wpdb instanceof wpdb ) ) {
				return;
			}

			$level = strtolower( sanitize_text_field( (string) $level ) );
			if ( ! in_array( $level, self::LEVELS, true ) ) {
				$level = 'info';
			}

			$settings = $this->settings->get_all();
			if ( ! is_array( $settings ) || empty( $settings['logging']['enabled'] ) ) {
				return;
			}

			$table  = $this->db->get_table_name( 'logs' );
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $exists !== $table ) {
				return;
			}

			$encoded_context = wp_json_encode( $context );
			if ( false === $encoded_context ) {
				$encoded_context = '{}';
			}

			$wpdb->insert(
				$table,
				array(
					'level'        => $level,
					'event_type'   => sanitize_text_field( (string) $event_type ),
					'context_json' => $encoded_context,
					'message'      => sanitize_textarea_field( (string) $message ),
					'created_at'   => current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);

			if ( ! empty( $settings['logging']['mirror_wp_debug'] ) ) {
				error_log( '[JSDW AI Chat][' . $level . '][' . $event_type . '] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			if ( in_array( $level, array( 'error', 'critical' ), true ) ) {
				update_option(
					JSDW_AI_CHAT_OPTION_LAST_ERROR,
					array(
						'level'      => $level,
						'event_type' => sanitize_text_field( (string) $event_type ),
						'message'    => sanitize_textarea_field( (string) $message ),
						'time'       => current_time( 'mysql', true ),
					),
					false
				);
			}
		} catch ( Throwable $throwable ) {
			// Never allow logger failures to break execution flow.
			return;
		}
	}

	public function debug( $event_type, $message, $context = array() ) {
		$this->log( 'debug', $event_type, $message, $context );
	}

	public function info( $event_type, $message, $context = array() ) {
		$this->log( 'info', $event_type, $message, $context );
	}

	public function warning( $event_type, $message, $context = array() ) {
		$this->log( 'warning', $event_type, $message, $context );
	}

	public function error( $event_type, $message, $context = array() ) {
		$this->log( 'error', $event_type, $message, $context );
	}

	/**
	 * Recent log lines for admin diagnostics (reads DB even when file logging is off).
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array<string,mixed>>
	 */
	public function get_recent_log_rows( $limit = 30 ) {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return array();
		}
		$table  = $this->db->get_table_name( 'logs' );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $exists !== $table ) {
			return array();
		}
		$limit = max( 1, min( 100, absint( $limit ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from plugin registry.
		$sql  = $wpdb->prepare( "SELECT id, level, event_type, message, created_at FROM {$table} ORDER BY id DESC LIMIT %d", $limit );
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Recent log rows whose JSON context contains the given source_id (best-effort LIKE filter).
	 *
	 * @param int $source_id
	 * @param int $limit
	 * @return array<int, array<string,mixed>>
	 */
	public function get_recent_log_rows_for_source( $source_id, $limit = 10 ) {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return array();
		}
		$table  = $this->db->get_table_name( 'logs' );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $exists !== $table ) {
			return array();
		}
		$sid   = absint( $source_id );
		$limit = max( 1, min( 50, absint( $limit ) ) );
		if ( $sid <= 0 ) {
			return array();
		}
		$needle = '%"source_id":' . $sid . '%';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql  = $wpdb->prepare(
			"SELECT id, level, event_type, message, created_at FROM {$table} WHERE context_json LIKE %s ORDER BY id DESC LIMIT %d",
			$needle,
			$limit
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
