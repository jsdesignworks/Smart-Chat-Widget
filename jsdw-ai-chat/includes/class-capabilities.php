<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Capabilities {
	public function get_capabilities() {
		return array(
			'manage_ai_chat_widget',
			'manage_ai_chat_widget_settings',
			'manage_ai_chat_widget_index',
			'manage_ai_chat_widget_logs',
			'manage_ai_chat_widget_conversations',
		);
	}

	public function register_for_administrators() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		foreach ( $this->get_capabilities() as $capability ) {
			$role->add_cap( $capability );
		}
	}
}
