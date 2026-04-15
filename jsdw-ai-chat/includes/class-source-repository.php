<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Source_Repository {
	/**
	 * @var JSDW_AI_Chat_DB
	 */
	private $db;

	public function __construct( JSDW_AI_Chat_DB $db ) {
		$this->db = $db;
	}

	/**
	 * @param array<string,mixed> $candidate
	 * @return array<string,mixed>|null
	 */
	public function find_existing_for_candidate( array $candidate ) {
		global $wpdb;
		$table      = $this->db->get_table_name( 'sources' );
		$source_type = isset( $candidate['source_type'] ) ? (string) $candidate['source_type'] : '';

		if ( isset( $candidate['source_object_id'] ) && absint( $candidate['source_object_id'] ) > 0 ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE source_type = %s AND source_object_id = %d LIMIT 1",
					$source_type,
					absint( $candidate['source_object_id'] )
				),
				ARRAY_A
			);
			return is_array( $row ) ? $row : null;
		}

		if ( ! empty( $candidate['source_key'] ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE source_type = %s AND source_key = %s LIMIT 1",
					$source_type,
					(string) $candidate['source_key']
				),
				ARRAY_A
			);
			return is_array( $row ) ? $row : null;
		}

		if ( ! empty( $candidate['source_url'] ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE source_type = %s AND source_url = %s LIMIT 1",
					$source_type,
					(string) $candidate['source_url']
				),
				ARRAY_A
			);
			return is_array( $row ) ? $row : null;
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $candidate
	 * @param array<string,mixed> $decision
	 * @param string $status
	 * @param string $change_reason
	 * @return array<string,mixed> Keys: id (int), material_discovery_change (bool)
	 */
	public function upsert_source( array $candidate, array $decision, $status, $change_reason ) {
		global $wpdb;
		$table    = $this->db->get_table_name( 'sources' );
		$existing = $this->find_existing_for_candidate( $candidate );
		$now      = current_time( 'mysql', true );

		$access_visibility = JSDW_AI_Chat_Source_Visibility::sanitize_stored(
			isset( $candidate['access_visibility'] ) ? (string) $candidate['access_visibility'] : JSDW_AI_Chat_Source_Visibility::INTERNAL
		);

		$record = array(
			'source_type'           => sanitize_text_field( (string) $candidate['source_type'] ),
			'source_object_id'      => isset( $candidate['source_object_id'] ) ? absint( $candidate['source_object_id'] ) : null,
			'source_key'            => sanitize_text_field( (string) $candidate['source_key'] ),
			'source_url'            => isset( $candidate['source_url'] ) ? esc_url_raw( (string) $candidate['source_url'] ) : '',
			'title'                 => sanitize_text_field( (string) $candidate['title'] ),
			'status'                => sanitize_text_field( (string) $status ),
			'authority_level'       => absint( isset( $candidate['authority_level'] ) ? $candidate['authority_level'] : 50 ),
			'discovery_context'     => wp_json_encode( isset( $candidate['discovery_context'] ) ? $candidate['discovery_context'] : array() ),
			'visibility_flags'      => wp_json_encode( isset( $candidate['visibility_flags'] ) ? $candidate['visibility_flags'] : array() ),
			'access_visibility'     => $access_visibility,
			'last_wp_modified_gmt'  => ! empty( $candidate['last_wp_modified_gmt'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $candidate['last_wp_modified_gmt'] ) ) : null,
			'last_checked_gmt'      => $now,
			'needs_reindex'         => ! empty( $candidate['needs_reindex'] ) ? 1 : 0,
			'change_reason'         => sanitize_text_field( (string) $change_reason ),
			'content_version'       => isset( $candidate['content_version'] ) ? absint( $candidate['content_version'] ) : 1,
			'schema_version'        => JSDW_AI_CHAT_DB_SCHEMA_VERSION,
			'updated_at'            => $now,
		);

		$record['discovery_context'] = false === $record['discovery_context'] ? '{}' : $record['discovery_context'];
		$record['visibility_flags']  = false === $record['visibility_flags'] ? '{}' : $record['visibility_flags'];

		if ( is_array( $existing ) && isset( $existing['id'] ) ) {
			$material_change = $this->has_material_change( $existing, $record, (string) $status );
			if ( ! $material_change ) {
				$record['needs_reindex'] = isset( $existing['needs_reindex'] ) ? absint( $existing['needs_reindex'] ) : 0;
				$record['change_reason'] = isset( $existing['change_reason'] ) ? sanitize_text_field( (string) $existing['change_reason'] ) : '';
			}
			$wpdb->update( $table, $record, array( 'id' => absint( $existing['id'] ) ) );
			return array(
				'id'                          => absint( $existing['id'] ),
				'material_discovery_change'   => (bool) $material_change,
			);
		}

		$record['created_at'] = $now;
		$inserted = $wpdb->insert( $table, $record );
		if ( false === $inserted ) {
			$retry_existing = $this->find_existing_for_candidate( $candidate );
			if ( is_array( $retry_existing ) && isset( $retry_existing['id'] ) ) {
				$wpdb->update( $table, $record, array( 'id' => absint( $retry_existing['id'] ) ) );
				return array(
					'id'                        => absint( $retry_existing['id'] ),
					'material_discovery_change' => true,
				);
			}
			return array(
				'id'                        => 0,
				'material_discovery_change' => false,
			);
		}
		return array(
			'id'                        => absint( $wpdb->insert_id ),
			'material_discovery_change' => true,
		);
	}

	/**
	 * @param array<string,mixed> $existing
	 * @param array<string,mixed> $incoming
	 */
	private function has_material_change( array $existing, array $incoming, $status ) {
		$tracked_fields = array(
			'title',
			'source_url',
			'authority_level',
			'last_wp_modified_gmt',
			'discovery_context',
			'visibility_flags',
			'access_visibility',
		);
		foreach ( $tracked_fields as $field ) {
			$old = isset( $existing[ $field ] ) ? (string) $existing[ $field ] : '';
			$new = isset( $incoming[ $field ] ) ? (string) $incoming[ $field ] : '';
			if ( $old !== $new ) {
				return true;
			}
		}

		$old_status = isset( $existing['status'] ) ? (string) $existing['status'] : '';
		if ( $old_status !== (string) $status ) {
			return true;
		}

		return false;
	}

	/**
	 * @param array<string> $present_keys
	 */
	public function mark_missing_not_in_keys( array $present_keys ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$rows  = $wpdb->get_results( "SELECT id, source_key, status FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) ) {
			return;
		}
		$present = array_fill_keys( $present_keys, true );
		foreach ( $rows as $row ) {
			$key = isset( $row['source_key'] ) ? (string) $row['source_key'] : '';
			if ( '' === $key || isset( $present[ $key ] ) ) {
				continue;
			}
			$current_status = isset( $row['status'] ) ? (string) $row['status'] : '';
			if ( in_array( $current_status, array( JSDW_AI_Chat_DB::SOURCE_STATUS_EXCLUDED, JSDW_AI_Chat_DB::SOURCE_STATUS_DISABLED, JSDW_AI_Chat_DB::SOURCE_STATUS_MISSING, JSDW_AI_Chat_DB::SOURCE_STATUS_INACTIVE ), true ) ) {
				continue;
			}
			$wpdb->update(
				$table,
				array(
					'status'        => JSDW_AI_Chat_DB::SOURCE_STATUS_MISSING,
					'needs_reindex' => 1,
					'change_reason' => JSDW_AI_Chat_DB::CHANGE_VERIFY_MISSING,
					'updated_at'    => current_time( 'mysql', true ),
				),
				array( 'id' => absint( $row['id'] ) ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	public function list_sources( $filters = array(), $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table  = $this->db->get_table_name( 'sources' );
		$where  = array( '1=1' );
		$params = array();

		$allowed_lifecycle = array(
			JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE,
			JSDW_AI_Chat_DB::SOURCE_STATUS_INACTIVE,
			JSDW_AI_Chat_DB::SOURCE_STATUS_EXCLUDED,
			JSDW_AI_Chat_DB::SOURCE_STATUS_MISSING,
			JSDW_AI_Chat_DB::SOURCE_STATUS_DISABLED,
			JSDW_AI_Chat_DB::SOURCE_STATUS_PENDING,
		);
		$allowed_content = array(
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_PENDING,
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK,
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_FAILED,
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNSUPPORTED,
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNAVAILABLE,
		);
		$allowed_knowledge = array(
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_PENDING,
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_READY,
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED,
		);

		if ( ! empty( $filters['source_type'] ) ) {
			$where[]  = 'source_type = %s';
			$params[] = sanitize_text_field( (string) $filters['source_type'] );
		}
		if ( ! empty( $filters['status'] ) ) {
			$st = sanitize_key( (string) $filters['status'] );
			if ( in_array( $st, $allowed_lifecycle, true ) ) {
				$where[]  = 'status = %s';
				$params[] = $st;
			}
		}
		if ( ! empty( $filters['content_processing_status'] ) ) {
			$cs = sanitize_key( (string) $filters['content_processing_status'] );
			if ( in_array( $cs, $allowed_content, true ) ) {
				$where[]  = 'content_processing_status = %s';
				$params[] = $cs;
			}
		}
		if ( ! empty( $filters['knowledge_processing_status'] ) ) {
			$ks = sanitize_key( (string) $filters['knowledge_processing_status'] );
			if ( in_array( $ks, $allowed_knowledge, true ) ) {
				$where[]  = 'knowledge_processing_status = %s';
				$params[] = $ks;
			}
		}
		if ( ! empty( $filters['failed_any'] ) ) {
			$where[] = '( content_processing_status = %s OR knowledge_processing_status = %s )';
			$params[] = JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_FAILED;
			$params[] = JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED;
		}
		if ( isset( $filters['needs_reindex'] ) && '' !== (string) $filters['needs_reindex'] ) {
			$where[]  = 'needs_reindex = %d';
			$params[] = ! empty( $filters['needs_reindex'] ) ? 1 : 0;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC LIMIT %d OFFSET %d';
		$params[] = absint( $limit );
		$params[] = absint( $offset );
		$query    = $wpdb->prepare( $sql, $params );
		return $wpdb->get_results( $query, ARRAY_A );
	}

	public function get_source_by_id( $source_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $source_id ) ), ARRAY_A );
	}

	public function get_source_counts() {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$rows  = $wpdb->get_results( "SELECT status, source_type, COUNT(*) AS count_total FROM {$table} GROUP BY status, source_type", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	public function get_pending_reindex_count() {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE needs_reindex = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function list_manual_sources() {
		global $wpdb;
		$table = $this->db->get_table_name( 'manual_sources' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_manual_source_by_id( $manual_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'manual_sources' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $manual_id ) ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Whitelisted content-state update for Phase 3.
	 *
	 * @param array<string,mixed> $fields
	 * @return bool
	 */
	public function update_content_state( $source_id, array $fields ) {
		global $wpdb;
		$table    = $this->db->get_table_name( 'sources' );
		$allowed  = array(
			'raw_snapshot_text',
			'normalized_snapshot_text',
			'content_hash',
			'title_hash',
			'structure_hash',
			'metadata_hash',
			'last_content_check_gmt',
			'last_content_change_gmt',
			'normalized_length',
			'extraction_method',
			'content_processing_status',
			'content_processing_reason',
			'material_content_change',
			'needs_reindex',
			'content_version',
			'updated_at',
		);
		$update = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				$update[ $key ] = $fields[ $key ];
			}
		}
		if ( empty( $update ) ) {
			return false;
		}
		$result = $wpdb->update( $table, $update, array( 'id' => absint( $source_id ) ) );
		return false !== $result;
	}

	/**
	 * Phase 4: knowledge state only.
	 *
	 * @param array<string,mixed> $fields
	 * @return bool
	 */
	public function update_knowledge_state( $source_id, array $fields ) {
		global $wpdb;
		$table   = $this->db->get_table_name( 'sources' );
		$allowed = array(
			'knowledge_processing_status',
			'knowledge_processing_reason',
			'last_knowledge_processing_gmt',
			'knowledge_headings_json',
			'updated_at',
		);
		$update = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				$update[ $key ] = $fields[ $key ];
			}
		}
		if ( empty( $update ) ) {
			return false;
		}
		$result = $wpdb->update( $table, $update, array( 'id' => absint( $source_id ) ) );
		return false !== $result;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function fetch_sources_pending_knowledge_processing( $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = %s AND content_processing_status = %s AND knowledge_processing_status = %s ORDER BY updated_at ASC LIMIT %d OFFSET %d",
			JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE,
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK,
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_PENDING,
			absint( $limit ),
			absint( $offset )
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Sources with OK content but knowledge older than threshold (or never run).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function fetch_stale_knowledge_sources( $older_than_gmt, $limit = 50 ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$ts    = is_numeric( $older_than_gmt ) ? gmdate( 'Y-m-d H:i:s', absint( $older_than_gmt ) ) : gmdate( 'Y-m-d H:i:s', strtotime( (string) $older_than_gmt ) );
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = %s AND content_processing_status = %s AND (last_knowledge_processing_gmt IS NULL OR last_knowledge_processing_gmt < %s) ORDER BY last_knowledge_processing_gmt IS NULL DESC, last_knowledge_processing_gmt ASC LIMIT %d",
			JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE,
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK,
			$ts,
			absint( $limit )
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string,int>
	 */
	public function get_knowledge_processing_status_counts() {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$rows  = $wpdb->get_results( "SELECT knowledge_processing_status, COUNT(*) AS c FROM {$table} GROUP BY knowledge_processing_status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$k = isset( $row['knowledge_processing_status'] ) ? (string) $row['knowledge_processing_status'] : '';
				if ( '' !== $k ) {
					$out[ $k ] = isset( $row['c'] ) ? absint( $row['c'] ) : 0;
				}
			}
		}
		return $out;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function fetch_sources_pending_content_processing( $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE content_processing_status = %s AND status = %s ORDER BY updated_at ASC LIMIT %d OFFSET %d",
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_PENDING,
			JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE,
			absint( $limit ),
			absint( $offset )
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Sources with last content check older than threshold (GMT mysql string or strtotime).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function fetch_stale_content_sources( $older_than_gmt, $limit = 50 ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$ts    = is_numeric( $older_than_gmt ) ? gmdate( 'Y-m-d H:i:s', absint( $older_than_gmt ) ) : gmdate( 'Y-m-d H:i:s', strtotime( (string) $older_than_gmt ) );
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = %s AND (last_content_check_gmt IS NULL OR last_content_check_gmt < %s) ORDER BY last_content_check_gmt IS NULL DESC, last_content_check_gmt ASC LIMIT %d",
			JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE,
			$ts,
			absint( $limit )
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string,int>
	 */
	public function get_content_processing_status_counts() {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$rows  = $wpdb->get_results( "SELECT content_processing_status, COUNT(*) AS c FROM {$table} GROUP BY content_processing_status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$k = isset( $row['content_processing_status'] ) ? (string) $row['content_processing_status'] : '';
				if ( '' !== $k ) {
					$out[ $k ] = isset( $row['c'] ) ? absint( $row['c'] ) : 0;
				}
			}
		}
		return $out;
	}

	/**
	 * @return array<string,int>
	 */
	public function get_material_content_change_counts() {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$rows  = $wpdb->get_results( "SELECT material_content_change, COUNT(*) AS c FROM {$table} GROUP BY material_content_change", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out   = array( '0' => 0, '1' => 0 );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$k = isset( $row['material_content_change'] ) ? (string) (int) $row['material_content_change'] : '0';
				$out[ $k ] = isset( $row['c'] ) ? absint( $row['c'] ) : 0;
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return int
	 */
	/**
	 * One-time backfill for access_visibility (migration 1.5.0).
	 *
	 * @param array<string,mixed> $settings Settings from JSDW_AI_Chat_Settings::get_all().
	 */
	public function backfill_access_visibility_rows( array $settings ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT id, source_type, source_url, visibility_flags FROM {$table}", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return;
		}
		foreach ( $rows as $row ) {
			$flags = array();
			if ( ! empty( $row['visibility_flags'] ) ) {
				$decoded = json_decode( (string) $row['visibility_flags'], true );
				if ( is_array( $decoded ) ) {
					$flags = $decoded;
				}
			}
			$candidate = array(
				'source_type'      => (string) ( $row['source_type'] ?? '' ),
				'source_url'       => (string) ( $row['source_url'] ?? '' ),
				'visibility_flags' => $flags,
			);
			$v = JSDW_AI_Chat_Source_Visibility::compute_for_candidate( $candidate, $settings );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array( 'access_visibility' => $v ),
				array( 'id' => absint( $row['id'] ) ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Default manual_sources.access_visibility to internal for legacy rows.
	 */
	public function backfill_manual_sources_access_visibility() {
		global $wpdb;
		$table = $this->db->get_table_name( 'manual_sources' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "UPDATE {$table} SET access_visibility = 'internal' WHERE access_visibility = '' OR access_visibility IS NULL" );
	}

	/**
	 * @return array<string,int>
	 */
	public function get_access_visibility_counts() {
		global $wpdb;
		$table = $this->db->get_table_name( 'sources' );
		$rows  = $wpdb->get_results( "SELECT access_visibility, COUNT(*) AS c FROM {$table} GROUP BY access_visibility", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out   = array(
			JSDW_AI_Chat_Source_Visibility::PUBLIC_VIS     => 0,
			JSDW_AI_Chat_Source_Visibility::INTERNAL       => 0,
			JSDW_AI_Chat_Source_Visibility::ADMIN_ONLY       => 0,
			'_unclassified' => 0,
		);
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$k = isset( $row['access_visibility'] ) ? (string) $row['access_visibility'] : '';
				$c = isset( $row['c'] ) ? absint( $row['c'] ) : 0;
				if ( in_array( $k, array( JSDW_AI_Chat_Source_Visibility::PUBLIC_VIS, JSDW_AI_Chat_Source_Visibility::INTERNAL, JSDW_AI_Chat_Source_Visibility::ADMIN_ONLY ), true ) ) {
					$out[ $k ] = $c;
				} else {
					$out['_unclassified'] += $c;
				}
			}
		}
		return $out;
	}

	public function save_manual_source( array $data ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'manual_sources' );
		$now   = current_time( 'mysql', true );
		$av    = isset( $data['access_visibility'] ) ? sanitize_key( (string) $data['access_visibility'] ) : JSDW_AI_Chat_Source_Visibility::INTERNAL;
		if ( ! in_array( $av, array( JSDW_AI_Chat_Source_Visibility::PUBLIC_VIS, JSDW_AI_Chat_Source_Visibility::INTERNAL, JSDW_AI_Chat_Source_Visibility::ADMIN_ONLY ), true ) ) {
			$av = JSDW_AI_Chat_Source_Visibility::INTERNAL;
		}
		$record = array(
			'source_key'         => sanitize_text_field( (string) $data['source_key'] ),
			'title'              => sanitize_text_field( (string) $data['title'] ),
			'source_url'         => ! empty( $data['source_url'] ) ? esc_url_raw( (string) $data['source_url'] ) : '',
			'source_notes'       => sanitize_textarea_field( (string) ( isset( $data['source_notes'] ) ? $data['source_notes'] : '' ) ),
			'allow_behavior'     => 'deny' === ( isset( $data['allow_behavior'] ) ? $data['allow_behavior'] : '' ) ? 'deny' : 'allow',
			'source_scope'       => sanitize_text_field( (string) ( isset( $data['source_scope'] ) ? $data['source_scope'] : '' ) ),
			'enabled'            => ! empty( $data['enabled'] ) ? 1 : 0,
			'authority_override' => isset( $data['authority_override'] ) && '' !== (string) $data['authority_override'] ? absint( $data['authority_override'] ) : null,
			'access_visibility'  => $av,
			'updated_at'         => $now,
		);

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $table, $record, array( 'id' => absint( $data['id'] ) ) );
			return absint( $data['id'] );
		}

		$record['created_at'] = $now;
		$wpdb->insert( $table, $record );
		return absint( $wpdb->insert_id );
	}
}
