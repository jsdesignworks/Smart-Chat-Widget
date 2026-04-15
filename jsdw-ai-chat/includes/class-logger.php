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
}
