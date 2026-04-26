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

		if ( version_compare( (string) $stored_version, '1.8.0', '<' ) ) {
			global $wpdb;
			$table = $this->db->get_table_name( 'conversations' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$has_name = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'visitor_display_name'" );
			if ( empty( $has_name ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN visitor_display_name varchar(191) DEFAULT NULL AFTER visitor_hash" );
				$this->logger->info( 'migration_visitor_display_name', 'Added conversations.visitor_display_name column.' );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$has_email = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'visitor_email'" );
			if ( empty( $has_email ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN visitor_email varchar(191) DEFAULT NULL AFTER visitor_display_name" );
				$this->logger->info( 'migration_visitor_email', 'Added conversations.visitor_email column.' );
			}
		}

		if ( version_compare( (string) $stored_version, '1.9.0', '<' ) ) {
			global $wpdb;
			$chunks = $this->db->get_table_name( 'chunks' );
			$facts  = $this->db->get_table_name( 'facts' );
			$ck     = JSDW_AI_Chat_Knowledge_Constants::DB_FT_CHUNKS_KEY;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$has_chunk_ft = $wpdb->get_results( "SHOW INDEX FROM {$chunks} WHERE Key_name = '{$ck}'" );
			if ( empty( $has_chunk_ft ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$chunks} ADD FULLTEXT KEY {$ck} (normalized_text)" );
				$this->logger->info( 'migration_chunks_fulltext', 'Added FULLTEXT index on chunks.normalized_text.' );
			}
			$fk = JSDW_AI_Chat_Knowledge_Constants::DB_FT_FACTS_KEY;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$has_fact_ft = $wpdb->get_results( "SHOW INDEX FROM {$facts} WHERE Key_name = '{$fk}'" );
			if ( empty( $has_fact_ft ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$facts} ADD FULLTEXT KEY {$fk} (fact_value)" );
				$this->logger->info( 'migration_facts_fulltext', 'Added FULLTEXT index on facts.fact_value.' );
			}
		}

		if ( version_compare( (string) $stored_version, '1.10.0', '<' ) ) {
			global $wpdb;
			$sources = $this->db->get_table_name( 'sources' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$has_elig = $wpdb->get_results( "SHOW COLUMNS FROM {$sources} LIKE 'eligibility'" );
			if ( empty( $has_elig ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$sources} ADD COLUMN eligibility varchar(20) NOT NULL DEFAULT 'unknown' AFTER knowledge_headings_json" );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$sources} ADD COLUMN eligibility_reason_code varchar(100) DEFAULT NULL AFTER eligibility" );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$sources} ADD COLUMN eligibility_matched_rule text DEFAULT NULL AFTER eligibility_reason_code" );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$sources} ADD COLUMN eligibility_evaluated_gmt datetime DEFAULT NULL AFTER eligibility_matched_rule" );
				$this->logger->info( 'migration_sources_eligibility', 'Added sources eligibility columns.' );
			}
		}

		update_option( JSDW_AI_CHAT_OPTION_DB_SCHEMA_VERSION, $target_version, false );
		$this->logger->info( 'migration_finished', 'Schema migration finished.', array( 'to' => $target_version ) );
	}
}
