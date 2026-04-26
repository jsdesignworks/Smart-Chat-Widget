<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Local_Answer_Builder {
	/**
	 * @param array<int,array<string,mixed>> $hits
	 * @param array<string,mixed> $context
	 * @param array<string,mixed> $stats
	 * @return array<string,mixed>
	 */
	public function build( array $hits, array $context, array $stats ) {
		$best_hit = isset( $hits[0] ) && is_array( $hits[0] ) ? $hits[0] : array();
		$next_hit = isset( $hits[1] ) && is_array( $hits[1] ) ? $hits[1] : array();

		$best_score = isset( $best_hit['score'] ) ? (float) $best_hit['score'] : 0.0;
		$next_score = isset( $next_hit['score'] ) ? (float) $next_hit['score'] : 0.0;
		$best_sid   = isset( $best_hit['source_id'] ) ? absint( $best_hit['source_id'] ) : 0;
		$next_sid   = isset( $next_hit['source_id'] ) ? absint( $next_hit['source_id'] ) : 0;

		// If two sources score within a narrow band and neither hit is anchored, ask for clarification.
		$gap = JSDW_AI_Chat_Knowledge_Constants::LOCAL_ANSWER_CROSS_SOURCE_SCORE_GAP;
		if (
			$best_sid > 0 && $next_sid > 0 && $best_sid !== $next_sid
			&& abs( $best_score - $next_score ) < $gap
			&& ! $this->hit_has_retrieval_anchor( $best_hit )
			&& ! $this->hit_has_retrieval_anchor( $next_hit )
		) {
			return array(
				'answer_text' => __( 'I found similarly strong matches from different sources. Please clarify what you want to know.', 'jsdw-ai-chat' ),
				'answer_type' => JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_AMBIGUOUS_CLARIFY,
			);
		}

		if ( isset( $best_hit['fact_id'] ) && absint( $best_hit['fact_id'] ) > 0 ) {
			$value = trim( (string) ( $best_hit['fact_value'] ?? '' ) );
			$key   = trim( (string) ( $best_hit['fact_key'] ?? '' ) );
			$text  = '' !== $value ? $value : $key;
			return array(
				'answer_text' => $text,
				'answer_type' => JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_FACTUAL_DIRECT,
			);
		}

		$snippet = trim( (string) ( $best_hit['snippet'] ?? '' ) );
		if ( '' !== $snippet ) {
			return array(
				'answer_text' => $snippet,
				'answer_type' => JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_FACTUAL_SUMMARY,
			);
		}

		$first_chunk = isset( $context['chunks'][0] ) && is_array( $context['chunks'][0] ) ? $context['chunks'][0] : array();
		$fallback_snippet = trim( (string) ( $first_chunk['snippet'] ?? '' ) );
		return array(
			'answer_text' => $fallback_snippet,
			'answer_type' => JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_FAQ_STYLE,
		);
	}

	/**
	 * @param array<string,mixed> $hit
	 */
	private function hit_has_retrieval_anchor( array $hit ) {
		if ( ! empty( $hit['matched_source_title'] ) ) {
			return true;
		}
		$kind = isset( $hit['hit_kind'] ) ? (string) $hit['hit_kind'] : '';
		return in_array(
			$kind,
			array(
				JSDW_AI_Chat_Knowledge_Constants::HIT_KIND_CHUNK_HEADING,
				JSDW_AI_Chat_Knowledge_Constants::HIT_KIND_CHUNK_SECTION,
				JSDW_AI_Chat_Knowledge_Constants::HIT_KIND_SOURCE_TITLE,
			),
			true
		);
	}
}
