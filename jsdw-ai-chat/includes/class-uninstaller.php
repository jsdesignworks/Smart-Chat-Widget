<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Uninstaller {
	public static function uninstall() {
		$settings = get_option( JSDW_AI_CHAT_OPTION_SETTINGS, array() );
		$cleanup  = false;

		if ( is_array( $settings ) && isset( $settings['features'] ) && is_array( $settings['features'] ) ) {
			$cleanup = ! empty( $settings['features']['cleanup_on_uninstall'] );
		}

		if ( ! $cleanup ) {
			return;
		}

		$db = new JSDW_AI_Chat_DB();
		$db->drop_tables();

		delete_option( JSDW_AI_CHAT_OPTION_SETTINGS );
		delete_option( JSDW_AI_CHAT_OPTION_PLUGIN_VERSION );
		delete_option( JSDW_AI_CHAT_OPTION_DB_SCHEMA_VERSION );
		delete_option( JSDW_AI_CHAT_OPTION_LAST_ERROR );
		delete_option( JSDW_AI_CHAT_OPTION_LAST_CRON_RUN );
		delete_option( JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_SCAN );
		delete_option( JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_RESULT );
		delete_option( JSDW_AI_CHAT_OPTION_LAST_CONTENT_VERIFICATION );
		delete_option( JSDW_AI_CHAT_OPTION_LAST_KNOWLEDGE_VERIFICATION );
		delete_option( JSDW_AI_Chat_Cron::LOCK_OPTION_NAME );
	}
}
