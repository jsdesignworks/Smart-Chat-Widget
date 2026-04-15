<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Source_Content_Processor {
	const SNAPSHOT_MAX_CHARS = 500000;

	/**
	 * @var JSDW_AI_Chat_Source_Repository
	 */
	private $repository;

	/**
	 * @var JSDW_AI_Chat_Source_Rules
	 */
	private $rules;

	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	/**
	 * @var JSDW_AI_Chat_Source_Content_Builder
	 */
	private $builder;

	/**
	 * @var JSDW_AI_Chat_Content_Normalizer
	 */
	private $normalizer;

	/**
	 * @var JSDW_AI_Chat_Content_Fingerprint
	 */
	private $fingerprint;

	/**
	 * @var JSDW_AI_Chat_Content_State_Comparator
	 */
	private $comparator;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	/**
	 * @var JSDW_AI_Chat_Queue
	 */
	private $queue;

	/**
	 * @var JSDW_AI_Chat_Chunk_Repository
	 */
	private $chunk_repository;

	public function __construct(
		JSDW_AI_Chat_Source_Repository $repository,
		JSDW_AI_Chat_Source_Rules $rules,
		JSDW_AI_Chat_Settings $settings,
		JSDW_AI_Chat_Source_Content_Builder $builder,
		JSDW_AI_Chat_Content_Normalizer $normalizer,
		JSDW_AI_Chat_Content_Fingerprint $fingerprint,
		JSDW_AI_Chat_Content_State_Comparator $comparator,
		JSDW_AI_Chat_Logger $logger,
		JSDW_AI_Chat_Queue $queue,
		JSDW_AI_Chat_Chunk_Repository $chunk_repository
	) {
		$this->repository       = $repository;
		$this->rules            = $rules;
		$this->settings         = $settings;
		$this->builder          = $builder;
		$this->normalizer       = $normalizer;
		$this->fingerprint      = $fingerprint;
		$this->comparator       = $comparator;
		$this->logger           = $logger;
		$this->queue            = $queue;
		$this->chunk_repository = $chunk_repository;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function process_single( $source_id ) {
		$source_id = absint( $source_id );
		$row       = $this->repository->get_source_by_id( $source_id );
		if ( ! is_array( $row ) ) {
			$this->logger->warning( 'content_pipeline_missing_source', 'Source row missing for content processing.', array( 'source_id' => $source_id ) );
			return array( 'ok' => false, 'reason' => 'not_found' );
		}

		$this->logger->info( 'content_pipeline_started', 'Content pipeline started.', array( 'source_id' => $source_id ) );

		$status = isset( $row['status'] ) ? (string) $row['status'] : '';
		if ( JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE !== $status ) {
			$this->logger->info( 'content_skipped_inactive', 'Skipped: source not active.', array( 'source_id' => $source_id, 'status' => $status ) );
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'inactive' );
		}

		$settings_all = $this->settings->get_all();
		$candidate      = $this->build_candidate_for_rules( $row );
		$decision       = $this->rules->evaluate_candidate( $candidate, $settings_all );
		if ( empty( $decision['allowed'] ) ) {
			$this->repository->update_content_state(
				$source_id,
				array(
					'content_processing_status'  => JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_FAILED,
					'content_processing_reason'  => 'rules_blocked',
					'last_content_check_gmt'     => current_time( 'mysql', true ),
					'updated_at'                 => current_time( 'mysql', true ),
				)
			);
			$this->logger->warning( 'content_rules_blocked', 'Content processing blocked by rules.', array( 'source_id' => $source_id ) );
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'rules' );
		}

		$built = $this->builder->build( $row );
		if ( isset( $built['status'] ) && 'unsupported' === $built['status'] ) {
			$code = isset( $built['reason_code'] ) ? (string) $built['reason_code'] : JSDW_AI_Chat_DB::CONTENT_REASON_UNSUPPORTED_TYPE;
			$this->persist_failure( $source_id, JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNSUPPORTED, $code );
			$this->logger->warning( 'content_unsupported', 'Unsupported source for content extraction.', array( 'source_id' => $source_id, 'reason' => $code ) );
			return array( 'ok' => true, 'status' => 'unsupported', 'reason' => $code );
		}
		if ( isset( $built['status'] ) && 'unavailable' === $built['status'] ) {
			$code = isset( $built['reason_code'] ) ? (string) $built['reason_code'] : JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE;
			$this->persist_failure( $source_id, JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNAVAILABLE, $code );
			$this->logger->warning( 'content_unavailable', 'Source content unavailable.', array( 'source_id' => $source_id, 'reason' => $code ) );
			return array( 'ok' => true, 'status' => 'unavailable', 'reason' => $code );
		}

		$norm = $this->normalizer->normalize( $built );
		if ( is_wp_error( $norm ) ) {
			$this->persist_failure( $source_id, JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_FAILED, JSDW_AI_Chat_DB::CONTENT_REASON_NORMALIZATION_FAILED );
			$this->logger->error( 'normalization_failed', 'Content normalization failed.', array( 'source_id' => $source_id, 'error' => $norm->get_error_message() ) );
			return array( 'ok' => false, 'reason' => 'normalize' );
		}

		$hashes_new = $this->fingerprint->hash( $norm );
		$previous   = array(
			'title_hash'     => isset( $row['title_hash'] ) ? (string) $row['title_hash'] : null,
			'content_hash'   => isset( $row['content_hash'] ) ? (string) $row['content_hash'] : null,
			'structure_hash' => isset( $row['structure_hash'] ) ? (string) $row['structure_hash'] : null,
			'metadata_hash'  => isset( $row['metadata_hash'] ) ? (string) $row['metadata_hash'] : null,
		);

		$comparison = $this->comparator->compare( $previous, $hashes_new );
		$now_gmt    = current_time( 'mysql', true );

		$reason = (string) $comparison['primary_reason'];
		if ( 'baseline' === $comparison['outcome'] ) {
			$reason = JSDW_AI_Chat_DB::CONTENT_REASON_CONTENT_CHANGED;
		}

		$raw_snap = '';
		if ( isset( $built['title'], $built['body_html'] ) ) {
			$raw_snap = (string) $built['title'] . "\n\n" . (string) $built['body_html'];
		}
		$norm_snap = '';
		if ( isset( $norm['title'], $norm['body'] ) ) {
			$norm_snap = (string) $norm['title'] . "\n\n" . (string) $norm['body'];
		}

		$fields = array(
			'raw_snapshot_text'            => $this->cap_snapshot( $raw_snap ),
			'normalized_snapshot_text'     => $this->cap_snapshot( $norm_snap ),
			'content_hash'                 => $hashes_new['content_hash'],
			'title_hash'                   => $hashes_new['title_hash'],
			'structure_hash'               => $hashes_new['structure_hash'],
			'metadata_hash'                => $hashes_new['metadata_hash'],
			'last_content_check_gmt'       => $now_gmt,
			'normalized_length'            => isset( $norm['body'] ) ? strlen( (string) $norm['body'] ) : 0,
			'extraction_method'            => isset( $built['extraction_method'] ) ? sanitize_text_field( (string) $built['extraction_method'] ) : '',
			'content_processing_status'    => JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK,
			'content_processing_reason'    => $reason,
			'material_content_change'      => ! empty( $comparison['material_for_reindex'] ) ? 1 : 0,
			'updated_at'                   => $now_gmt,
		);

		if ( 'no_change' === $comparison['outcome'] ) {
			$this->logger->info( 'content_no_change', 'Content fingerprints unchanged.', array( 'source_id' => $source_id ) );
		} else {
			$fields['last_content_change_gmt'] = $now_gmt;
			if ( 'changed' === $comparison['outcome'] ) {
				$ver = isset( $row['content_version'] ) ? absint( $row['content_version'] ) : 1;
				$fields['content_version'] = $ver + 1;
			}
			if ( ! empty( $comparison['material_for_reindex'] ) ) {
				$fields['needs_reindex'] = 1;
				$this->logger->info( 'content_material_change', 'Material content change detected.', array( 'source_id' => $source_id, 'reason' => $reason ) );
			}
		}

		$this->repository->update_content_state( $source_id, $fields );
		$this->logger->info( 'content_pipeline_completed', 'Content pipeline completed.', array( 'source_id' => $source_id, 'outcome' => $comparison['outcome'], 'reason' => $reason ) );

		$content_version = isset( $fields['content_version'] ) ? absint( $fields['content_version'] ) : ( isset( $row['content_version'] ) ? absint( $row['content_version'] ) : 1 );
		if ( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK === $fields['content_processing_status'] ) {
			$should_queue = true;
			if ( 'no_change' === $comparison['outcome'] && $this->chunk_repository->count_active_chunks_for_version( $source_id, $content_version ) > 0 ) {
				$should_queue = false;
			}
			if ( $should_queue ) {
				$this->repository->update_knowledge_state(
					$source_id,
					array(
						'knowledge_processing_status' => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_PENDING,
						'updated_at'                  => current_time( 'mysql', true ),
					)
				);
				$kid = $this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS, array( 'source_id' => $source_id ), 15 );
				$this->logger->info( 'knowledge_processing_queued', 'Knowledge job queued after content.', array( 'source_id' => $source_id, 'job_id' => $kid ) );
			}
		}

		return array(
			'ok'      => true,
			'outcome' => $comparison['outcome'],
			'reason'  => $reason,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function process_batch( $limit = 10, $offset = 0 ) {
		$rows = $this->repository->fetch_sources_pending_content_processing( $limit, $offset );
		$done = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['id'] ) ) {
				continue;
			}
			$done[] = $this->process_single( absint( $row['id'] ) );
		}
		$this->logger->info( 'content_batch_completed', 'Content batch processing finished.', array( 'count' => count( $done ) ) );
		return array( 'ok' => true, 'processed' => count( $done ), 'results' => $done );
	}

	/**
	 * @param string|int $older_than strtotime-compatible or unix timestamp
	 * @return array<string,mixed>
	 */
	public function verify_stale( $older_than, $limit = 25 ) {
		$rows = $this->repository->fetch_stale_content_sources( $older_than, $limit );
		$results = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['id'] ) ) {
				continue;
			}
			$results[] = $this->process_single( absint( $row['id'] ) );
		}
		update_option( JSDW_AI_CHAT_OPTION_LAST_CONTENT_VERIFICATION, current_time( 'mysql', true ), false );
		return array( 'ok' => true, 'processed' => count( $results ), 'results' => $results );
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function build_candidate_for_rules( array $row ) {
		$type = isset( $row['source_type'] ) ? (string) $row['source_type'] : '';
		$base = array(
			'source_type'      => $type,
			'source_object_id' => isset( $row['source_object_id'] ) ? absint( $row['source_object_id'] ) : null,
			'source_key'       => isset( $row['source_key'] ) ? (string) $row['source_key'] : '',
			'source_url'       => isset( $row['source_url'] ) ? (string) $row['source_url'] : '',
			'title'            => isset( $row['title'] ) ? (string) $row['title'] : '',
		);

		if ( in_array( $type, array( 'post', 'page', 'cpt' ), true ) ) {
			$pid = isset( $row['source_object_id'] ) ? absint( $row['source_object_id'] ) : 0;
			$post = get_post( $pid );
			if ( $post instanceof WP_Post ) {
				$base['post_id']       = $pid;
				$base['post_type']     = (string) $post->post_type;
				$base['post_status']   = (string) $post->post_status;
				$base['has_password']  = ! empty( $post->post_password );
			}
		}

		if ( 'taxonomy' === $type && ! empty( $row['source_key'] ) && preg_match( '/^taxonomy:([^:]+):(\d+)$/', (string) $row['source_key'], $m ) ) {
			$base['term_key'] = (string) $m[1] . ':' . (string) absint( $m[2] );
		}

		if ( 'manual' === $type && isset( $row['source_object_id'] ) ) {
			$manual = $this->repository->get_manual_source_by_id( absint( $row['source_object_id'] ) );
			if ( is_array( $manual ) ) {
				$base['manual_enabled']   = ! empty( $manual['enabled'] );
				$base['allow_behavior']   = isset( $manual['allow_behavior'] ) ? (string) $manual['allow_behavior'] : 'allow';
			}
		}

		return $base;
	}

	private function persist_failure( $source_id, $proc_status, $reason_code ) {
		$this->repository->update_content_state(
			absint( $source_id ),
			array(
				'content_processing_status' => sanitize_text_field( (string) $proc_status ),
				'content_processing_reason' => sanitize_text_field( (string) $reason_code ),
				'last_content_check_gmt'    => current_time( 'mysql', true ),
				'updated_at'                => current_time( 'mysql', true ),
			)
		);
	}

	private function cap_snapshot( $text ) {
		$text = (string) $text;
		if ( strlen( $text ) <= self::SNAPSHOT_MAX_CHARS ) {
			return $text;
		}
		return substr( $text, 0, self::SNAPSHOT_MAX_CHARS );
	}
}
