<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves user-facing fallback lines using tone (answer_style) and optional admin overrides.
 */
class JSDW_AI_Chat_Fallback_Responses {
	/**
	 * @var JSDW_AI_Chat_Canned_Responses
	 */
	private $canned;

	public function __construct( JSDW_AI_Chat_Canned_Responses $canned ) {
		$this->canned = $canned;
	}

	/**
	 * @param string               $status   One of JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_*.
	 * @param array<string, mixed> $settings Full plugin settings (uses chat.answer_style + chat.canned_responses).
	 * @return string
	 */
	public function get_text( $status, array $settings ) {
		$chat      = isset( $settings['chat'] ) && is_array( $settings['chat'] ) ? $settings['chat'] : array();
		$tone      = isset( $chat['answer_style'] ) ? (string) $chat['answer_style'] : 'neutral';
		$overrides = isset( $chat['canned_responses'] ) && is_array( $chat['canned_responses'] ) ? $chat['canned_responses'] : array();

		return $this->canned->get_text( (string) $status, $tone, $overrides );
	}
}
