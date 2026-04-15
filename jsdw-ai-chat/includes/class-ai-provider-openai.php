<?php
/**
 * OpenAI adapter for phrase refinement (chat completions).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_AI_Provider_OpenAI {

	const DEFAULT_MODEL = 'gpt-4o-mini';
	const TIMEOUT       = 22;
	const API_URL       = 'https://api.openai.com/v1/chat/completions';

	/**
	 * @param string $text   Final local answer only.
	 * @param string $tone   concise|neutral|friendly
	 * @param string $api_key
	 * @param string $model
	 * @return array{ok:bool,text?:string,error?:string,duration_ms?:int}
	 */
	public function refine_phrase( $text, $tone, $api_key, $model ) {
		$start = microtime( true );
		$key   = trim( (string) $api_key );
		if ( '' === $key ) {
			return array( 'ok' => false, 'error' => 'missing_api_key', 'duration_ms' => 0 );
		}

		$model_use = trim( (string) $model );
		if ( '' === $model_use || ! preg_match( '/^[a-zA-Z0-9._-]{1,80}$/', $model_use ) ) {
			$model_use = self::DEFAULT_MODEL;
		}

		$system = $this->build_system_prompt( $tone );
		$user   = $this->build_user_payload( $text );

		$body = wp_json_encode(
			array(
				'model'       => $model_use,
				'messages'    => array(
					array( 'role' => 'system', 'content' => $system ),
					array( 'role' => 'user', 'content' => $user ),
				),
				'temperature' => 0.25,
				'max_tokens'  => min( 1024, max( 64, (int) ( strlen( $text ) * 1.5 ) + 64 ) ),
			)
		);

		if ( false === $body ) {
			return array( 'ok' => false, 'error' => 'encode_failed', 'duration_ms' => (int) round( ( microtime( true ) - $start ) * 1000 ) );
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		$duration_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'error' => 'transport', 'duration_ms' => $duration_ms );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return array( 'ok' => false, 'error' => 'http_' . (string) $code, 'duration_ms' => $duration_ms );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array( 'ok' => false, 'error' => 'invalid_json', 'duration_ms' => $duration_ms );
		}

		$content = '';
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = (string) $data['choices'][0]['message']['content'];
		}

		$content = trim( $content );
		if ( '' === $content ) {
			return array( 'ok' => false, 'error' => 'empty_content', 'duration_ms' => $duration_ms );
		}

		if ( strlen( $content ) >= 2 && ( '"' === $content[0] || "'" === $content[0] ) ) {
			$last = substr( $content, -1 );
			if ( ( '"' === $content[0] && '"' === $last ) || ( "'" === $content[0] && "'" === $last ) ) {
				$content = trim( substr( $content, 1, -1 ) );
			}
		}

		if ( ! $this->is_output_plausible( $text, $content ) ) {
			return array( 'ok' => false, 'error' => 'output_rejected', 'duration_ms' => $duration_ms );
		}

		return array(
			'ok'          => true,
			'text'        => $content,
			'duration_ms' => $duration_ms,
		);
	}

	private function build_system_prompt( $tone ) {
		$tone = strtolower( sanitize_text_field( (string) $tone ) );
		if ( ! in_array( $tone, array( 'concise', 'neutral', 'friendly' ), true ) ) {
			$tone = 'neutral';
		}

		$tone_line = '';
		if ( 'concise' === $tone ) {
			$tone_line = 'Use shorter, tighter wording. Remove redundancy while keeping every fact.';
		} elseif ( 'friendly' === $tone ) {
			$tone_line = 'Use a slightly warmer, approachable tone. Do not add enthusiasm that changes claims.';
		} else {
			$tone_line = 'Use clear, professional neutral wording.';
		}

		return 'You rewrite answers for clarity and tone only. Preserve meaning exactly. Do not add facts, infer missing data, generalize beyond the input, or answer new questions. Do not mention AI, models, or that you are rewriting. ' . $tone_line . ' Output only the rewritten answer text with no preamble, labels, or markdown fences.';
	}

	private function build_user_payload( $text ) {
		return "Original answer to refine (keep meaning identical):\n\n" . $text;
	}

	private function is_output_plausible( $original, $out ) {
		$ol = strlen( $original );
		$nl = strlen( $out );
		if ( $nl < 1 ) {
			return false;
		}
		if ( $ol > 0 && $nl > max( 8000, (int) ( $ol * 2.5 ) + 400 ) ) {
			return false;
		}
		return true;
	}
}
