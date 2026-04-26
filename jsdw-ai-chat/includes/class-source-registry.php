<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Source_Registry {
	/**
	 * @var JSDW_AI_Chat_Source_Discovery
	 */
	private $discovery;

	/**
	 * @var JSDW_AI_Chat_Source_Rules
	 */
	private $rules;

	/**
	 * @var JSDW_AI_Chat_Source_Repository
	 */
	private $repository;

	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	/**
	 * @var JSDW_AI_Chat_Queue
	 */
	private $queue;

	public function __construct( JSDW_AI_Chat_Source_Discovery $discovery, JSDW_AI_Chat_Source_Rules $rules, JSDW_AI_Chat_Source_Repository $repository, JSDW_AI_Chat_Settings $settings, JSDW_AI_Chat_Logger $logger, JSDW_AI_Chat_Queue $queue ) {
		$this->discovery  = $discovery;
		$this->rules      = $rules;
		$this->repository = $repository;
		$this->settings   = $settings;
		$this->logger     = $logger;
		$this->queue      = $queue;
	}

	public function queue_full_scan() {
		$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_DISCOVERY_FULL_SCAN, array( 'requested_at' => current_time( 'mysql', true ) ), 5 );
	}

	public function queue_single_post( $post_id ) {
		$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_DISCOVERY_SINGLE, array( 'post_id' => absint( $post_id ) ), 10 );
	}

	public function queue_verify_missing() {
		$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_VERIFY_MISSING, array(), 20 );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function run_full_scan() {
		$settings = $this->settings->get_all();
		$this->logger->info( 'source_discovery_started', 'Full source discovery scan started.' );

		$candidates  = $this->discovery->discover_all();
		$present_keys = array();
		$processed    = 0;
		$new_count    = 0;
		$updated_count = 0;
		$excluded_count = 0;

		foreach ( $candidates as $candidate ) {
			$candidate['authority_level'] = $this->resolve_authority( $candidate, $settings );
			$candidate['access_visibility'] = JSDW_AI_Chat_Source_Visibility::compute_for_candidate( $candidate, $settings );
			$decision = $this->rules->evaluate_candidate( $candidate, $settings );
			$existing = $this->repository->find_existing_for_candidate( $candidate );
			$present_keys[] = (string) $candidate['source_key'];
			$status = $this->resolve_status_for_candidate( $candidate, $decision );
			$reason = $decision['allowed'] ? JSDW_AI_Chat_DB::CHANGE_DISCOVERED_NEW : JSDW_AI_Chat_DB::CHANGE_RULE_EXCLUDED;
			$candidate['needs_reindex'] = empty( $existing );
			$upsert    = $this->repository->upsert_source( $candidate, $decision, $status, $reason );
			$source_id = isset( $upsert['id'] ) ? absint( $upsert['id'] ) : 0;
			$processed++;

			if ( empty( $existing ) ) {
				$new_count++;
				$this->logger->info( 'source_discovered', 'New source discovered.', array( 'source_id' => $source_id, 'source_key' => $candidate['source_key'] ) );
			} elseif ( $decision['allowed'] ) {
				$updated_count++;
				$this->logger->info( 'source_updated', 'Source updated during discovery.', array( 'source_id' => $source_id, 'source_key' => $candidate['source_key'] ) );
			} else {
				$excluded_count++;
				$this->logger->info( 'source_excluded', 'Source excluded by rules.', array( 'source_key' => $candidate['source_key'], 'reason_code' => $decision['reason_code'] ) );
			}

			if ( $source_id > 0 && ! empty( $decision['allowed'] ) && JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE === $status && ! empty( $upsert['material_discovery_change'] ) ) {
				$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS, array( 'source_id' => $source_id ), 15 );
				$this->logger->info( 'content_queued', 'Content processing job queued after discovery.', array( 'source_id' => $source_id ) );
			}
		}

		$this->repository->mark_missing_not_in_keys( array_values( array_unique( $present_keys ) ) );
		$result = array(
			'processed' => $processed,
			'new'       => $new_count,
			'updated'   => $updated_count,
			'excluded'  => $excluded_count,
		);

		update_option( JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_SCAN, current_time( 'mysql', true ), false );
		update_option( JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_RESULT, $result, false );
		$this->logger->info( 'source_discovery_completed', 'Full source discovery scan completed.', $result );

		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function run_single_post_scan( $post_id ) {
		$settings   = $this->settings->get_all();
		$candidates = $this->discovery->discover_single_post( $post_id );
		if ( empty( $candidates ) ) {
			return array( 'processed' => 0, 'message' => 'No candidate found for post.' );
		}

		$candidate = $candidates[0];
		$candidate['authority_level'] = $this->resolve_authority( $candidate, $settings );
		$candidate['access_visibility'] = JSDW_AI_Chat_Source_Visibility::compute_for_candidate( $candidate, $settings );
		$decision = $this->rules->evaluate_candidate( $candidate, $settings );
		$status   = $this->resolve_status_for_candidate( $candidate, $decision );
		$reason   = $decision['allowed'] ? JSDW_AI_Chat_DB::CHANGE_SOURCE_UPDATED : JSDW_AI_Chat_DB::CHANGE_RULE_EXCLUDED;
		$candidate['needs_reindex'] = true;
		$upsert    = $this->repository->upsert_source( $candidate, $decision, $status, $reason );
		$source_id = isset( $upsert['id'] ) ? absint( $upsert['id'] ) : 0;

		if ( $source_id > 0 && ! empty( $decision['allowed'] ) && JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE === $status && ! empty( $upsert['material_discovery_change'] ) ) {
			$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS, array( 'source_id' => $source_id ), 15 );
			$this->logger->info( 'content_queued', 'Content processing job queued after single post discovery.', array( 'source_id' => $source_id ) );
		}

		return array(
			'processed' => 1,
			'source_id' => $source_id,
			'allowed'   => ! empty( $decision['allowed'] ),
			'reason'    => (string) $decision['reason_code'],
		);
	}

	public function on_save_post( $post_id, $post ) {
		if ( ! $this->should_process_post_event( $post_id, $post ) ) {
			return;
		}
		$this->queue_single_post( $post_id );
	}

	public function on_post_deleted( $post_id ) {
		if ( $this->is_post_sync_debounced( $post_id ) ) {
			return;
		}
		$this->queue_single_post( $post_id );
	}

	public function on_transition_post_status( $new_status, $old_status, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( $post instanceof WP_Post && $this->should_process_post_event( $post->ID, $post ) ) {
			$this->queue_single_post( $post->ID );
		}
	}

	public function on_term_changed( $term_id = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( get_transient( 'jsdw_ai_chat_term_sync_debounce' ) ) {
			return;
		}
		set_transient( 'jsdw_ai_chat_term_sync_debounce', 1, 30 );
		$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_SYNC, array( 'trigger' => 'term_change' ), 20 );
	}

	public function on_menu_updated( $menu_id = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( get_transient( 'jsdw_ai_chat_menu_sync_debounce' ) ) {
			return;
		}
		set_transient( 'jsdw_ai_chat_menu_sync_debounce', 1, 30 );
		$this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_SYNC, array( 'trigger' => 'menu_update' ), 20 );
	}

	private function default_status_for_candidate( array $candidate ) {
		return JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE;
	}

	private function resolve_status_for_candidate( array $candidate, array $decision ) {
		if ( ! empty( $decision['allowed'] ) ) {
			return $this->default_status_for_candidate( $candidate );
		}
		if ( isset( $decision['reason_code'] ) && JSDW_AI_Chat_Source_Rules::REASON_MANUAL_SOURCE_DISABLED === $decision['reason_code'] ) {
			return JSDW_AI_Chat_DB::SOURCE_STATUS_DISABLED;
		}
		return JSDW_AI_Chat_DB::SOURCE_STATUS_EXCLUDED;
	}

	private function should_process_post_event( $post_id, $post ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! ( $post instanceof WP_Post ) ) {
			return false;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return false;
		}
		if ( $this->is_post_sync_debounced( $post_id ) ) {
			return false;
		}

		$settings = $this->settings->get_all();
		$sources  = isset( $settings['sources'] ) ? $settings['sources'] : array();
		$enabled  = array_map( 'strval', (array) $sources['enabled_source_types'] );
		$post_source_type = in_array( $post->post_type, array( 'post', 'page' ), true ) ? $post->post_type : 'cpt';
		if ( ! in_array( $post_source_type, $enabled, true ) ) {
			return false;
		}
		$excluded_post_types = array_map( 'strval', (array) $sources['excluded_post_types'] );
		if ( in_array( $post->post_type, $excluded_post_types, true ) ) {
			return false;
		}
		$included_post_types = array_map( 'strval', (array) $sources['included_post_types'] );
		if ( ! empty( $included_post_types ) && ! in_array( $post->post_type, $included_post_types, true ) ) {
			return false;
		}

		return true;
	}

	private function is_post_sync_debounced( $post_id ) {
		$key = 'jsdw_ai_chat_post_sync_' . absint( $post_id );
		if ( get_transient( $key ) ) {
			return true;
		}
		set_transient( $key, 1, 20 );
		return false;
	}

	private function resolve_authority( array $candidate, array $settings ) {
		$sources = isset( $settings['sources'] ) && is_array( $settings['sources'] ) ? $settings['sources'] : array();
		$map     = isset( $sources['default_source_authority_by_type'] ) && is_array( $sources['default_source_authority_by_type'] ) ? $sources['default_source_authority_by_type'] : array();
		$type    = isset( $candidate['source_type'] ) ? (string) $candidate['source_type'] : 'post';

		if ( 'manual' === $type && isset( $candidate['authority_level'] ) ) {
			return absint( $candidate['authority_level'] );
		}

		return isset( $map[ $type ] ) ? absint( $map[ $type ] ) : 50;
	}

	/**
	 * Queue background re-evaluation of persisted eligibility columns (e.g. after settings save).
	 */
	public function queue_eligibility_revalidation() {
		$this->queue->add_job(
			JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_ELIGIBILITY_REVALIDATE,
			array( 'offset' => 0 ),
			12
		);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public function run_eligibility_revalidation_job( array $payload ) {
		$settings = $this->settings->get_all();
		$offset   = isset( $payload['offset'] ) ? absint( $payload['offset'] ) : 0;
		$updated  = $this->repository->recompute_eligibility_batch( $this->rules, $settings, $offset, 100 );
		if ( $updated >= 100 ) {
			$this->queue->add_job(
				JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_ELIGIBILITY_REVALIDATE,
				array( 'offset' => $offset + 100 ),
				12
			);
		}
		return array(
			'updated'     => $updated,
			'next_offset' => $offset + $updated,
			'chained'     => $updated >= 100,
		);
	}
}
