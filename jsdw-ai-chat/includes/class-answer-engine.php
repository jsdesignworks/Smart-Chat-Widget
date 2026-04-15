<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Answer_Engine {
	/**
	 * @var JSDW_AI_Chat_Knowledge_Retriever
	 */
	private $knowledge_retriever;

	/**
	 * @var JSDW_AI_Chat_Answer_Context_Builder
	 */
	private $answer_context_builder;

	/**
	 * @var JSDW_AI_Chat_Confidence_Policy
	 */
	private $confidence_policy;

	/**
	 * @var JSDW_AI_Chat_Query_Normalizer
	 */
	private $query_normalizer;

	/**
	 * @var JSDW_AI_Chat_Answer_Policy
	 */
	private $answer_policy;

	/**
	 * @var JSDW_AI_Chat_Answer_Status_Mapper
	 */
	private $status_mapper;

	/**
	 * @var JSDW_AI_Chat_Local_Answer_Builder
	 */
	private $local_builder;

	/**
	 * @var JSDW_AI_Chat_Fallback_Responses
	 */
	private $fallback_responses;

	/**
	 * @var JSDW_AI_Chat_AI_Phrase_Assist
	 */
	private $ai_phrase_assist;

	/**
	 * @var JSDW_AI_Chat_Answer_Trace
	 */
	private $answer_trace;

	public function __construct(
		JSDW_AI_Chat_Knowledge_Retriever $knowledge_retriever,
		JSDW_AI_Chat_Answer_Context_Builder $answer_context_builder,
		JSDW_AI_Chat_Confidence_Policy $confidence_policy,
		JSDW_AI_Chat_Query_Normalizer $query_normalizer,
		JSDW_AI_Chat_Answer_Policy $answer_policy,
		JSDW_AI_Chat_Answer_Status_Mapper $status_mapper,
		JSDW_AI_Chat_Local_Answer_Builder $local_builder,
		JSDW_AI_Chat_Fallback_Responses $fallback_responses,
		JSDW_AI_Chat_AI_Phrase_Assist $ai_phrase_assist,
		JSDW_AI_Chat_Answer_Trace $answer_trace
	) {
		$this->knowledge_retriever    = $knowledge_retriever;
		$this->answer_context_builder = $answer_context_builder;
		$this->confidence_policy      = $confidence_policy;
		$this->query_normalizer       = $query_normalizer;
		$this->answer_policy          = $answer_policy;
		$this->status_mapper          = $status_mapper;
		$this->local_builder          = $local_builder;
		$this->fallback_responses     = $fallback_responses;
		$this->ai_phrase_assist       = $ai_phrase_assist;
		$this->answer_trace           = $answer_trace;
	}

	/**
	 * @param string $query
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $guard_result
	 * @param string $retrieval_context One of JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_*.
	 * @return array<string,mixed>
	 */
	public function answer( $query, array $settings, array $guard_result = array(), $retrieval_context = JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_INTERNAL ) {
		$normalized = $this->query_normalizer->normalize( $query );
		$hits       = $this->knowledge_retriever->search( $query, 30, $retrieval_context );
		$context    = $this->answer_context_builder->build( $hits );
		$stats      = $this->knowledge_retriever->aggregate_hit_stats( $hits );
		$confidence = $this->confidence_policy->evaluate( $stats );
		$policy     = $this->answer_policy->evaluate( $confidence, $settings );
		$status     = $this->status_mapper->map_confidence_to_answer_status( $confidence, $guard_result, $policy );

		$answer_text     = '';
		$answer_type     = JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_ERROR_RESPONSE;
		$answer_strategy = JSDW_AI_Chat_Answer_Constants::ANSWER_STRATEGY_FALLBACK_STANDARD;
		$clarification   = '';

		if ( ! empty( $guard_result['rejected'] ) ) {
			$answer_text = $this->fallback_responses->get_text( JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_GUARD_REJECTED, $settings );
			$answer_type = JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_UNSUPPORTED_RESPONSE;
		} elseif ( ! empty( $policy['allow_local_answer'] ) && JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_ANSWERED_LOCALLY === $status ) {
			$built         = $this->local_builder->build( $hits, $context, $stats );
			$answer_text   = (string) ( $built['answer_text'] ?? '' );
			$answer_type   = (string) ( $built['answer_type'] ?? JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_FACTUAL_SUMMARY );
			$answer_strategy = JSDW_AI_Chat_Answer_Constants::ANSWER_STRATEGY_LOCAL_TEMPLATE;
			$phrased       = $this->ai_phrase_assist->maybe_rephrase( $answer_text, $settings, $confidence, $status, $guard_result, $policy, (string) ( $built['answer_type'] ?? '' ) );
			$answer_text   = (string) ( $phrased['text'] ?? $answer_text );
			if ( ! empty( $phrased['used_ai'] ) ) {
				$answer_strategy = JSDW_AI_Chat_Answer_Constants::ANSWER_STRATEGY_AI_PHRASE_ASSIST;
			}
			if ( JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_AMBIGUOUS_CLARIFY === $answer_type ) {
				$status        = JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_REQUIRES_CLARIFICATION;
				$clarification = $answer_text;
			}
			if ( '' === trim( $answer_text ) ) {
				$status          = JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_FAILED;
				$answer_text     = $this->fallback_responses->get_text( JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_FAILED, $settings );
				$answer_type     = JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_ERROR_RESPONSE;
				$answer_strategy = JSDW_AI_Chat_Answer_Constants::ANSWER_STRATEGY_FALLBACK_STANDARD;
			}
		} else {
			$answer_text = $this->fallback_responses->get_text( $status, $settings );
			if ( JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_NO_MATCH === $status ) {
				$answer_type = JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_NO_MATCH_RESPONSE;
			} elseif ( JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_LOW_CONFIDENCE === $status ) {
				$answer_type = JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_LOW_CONF_RESPONSE;
			} elseif ( JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_REQUIRES_CLARIFICATION === $status ) {
				$answer_type   = JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_AMBIGUOUS_CLARIFY;
				$clarification = $answer_text;
			} else {
				$answer_type = JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_UNSUPPORTED_RESPONSE;
			}
		}

		$trace = array(
			'hit_count'             => count( $hits ),
			'best_score'            => (float) ( $stats['best_score'] ?? 0.0 ),
			'has_title_hit'         => ! empty( $stats['has_title_hit'] ),
			'distinct_source_count' => (int) ( $stats['distinct_source_count'] ?? 0 ),
		);

		if ( JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_PUBLIC_SAFE === $retrieval_context ) {
			$trace = array(
				'hit_count' => count( $hits ),
			);
		}

		$snapshot = $this->answer_trace->build_snapshot(
			array(
				'query'             => (string) $query,
				'normalized_query'  => (string) ( $normalized['normalized'] ?? '' ),
				'retrieval_stats'   => $stats,
				'confidence'        => $confidence,
				'answer_status'     => $status,
				'sources'           => $context['sources'] ?? array(),
				'chunks'            => $context['chunks'] ?? array(),
				'facts'             => $context['facts'] ?? array(),
				'trace'             => $trace,
			)
		);

		if ( JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_PUBLIC_SAFE === $retrieval_context ) {
			$snapshot = JSDW_AI_Chat_Public_Response_Policy::sanitize_snapshot_for_public_path( $snapshot );
		}

		return array(
			'query'                   => (string) $query,
			'normalized_query'        => (string) ( $normalized['normalized'] ?? '' ),
			'response_mode'           => (string) ( $policy['answer_mode'] ?? 'strict_local_only' ),
			'confidence'              => $confidence,
			'answer_status'           => $status,
			'answer_text'             => $answer_text,
			'answer_type'             => $answer_type,
			'answer_strategy'         => $answer_strategy,
			'clarification_question'  => $clarification,
			'sources'                 => $context['sources'] ?? array(),
			'chunks'                  => $context['chunks'] ?? array(),
			'facts'                   => $context['facts'] ?? array(),
			'retrieval_stats'         => $stats,
			'trace'                   => $trace,
			'snapshot'                => $snapshot,
			'generated_at'            => gmdate( 'c' ),
			'used_ai_phrase_assist'   => JSDW_AI_Chat_Answer_Constants::ANSWER_STRATEGY_AI_PHRASE_ASSIST === $answer_strategy,
			'guard'                   => $guard_result,
		);
	}
}
