<?php
/**
 * Phase 4: centralized knowledge / chunk / fact / retrieval constants.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Knowledge_Constants {
	// Sources.knowledge_processing_status (separate from content_processing_*).
	const KNOWLEDGE_STATUS_PENDING = 'pending';
	const KNOWLEDGE_STATUS_READY   = 'ready';
	const KNOWLEDGE_STATUS_FAILED  = 'failed';

	// Sources.knowledge_processing_reason (and structured logs).
	const KNOWLEDGE_REASON_NONE                    = '';
	const KNOWLEDGE_REASON_READY                   = 'knowledge_ready';
	const KNOWLEDGE_REASON_NO_CHANGE               = 'knowledge_no_change';
	const KNOWLEDGE_REASON_CHUNK_FAILED            = 'chunk_generation_failed';
	const KNOWLEDGE_REASON_FACT_FAILED             = 'fact_extraction_failed';
	const KNOWLEDGE_REASON_CONTENT_NOT_OK          = 'content_not_ready';
	const KNOWLEDGE_REASON_SOURCE_INACTIVE         = 'source_inactive';
	const KNOWLEDGE_REASON_RULES_BLOCKED           = 'rules_blocked';

	// Chunks.chunk_status.
	const CHUNK_STATUS_ACTIVE     = 'active';
	const CHUNK_STATUS_SUPERSEDED = 'superseded';
	const CHUNK_STATUS_RETIRED    = 'retired';
	const CHUNK_STATUS_FAILED     = 'failed';

	// Facts.fact_status.
	const FACT_STATUS_ACTIVE   = 'active';
	const FACT_STATUS_RETIRED  = 'retired';

	// Fact types (fact_type column).
	const FACT_TYPE_TITLE   = 'title';
	const FACT_TYPE_HEADING = 'heading';
	const FACT_TYPE_URL     = 'url';
	const FACT_TYPE_EMAIL   = 'email';
	const FACT_TYPE_PHONE   = 'phone';
	const FACT_TYPE_FAQ_Q   = 'faq_question';
	const FACT_TYPE_FAQ_A   = 'faq_answer';
	const FACT_TYPE_HOURS   = 'business_hours';
	const FACT_TYPE_LABEL   = 'label';

	// Confidence policy outcomes (retrieval / answer-readiness).
	const CONF_ANSWERABLE_LOCALLY       = 'answerable_locally';
	const CONF_LOW_CONFIDENCE           = 'low_confidence';
	const CONF_NO_MATCH                 = 'no_match';
	const CONF_REQUIRES_CLARIFICATION   = 'requires_clarification';
	const CONF_REQUIRES_FUTURE_AI_ASSIST = 'requires_future_ai_assist';

	// Retrieval hit kinds (structured).
	const HIT_KIND_CHUNK_TITLE   = 'chunk_title';
	const HIT_KIND_CHUNK_HEADING = 'chunk_heading';
	const HIT_KIND_CHUNK_SECTION = 'chunk_section';
	const HIT_KIND_CHUNK_BODY    = 'chunk_body';
	const HIT_KIND_FACT          = 'fact';
	const HIT_KIND_SOURCE_TITLE  = 'source_title';

	/** Retrieval scope for chunk/fact search (server-side boundary). */
	const RETRIEVAL_PUBLIC_SAFE = 'public_safe';
	const RETRIEVAL_INTERNAL    = 'internal';
	const RETRIEVAL_ADMIN_DEBUG = 'admin_debug';

	// Keyword hit scoring (must stay aligned with JSDW_AI_Chat_Confidence_Policy thresholds).
	const RETRIEVAL_SCORE_BASE           = 1.0;
	const RETRIEVAL_SCORE_TITLE_MATCH    = 20.0;
	const RETRIEVAL_SCORE_HEADING_MATCH  = 12.0;
	const RETRIEVAL_SCORE_SECTION_MATCH  = 8.0;
	const RETRIEVAL_SCORE_BODY_MATCH     = 4.0;
	const RETRIEVAL_SCORE_FULL_PHRASE    = 6.0;
	const RETRIEVAL_SCORE_VERSION_CAP    = 3.0;

	const RETRIEVAL_FACT_BASE           = 5.0;
	const RETRIEVAL_FACT_EXACT_BOOST    = 25.0;
	const RETRIEVAL_FACT_PARTIAL_BOOST  = 10.0;
	const RETRIEVAL_FACT_TITLE_ONLY_BOOST = 6.0;

	// Confidence policy (same scale as retrieval scores above).
	const CONF_THRESHOLD_STRONG           = 12.0;
	const CONF_THRESHOLD_TITLE_ANCHOR   = 10.0;
	const CONF_THRESHOLD_WEAK_BODY      = 4.0;
	const CONF_THRESHOLD_SCATTER_LOW    = 15.0;
	const CONF_THRESHOLD_SCATTER_MED    = 20.0;
	const CONF_THRESHOLD_ONE_PER_TITLE  = 18.0;

	/** Hit count alone is a weak ambiguity signal; combine with distinct sources in aggregate_hit_stats(). */
	const RETRIEVAL_AMBIGUOUS_HIT_COUNT   = 24;
	const RETRIEVAL_AMBIGUOUS_MIN_SOURCES = 4;
	/** With many sources and hits, ambiguity is more meaningful when the best score is still weak. */
	const RETRIEVAL_AMBIGUOUS_LOW_BEST    = 11.0;
	const RETRIEVAL_AMBIGUOUS_MIN_HITS    = 10;

	/** Cross-source clarification in JSDW_AI_Chat_Local_Answer_Builder when top two scores are this close. */
	const LOCAL_ANSWER_CROSS_SOURCE_SCORE_GAP = 5.0;

	/** MySQL FULLTEXT index names (see migrations + install_tables). */
	const DB_FT_CHUNKS_KEY = 'jsdw_ft_chunks_norm';
	const DB_FT_FACTS_KEY  = 'jsdw_ft_facts_value';
}
