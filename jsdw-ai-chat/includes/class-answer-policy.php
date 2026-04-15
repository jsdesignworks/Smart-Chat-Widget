<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Answer_Policy {
	/**
	 * @param string $confidence
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	public function evaluate( $confidence, array $settings ) {
		$chat_settings = isset( $settings['chat'] ) && is_array( $settings['chat'] ) ? $settings['chat'] : array();
		$answer_mode   = isset( $chat_settings['answer_mode'] ) ? (string) $chat_settings['answer_mode'] : 'strict_local_only';

		$allow_ai_phrase = ! empty( $chat_settings['allow_ai_phrase_assist'] )
			&& ! empty( $settings['features']['enable_ai'] )
			&& '' !== (string) ( $settings['ai']['provider'] ?? '' )
			&& JSDW_AI_Chat_AI_Provider_Client::is_provider_configured( $settings )
			&& JSDW_AI_Chat_Knowledge_Constants::CONF_ANSWERABLE_LOCALLY === (string) $confidence
			&& 'strict_local_only' !== $answer_mode;

		return array(
			'allow_local_answer' => JSDW_AI_Chat_Knowledge_Constants::CONF_ANSWERABLE_LOCALLY === (string) $confidence,
			'allow_clarification'=> ! empty( $chat_settings['clarification_enabled'] ),
			'allow_ai_phrase'    => $allow_ai_phrase,
			'answer_mode'        => $answer_mode,
			'force_status'       => '',
		);
	}
}
