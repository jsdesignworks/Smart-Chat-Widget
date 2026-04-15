<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Answer_Constants {
	const ANSWER_STATUS_ANSWERED_LOCALLY       = 'answered_locally';
	const ANSWER_STATUS_LOW_CONFIDENCE         = 'low_confidence';
	const ANSWER_STATUS_NO_MATCH               = 'no_match';
	const ANSWER_STATUS_REQUIRES_CLARIFICATION = 'requires_clarification';
	const ANSWER_STATUS_UNSUPPORTED            = 'unsupported';
	const ANSWER_STATUS_FAILED                 = 'failed';
	const ANSWER_STATUS_GUARD_REJECTED         = 'guard_rejected';

	const ANSWER_TYPE_FACTUAL_DIRECT        = 'factual_direct';
	const ANSWER_TYPE_FACTUAL_SUMMARY       = 'factual_summary';
	const ANSWER_TYPE_FAQ_STYLE             = 'faq_style';
	const ANSWER_TYPE_AMBIGUOUS_CLARIFY     = 'ambiguous_clarification';
	const ANSWER_TYPE_NO_MATCH_RESPONSE     = 'no_match_response';
	const ANSWER_TYPE_LOW_CONF_RESPONSE     = 'low_confidence_response';
	const ANSWER_TYPE_UNSUPPORTED_RESPONSE  = 'unsupported_response';
	const ANSWER_TYPE_ERROR_RESPONSE        = 'error_response';
	const ANSWER_TYPE_LIVE_AGENT_HANDOFF    = 'live_agent_handoff';

	const ANSWER_STRATEGY_LOCAL_TEMPLATE   = 'local_template';
	const ANSWER_STRATEGY_FALLBACK_STANDARD = 'fallback_standard';
	const ANSWER_STRATEGY_AI_PHRASE_ASSIST  = 'ai_phrase_assist';
}
