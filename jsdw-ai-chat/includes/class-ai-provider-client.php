<?php
/**
 * Routes phrase refinement to provider adapters.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_AI_Provider_Client {

	/**
	 * @var JSDW_AI_Chat_AI_Provider_OpenAI
	 */
	private $openai;

	/**
	 * @var JSDW_AI_Chat_AI_Provider_Anthropic
	 */
	private $anthropic;

	/**
	 * @var JSDW_AI_Chat_AI_Provider_Google
	 */
	private $google;

	public function __construct(
		JSDW_AI_Chat_AI_Provider_OpenAI $openai,
		JSDW_AI_Chat_AI_Provider_Anthropic $anthropic,
		JSDW_AI_Chat_AI_Provider_Google $google
	) {
		$this->openai    = $openai;
		$this->anthropic = $anthropic;
		$this->google    = $google;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return bool
	 */
	public static function is_provider_configured( array $settings ) {
		$ai = isset( $settings['ai'] ) && is_array( $settings['ai'] ) ? $settings['ai'] : array();
		$p  = JSDW_AI_Chat_AI_Provider_Status::sanitize_provider( isset( $ai['provider'] ) ? (string) $ai['provider'] : '' );

		if ( JSDW_AI_Chat_AI_Provider_Status::PROVIDER_OPENAI === $p ) {
			return '' !== trim( (string) ( $ai['openai_api_key'] ?? '' ) );
		}

		return false;
	}

	/**
	 * @param string              $local_answer
	 * @param string              $tone
	 * @param array<string,mixed> $settings
	 * @return array{ok:bool,text?:string,error?:string,provider?:string,duration_ms?:int}
	 */
	public function refine_phrase( $local_answer, $tone, array $settings ) {
		$ai = isset( $settings['ai'] ) && is_array( $settings['ai'] ) ? $settings['ai'] : array();
		$p  = JSDW_AI_Chat_AI_Provider_Status::sanitize_provider( isset( $ai['provider'] ) ? (string) $ai['provider'] : '' );

		if ( JSDW_AI_Chat_AI_Provider_Status::PROVIDER_OPENAI === $p ) {
			$r = $this->openai->refine_phrase(
				$local_answer,
				$tone,
				(string) ( $ai['openai_api_key'] ?? '' ),
				(string) ( $ai['openai_model'] ?? JSDW_AI_Chat_AI_Provider_OpenAI::DEFAULT_MODEL )
			);
			$r['provider'] = 'openai';
			return $r;
		}

		if ( JSDW_AI_Chat_AI_Provider_Status::PROVIDER_ANTHROPIC === $p ) {
			$r = $this->anthropic->refine_phrase( $local_answer, $tone );
			$r['provider'] = 'anthropic';
			return $r;
		}

		if ( JSDW_AI_Chat_AI_Provider_Status::PROVIDER_GOOGLE === $p ) {
			$r = $this->google->refine_phrase( $local_answer, $tone );
			$r['provider'] = 'google';
			return $r;
		}

		return array(
			'ok'          => false,
			'error'       => 'no_provider',
			'provider'    => '',
			'duration_ms' => 0,
		);
	}
}
