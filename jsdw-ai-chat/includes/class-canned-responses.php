<?php
/**
 * Central library for user-facing fallback / canned chat copy (tone-aware).
 *
 * Tone keys match `chat.answer_style`: concise (short, direct), neutral (clear, professional),
 * friendly (warmer, still restrained). If tone is missing or invalid, `neutral` is used
 * (see JSDW_AI_Chat_Fallback_Responses and normalize_tone).
 *
 * Copy avoids technical jargon; defaults are honest about limits (no match, low confidence, etc.).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Canned_Responses {
	/**
	 * Allowed tone keys (must match chat.answer_style).
	 */
	private const TONES = array( 'concise', 'neutral', 'friendly' );

	/**
	 * Resolve final copy: non-empty saved override wins; otherwise recommended default for state+tone.
	 *
	 * @param string $status_key Canonical state (no_match, low_confidence, …) or value normalized like get_text().
	 * @param string $tone       concise|neutral|friendly (invalid → neutral).
	 * @param string $saved_raw  Stored custom text; empty/whitespace → use recommended.
	 * @return string
	 */
	public function resolve( $status_key, $tone, $saved_raw ) {
		$tone       = $this->normalize_tone( $tone );
		$status_key = $this->normalize_status_key( (string) $status_key );
		$trimmed    = trim( (string) $saved_raw );
		if ( '' !== $trimmed ) {
			return $trimmed;
		}
		return $this->get_recommended_text( $status_key, $tone );
	}

	/**
	 * Plugin-recommended default string for one state + tone (for prefill, sanitize compare, docs).
	 *
	 * @param string $status_key Canonical state key.
	 * @param string $tone       concise|neutral|friendly.
	 * @return string
	 */
	public function get_recommended_text( $status_key, $tone ) {
		$tone       = $this->normalize_tone( $tone );
		$status_key = $this->normalize_status_key( (string) $status_key );
		$row        = $this->get_recommended_row( $status_key );
		if ( isset( $row[ $tone ] ) && '' !== $row[ $tone ] ) {
			return $row[ $tone ];
		}
		return isset( $row['neutral'] ) ? $row['neutral'] : __( 'Something went wrong. Please try again.', 'jsdw-ai-chat' );
	}

	/**
	 * Resolve message for a status and tone. Admin overrides (non-empty per tone) replace defaults.
	 *
	 * @param string               $status    One of ANSWER_STATUS_* constants.
	 * @param string               $tone      concise|neutral|friendly.
	 * @param array<string, mixed> $overrides chat.canned_responses shaped array.
	 * @return string
	 */
	public function get_text( $status, $tone, array $overrides = array() ) {
		$tone       = $this->normalize_tone( $tone );
		$status_key = $this->normalize_status_key( (string) $status );
		$saved      = '';
		if ( isset( $overrides[ $status_key ] ) && is_array( $overrides[ $status_key ] ) && isset( $overrides[ $status_key ][ $tone ] ) ) {
			$saved = (string) $overrides[ $status_key ][ $tone ];
		}
		return $this->resolve( $status_key, $tone, $saved );
	}

	/**
	 * @param string $tone Raw tone slug.
	 * @return string
	 */
	private function normalize_tone( $tone ) {
		$t = sanitize_key( (string) $tone );
		return in_array( $t, self::TONES, true ) ? $t : 'neutral';
	}

	/**
	 * @param string $status Raw status from engine (matches ANSWER_STATUS_* values).
	 * @return string Key used in overrides array.
	 */
	private function normalize_status_key( $status ) {
		$allowed = array(
			JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_NO_MATCH,
			JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_LOW_CONFIDENCE,
			JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_REQUIRES_CLARIFICATION,
			JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_GUARD_REJECTED,
			JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_FAILED,
			JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_UNSUPPORTED,
		);
		$s = (string) $status;
		return in_array( $s, $allowed, true ) ? $s : JSDW_AI_Chat_Answer_Constants::ANSWER_STATUS_FAILED;
	}

	/**
	 * Recommended default copy per tone for a canonical status key (single source of truth).
	 *
	 * @param string $status_key no_match|low_confidence|...
	 * @return array<string, string> concise, neutral, friendly
	 */
	private function get_recommended_row( $status_key ) {
		switch ( $status_key ) {
			case 'no_match':
				return array(
					'concise'  => __( 'I couldn’t find a clear answer to that here.', 'jsdw-ai-chat' ),
					'neutral'  => __( 'I couldn’t find a clear answer to that in the current website content.', 'jsdw-ai-chat' ),
					'friendly' => __( 'I’m not seeing a clear answer for that in the website content right now.', 'jsdw-ai-chat' ),
				);
			case 'low_confidence':
				return array(
					'concise'  => __( 'I found something related, but not enough to answer confidently.', 'jsdw-ai-chat' ),
					'neutral'  => __( 'I found some related information, but not enough to answer that confidently.', 'jsdw-ai-chat' ),
					'friendly' => __( 'I found something close, but I don’t want to guess and give you the wrong answer.', 'jsdw-ai-chat' ),
				);
			case 'requires_clarification':
				return array(
					'concise'  => __( 'I found a few possible matches. Try being a little more specific.', 'jsdw-ai-chat' ),
					'neutral'  => __( 'I found a few possible matches. Try asking in a more specific way.', 'jsdw-ai-chat' ),
					'friendly' => __( 'I found a few possible matches. Try narrowing it down a bit and I’ll do my best to help.', 'jsdw-ai-chat' ),
				);
			case 'guard_rejected':
				return array(
					'concise'  => __( 'I couldn’t process that request.', 'jsdw-ai-chat' ),
					'neutral'  => __( 'I couldn’t process that request. Please shorten it or rephrase it.', 'jsdw-ai-chat' ),
					'friendly' => __( 'I couldn’t process that request as written. Try shortening it or asking it a different way.', 'jsdw-ai-chat' ),
				);
			case 'failed':
				return array(
					'concise'  => __( 'Something went wrong. Please try again.', 'jsdw-ai-chat' ),
					'neutral'  => __( 'Something went wrong while I was checking the site content. Please try again in a moment.', 'jsdw-ai-chat' ),
					'friendly' => __( 'Something went wrong while I was checking the site. Please try again in a moment.', 'jsdw-ai-chat' ),
				);
			case 'unsupported':
				return array(
					'concise'  => __( 'I can\'t help with that here.', 'jsdw-ai-chat' ),
					'neutral'  => __( 'That request isn\'t something I can help with through this chat.', 'jsdw-ai-chat' ),
					'friendly' => __( 'That\'s outside what I can help with here, but I can still help with questions about this site.', 'jsdw-ai-chat' ),
				);
			default:
				return array(
					'concise'  => __( 'Something went wrong. Please try again.', 'jsdw-ai-chat' ),
					'neutral'  => __( 'Something went wrong. Please try again.', 'jsdw-ai-chat' ),
					'friendly' => __( 'Something went wrong. Please try again.', 'jsdw-ai-chat' ),
				);
		}
	}

	/**
	 * Empty template for sanitization / defaults merge (all tones empty = use library defaults).
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_empty_overrides_structure() {
		$out = array();
		foreach ( array( 'no_match', 'low_confidence', 'requires_clarification', 'guard_rejected', 'failed', 'unsupported' ) as $key ) {
			$out[ $key ] = array(
				'concise'  => '',
				'neutral'  => '',
				'friendly' => '',
			);
		}
		return $out;
	}
}
