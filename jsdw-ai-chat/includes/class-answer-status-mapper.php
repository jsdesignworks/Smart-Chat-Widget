<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Answer_Status_Mapper {
	/**
	 * @param string $confidence CONF_* from confidence policy.
	 * @param array<string,mixed> $guard_result Query guard output.
	 * @param array<string,mixed> $policy_context Optional policy overrides.
	 * @return string
	 */
	public function map_confidence_to_answer_status( $confidence, array $guard_result = array(), array $policy_context = array() ) {
		if ( ! empty( $guard_result['rejected'] ) ) {
			return JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_GUARD_REJECTED;
		}

		if ( ! empty( $policy_context['force_status'] ) ) {
			return sanitize_key( (string) $policy_context['force_status'] );
		}

		switch ( (string) $confidence ) {
			case JSDW_AI_Chat_Knowledge_Constants::CONF_ANSWERABLE_LOCALLY:
				return JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_ANSWERED_LOCALLY;
			case JSDW_AI_Chat_Knowledge_Constants::CONF_LOW_CONFIDENCE:
				return JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_LOW_CONFIDENCE;
			case JSDW_AI_Chat_Knowledge_Constants::CONF_NO_MATCH:
				return JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_NO_MATCH;
			case JSDW_AI_Chat_Knowledge_Constants::CONF_REQUIRES_CLARIFICATION:
				return JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_REQUIRES_CLARIFICATION;
			default:
				return JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_UNSUPPORTED;
		}
	}
}
