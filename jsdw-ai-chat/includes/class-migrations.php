<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Migrations {
	/**
	 * @var JSDW_AI_Chat_DB
	 */
	private $db;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	public function __construct( JSDW_AI_Chat_DB $db, JSDW_AI_Chat_Logger $logger ) {
		$this->db     = $db;
		$this->logger = $logger;
	}

	public function maybe_migrate() {
		$stored_version = get_option( JSDW_AI_CHAT_OPTION_DB_SCHEMA_VERSION, '0.0.0' );
		$target_version = JSDW_AI_CHAT_DB_SCHEMA_VERSION;

		if ( version_compare( (string) $stored_version, $target_version, '>=' ) ) {
			return;
		}

		$this->logger->info( 'migration_started', 'Schema migration started.', array( 'from' => $stored_version, 'to' => $target_version ) );
		$this->db->install_tables();

		if ( version_compare( (string) $stored_version, '1.5.0', '<' ) ) {
			$repo = new JSDW_AI_Chat_Source_Repository( $this->db );
			$settings = new JSDW_AI_Chat_Settings();
			$repo->backfill_access_visibility_rows( $settings->get_all() );
			$repo->backfill_manual_sources_access_visibility();
			$this->logger->info( 'migration_visibility_backfill', 'access_visibility backfill completed.' );
		}

		if ( version_compare( (string) $stored_version, '1.6.0', '<' ) ) {
			global $wpdb;
			$table = $this->db->get_table_name( 'conversations' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from plugin registry.
			$has_col = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'agent_connected'" );
			if ( empty( $has_col ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN agent_connected tinyint(1) NOT NULL DEFAULT 0 AFTER status" );
				$this->logger->info( 'migration_agent_connected', 'Added conversations.agent_connected column.' );
			}
		}

		if ( version_compare( (string) $stored_version, '1.7.0', '<' ) ) {
			global $wpdb;
			$table          = $this->db->get_table_name( 'conversations' );
			$messages_table = $this->db->get_table_name( 'messages' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from plugin registry.
			$has_read = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'last_read_message_id_admin'" );
			if ( empty( $has_read ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN last_read_message_id_admin bigint(20) unsigned NOT NULL DEFAULT 0 AFTER agent_connected" );
				$this->logger->info( 'migration_last_read_admin_col', 'Added conversations.last_read_message_id_admin column.' );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names from plugin registry.
			$wpdb->query(
				"UPDATE {$table} c SET last_read_message_id_admin = ( SELECT COALESCE( MAX( m.id ), 0 ) FROM {$messages_table} m WHERE m.conversation_id = c.id )"
			);
			$this->logger->info( 'migration_last_read_admin_backfill', 'Backfilled conversations.last_read_message_id_admin from messages.' );
		}

		update_option( JSDW_AI_CHAT_OPTION_DB_SCHEMA_VERSION, $target_version, false );
		$this->logger->info( 'migration_finished', 'Schema migration finished.', array( 'to' => $target_version ) );
	}
}
