<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Activator {
	public static function activate() {
		if ( ! function_exists( 'jsdw_ai_chat_requirements_met' ) || ! jsdw_ai_chat_requirements_met() ) {
			deactivate_plugins( JSDW_AI_CHAT_BASENAME );
			wp_die( esc_html__( 'JSDW AI Chat requires WordPress 6.0+ and PHP 8.0+.', 'jsdw-ai-chat' ) );
		}

		$db           = new JSDW_AI_Chat_DB();
		$settings     = new JSDW_AI_Chat_Settings();
		$logger       = new JSDW_AI_Chat_Logger( $db, $settings );
		$migrations   = new JSDW_AI_Chat_Migrations( $db, $logger );
		$capabilities = new JSDW_AI_Chat_Capabilities();
		$cron         = new JSDW_AI_Chat_Cron( $logger );

		$settings->ensure_defaults();
		$db->install_tables();
		$migrations->maybe_migrate();
		$capabilities->register_for_administrators();
		$cron->schedule_events();

		update_option( JSDW_AI_CHAT_OPTION_PLUGIN_VERSION, JSDW_AI_CHAT_VERSION, false );
		update_option( JSDW_AI_CHAT_OPTION_DB_SCHEMA_VERSION, JSDW_AI_CHAT_DB_SCHEMA_VERSION, false );

		$logger->info( 'plugin_activation', 'Plugin activated successfully.' );
	}
}
