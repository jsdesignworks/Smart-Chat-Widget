<?php
/**
 * Phase 4: orchestrates source → chunks → facts using Phase 3 normalized content only.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Source_Knowledge_Processor {

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
	 * @var JSDW_AI_Chat_Content_Chunker
	 */
	private $chunker;

	/**
	 * @var JSDW_AI_Chat_Chunk_Repository
	 */
	private $chunks;

	/**
	 * @var JSDW_AI_Chat_Fact_Extractor
	 */
	private $fact_extractor;

	/**
	 * @var JSDW_AI_Chat_Fact_Repository
	 */
	private $facts;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	public function __construct(
		JSDW_AI_Chat_Source_Repository $repository,
		JSDW_AI_Chat_Source_Rules $rules,
		JSDW_AI_Chat_Settings $settings,
		JSDW_AI_Chat_Source_Content_Builder $builder,
		JSDW_AI_Chat_Content_Normalizer $normalizer,
		JSDW_AI_Chat_Content_Chunker $chunker,
		JSDW_AI_Chat_Chunk_Repository $chunks,
		JSDW_AI_Chat_Fact_Extractor $fact_extractor,
		JSDW_AI_Chat_Fact_Repository $facts,
		JSDW_AI_Chat_Logger $logger
	) {
		$this->repository     = $repository;
		$this->rules          = $rules;
		$this->settings       = $settings;
		$this->builder        = $builder;
		$this->normalizer     = $normalizer;
		$this->chunker        = $chunker;
		$this->chunks         = $chunks;
		$this->fact_extractor = $fact_extractor;
		$this->facts          = $facts;
		$this->logger         = $logger;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function process_single( $source_id ) {
		$source_id = absint( $source_id );
		$row       = $this->repository->get_source_by_id( $source_id );
		if ( ! is_array( $row ) ) {
			$this->logger->warning( 'knowledge_pipeline_missing_source', 'Knowledge: source missing.', array( 'source_id' => $source_id ) );
			return array( 'ok' => false, 'reason' => 'not_found' );
		}

		$this->logger->info( 'knowledge_processing_started', 'Knowledge processing started.', array( 'source_id' => $source_id ) );

		$status = isset( $row['status'] ) ? (string) $row['status'] : '';
		if ( JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE !== $status ) {
			$this->repository->update_knowledge_state(
				$source_id,
				array(
					'knowledge_processing_status'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED,
					'knowledge_processing_reason'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_SOURCE_INACTIVE,
					'last_knowledge_processing_gmt'=> current_time( 'mysql', true ),
					'updated_at'                   => current_time( 'mysql', true ),
				)
			);
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'inactive' );
		}

		$proc = isset( $row['content_processing_status'] ) ? (string) $row['content_processing_status'] : '';
		if ( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK !== $proc ) {
			$this->repository->update_knowledge_state(
				$source_id,
				array(
					'knowledge_processing_status'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED,
					'knowledge_processing_reason'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_CONTENT_NOT_OK,
					'last_knowledge_processing_gmt'=> current_time( 'mysql', true ),
					'updated_at'                   => current_time( 'mysql', true ),
				)
			);
			$this->logger->info( 'knowledge_skipped_content', 'Knowledge skipped: content not ok.', array( 'source_id' => $source_id, 'content_processing_status' => $proc ) );
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'content_not_ok' );
		}

		$settings_all = $this->settings->get_all();
		$candidate    = $this->build_candidate_for_rules( $row );
		$decision     = $this->rules->evaluate_candidate( $candidate, $settings_all );
		if ( empty( $decision['allowed'] ) ) {
			$this->repository->update_knowledge_state(
				$source_id,
				array(
					'knowledge_processing_status'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED,
					'knowledge_processing_reason'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_RULES_BLOCKED,
					'last_knowledge_processing_gmt'=> current_time( 'mysql', true ),
					'updated_at'                   => current_time( 'mysql', true ),
				)
			);
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'rules' );
		}

		$built = $this->builder->build( $row );
		if ( isset( $built['status'] ) && in_array( $built['status'], array( 'unsupported', 'unavailable' ), true ) ) {
			$this->repository->update_knowledge_state(
				$source_id,
				array(
					'knowledge_processing_status'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED,
					'knowledge_processing_reason'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_CONTENT_NOT_OK,
					'last_knowledge_processing_gmt'=> current_time( 'mysql', true ),
					'updated_at'                   => current_time( 'mysql', true ),
				)
			);
			return array( 'ok' => false, 'reason' => 'build' );
		}

		$norm = $this->normalizer->normalize( $built );
		if ( is_wp_error( $norm ) ) {
			$this->repository->update_knowledge_state(
				$source_id,
				array(
					'knowledge_processing_status'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED,
					'knowledge_processing_reason'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_CHUNK_FAILED,
					'last_knowledge_processing_gmt'=> current_time( 'mysql', true ),
					'updated_at'                   => current_time( 'mysql', true ),
				)
			);
			$this->logger->error( 'knowledge_normalization_failed', 'Knowledge: normalize failed.', array( 'source_id' => $source_id ) );
			return array( 'ok' => false, 'reason' => 'normalize' );
		}

		$content_version = isset( $row['content_version'] ) ? absint( $row['content_version'] ) : 1;

		$headings = isset( $norm['headings'] ) && is_array( $norm['headings'] ) ? $norm['headings'] : array();
		$this->repository->update_knowledge_state(
			$source_id,
			array(
				'knowledge_headings_json' => wp_json_encode( $headings ),
				'updated_at'              => current_time( 'mysql', true ),
			)
		);

		$package = array(
			'source_id'        => $source_id,
			'content_version'  => $content_version,
			'title'            => isset( $norm['title'] ) ? (string) $norm['title'] : '',
			'body'             => isset( $norm['body'] ) ? (string) $norm['body'] : '',
			'headings'         => $headings,
		);

		$chunk_rows = $this->chunker->chunk( $package );
		if ( empty( $chunk_rows ) ) {
			$this->repository->update_knowledge_state(
				$source_id,
				array(
					'knowledge_processing_status'   => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_READY,
					'knowledge_processing_reason'     => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_NO_CHANGE,
					'last_knowledge_processing_gmt' => current_time( 'mysql', true ),
					'updated_at'                    => current_time( 'mysql', true ),
				)
			);
			$this->logger->info( 'chunk_generation_completed', 'Knowledge: no chunks (empty body).', array( 'source_id' => $source_id, 'chunks' => 0 ) );
			return array( 'ok' => true, 'chunks' => 0, 'reason' => 'empty' );
		}

		try {
			$chunk_ids = $this->chunks->replace_chunk_set( $source_id, $content_version, $chunk_rows );
		} catch ( Exception $e ) {
			$this->repository->update_knowledge_state(
				$source_id,
				array(
					'knowledge_processing_status'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED,
					'knowledge_processing_reason'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_CHUNK_FAILED,
					'last_knowledge_processing_gmt'=> current_time( 'mysql', true ),
					'updated_at'                   => current_time( 'mysql', true ),
				)
			);
			$this->logger->error( 'chunk_generation_failed', $e->getMessage(), array( 'source_id' => $source_id ) );
			return array( 'ok' => false, 'reason' => 'chunk_persist' );
		}

		$this->logger->info( 'chunk_generation_completed', 'Chunks written.', array( 'source_id' => $source_id, 'count' => count( $chunk_ids ) ) );
		$this->logger->info( 'chunks_retired', 'Prior active chunks superseded.', array( 'source_id' => $source_id ) );

		$fact_rows = $this->fact_extractor->extract( $norm );
		try {
			$this->facts->replace_fact_set( $source_id, $content_version, $fact_rows );
		} catch ( Exception $e ) {
			$this->repository->update_knowledge_state(
				$source_id,
				array(
					'knowledge_processing_status'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED,
					'knowledge_processing_reason'  => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_FACT_FAILED,
					'last_knowledge_processing_gmt'=> current_time( 'mysql', true ),
					'updated_at'                   => current_time( 'mysql', true ),
				)
			);
			$this->logger->error( 'fact_extraction_failed', $e->getMessage(), array( 'source_id' => $source_id ) );
			return array( 'ok' => false, 'reason' => 'fact_persist' );
		}

		$this->logger->info( 'fact_extraction_completed', 'Facts written.', array( 'source_id' => $source_id, 'count' => count( $fact_rows ) ) );

		$this->repository->update_knowledge_state(
			$source_id,
			array(
				'knowledge_processing_status'   => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_READY,
				'knowledge_processing_reason'   => JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_READY,
				'last_knowledge_processing_gmt' => current_time( 'mysql', true ),
				'updated_at'                    => current_time( 'mysql', true ),
			)
		);

		return array(
			'ok'           => true,
			'chunks'       => count( $chunk_ids ),
			'facts'        => count( $fact_rows ),
			'content_version' => $content_version,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function process_batch( $limit = 10, $offset = 0 ) {
		$rows = $this->repository->fetch_sources_pending_knowledge_processing( $limit, $offset );
		$done = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['id'] ) ) {
				continue;
			}
			$done[] = $this->process_single( absint( $row['id'] ) );
		}
		$this->logger->info( 'knowledge_batch_completed', 'Knowledge batch finished.', array( 'count' => count( $done ) ) );
		return array( 'ok' => true, 'processed' => count( $done ), 'results' => $done );
	}

	/**
	 * Re-queue sources with OK content but pending knowledge, or stale verification.
	 *
	 * @return array<string,mixed>
	 */
	public function verify_stale( $older_than, $limit = 25 ) {
		$rows = $this->repository->fetch_stale_knowledge_sources( $older_than, $limit );
		$results = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['id'] ) ) {
				continue;
			}
			$results[] = $this->process_single( absint( $row['id'] ) );
		}
		update_option( JSDW_AI_CHAT_OPTION_LAST_KNOWLEDGE_VERIFICATION, current_time( 'mysql', true ), false );
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
		if ( 'manual' === $type && isset( $row['source_object_id'] ) ) {
			$manual = $this->repository->get_manual_source_by_id( absint( $row['source_object_id'] ) );
			if ( is_array( $manual ) ) {
				$base['manual_enabled'] = ! empty( $manual['enabled'] );
				$base['allow_behavior'] = isset( $manual['allow_behavior'] ) ? (string) $manual['allow_behavior'] : 'allow';
			}
		}
		return $base;
	}
}
