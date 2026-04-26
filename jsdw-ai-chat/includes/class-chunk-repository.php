<?php
/**
 * Phase 4: chunk persistence and lifecycle.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Chunk_Repository {
	/**
	 * @var JSDW_AI_Chat_DB
	 */
	private $db;

	/**
	 * @var bool|null
	 */
	private static $fulltext_chunks_ok = null;

	public function __construct( JSDW_AI_Chat_DB $db ) {
		$this->db = $db;
	}

	/**
	 * @return bool
	 */
	public function normalized_text_fulltext_available() {
		if ( null !== self::$fulltext_chunks_ok ) {
			return self::$fulltext_chunks_ok;
		}
		global $wpdb;
		$table = $this->db->get_table_name( 'chunks' );
		$key   = JSDW_AI_Chat_Knowledge_Constants::DB_FT_CHUNKS_KEY;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from plugin registry; key is a constant slug.
		$rows = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = '{$key}'" );
		self::$fulltext_chunks_ok = ! empty( $rows );
		return self::$fulltext_chunks_ok;
	}

	/**
	 * Retire all active chunks for a source (before inserting a new set for the same or newer content version).
	 *
	 * @return int Rows affected.
	 */
	public function retire_active_chunks_for_source( $source_id, $supersede_reason = '' ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'chunks' );
		$now   = current_time( 'mysql', true );
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_active = 0, chunk_status = %s, chunk_reason = %s, superseded_at = %s, updated_at = %s WHERE source_id = %d AND is_active = 1",
				JSDW_AI_Chat_Knowledge_Constants::CHUNK_STATUS_SUPERSEDED,
				$supersede_reason !== '' ? sanitize_text_field( $supersede_reason ) : null,
				$now,
				$now,
				absint( $source_id )
			)
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $chunks Rows ready for insert (keys match columns).
	 * @return array<int,int> Inserted chunk IDs in order.
	 */
	public function insert_chunk_batch( $source_id, $source_content_version, array $chunks ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'chunks' );
		$now   = current_time( 'mysql', true );
		$ids   = array();
		foreach ( $chunks as $row ) {
			$insert = array(
				'source_id'              => absint( $source_id ),
				'source_content_version' => absint( $source_content_version ),
				'chunk_index'            => isset( $row['chunk_index'] ) ? absint( $row['chunk_index'] ) : 0,
				'section_label'          => isset( $row['section_label'] ) ? sanitize_text_field( (string) $row['section_label'] ) : null,
				'heading'                => isset( $row['heading'] ) ? sanitize_text_field( (string) $row['heading'] ) : null,
				'raw_text'               => isset( $row['raw_text'] ) ? (string) $row['raw_text'] : '',
				'normalized_text'        => isset( $row['normalized_text'] ) ? (string) $row['normalized_text'] : '',
				'text_hash'              => isset( $row['text_hash'] ) ? sanitize_text_field( (string) $row['text_hash'] ) : null,
				'chunk_hash'             => isset( $row['chunk_hash'] ) ? sanitize_text_field( (string) $row['chunk_hash'] ) : null,
				'chunk_status'           => JSDW_AI_Chat_Knowledge_Constants::CHUNK_STATUS_ACTIVE,
				'token_estimate'         => isset( $row['token_estimate'] ) ? absint( $row['token_estimate'] ) : null,
				'position_start'         => isset( $row['position_start'] ) && null !== $row['position_start'] ? absint( $row['position_start'] ) : null,
				'position_end'           => isset( $row['position_end'] ) && null !== $row['position_end'] ? absint( $row['position_end'] ) : null,
				'is_active'              => 1,
				'created_at'             => $now,
				'updated_at'             => $now,
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $insert );
			$ids[] = (int) $wpdb->insert_id;
		}
		return $ids;
	}

	/**
	 * Replace active chunk set: retire existing actives, insert new rows.
	 *
	 * @param array<int,array<string,mixed>> $chunks
	 * @return array<int,int> Inserted chunk IDs.
	 */
	public function replace_chunk_set( $source_id, $source_content_version, array $chunks ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'START TRANSACTION' );
		try {
			$this->retire_active_chunks_for_source( $source_id, JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_READY );
			$ids = $this->insert_chunk_batch( $source_id, $source_content_version, $chunks );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'COMMIT' );
			return $ids;
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function get_active_chunks_by_source( $source_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'chunks' );
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE source_id = %d AND is_active = 1 AND chunk_status = %s ORDER BY chunk_index ASC",
			absint( $source_id ),
			JSDW_AI_Chat_Knowledge_Constants::CHUNK_STATUS_ACTIVE
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public function count_active_chunks_by_source( $source_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'chunks' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE source_id = %d AND is_active = 1 AND chunk_status = %s",
				absint( $source_id ),
				JSDW_AI_Chat_Knowledge_Constants::CHUNK_STATUS_ACTIVE
			)
		);
	}

	public function count_active_chunks_for_version( $source_id, $version ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'chunks' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE source_id = %d AND source_content_version = %d AND is_active = 1 AND chunk_status = %s",
				absint( $source_id ),
				absint( $version ),
				JSDW_AI_Chat_Knowledge_Constants::CHUNK_STATUS_ACTIVE
			)
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function search_chunks_text( $like_pattern, $limit = 50, $retrieval_context = JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_INTERNAL ) {
		$term = trim( (string) $like_pattern );
		if ( '' === $term ) {
			return array();
		}
		if ( $this->normalized_text_fulltext_available() && strlen( $term ) >= 3 && $this->is_safe_fulltext_query_token( $term ) ) {
			$ft = $this->search_chunks_fulltext( $term, $limit, $retrieval_context );
			if ( ! empty( $ft ) ) {
				return $ft;
			}
		}
		return $this->search_chunks_like( $term, $limit, $retrieval_context );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function search_chunks_fulltext( $term, $limit, $retrieval_context ) {
		global $wpdb;
		$sources = $this->db->get_table_name( 'sources' );
		$chunks  = $this->db->get_table_name( 'chunks' );
		$lim     = absint( $limit );
		$pattern = '%' . $wpdb->esc_like( $term ) . '%';
		$vis_sql = JSDW_AI_Chat_Source_Visibility::sql_where_access_visibility( $retrieval_context );
		$bool    = $this->boolean_fulltext_term( $term );
		if ( '' === $bool ) {
			return array();
		}
		$sql = $wpdb->prepare(
			"SELECT c.*, s.title AS source_title, s.source_url, s.status AS source_status, s.access_visibility AS source_access_visibility
			FROM {$chunks} c
			INNER JOIN {$sources} s ON s.id = c.source_id
			WHERE c.is_active = 1 AND c.chunk_status = %s
			AND s.status = %s
			{$vis_sql}
			AND ( MATCH(c.normalized_text) AGAINST (%s IN BOOLEAN MODE) OR c.heading LIKE %s OR c.section_label LIKE %s OR s.title LIKE %s )
			ORDER BY c.source_content_version DESC, c.id ASC
			LIMIT {$lim}",
			JSDW_AI_Chat_Knowledge_Constants::CHUNK_STATUS_ACTIVE,
			JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE,
			$bool,
			$pattern,
			$pattern,
			$pattern
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function search_chunks_like( $term, $limit, $retrieval_context ) {
		global $wpdb;
		$sources = $this->db->get_table_name( 'sources' );
		$chunks  = $this->db->get_table_name( 'chunks' );
		$lim     = absint( $limit );
		$pattern = '%' . $wpdb->esc_like( $term ) . '%';
		$vis_sql = JSDW_AI_Chat_Source_Visibility::sql_where_access_visibility( $retrieval_context );
		$sql     = $wpdb->prepare(
			"SELECT c.*, s.title AS source_title, s.source_url, s.status AS source_status, s.access_visibility AS source_access_visibility
			FROM {$chunks} c
			INNER JOIN {$sources} s ON s.id = c.source_id
			WHERE c.is_active = 1 AND c.chunk_status = %s
			AND s.status = %s
			{$vis_sql}
			AND ( c.normalized_text LIKE %s OR c.heading LIKE %s OR c.section_label LIKE %s OR s.title LIKE %s )
			ORDER BY c.source_content_version DESC, c.id ASC
			LIMIT {$lim}",
			JSDW_AI_Chat_Knowledge_Constants::CHUNK_STATUS_ACTIVE,
			JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE,
			$pattern,
			$pattern,
			$pattern,
			$pattern
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param string $term
	 * @return string
	 */
	private function boolean_fulltext_term( $term ) {
		$w = preg_replace( '/[^\p{L}\p{N}]+/u', '', (string) $term );
		if ( null === $w || strlen( $w ) < 2 ) {
			return '';
		}
		return '+' . $w;
	}

	/**
	 * @param string $term
	 * @return bool
	 */
	private function is_safe_fulltext_query_token( $term ) {
		if ( preg_match( '/["\'\\\\]/', (string) $term ) ) {
			return false;
		}
		return (bool) preg_match( '/^[\p{L}\p{N}\s._:-]+$/u', (string) $term );
	}

	/**
	 * @return array<string,int>
	 */
	public function get_chunk_status_counts() {
		global $wpdb;
		$table = $this->db->get_table_name( 'chunks' );
		$rows  = $wpdb->get_results( "SELECT chunk_status, COUNT(*) AS c FROM {$table} GROUP BY chunk_status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$k = isset( $row['chunk_status'] ) ? (string) $row['chunk_status'] : '';
				if ( '' !== $k ) {
					$out[ $k ] = isset( $row['c'] ) ? absint( $row['c'] ) : 0;
				}
			}
		}
		return $out;
	}

	public function get_chunk_by_id( $chunk_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'chunks' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $chunk_id ) ), ARRAY_A );
	}

	/**
	 * Count sources that have at least one active chunk.
	 */
	public function count_sources_with_active_chunks() {
		global $wpdb;
		$table = $this->db->get_table_name( 'chunks' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT source_id) FROM {$table} WHERE is_active = 1 AND chunk_status = %s",
				JSDW_AI_Chat_Knowledge_Constants::CHUNK_STATUS_ACTIVE
			)
		);
	}
}
