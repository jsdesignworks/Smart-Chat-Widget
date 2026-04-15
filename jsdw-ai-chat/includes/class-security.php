<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Security {
	public static function user_can_manage() {
		return current_user_can( 'manage_ai_chat_widget' );
	}

	public static function verify_nonce( $nonce, $action ) {
		if ( ! is_string( $nonce ) || ! is_string( $action ) ) {
			return false;
		}
		return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), $action );
	}
}
