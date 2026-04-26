<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Query_Guard {
	/**
	 * @param string $query
	 * @param array<string,mixed> $settings
	 * @param string $session_key
	 * @return array<string,mixed>
	 */
	public function validate( $query, array $settings, $session_key = '' ) {
		$chat_settings = isset( $settings['chat'] ) && is_array( $settings['chat'] ) ? $settings['chat'] : array();
		$trimmed       = trim( (string) $query );
		$min_len       = max( 1, absint( $chat_settings['min_query_length'] ?? 2 ) );
		$max_len       = max( $min_len, absint( $chat_settings['max_query_length'] ?? 500 ) );

		if ( '' === $trimmed ) {
			return array( 'rejected' => true, 'reason' => 'empty_query' );
		}
		if ( strlen( $trimmed ) < $min_len ) {
			return array( 'rejected' => true, 'reason' => 'min_length' );
		}
		if ( strlen( $trimmed ) > $max_len ) {
			return array( 'rejected' => true, 'reason' => 'max_length' );
		}
		if ( ! preg_match( '/[a-zA-Z0-9]/', $trimmed ) ) {
			return array( 'rejected' => true, 'reason' => 'junk_input' );
		}

		// Throttle bucket is keyed by client IP only. Including a client-supplied
		// session_key let an unauthenticated visitor mint fresh buckets per request.
		$client_ip      = $this->resolve_client_ip();
		$throttle_key   = 'jsdw_ai_chat_t_' . md5( $client_ip );
		$throttle_count = (int) get_transient( $throttle_key );
		$per_minute     = isset( $chat_settings['query_throttle_per_minute'] ) ? absint( $chat_settings['query_throttle_per_minute'] ) : 30;
		$per_minute     = max( 5, min( 300, $per_minute ) );
		if ( $throttle_count >= $per_minute ) {
			return array( 'rejected' => true, 'reason' => 'throttled' );
		}
		set_transient( $throttle_key, $throttle_count + 1, MINUTE_IN_SECONDS );

		return array( 'rejected' => false, 'reason' => '' );
	}

	/**
	 * Resolve the client IP for throttling. REMOTE_ADDR is the only value we
	 * trust by default; sites behind a reverse proxy or CDN should override
	 * this via the `jsdw_ai_chat_client_ip` filter after validating their own
	 * forwarded-for chain. An empty/invalid IP collapses to a shared bucket
	 * rather than skipping throttling.
	 *
	 * @return string Non-empty bucket identifier.
	 */
	private function resolve_client_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$remote = trim( $remote );
		$ip     = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';

		/**
		 * Filter the client IP used for chat-query throttling.
		 *
		 * @param string $ip Validated REMOTE_ADDR, or '' if invalid/missing.
		 */
		$ip = (string) apply_filters( 'jsdw_ai_chat_client_ip', $ip );
		$ip = trim( $ip );

		return '' !== $ip ? $ip : 'unknown';
	}
}
