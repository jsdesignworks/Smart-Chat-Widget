<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_DB {
	const JOB_STATUS_PENDING   = 'pending';
	const JOB_STATUS_RUNNING   = 'running';
	const JOB_STATUS_COMPLETED = 'completed';
	const JOB_STATUS_FAILED    = 'failed';

	const SOURCE_STATUS_ACTIVE   = 'active';
	const SOURCE_STATUS_INACTIVE = 'inactive';
	const SOURCE_STATUS_EXCLUDED = 'excluded';
	const SOURCE_STATUS_MISSING  = 'missing';
	const SOURCE_STATUS_DISABLED = 'disabled';
	const SOURCE_STATUS_PENDING  = 'pending';

	const CHANGE_DISCOVERED_NEW    = 'discovered_new';
	const CHANGE_SETTINGS_CHANGED  = 'settings_changed';
	const CHANGE_SOURCE_UPDATED    = 'source_updated';
	const CHANGE_SOURCE_REMOVED    = 'source_removed';
	const CHANGE_MANUALLY_EXCLUDED = 'manually_excluded';
	const CHANGE_MANUALLY_INCLUDED = 'manually_included';
	const CHANGE_RULE_EXCLUDED     = 'rule_excluded';
	const CHANGE_VERIFY_MISSING    = 'verification_missing';

	// Phase 3: content processing status (sources.content_processing_status).
	const CONTENT_PROC_STATUS_PENDING      = 'pending';
	const CONTENT_PROC_STATUS_OK           = 'ok';
	const CONTENT_PROC_STATUS_FAILED       = 'failed';
	const CONTENT_PROC_STATUS_UNSUPPORTED  = 'unsupported';
	const CONTENT_PROC_STATUS_UNAVAILABLE  = 'unavailable';

	// Phase 3: content-level comparison / processing reasons (sources.content_processing_reason). Distinct from discovery change_reason.
	const CONTENT_REASON_NO_CHANGE            = 'content_no_change';
	const CONTENT_REASON_TITLE_CHANGED        = 'title_changed';
	const CONTENT_REASON_CONTENT_CHANGED      = 'content_changed';
	const CONTENT_REASON_STRUCTURE_CHANGED    = 'structure_changed';
	const CONTENT_REASON_METADATA_CHANGED     = 'metadata_changed';
	const CONTENT_REASON_SOURCE_UNAVAILABLE   = 'source_unavailable';
	const CONTENT_REASON_UNSUPPORTED_TYPE     = 'unsupported_source_type';
	const CONTENT_REASON_NORMALIZATION_FAILED = 'normalization_failed';

	// Phase 4: mirror JSDW_AI_Chat_Knowledge_Constants for DB defaults where needed.
	const KNOWLEDGE_STATUS_PENDING = 'pending';

	public function init() {
		// Reserved for future DB runtime hooks.
	}

	public function get_table_name( $key ) {
		global $wpdb;
		return $wpdb->prefix . 'jsdw_ai_chat_' . $key;
	}

	/**
	 * @return array<string, string>
	 */
	public function get_tables() {
		return array(
			'sources'       => $this->get_table_name( 'sources' ),
			'manual_sources'=> $this->get_table_name( 'manual_sources' ),
			'chunks'        => $this->get_table_name( 'chunks' ),
			'facts'         => $this->get_table_name( 'facts' ),
			'jobs'          => $this->get_table_name( 'jobs' ),
			'conversations' => $this->get_table_name( 'conversations' ),
			'messages'      => $this->get_table_name( 'messages' ),
			'logs'          => $this->get_table_name( 'logs' ),
		);
	}

	public function install_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		$tables  = $this->get_tables();

		$sql = array();

		$sql[] = "CREATE TABLE {$tables['sources']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_type varchar(50) NOT NULL,
			source_object_id bigint(20) unsigned DEFAULT NULL,
			source_key varchar(191) NOT NULL,
			source_url text DEFAULT NULL,
			title text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT '" . self::SOURCE_STATUS_ACTIVE . "',
			authority_level int(11) NOT NULL DEFAULT 50,
			discovery_context longtext DEFAULT NULL,
			visibility_flags longtext DEFAULT NULL,
			access_visibility varchar(20) NOT NULL DEFAULT 'internal',
			last_wp_modified_gmt datetime DEFAULT NULL,
			last_indexed_gmt datetime DEFAULT NULL,
			last_checked_gmt datetime DEFAULT NULL,
			needs_reindex tinyint(1) NOT NULL DEFAULT 1,
			change_reason varchar(100) DEFAULT NULL,
			content_version int(11) unsigned NOT NULL DEFAULT 1,
			schema_version varchar(20) DEFAULT NULL,
			raw_snapshot_text longtext DEFAULT NULL,
			normalized_snapshot_text longtext DEFAULT NULL,
			content_hash char(64) DEFAULT NULL,
			title_hash char(64) DEFAULT NULL,
			structure_hash char(64) DEFAULT NULL,
			metadata_hash char(64) DEFAULT NULL,
			last_content_check_gmt datetime DEFAULT NULL,
			last_content_change_gmt datetime DEFAULT NULL,
			normalized_length int(11) unsigned DEFAULT NULL,
			extraction_method varchar(50) DEFAULT NULL,
			content_processing_status varchar(30) NOT NULL DEFAULT '" . self::CONTENT_PROC_STATUS_PENDING . "',
			content_processing_reason varchar(100) DEFAULT NULL,
			material_content_change tinyint(1) NOT NULL DEFAULT 0,
			knowledge_processing_status varchar(30) NOT NULL DEFAULT 'pending',
			knowledge_processing_reason varchar(100) DEFAULT NULL,
			last_knowledge_processing_gmt datetime DEFAULT NULL,
			knowledge_headings_json longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY source_key (source_key(100)),
			UNIQUE KEY source_type_key (source_type, source_key(100)),
			KEY source_type_status (source_type, status),
			KEY access_visibility (access_visibility),
			KEY source_object_id (source_object_id),
			KEY needs_reindex (needs_reindex),
			KEY content_proc_status (content_processing_status),
			KEY last_content_check (last_content_check_gmt),
			KEY knowledge_proc_status (knowledge_processing_status)
		) {$collate};";

		$sql[] = "CREATE TABLE {$tables['manual_sources']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_key varchar(191) NOT NULL,
			title text NOT NULL,
			source_url text DEFAULT NULL,
			source_notes longtext DEFAULT NULL,
			allow_behavior varchar(20) NOT NULL DEFAULT 'allow',
			source_scope varchar(100) DEFAULT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			authority_override int(11) DEFAULT NULL,
			access_visibility varchar(20) NOT NULL DEFAULT 'internal',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY source_key (source_key),
			KEY enabled (enabled)
		) {$collate};";

		$sql[] = "CREATE TABLE {$tables['chunks']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL,
			source_content_version int(11) unsigned NOT NULL DEFAULT 1,
			chunk_index int(11) unsigned NOT NULL DEFAULT 0,
			section_label varchar(191) DEFAULT NULL,
			heading varchar(191) DEFAULT NULL,
			raw_text longtext NOT NULL,
			normalized_text longtext DEFAULT NULL,
			text_hash varchar(64) DEFAULT NULL,
			chunk_hash char(64) DEFAULT NULL,
			chunk_status varchar(20) NOT NULL DEFAULT 'active',
			chunk_reason varchar(100) DEFAULT NULL,
			token_estimate int(11) unsigned DEFAULT NULL,
			position_start int(11) unsigned DEFAULT NULL,
			position_end int(11) unsigned DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			superseded_at datetime DEFAULT NULL,
			superseded_by_chunk_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY source_chunk (source_id, chunk_index),
			KEY text_hash (text_hash),
			KEY source_ver_status (source_id, source_content_version, chunk_status),
			KEY chunk_active (source_id, is_active)
		) {$collate};";

		$sql[] = "CREATE TABLE {$tables['facts']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_id bigint(20) unsigned NOT NULL,
			source_content_version int(11) unsigned NOT NULL DEFAULT 1,
			chunk_id bigint(20) unsigned DEFAULT NULL,
			fact_type varchar(50) NOT NULL,
			fact_key varchar(191) NOT NULL,
			fact_value longtext DEFAULT NULL,
			fact_hash varchar(64) DEFAULT NULL,
			fact_status varchar(20) NOT NULL DEFAULT 'active',
			fact_reason varchar(100) DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			superseded_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY source_fact (source_id, fact_type),
			KEY fact_key (fact_key(100)),
			KEY source_ver_fact (source_id, source_content_version, fact_status)
		) {$collate};";

		$sql[] = "CREATE TABLE {$tables['jobs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_type varchar(50) NOT NULL,
			source_id bigint(20) unsigned DEFAULT NULL,
			priority smallint(5) unsigned NOT NULL DEFAULT 10,
			trigger_type varchar(50) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT '" . self::JOB_STATUS_PENDING . "',
			attempts int(11) unsigned NOT NULL DEFAULT 0,
			max_attempts int(11) unsigned NOT NULL DEFAULT 3,
			payload_json longtext DEFAULT NULL,
			result_json longtext DEFAULT NULL,
			error_message text DEFAULT NULL,
			queued_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			started_at datetime DEFAULT NULL,
			finished_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status_priority (status, priority),
			KEY job_type (job_type)
		) {$collate};";

		$sql[] = "CREATE TABLE {$tables['conversations']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_key varchar(191) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			visitor_hash varchar(64) DEFAULT NULL,
			channel varchar(30) NOT NULL DEFAULT 'web',
			status varchar(20) NOT NULL DEFAULT 'open',
			agent_connected tinyint(1) NOT NULL DEFAULT 0,
			last_read_message_id_admin bigint(20) unsigned NOT NULL DEFAULT 0,
			started_at datetime DEFAULT NULL,
			last_active_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY session_key (session_key),
			KEY user_status (user_id, status)
		) {$collate};";

		$sql[] = "CREATE TABLE {$tables['messages']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL,
			message_text longtext DEFAULT NULL,
			normalized_message longtext DEFAULT NULL,
			answer_text longtext DEFAULT NULL,
			answer_status varchar(50) DEFAULT NULL,
			answer_type varchar(50) DEFAULT NULL,
			answer_strategy varchar(50) DEFAULT NULL,
			ai_used varchar(100) DEFAULT NULL,
			source_snapshot_json longtext DEFAULT NULL,
			confidence_score decimal(5,2) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_created (conversation_id, created_at),
			KEY role (role)
		) {$collate};";

		$sql[] = "CREATE TABLE {$tables['logs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL,
			event_type varchar(100) NOT NULL,
			context_json longtext DEFAULT NULL,
			message text NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY level_created (level, created_at),
			KEY event_type (event_type)
		) {$collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	public function drop_tables() {
		global $wpdb;
		foreach ( $this->get_tables() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * @return array<string, bool>
	 */
	public function get_table_status() {
		global $wpdb;
		$results = array();
		foreach ( $this->get_tables() as $key => $table ) {
			$exists          = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results[ $key ] = ( $exists === $table );
		}
		return $results;
	}
}
