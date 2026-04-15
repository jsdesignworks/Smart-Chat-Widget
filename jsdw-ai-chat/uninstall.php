<?php
/**
 * Uninstall handler.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'JSDW_AI_CHAT_OPTION_SETTINGS', 'jsdw_ai_chat_settings' );
define( 'JSDW_AI_CHAT_OPTION_PLUGIN_VERSION', 'jsdw_ai_chat_plugin_version' );
define( 'JSDW_AI_CHAT_OPTION_DB_SCHEMA_VERSION', 'jsdw_ai_chat_db_schema_version' );
define( 'JSDW_AI_CHAT_OPTION_LAST_ERROR', 'jsdw_ai_chat_last_error' );
define( 'JSDW_AI_CHAT_OPTION_LAST_CRON_RUN', 'jsdw_ai_chat_last_cron_run' );
define( 'JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_SCAN', 'jsdw_ai_chat_last_discovery_scan' );
define( 'JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_RESULT', 'jsdw_ai_chat_last_discovery_result' );
define( 'JSDW_AI_CHAT_OPTION_LAST_CONTENT_VERIFICATION', 'jsdw_ai_chat_last_content_verification' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-db.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cron.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-uninstaller.php';

JSDW_AI_Chat_Uninstaller::uninstall();
