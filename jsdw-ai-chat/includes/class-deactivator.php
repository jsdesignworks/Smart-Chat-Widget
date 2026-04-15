<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Deactivator {
	public static function deactivate() {
		$db       = new JSDW_AI_Chat_DB();
		$settings = new JSDW_AI_Chat_Settings();
		$logger   = new JSDW_AI_Chat_Logger( $db, $settings );
		$cron     = new JSDW_AI_Chat_Cron( $logger );

		$cron->clear_events();
		$logger->info( 'plugin_deactivation', 'Plugin deactivated.' );
	}
}
