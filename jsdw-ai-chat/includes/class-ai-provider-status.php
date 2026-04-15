<?php
/**
 * Admin-facing AI configuration status (honest, no API connectivity claims).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_AI_Provider_Status {

	/**
	 * Whitelist for stored provider slug (empty = none).
	 */
	public const PROVIDER_OPENAI    = 'openai';
	public const PROVIDER_ANTHROPIC = 'anthropic';
	public const PROVIDER_GOOGLE    = 'google';

	/**
	 * @return list<string>
	 */
	public static function allowed_provider_values() {
		return array( '', self::PROVIDER_OPENAI, self::PROVIDER_ANTHROPIC, self::PROVIDER_GOOGLE );
	}

	/**
	 * @param string $raw Raw provider value.
	 * @return string Sanitized slug or '' if unknown.
	 */
	public static function sanitize_provider( $raw ) {
		$s = sanitize_text_field( (string) $raw );
		return in_array( $s, self::allowed_provider_values(), true ) ? $s : '';
	}

	/**
	 * Human label for a stored provider slug (settings UI).
	 *
	 * @param string $slug
	 * @return string
	 */
	public static function provider_label( $slug ) {
		switch ( (string) $slug ) {
			case self::PROVIDER_OPENAI:
				return __( 'OpenAI', 'jsdw-ai-chat' );
			case self::PROVIDER_ANTHROPIC:
				return __( 'Anthropic', 'jsdw-ai-chat' );
			case self::PROVIDER_GOOGLE:
				return __( 'Google', 'jsdw-ai-chat' );
			default:
				return __( 'None', 'jsdw-ai-chat' );
		}
	}

	/**
	 * Summarize AI configuration for admin display. Never implies external connectivity or "active" AI.
	 *
	 * Gating order matches runtime phrase-assist preconditions (see answer policy + phrase assist stub).
	 *
	 * @param array<string,mixed> $settings Full settings array from JSDW_AI_Chat_Settings::get_all().
	 * @return array{code:string,label:string,severity:string,detail:string}
	 */
	public static function summarize( array $settings ) {
		$feat = isset( $settings['features'] ) && is_array( $settings['features'] ) ? $settings['features'] : array();
		$ai   = isset( $settings['ai'] ) && is_array( $settings['ai'] ) ? $settings['ai'] : array();
		$chat = isset( $settings['chat'] ) && is_array( $settings['chat'] ) ? $settings['chat'] : array();

		$enable_ai   = ! empty( $feat['enable_ai'] );
		$provider    = self::sanitize_provider( isset( $ai['provider'] ) ? (string) $ai['provider'] : '' );
		$answer_mode = isset( $chat['answer_mode'] ) ? (string) $chat['answer_mode'] : 'strict_local_only';
		$phrase_on   = ! empty( $chat['allow_ai_phrase_assist'] );

		if ( ! $enable_ai ) {
			return array(
				'code'     => 'ai_disabled',
				'label'    => __( 'AI features disabled', 'jsdw-ai-chat' ),
				'severity' => 'neutral',
				'detail'   => __( 'Optional AI-related settings are ignored until you enable AI features below. Answers are generated locally from your indexed content.', 'jsdw-ai-chat' ),
			);
		}

		if ( '' === $provider ) {
			return array(
				'code'     => 'no_provider',
				'label'    => __( 'No provider selected', 'jsdw-ai-chat' ),
				'severity' => 'warning',
				'detail'   => __( 'Choose a provider to save a preference for future integration. No external AI calls occur until a provider is implemented in this plugin.', 'jsdw-ai-chat' ),
			);
		}

		if ( 'strict_local_only' === $answer_mode ) {
			return array(
				'code'     => 'blocked_by_mode',
				'label'    => __( 'Blocked by answer mode', 'jsdw-ai-chat' ),
				'severity' => 'warning',
				'detail'   => __( 'Strict local only turns off the optional phrase-assist path. Switch to "Local with optional AI phrase" or "Debug trace" if you want phrase refinement when configured.', 'jsdw-ai-chat' ),
			);
		}

		if ( ! $phrase_on ) {
			return array(
				'code'     => 'phrase_assist_off',
				'label'    => __( 'Phrase assist disabled', 'jsdw-ai-chat' ),
				'severity' => 'info',
				'detail'   => __( 'Turn on "AI-assisted phrasing" below to allow optional wording refinement when all runtime gates pass.', 'jsdw-ai-chat' ),
			);
		}

		if ( self::PROVIDER_OPENAI === $provider ) {
			if ( ! JSDW_AI_Chat_AI_Provider_Client::is_provider_configured( $settings ) ) {
				return array(
					'code'     => 'openai_missing_key',
					'label'    => __( 'OpenAI API key required', 'jsdw-ai-chat' ),
					'severity' => 'warning',
					'detail'   => __( 'Add an API key below to enable outbound phrase refinement. Until then, only local answers are used.', 'jsdw-ai-chat' ),
				);
			}
			return array(
				'code'     => 'openai_ready',
				'label'    => __( 'Phrase assist configured (OpenAI)', 'jsdw-ai-chat' ),
				'severity' => 'info',
				'detail'   => __( 'High-confidence local answers may be sent to OpenAI for wording-only refinement. Only the final answer text and tone are sent—never raw chunks or retrieval metadata. If the API fails, the local answer is returned unchanged.', 'jsdw-ai-chat' ),
			);
		}

		if ( in_array( $provider, array( self::PROVIDER_ANTHROPIC, self::PROVIDER_GOOGLE ), true ) ) {
			return array(
				'code'     => 'provider_not_implemented',
				'label'    => __( 'Provider not implemented', 'jsdw-ai-chat' ),
				'severity' => 'warning',
				'detail'   => __( 'Phrase assist is not available for this provider yet. Choose OpenAI or turn off phrase assist.', 'jsdw-ai-chat' ),
			);
		}

		return array(
			'code'     => 'eligible_incomplete',
			'label'    => __( 'Phrase assist path incomplete', 'jsdw-ai-chat' ),
			'severity' => 'info',
			'detail'   => __( 'Select a supported provider and complete its credentials. Answers remain local until then.', 'jsdw-ai-chat' ),
		);
	}
}
