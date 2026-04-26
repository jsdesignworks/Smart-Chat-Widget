<?php
/**
 * Phase 4: conservative retrieval confidence (no LLM).
 *
 * Uses aggregate_hit_stats() output: hit_count, best_score, has_title_hit, distinct_source_count,
 * max_hits_per_source, ambiguous.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Confidence_Policy {

	/**
	 * @param array<string,mixed> $stats From JSDW_AI_Chat_Knowledge_Retriever::aggregate_hit_stats() plus optional legacy keys.
	 * @return string JSDW_AI_Chat_Knowledge_Constants CONF_*
	 */
	public function evaluate( array $stats ) {
		$hits        = isset( $stats['hit_count'] ) ? absint( $stats['hit_count'] ) : 0;
		$best        = isset( $stats['best_score'] ) ? (float) $stats['best_score'] : 0.0;
		$has_title   = ! empty( $stats['has_title_hit'] );
		$amb         = ! empty( $stats['ambiguous'] );
		$distinct    = isset( $stats['distinct_source_count'] ) ? absint( $stats['distinct_source_count'] ) : 0;
		$max_per     = isset( $stats['max_hits_per_source'] ) ? absint( $stats['max_hits_per_source'] ) : 0;

		if ( $hits <= 0 || $best < 0.01 ) {
			return JSDW_AI_Chat_Knowledge_Constants::CONF_NO_MATCH;
		}

		// Dominant: one source accounts for at least 60% of hits (min 2 hits).
		$dominant = $hits >= 2 && $max_per >= (int) ceil( $hits * 0.6 );

		// Scattered: several sources and no source has half or more of the hits.
		$scattered = $distinct >= 3 && $hits >= 4 && $max_per < (int) ceil( $hits * 0.5 );

		// One hit per source (high entropy): many singletons, weak concentration.
		$one_per_source = $hits >= 2 && $distinct === $hits;

		if ( $amb && $hits > 3 ) {
			return JSDW_AI_Chat_Knowledge_Constants::CONF_REQUIRES_CLARIFICATION;
		}

		// Many weak matches across sources without a title anchor.
		$t_one_per = JSDW_AI_Chat_Knowledge_Constants::CONF_THRESHOLD_ONE_PER_TITLE;
		$t_scatter_lo = JSDW_AI_Chat_Knowledge_Constants::CONF_THRESHOLD_SCATTER_LOW;
		$t_scatter_med = JSDW_AI_Chat_Knowledge_Constants::CONF_THRESHOLD_SCATTER_MED;
		$t_title = JSDW_AI_Chat_Knowledge_Constants::CONF_THRESHOLD_TITLE_ANCHOR;
		$t_strong = JSDW_AI_Chat_Knowledge_Constants::CONF_THRESHOLD_STRONG;
		$t_weak = JSDW_AI_Chat_Knowledge_Constants::CONF_THRESHOLD_WEAK_BODY;

		if ( $one_per_source && $hits >= 5 && ! $has_title && $best < $t_one_per ) {
			return JSDW_AI_Chat_Knowledge_Constants::CONF_REQUIRES_CLARIFICATION;
		}

		if ( $scattered && ! $has_title && $best < $t_scatter_lo ) {
			return JSDW_AI_Chat_Knowledge_Constants::CONF_REQUIRES_CLARIFICATION;
		}

		if ( $scattered && ! $has_title && $best < $t_scatter_med ) {
			return JSDW_AI_Chat_Knowledge_Constants::CONF_LOW_CONFIDENCE;
		}

		// Title match is a strong local signal when score is sufficient.
		if ( $has_title && $best >= $t_title ) {
			if ( $one_per_source && $hits >= 8 ) {
				return JSDW_AI_Chat_Knowledge_Constants::CONF_LOW_CONFIDENCE;
			}
			return JSDW_AI_Chat_Knowledge_Constants::CONF_ANSWERABLE_LOCALLY;
		}

		if ( $dominant && $best >= $t_strong && $hits >= 1 ) {
			return JSDW_AI_Chat_Knowledge_Constants::CONF_ANSWERABLE_LOCALLY;
		}

		if ( $best >= $t_strong && $hits >= 1 ) {
			if ( $scattered && ! $has_title ) {
				return JSDW_AI_Chat_Knowledge_Constants::CONF_LOW_CONFIDENCE;
			}
			return JSDW_AI_Chat_Knowledge_Constants::CONF_ANSWERABLE_LOCALLY;
		}

		if ( $best >= $t_weak ) {
			return JSDW_AI_Chat_Knowledge_Constants::CONF_LOW_CONFIDENCE;
		}

		return JSDW_AI_Chat_Knowledge_Constants::CONF_REQUIRES_FUTURE_AI_ASSIST;
	}
}
