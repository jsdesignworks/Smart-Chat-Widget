<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Job_Repository {
	const STATUS_PENDING   = JSDW_AI_Chat_DB::JOB_STATUS_PENDING;
	const STATUS_RUNNING   = JSDW_AI_Chat_DB::JOB_STATUS_RUNNING;
	const STATUS_COMPLETED = JSDW_AI_Chat_DB::JOB_STATUS_COMPLETED;
	const STATUS_FAILED    = JSDW_AI_Chat_DB::JOB_STATUS_FAILED;
	const TYPE_SOURCE_DISCOVERY_FULL_SCAN = 'source_discovery_full_scan';
	const TYPE_SOURCE_DISCOVERY_SINGLE    = 'source_discovery_single';
	const TYPE_SOURCE_SYNC                = 'source_sync';
	const TYPE_SOURCE_VERIFY_MISSING      = 'source_verify_missing';
	const TYPE_SOURCE_CONTENT_PROCESS       = 'source_content_process';
	const TYPE_SOURCE_CONTENT_PROCESS_BATCH = 'source_content_process_batch';
	const TYPE_SOURCE_CONTENT_VERIFY        = 'source_content_verify';
	const TYPE_SOURCE_CONTENT_REFRESH       = 'source_content_refresh';
	const TYPE_SOURCE_KNOWLEDGE_PROCESS       = 'source_knowledge_process';
	const TYPE_SOURCE_KNOWLEDGE_PROCESS_BATCH = 'source_knowledge_process_batch';
	const TYPE_SOURCE_FACT_EXTRACT          = 'source_fact_extract';
	const TYPE_SOURCE_FACT_REFRESH          = 'source_fact_refresh';
	const TYPE_SOURCE_KNOWLEDGE_VERIFY      = 'source_knowledge_verify';
	const TYPE_SOURCE_KNOWLEDGE_REFRESH     = 'source_knowledge_refresh';

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

	public function add_job( $job_type, $payload = array(), $priority = 10 ) {
		global $wpdb;

		$table = $this->db->get_table_name( 'jobs' );
		$row     = array(
			'job_type'     => sanitize_text_field( (string) $job_type ),
			'priority'     => absint( $priority ),
			'status'       => self::STATUS_PENDING,
			'payload_json' => wp_json_encode( $payload ),
			'queued_at'    => current_time( 'mysql', true ),
			'created_at'   => current_time( 'mysql', true ),
			'updated_at'   => current_time( 'mysql', true ),
		);
		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

		if ( is_array( $payload ) && isset( $payload['source_id'] ) && absint( $payload['source_id'] ) > 0 ) {
			$row['source_id'] = absint( $payload['source_id'] );
			$formats[]        = '%d';
		}

		$inserted = $wpdb->insert(
			$table,
			$row,
			$formats
		);

		if ( false === $inserted ) {
			$this->logger->error( 'job_add_failed', 'Failed to add job to queue.', array( 'job_type' => $job_type ) );
			return 0;
		}

		$job_id = (int) $wpdb->insert_id;
		$this->logger->info( 'job_added', 'Job was added to queue.', array( 'job_id' => $job_id, 'job_type' => $job_type ) );

		return $job_id;
	}

	public function get_pending_jobs( $limit = 20 ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'jobs' );
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY priority ASC, id ASC LIMIT %d",
			self::STATUS_PENDING,
			absint( $limit )
		);
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public function get_pending_jobs_by_types( array $job_types, $limit = 20 ) {
		global $wpdb;
		if ( empty( $job_types ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $job_types ), '%s' ) );
		$table        = $this->db->get_table_name( 'jobs' );
		$params       = array_merge( array( self::STATUS_PENDING ), array_map( 'sanitize_text_field', $job_types ), array( absint( $limit ) ) );
		$sql          = "SELECT * FROM {$table} WHERE status = %s AND job_type IN ({$placeholders}) ORDER BY priority ASC, id ASC LIMIT %d";
		$query        = $wpdb->prepare( $sql, $params );
		return $wpdb->get_results( $query, ARRAY_A );
	}

	public function mark_job_running( $job_id ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'jobs' );
		$wpdb->update(
			$table,
			array(
				'status'     => self::STATUS_RUNNING,
				'started_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $job_id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public function mark_job_complete( $job_id, $result = array() ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'jobs' );
		$updated = $wpdb->update(
			$table,
			array(
				'status'      => self::STATUS_COMPLETED,
				'result_json' => wp_json_encode( $result ),
				'finished_at' => current_time( 'mysql', true ),
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $job_id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			$this->logger->error( 'job_complete_failed', 'Failed to mark job complete.', array( 'job_id' => absint( $job_id ) ) );
			return;
		}

		$this->logger->info( 'job_completed', 'Job marked complete.', array( 'job_id' => absint( $job_id ) ) );
	}

	public function mark_job_failed( $job_id, $error_message ) {
		global $wpdb;
		$table = $this->db->get_table_name( 'jobs' );
		$wpdb->update(
			$table,
			array(
				'status'        => self::STATUS_FAILED,
				'error_message' => sanitize_textarea_field( (string) $error_message ),
				'finished_at'   => current_time( 'mysql', true ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $job_id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		$this->logger->error( 'job_failed', 'Job marked as failed.', array( 'job_id' => absint( $job_id ) ) );
	}

	public function get_queue_counts_by_job_type( array $job_types ) {
		global $wpdb;
		if ( empty( $job_types ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $job_types ), '%s' ) );
		$table        = $this->db->get_table_name( 'jobs' );
		$params       = array_map( 'sanitize_text_field', $job_types );
		$sql          = "SELECT job_type, status, COUNT(*) as total FROM {$table} WHERE job_type IN ({$placeholders}) GROUP BY job_type, status";
		$query        = $wpdb->prepare( $sql, $params );
		$rows         = $wpdb->get_results( $query, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
