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

		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$throttle_key = 'jsdw_ai_chat_t_' . md5( $ip . '|' . (string) $session_key );
		$throttle_count = (int) get_transient( $throttle_key );
		if ( $throttle_count >= 30 ) {
			return array( 'rejected' => true, 'reason' => 'throttled' );
		}
		set_transient( $throttle_key, $throttle_count + 1, MINUTE_IN_SECONDS );

		return array( 'rejected' => false, 'reason' => '' );
	}
}
