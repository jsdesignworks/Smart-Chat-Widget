<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Cron {
	const HOOK_HOURLY      = 'jsdw_ai_chat_hourly_maintenance';
	const HOOK_DAILY       = 'jsdw_ai_chat_daily_verification';
	const HOOK_QUEUE_RUN   = 'jsdw_ai_chat_queue_runner';
	const HOOK_DISCOVERY_FULL_SCAN = 'jsdw_ai_chat_discovery_full_scan';
	const HOOK_DISCOVERY_VERIFY_MISSING = 'jsdw_ai_chat_discovery_verify_missing';
	const HOOK_CONTENT_VERIFY  = 'jsdw_ai_chat_content_verification';
	const HOOK_CONTENT_REFRESH = 'jsdw_ai_chat_content_refresh';
	const HOOK_KNOWLEDGE_VERIFY  = 'jsdw_ai_chat_knowledge_verification';
	const HOOK_KNOWLEDGE_REFRESH = 'jsdw_ai_chat_knowledge_refresh';
	const LOCK_OPTION_NAME = 'jsdw_ai_chat_queue_lock';

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;
	/**
	 * @var JSDW_AI_Chat_Queue|null
	 */
	private $queue;

	/**
	 * @var JSDW_AI_Chat_Source_Registry|null
	 */
	private $source_registry;

	/**
	 * @var JSDW_AI_Chat_Source_Content_Processor|null
	 */
	private $content_processor;

	/**
	 * @var JSDW_AI_Chat_Source_Knowledge_Processor|null
	 */
	private $knowledge_processor;

	public function __construct( JSDW_AI_Chat_Logger $logger ) {
		$this->logger = $logger;
		$this->queue  = null;
		$this->source_registry = null;
		$this->content_processor = null;
		$this->knowledge_processor = null;
	}

	public function set_dependencies( JSDW_AI_Chat_Queue $queue, JSDW_AI_Chat_Source_Registry $source_registry, JSDW_AI_Chat_Source_Content_Processor $content_processor, ?JSDW_AI_Chat_Source_Knowledge_Processor $knowledge_processor = null ) {
		$this->queue                 = $queue;
		$this->source_registry       = $source_registry;
		$this->content_processor     = $content_processor;
		$this->knowledge_processor   = $knowledge_processor;
	}

	public function register_handlers() {
		add_action( self::HOOK_HOURLY, array( $this, 'handle_hourly_maintenance' ) );
		add_action( self::HOOK_DAILY, array( $this, 'handle_daily_verification' ) );
		add_action( self::HOOK_QUEUE_RUN, array( $this, 'handle_queue_runner' ) );
		add_action( self::HOOK_DISCOVERY_FULL_SCAN, array( $this, 'handle_discovery_full_scan' ) );
		add_action( self::HOOK_DISCOVERY_VERIFY_MISSING, array( $this, 'handle_discovery_verify_missing' ) );
		add_action( self::HOOK_CONTENT_VERIFY, array( $this, 'handle_content_verification' ) );
		add_action( self::HOOK_CONTENT_REFRESH, array( $this, 'handle_content_refresh' ) );
		add_action( self::HOOK_KNOWLEDGE_VERIFY, array( $this, 'handle_knowledge_verification' ) );
		add_action( self::HOOK_KNOWLEDGE_REFRESH, array( $this, 'handle_knowledge_refresh' ) );
	}

	public function schedule_events() {
		// Normalize scheduled hooks so repeated activation cannot accumulate duplicates.
		$this->clear_events();

		if ( ! wp_next_scheduled( self::HOOK_HOURLY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK_HOURLY );
		}
		if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::HOOK_DAILY );
		}
		if ( ! wp_next_scheduled( self::HOOK_QUEUE_RUN ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK_QUEUE_RUN );
		}
		if ( ! wp_next_scheduled( self::HOOK_DISCOVERY_FULL_SCAN ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_DISCOVERY_FULL_SCAN );
		}
		if ( ! wp_next_scheduled( self::HOOK_DISCOVERY_VERIFY_MISSING ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_DISCOVERY_VERIFY_MISSING );
		}
		if ( ! wp_next_scheduled( self::HOOK_CONTENT_VERIFY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_CONTENT_VERIFY );
		}
		if ( ! wp_next_scheduled( self::HOOK_CONTENT_REFRESH ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::HOOK_CONTENT_REFRESH );
		}
		if ( ! wp_next_scheduled( self::HOOK_KNOWLEDGE_VERIFY ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::HOOK_KNOWLEDGE_VERIFY );
		}
		if ( ! wp_next_scheduled( self::HOOK_KNOWLEDGE_REFRESH ) ) {
			wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', self::HOOK_KNOWLEDGE_REFRESH );
		}

		$this->logger->info( 'cron_registered', 'Cron events registered.' );
	}

	public function clear_events() {
		wp_clear_scheduled_hook( self::HOOK_HOURLY );
		wp_clear_scheduled_hook( self::HOOK_DAILY );
		wp_clear_scheduled_hook( self::HOOK_QUEUE_RUN );
		wp_clear_scheduled_hook( self::HOOK_DISCOVERY_FULL_SCAN );
		wp_clear_scheduled_hook( self::HOOK_DISCOVERY_VERIFY_MISSING );
		wp_clear_scheduled_hook( self::HOOK_CONTENT_VERIFY );
		wp_clear_scheduled_hook( self::HOOK_CONTENT_REFRESH );
		wp_clear_scheduled_hook( self::HOOK_KNOWLEDGE_VERIFY );
		wp_clear_scheduled_hook( self::HOOK_KNOWLEDGE_REFRESH );
		$this->logger->info( 'cron_cleared', 'Cron events cleared.' );
	}

	public function get_status() {
		return array(
			'hourly'      => (bool) wp_next_scheduled( self::HOOK_HOURLY ),
			'daily'       => (bool) wp_next_scheduled( self::HOOK_DAILY ),
			'queue_runner'=> (bool) wp_next_scheduled( self::HOOK_QUEUE_RUN ),
			'discovery_full_scan' => (bool) wp_next_scheduled( self::HOOK_DISCOVERY_FULL_SCAN ),
			'discovery_verify_missing' => (bool) wp_next_scheduled( self::HOOK_DISCOVERY_VERIFY_MISSING ),
			'content_verification'     => (bool) wp_next_scheduled( self::HOOK_CONTENT_VERIFY ),
			'content_refresh'          => (bool) wp_next_scheduled( self::HOOK_CONTENT_REFRESH ),
			'knowledge_verification'   => (bool) wp_next_scheduled( self::HOOK_KNOWLEDGE_VERIFY ),
			'knowledge_refresh'        => (bool) wp_next_scheduled( self::HOOK_KNOWLEDGE_REFRESH ),
		);
	}

	public function handle_hourly_maintenance() {
		update_option( JSDW_AI_CHAT_OPTION_LAST_CRON_RUN, current_time( 'mysql', true ), false );
		$this->logger->info( 'cron_hourly', 'Hourly maintenance ran.' );
	}

	public function handle_daily_verification() {
		update_option( JSDW_AI_CHAT_OPTION_LAST_CRON_RUN, current_time( 'mysql', true ), false );
		$this->logger->info( 'cron_daily', 'Daily verification ran.' );
	}

	public function handle_queue_runner() {
		update_option( JSDW_AI_CHAT_OPTION_LAST_CRON_RUN, current_time( 'mysql', true ), false );
		if ( ! $this->queue instanceof JSDW_AI_Chat_Queue || ! $this->source_registry instanceof JSDW_AI_Chat_Source_Registry ) {
			$this->logger->warning( 'cron_queue_runner_skipped', 'Queue runner skipped because dependencies are unavailable.' );
			return;
		}

		$jobs = $this->queue->get_pending_jobs_by_types(
			array(
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_DISCOVERY_FULL_SCAN,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_DISCOVERY_SINGLE,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_SYNC,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_VERIFY_MISSING,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_ELIGIBILITY_REVALIDATE,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS_BATCH,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_VERIFY,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_REFRESH,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS_BATCH,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_FACT_EXTRACT,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_FACT_REFRESH,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_VERIFY,
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_REFRESH,
			),
			10
		);

		foreach ( $jobs as $job ) {
			$job_id   = isset( $job['id'] ) ? absint( $job['id'] ) : 0;
			$job_type = isset( $job['job_type'] ) ? (string) $job['job_type'] : '';
			if ( $job_id <= 0 ) {
				continue;
			}

			$this->queue->mark_job_running( $job_id );
			try {
				$payload = isset( $job['payload_json'] ) ? json_decode( (string) $job['payload_json'], true ) : array();
				if ( ! is_array( $payload ) ) {
					$payload = array();
				}
				$result = array();
				if ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_DISCOVERY_FULL_SCAN === $job_type || JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_SYNC === $job_type ) {
					$result = $this->source_registry->run_full_scan();
				} elseif ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_DISCOVERY_SINGLE === $job_type ) {
					$result = $this->source_registry->run_single_post_scan( isset( $payload['post_id'] ) ? absint( $payload['post_id'] ) : 0 );
				} elseif ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_VERIFY_MISSING === $job_type ) {
					$result = $this->source_registry->run_full_scan();
				} elseif ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_ELIGIBILITY_REVALIDATE === $job_type ) {
					$result = $this->source_registry->run_eligibility_revalidation_job( $payload );
				} elseif ( $this->content_processor instanceof JSDW_AI_Chat_Source_Content_Processor ) {
					if ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS === $job_type ) {
						$result = $this->content_processor->process_single( isset( $payload['source_id'] ) ? absint( $payload['source_id'] ) : 0 );
					} elseif ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS_BATCH === $job_type ) {
						$lim = isset( $payload['batch_size'] ) ? absint( $payload['batch_size'] ) : 10;
						$off = isset( $payload['cursor'] ) ? absint( $payload['cursor'] ) : 0;
						$result = $this->content_processor->process_batch( $lim, $off );
					} elseif ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_VERIFY === $job_type ) {
						$older = isset( $payload['older_than'] ) ? (string) $payload['older_than'] : '-1 day';
						$lim   = isset( $payload['limit'] ) ? absint( $payload['limit'] ) : 25;
						$result = $this->content_processor->verify_stale( $older, $lim );
					} elseif ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_REFRESH === $job_type ) {
						$older = isset( $payload['older_than'] ) ? (string) $payload['older_than'] : '-30 days';
						$lim   = isset( $payload['limit'] ) ? absint( $payload['limit'] ) : 50;
						$result = $this->content_processor->verify_stale( $older, $lim );
					}
				}
				if ( $this->knowledge_processor instanceof JSDW_AI_Chat_Source_Knowledge_Processor ) {
					if ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS === $job_type ) {
						$result = $this->knowledge_processor->process_single( isset( $payload['source_id'] ) ? absint( $payload['source_id'] ) : 0 );
					} elseif ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS_BATCH === $job_type ) {
						$lim = isset( $payload['batch_size'] ) ? absint( $payload['batch_size'] ) : 10;
						$off = isset( $payload['cursor'] ) ? absint( $payload['cursor'] ) : 0;
						$result = $this->knowledge_processor->process_batch( $lim, $off );
					} elseif ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_VERIFY === $job_type || JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_REFRESH === $job_type || JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_FACT_REFRESH === $job_type ) {
						$older = isset( $payload['older_than'] ) ? (string) $payload['older_than'] : '-7 days';
						$lim   = isset( $payload['limit'] ) ? absint( $payload['limit'] ) : 25;
						$result = $this->knowledge_processor->verify_stale( $older, $lim );
					} elseif ( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_FACT_EXTRACT === $job_type ) {
						$result = $this->knowledge_processor->process_single( isset( $payload['source_id'] ) ? absint( $payload['source_id'] ) : 0 );
					}
				}
				$this->queue->complete_job( $job_id, $result );
			} catch ( Throwable $throwable ) {
				$this->queue->fail_job( $job_id, $throwable->getMessage() );
			}
		}

		$this->logger->info( 'cron_queue_runner', 'Queue runner cron triggered.' );
	}

	public function handle_discovery_full_scan() {
		if ( $this->source_registry instanceof JSDW_AI_Chat_Source_Registry ) {
			$this->source_registry->queue_full_scan();
		}
	}

	public function handle_discovery_verify_missing() {
		if ( $this->source_registry instanceof JSDW_AI_Chat_Source_Registry ) {
			$this->source_registry->queue_verify_missing();
		}
	}

	public function handle_content_verification() {
		if ( $this->queue instanceof JSDW_AI_Chat_Queue ) {
			$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_VERIFY, array( 'older_than' => '-1 day' ), 12 );
			$this->logger->info( 'content_verify_scheduled', 'Queued stale content verification job.' );
		}
	}

	public function handle_content_refresh() {
		if ( $this->queue instanceof JSDW_AI_Chat_Queue ) {
			$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_REFRESH, array( 'older_than' => '-30 days' ), 11 );
			$this->logger->info( 'content_refresh_scheduled', 'Queued content refresh job.' );
		}
	}

	public function handle_knowledge_verification() {
		if ( $this->queue instanceof JSDW_AI_Chat_Queue ) {
			$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_VERIFY, array( 'older_than' => '-3 days', 'limit' => 15 ), 13 );
			$this->logger->info( 'knowledge_verify_scheduled', 'Queued knowledge verification job.' );
		}
	}

	public function handle_knowledge_refresh() {
		if ( $this->queue instanceof JSDW_AI_Chat_Queue ) {
			$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_REFRESH, array( 'older_than' => '-30 days', 'limit' => 25 ), 12 );
			$this->logger->info( 'knowledge_refresh_scheduled', 'Queued knowledge refresh job.' );
		}
	}
}
