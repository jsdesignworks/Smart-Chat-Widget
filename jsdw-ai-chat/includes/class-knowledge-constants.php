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
}
