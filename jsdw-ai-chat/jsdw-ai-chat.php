<?php
/**
 * Plugin Name: JSDW AI Chat
 * Plugin URI: https://example.com
 * Description: Backend foundation for JSDW AI Chat knowledge and operations engine.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: JSDW
 * Text Domain: jsdw-ai-chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JSDW_AI_CHAT_VERSION', '1.1.0' );
define( 'JSDW_AI_CHAT_DB_SCHEMA_VERSION', '1.7.0' );
define( 'JSDW_AI_CHAT_SLUG', 'jsdw-ai-chat' );
define( 'JSDW_AI_CHAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'JSDW_AI_CHAT_URL', plugin_dir_url( __FILE__ ) );
define( 'JSDW_AI_CHAT_BASENAME', plugin_basename( __FILE__ ) );
define( 'JSDW_AI_CHAT_DEBUG', (bool) apply_filters( 'jsdw_ai_chat_debug', defined( 'WP_DEBUG' ) && WP_DEBUG ) );

define( 'JSDW_AI_CHAT_OPTION_SETTINGS', 'jsdw_ai_chat_settings' );
define( 'JSDW_AI_CHAT_OPTION_PLUGIN_VERSION', 'jsdw_ai_chat_plugin_version' );
define( 'JSDW_AI_CHAT_OPTION_DB_SCHEMA_VERSION', 'jsdw_ai_chat_db_schema_version' );
define( 'JSDW_AI_CHAT_OPTION_LAST_ERROR', 'jsdw_ai_chat_last_error' );
define( 'JSDW_AI_CHAT_OPTION_LAST_CRON_RUN', 'jsdw_ai_chat_last_cron_run' );
define( 'JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_SCAN', 'jsdw_ai_chat_last_discovery_scan' );
define( 'JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_RESULT', 'jsdw_ai_chat_last_discovery_result' );
define( 'JSDW_AI_CHAT_OPTION_LAST_CONTENT_VERIFICATION', 'jsdw_ai_chat_last_content_verification' );
define( 'JSDW_AI_CHAT_OPTION_LAST_KNOWLEDGE_VERIFICATION', 'jsdw_ai_chat_last_knowledge_verification' );
define( 'JSDW_AI_CHAT_OPTION_LAST_ANSWER_REQUEST', 'jsdw_ai_chat_last_answer_request' );

/** User meta: admin UI theme for plugin screens only (dark-violet | warm-clay). */
define( 'JSDW_AI_CHAT_USER_META_ADMIN_UI_MODE', 'jsdw_ai_chat_admin_ui_mode' );

require_once JSDW_AI_CHAT_PATH . 'includes/class-loader.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-container.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-db.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-knowledge-constants.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-source-visibility.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-public-response-policy.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-migrations.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-settings.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-ai-provider-status.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-ai-provider-openai.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-ai-provider-anthropic.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-ai-provider-google.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-ai-provider-client.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-capabilities.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-security.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-job-repository.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-source-rules.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-chunk-repository.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-fact-repository.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-source-repository.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-source-admin-presenter.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-source-discovery.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-source-registry.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-content-normalizer.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-content-fingerprint.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-content-state-comparator.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-content-chunker.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-fact-extractor.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-query-normalizer.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-confidence-policy.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-answer-context-builder.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-knowledge-retriever.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-answer-constants.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-answer-status-mapper.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-answer-policy.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-canned-responses.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-fallback-responses.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-local-answer-builder.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-ai-phrase-assist.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-answer-trace.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-answer-formatter.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-query-guard.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-answer-engine.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-conversation-service.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-chat-service.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-source-content-builder.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-queue.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-source-content-processor.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-source-knowledge-processor.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-cron.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-logger.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-health.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-rest.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-activator.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-deactivator.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-uninstaller.php';
require_once JSDW_AI_CHAT_PATH . 'admin/class-admin.php';
require_once JSDW_AI_CHAT_PATH . 'public/class-widget-renderer.php';
require_once JSDW_AI_CHAT_PATH . 'public/class-public.php';
require_once JSDW_AI_CHAT_PATH . 'includes/class-plugin.php';

function jsdw_ai_chat_requirements_met() {
	global $wp_version;

	$php_ok = version_compare( PHP_VERSION, '8.0.0', '>=' );
	$wp_ok  = version_compare( (string) $wp_version, '6.0', '>=' );

	return $php_ok && $wp_ok;
}

function jsdw_ai_chat_requirements_notice() {
	if ( jsdw_ai_chat_requirements_met() ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'JSDW AI Chat requires WordPress 6.0+ and PHP 8.0+.', 'jsdw-ai-chat' );
	echo '</p></div>';
}

if ( ! jsdw_ai_chat_requirements_met() ) {
	add_action( 'admin_notices', 'jsdw_ai_chat_requirements_notice' );
	return;
}

register_activation_hook( __FILE__, array( 'JSDW_AI_Chat_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JSDW_AI_Chat_Deactivator', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'JSDW_AI_Chat_Uninstaller', 'uninstall' ) );

function jsdw_ai_chat_bootstrap() {
	$plugin = new JSDW_AI_Chat_Plugin();
	$plugin->run();
}

jsdw_ai_chat_bootstrap();
