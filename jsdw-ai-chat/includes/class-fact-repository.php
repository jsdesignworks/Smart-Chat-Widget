<?php
/**
 * Phase 4: fact persistence and lifecycle.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Fact_Repository {
	/**
	 * @var JSDW_AI_Chat_DB
	 */
	private $db;

	public function __construct( JSDW_AI_Chat_DB $db ) {
		$this->db = $db;
	}

	/**
	 * @return int
	 */
	public function retire_active_facts_for_source( $source_id, $reason = '' ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'facts' );
		$now   = current_time( 'mysql', true );
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_active = 0, fact_status = %s, fact_reason = %s, superseded_at = %s, updated_at = %s WHERE source_id = %d AND is_active = 1",
				JSDW_AI_Chat_Knowledge_Constants::FACT_STATUS_RETIRED,
				$reason !== '' ? sanitize_text_field( $reason ) : null,
				$now,
				$now,
				absint( $source_id )
			)
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $facts
	 */
	public function insert_fact_batch( $source_id, $source_content_version, array $facts ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'facts' );
		$now   = current_time( 'mysql', true );
		foreach ( $facts as $row ) {
			$insert = array(
				'source_id'              => absint( $source_id ),
				'source_content_version' => absint( $source_content_version ),
				'chunk_id'               => isset( $row['chunk_id'] ) ? absint( $row['chunk_id'] ) : null,
				'fact_type'              => sanitize_text_field( (string) ( $row['fact_type'] ?? 'label' ) ),
				'fact_key'               => sanitize_text_field( (string) ( $row['fact_key'] ?? '' ) ),
				'fact_value'             => isset( $row['fact_value'] ) ? (string) $row['fact_value'] : '',
				'fact_hash'              => isset( $row['fact_hash'] ) ? sanitize_text_field( (string) $row['fact_hash'] ) : null,
				'fact_status'            => JSDW_AI_Chat_Knowledge_Constants::FACT_STATUS_ACTIVE,
				'fact_reason'            => null,
				'is_active'              => 1,
				'created_at'             => $now,
				'updated_at'             => $now,
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $insert );
		}
		return true;
	}

	public function replace_fact_set( $source_id, $source_content_version, array $facts ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'START TRANSACTION' );
		try {
			$this->retire_active_facts_for_source( $source_id, JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_READY );
			$this->insert_fact_batch( $source_id, $source_content_version, $facts );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'COMMIT' );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function get_active_facts_by_source( $source_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'facts' );
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE source_id = %d AND is_active = 1 AND fact_status = %s ORDER BY id ASC",
			absint( $source_id ),
			JSDW_AI_Chat_Knowledge_Constants::FACT_STATUS_ACTIVE
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public function count_active_facts_by_source( $source_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'facts' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE source_id = %d AND is_active = 1 AND fact_status = %s",
				absint( $source_id ),
				JSDW_AI_Chat_Knowledge_Constants::FACT_STATUS_ACTIVE
			)
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function search_facts_value( $like_pattern, $limit = 50, $retrieval_context = JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_INTERNAL ) {
		global $wpdb;
		$sources = $this->db->get_table_name( 'sources' );
		$facts   = $this->db->get_table_name( 'facts' );
		$lim     = absint( $limit );
		$pattern = '%' . $wpdb->esc_like( $like_pattern ) . '%';
		$vis_sql = JSDW_AI_Chat_Source_Visibility::sql_where_access_visibility( $retrieval_context );
		$sql     = $wpdb->prepare(
			"SELECT f.*, s.title AS source_title, s.source_url, s.status AS source_status, s.access_visibility AS source_access_visibility
			FROM {$facts} f
			INNER JOIN {$sources} s ON s.id = f.source_id
			WHERE f.is_active = 1 AND f.fact_status = %s
			AND s.status = %s
			{$vis_sql}
			AND ( f.fact_value LIKE %s OR f.fact_key LIKE %s OR s.title LIKE %s )
			ORDER BY f.source_content_version DESC, f.id ASC
			LIMIT {$lim}",
			JSDW_AI_Chat_Knowledge_Constants::FACT_STATUS_ACTIVE,
			JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE,
			$pattern,
			$pattern,
			$pattern
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public function get_fact_by_id( $fact_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'facts' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $fact_id ) ), ARRAY_A );
	}

	public function count_sources_with_active_facts() {
		global $wpdb;
		$table = $this->db->get_table_name( 'facts' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT source_id) FROM {$table} WHERE is_active = 1 AND fact_status = %s",
				JSDW_AI_Chat_Knowledge_Constants::FACT_STATUS_ACTIVE
			)
		);
	}
}
