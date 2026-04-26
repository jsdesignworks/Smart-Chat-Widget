<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Health {
	/**
	 * @var JSDW_AI_Chat_DB
	 */
	private $db;

	/**
	 * @var JSDW_AI_Chat_Cron
	 */
	private $cron;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	/**
	 * @var JSDW_AI_Chat_Queue
	 */
	private $queue;

	/**
	 * @var JSDW_AI_Chat_Source_Repository
	 */
	private $source_repository;

	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	/**
	 * @var JSDW_AI_Chat_Chunk_Repository
	 */
	private $chunk_repository;

	/**
	 * @var JSDW_AI_Chat_Fact_Repository
	 */
	private $fact_repository;

	/**
	 * @var JSDW_AI_Chat_Conversation_Service
	 */
	private $conversation_service;

	public function __construct( JSDW_AI_Chat_DB $db, JSDW_AI_Chat_Cron $cron, JSDW_AI_Chat_Logger $logger, JSDW_AI_Chat_Queue $queue, JSDW_AI_Chat_Source_Repository $source_repository, JSDW_AI_Chat_Settings $settings, JSDW_AI_Chat_Chunk_Repository $chunk_repository, JSDW_AI_Chat_Fact_Repository $fact_repository, JSDW_AI_Chat_Conversation_Service $conversation_service ) {
		$this->db     = $db;
		$this->cron   = $cron;
		$this->logger = $logger;
		$this->queue  = $queue;
		$this->source_repository = $source_repository;
		$this->settings = $settings;
		$this->chunk_repository = $chunk_repository;
		$this->fact_repository  = $fact_repository;
		$this->conversation_service = $conversation_service;
	}

	/**
	 * Minimal payload for unauthenticated / non-admin REST ping (no internal diagnostics).
	 *
	 * @return array<string,mixed>
	 */
	public function get_public_ping() {
		return array(
			'ok'             => true,
			'plugin_version' => JSDW_AI_CHAT_VERSION,
			'schema_version' => get_option( JSDW_AI_CHAT_OPTION_DB_SCHEMA_VERSION, '0.0.0' ),
			'rest_namespace' => 'ai-chat-widget/v1',
		);
	}

	/**
	 * Admin-only security posture signals (counts and risky discovery flags).
	 *
	 * @return array<string,mixed>
	 */
	public function get_security_posture() {
		$settings = $this->settings->get_all();
		$src      = isset( $settings['sources'] ) && is_array( $settings['sources'] ) ? $settings['sources'] : array();
		global $wpdb;
		$manual_table = $this->db->get_table_name( 'manual_sources' );
		$manual_total = 0;
		if ( $wpdb instanceof wpdb ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$manual_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$manual_table}" );
		}
		$allowed_cf = isset( $src['allowed_custom_field_keys'] ) ? array_filter( (array) $src['allowed_custom_field_keys'] ) : array();

		return array(
			'sources_by_access_visibility'       => $this->source_repository->get_access_visibility_counts(),
			'manual_sources_total'               => $manual_total,
			'allow_public_query_endpoint'        => ! empty( $settings['chat']['allow_public_query_endpoint'] ),
			'include_private_content'            => ! empty( $src['include_private_content'] ),
			'include_drafts'                     => ! empty( $src['include_drafts'] ),
			'include_password_protected_content' => ! empty( $src['include_password_protected_content'] ),
			'include_custom_fields'              => ! empty( $src['include_custom_fields'] ),
			'allowed_custom_field_keys_count'    => count( $allowed_cf ),
			'include_rendered_url_rules'         => ! empty( $src['include_rendered_url_rules'] ),
		);
	}

	public function get_report() {
		$tables = $this->db->get_table_status();
		$source_counts = $this->source_repository->get_source_counts();
		$sources_settings = $this->settings->get_all();
		return array(
			'plugin_version'  => JSDW_AI_CHAT_VERSION,
			'schema_version'  => get_option( JSDW_AI_CHAT_OPTION_DB_SCHEMA_VERSION, '0.0.0' ),
			'tables'          => $tables,
			'cron'            => $this->cron->get_status(),
			'queue'           => $this->queue->get_status(),
			'discovery'       => array(
				'enabled'                   => ! empty( $sources_settings['sources']['enabled_source_types'] ),
				'source_counts'             => $source_counts,
				'pending_reindex'           => $this->source_repository->get_pending_reindex_count(),
				'last_discovery_scan_time'  => get_option( JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_SCAN, '' ),
				'last_discovery_scan_result'=> get_option( JSDW_AI_CHAT_OPTION_LAST_DISCOVERY_RESULT, array() ),
				'queue_counts'              => $this->queue->normalize_queue_count_rows( $this->queue->get_discovery_queue_counts() ),
			),
			'content_state'   => array(
				'status_counts'                 => $this->source_repository->get_content_processing_status_counts(),
				'material_content_change_counts'=> $this->source_repository->get_material_content_change_counts(),
				'last_content_verification_run' => get_option( JSDW_AI_CHAT_OPTION_LAST_CONTENT_VERIFICATION, '' ),
				'queue_counts'                  => $this->queue->normalize_queue_count_rows( $this->queue->get_content_queue_counts() ),
			),
			'knowledge_state' => array(
				'knowledge_status_counts'        => $this->source_repository->get_knowledge_processing_status_counts(),
				'last_knowledge_verification_run'=> get_option( JSDW_AI_CHAT_OPTION_LAST_KNOWLEDGE_VERIFICATION, '' ),
				'queue_counts'                   => $this->queue->normalize_queue_count_rows( $this->queue->get_knowledge_queue_counts() ),
				'sources_with_active_chunks'     => $this->chunk_repository->count_sources_with_active_chunks(),
				'sources_with_active_facts'      => $this->fact_repository->count_sources_with_active_facts(),
				'chunk_status_counts'            => $this->chunk_repository->get_chunk_status_counts(),
			),
			'answer_state'    => $this->get_answer_state(),
			'rest'            => array(
				'namespace' => 'ai-chat-widget/v1',
				'ready'     => true,
			),
			'last_error'      => get_option( JSDW_AI_CHAT_OPTION_LAST_ERROR, array() ),
			'last_cron_run'   => get_option( JSDW_AI_CHAT_OPTION_LAST_CRON_RUN, '' ),
			'security'        => $this->get_security_posture(),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_answer_state() {
		global $wpdb;
		$conversations_table = $this->db->get_table_name( 'conversations' );
		$messages_table      = $this->db->get_table_name( 'messages' );
		$conversations_count = 0;
		$messages_count      = 0;
		if ( $wpdb instanceof wpdb ) {
			$conversations_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conversations_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			$messages_count      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$messages_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		}
		$settings = $this->settings->get_all();
		$ai_provider = '';
		if ( isset( $settings['ai'] ) && is_array( $settings['ai'] ) && isset( $settings['ai']['provider'] ) ) {
			$ai_provider = JSDW_AI_Chat_AI_Provider_Status::sanitize_provider( (string) $settings['ai']['provider'] );
		}
		$ai_status_summary = JSDW_AI_Chat_AI_Provider_Status::summarize( $settings );

		$widget_public_note = '';
		$feat               = isset( $settings['features'] ) && is_array( $settings['features'] ) ? $settings['features'] : array();
		$wu                 = isset( $settings['widget_ui'] ) && is_array( $settings['widget_ui'] ) ? $settings['widget_ui'] : array();
		$chat               = isset( $settings['chat'] ) && is_array( $settings['chat'] ) ? $settings['chat'] : array();
		if ( ! empty( $feat['enable_widget'] ) && ! empty( $wu['widget_enabled'] ) && empty( $chat['allow_public_query_endpoint'] ) ) {
			$widget_public_note = __( 'The widget is enabled, but unauthenticated visitors cannot query the chat endpoint until you enable “Allow unauthenticated visitors to call the chat query endpoint” in Settings → Chat.', 'jsdw-ai-chat' );
		}

		return array(
			'conversations'              => $conversations_count,
			'messages'                   => $messages_count,
			'allow_public_query_endpoint'=> ! empty( $settings['chat']['allow_public_query_endpoint'] ),
			'allow_ai_phrase_assist'     => ! empty( $settings['chat']['allow_ai_phrase_assist'] ),
			'answer_mode'                => (string) ( $settings['chat']['answer_mode'] ?? 'strict_local_only' ),
			'enable_ai'                  => ! empty( $settings['features']['enable_ai'] ),
			'ai_provider'                => $ai_provider,
			'ai_status_summary'          => $ai_status_summary,
			'last_answer_request'        => get_option( JSDW_AI_CHAT_OPTION_LAST_ANSWER_REQUEST, '' ),
			'widget_public_note'         => $widget_public_note,
		);
	}
}
