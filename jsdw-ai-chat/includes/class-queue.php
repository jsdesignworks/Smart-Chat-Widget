<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Queue {
	/**
	 * @var JSDW_AI_Chat_Job_Repository
	 */
	private $jobs;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	public function __construct( JSDW_AI_Chat_Job_Repository $jobs, JSDW_AI_Chat_Logger $logger ) {
		$this->jobs   = $jobs;
		$this->logger = $logger;
	}

	public function add_job( $job_type, $payload = array(), $priority = 10 ) {
		return $this->jobs->add_job( $job_type, $payload, $priority );
	}

	public function get_pending_jobs( $limit = 20 ) {
		return $this->jobs->get_pending_jobs( $limit );
	}

	public function get_pending_jobs_by_types( array $types, $limit = 20 ) {
		return $this->jobs->get_pending_jobs_by_types( $types, $limit );
	}

	public function complete_job( $job_id, $result = array() ) {
		$this->jobs->mark_job_complete( $job_id, $result );
	}

	public function mark_job_running( $job_id ) {
		$this->jobs->mark_job_running( $job_id );
	}

	public function fail_job( $job_id, $error_message ) {
		$this->jobs->mark_job_failed( $job_id, $error_message );
	}

	public function get_discovery_queue_counts() {
		return $this->jobs->get_queue_counts_by_job_type(
			array(
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_DISCOVERY_FULL_SCAN,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_DISCOVERY_SINGLE,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_SYNC,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_VERIFY_MISSING,
			)
		);
	}

	public function get_content_queue_counts() {
		return $this->jobs->get_queue_counts_by_job_type(
			array(
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS_BATCH,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_VERIFY,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_REFRESH,
			)
		);
	}

	public function get_knowledge_queue_counts() {
		return $this->jobs->get_queue_counts_by_job_type(
			array(
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS_BATCH,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_FACT_EXTRACT,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_FACT_REFRESH,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_VERIFY,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_REFRESH,
			)
		);
	}

	public function get_status() {
		$lock_until = (int) get_option( JSDW_AI_Chat_Cron::LOCK_OPTION_NAME, 0 );
		return array(
			'locked'     => ( $lock_until > time() ),
			'lock_until' => $lock_until,
			'pending'    => count( $this->get_pending_jobs( 100 ) ),
		);
	}
}
