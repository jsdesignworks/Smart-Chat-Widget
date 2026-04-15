<?php
/**
 * Optional AI phrase refinement after a local answer exists (gated).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_AI_Phrase_Assist {

	/**
	 * @var JSDW_AI_Chat_AI_Provider_Client
	 */
	private $client;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	public function __construct( JSDW_AI_Chat_AI_Provider_Client $client, JSDW_AI_Chat_Logger $logger ) {
		$this->client = $client;
		$this->logger = $logger;
	}

	/**
	 * @param string              $local_text
	 * @param array<string,mixed> $settings
	 * @param string              $confidence
	 * @param string              $answer_status
	 * @param array<string,mixed> $guard_result
	 * @param array<string,mixed> $policy        From answer policy evaluate().
	 * @param string              $answer_type   Local builder answer_type.
	 * @return array{text:string,used_ai:bool,allowed:bool}
	 */
	public function maybe_rephrase( $local_text, array $settings, $confidence, $answer_status, array $guard_result, array $policy, $answer_type ) {
		$local_text = (string) $local_text;

		$allowed = ! empty( $policy['allow_ai_phrase'] )
			&& JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_ANSWERED_LOCALLY === (string) $answer_status
			&& JSDW_AI_Chat_Knowledge_Constants::CONF_ANSWERABLE_LOCALLY === (string) $confidence
			&& empty( $guard_result['rejected'] )
			&& '' !== trim( $local_text )
			&& JSDW_AI_Chat_Answer_Constants::ANSWER_TYPE_AMBIGUOUS_CLARIFY !== (string) $answer_type;

		if ( ! $allowed ) {
			$this->logger->debug(
				'ai_phrase_skipped',
				'Phrase assist skipped (gates).',
				array(
					'reason' => 'gates',
				)
			);
			return array(
				'text'    => $local_text,
				'used_ai' => false,
				'allowed' => false,
			);
		}

		if ( ! JSDW_AI_Chat_AI_Provider_Client::is_provider_configured( $settings ) ) {
			$this->logger->info(
				'ai_phrase_skipped',
				'Phrase assist skipped (provider not configured).',
				array(
					'reason' => 'not_configured',
				)
			);
			return array(
				'text'    => $local_text,
				'used_ai' => false,
				'allowed' => true,
			);
		}

		$chat  = isset( $settings['chat'] ) && is_array( $settings['chat'] ) ? $settings['chat'] : array();
		$tone  = isset( $chat['answer_style'] ) ? (string) $chat['answer_style'] : 'neutral';
		$ai_in = isset( $settings['ai'] ) && is_array( $settings['ai'] ) ? $settings['ai'] : array();
		$prov  = JSDW_AI_Chat_AI_Provider_Status::sanitize_provider( isset( $ai_in['provider'] ) ? (string) $ai_in['provider'] : '' );

		$this->logger->info(
			'ai_phrase_invoked',
			'Phrase assist invoked.',
			array(
				'provider' => $prov,
			)
		);

		$result = $this->client->refine_phrase( $local_text, $tone, $settings );

		$dur = isset( $result['duration_ms'] ) ? (int) $result['duration_ms'] : 0;
		$pr  = isset( $result['provider'] ) ? (string) $result['provider'] : $prov;

		if ( ! empty( $result['ok'] ) && isset( $result['text'] ) && '' !== trim( (string) $result['text'] ) ) {
			$this->logger->info(
				'ai_phrase_ok',
				'Phrase assist succeeded.',
				array(
					'provider'    => $pr,
					'duration_ms' => $dur,
				)
			);
			return array(
				'text'    => trim( (string) $result['text'] ),
				'used_ai' => true,
				'allowed' => true,
			);
		}

		$err = isset( $result['error'] ) ? (string) $result['error'] : 'unknown';
		$this->logger->warning(
			'ai_phrase_failed',
			'Phrase assist failed; using local answer.',
			array(
				'provider'    => $pr,
				'duration_ms' => $dur,
				'error'       => $err,
			)
		);

		return array(
			'text'    => $local_text,
			'used_ai' => false,
			'allowed' => true,
		);
	}
}
