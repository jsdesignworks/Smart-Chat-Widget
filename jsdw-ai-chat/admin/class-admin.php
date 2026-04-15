<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Admin {
	/**
	 * @var JSDW_AI_Chat_Health
	 */
	private $health;

	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	/**
	 * @var JSDW_AI_Chat_Logger
	 */
	private $logger;

	/**
	 * @var JSDW_AI_Chat_Source_Repository
	 */
	private $source_repository;

	/**
	 * @var JSDW_AI_Chat_Source_Registry
	 */
	private $source_registry;

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

	/**
	 * @var JSDW_AI_Chat_Queue
	 */
	private $queue;

	/**
	 * @var JSDW_AI_Chat_Cron
	 */
	private $cron;

	public function __construct( JSDW_AI_Chat_Health $health, JSDW_AI_Chat_Settings $settings, JSDW_AI_Chat_Logger $logger, JSDW_AI_Chat_Source_Repository $source_repository, JSDW_AI_Chat_Source_Registry $source_registry, JSDW_AI_Chat_Chunk_Repository $chunk_repository, JSDW_AI_Chat_Fact_Repository $fact_repository, JSDW_AI_Chat_Conversation_Service $conversation_service, JSDW_AI_Chat_Queue $queue, JSDW_AI_Chat_Cron $cron ) {
		$this->health   = $health;
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->source_repository = $source_repository;
		$this->source_registry = $source_registry;
		$this->chunk_repository  = $chunk_repository;
		$this->fact_repository   = $fact_repository;
		$this->conversation_service = $conversation_service;
		$this->queue  = $queue;
		$this->cron   = $cron;
	}

	public function register_menu() {
		add_menu_page(
			__( 'AI Chat Widget', 'jsdw-ai-chat' ),
			__( 'AI Chat Widget', 'jsdw-ai-chat' ),
			'manage_ai_chat_widget',
			'jsdw-ai-chat-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-format-chat'
		);

		add_submenu_page(
			'jsdw-ai-chat-dashboard',
			__( 'Settings', 'jsdw-ai-chat' ),
			__( 'Settings', 'jsdw-ai-chat' ),
			'manage_ai_chat_widget_settings',
			'jsdw-ai-chat-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'jsdw-ai-chat-dashboard',
			__( 'Design Studio', 'jsdw-ai-chat' ),
			__( 'Design Studio', 'jsdw-ai-chat' ),
			'manage_ai_chat_widget_settings',
			'jsdw-ai-chat-design-studio',
			array( $this, 'render_design_studio_page' )
		);

		add_submenu_page(
			'jsdw-ai-chat-dashboard',
			__( 'Jobs & Logs', 'jsdw-ai-chat' ),
			__( 'Jobs & Logs', 'jsdw-ai-chat' ),
			'manage_ai_chat_widget_logs',
			'jsdw-ai-chat-jobs',
			array( $this, 'render_jobs_page' )
		);

		add_submenu_page(
			'jsdw-ai-chat-dashboard',
			__( 'Sources', 'jsdw-ai-chat' ),
			__( 'Sources', 'jsdw-ai-chat' ),
			'manage_ai_chat_widget_index',
			'jsdw-ai-chat-sources',
			array( $this, 'render_sources_page' )
		);

		add_submenu_page(
			'jsdw-ai-chat-dashboard',
			__( 'System Info', 'jsdw-ai-chat' ),
			__( 'System Info', 'jsdw-ai-chat' ),
			'manage_ai_chat_widget',
			'jsdw-ai-chat-system-info',
			array( $this, 'render_system_info_page' )
		);

		add_submenu_page(
			'jsdw-ai-chat-dashboard',
			__( 'Conversations', 'jsdw-ai-chat' ),
			__( 'Conversations', 'jsdw-ai-chat' ),
			'manage_ai_chat_widget_conversations',
			'jsdw-ai-chat-conversations',
			array( $this, 'render_conversations_page' )
		);

		add_action( 'admin_menu', array( $this, 'badge_conversations_submenu_unread' ), 999 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_foundation_assets' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_sources_assets' ), 12 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_conversations_inbox_assets' ), 16 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_design_studio_assets' ), 10 );
		add_filter( 'admin_body_class', array( $this, 'admin_plugin_body_class' ) );
	}

	/**
	 * Whether the current admin screen belongs to this plugin.
	 *
	 * @return bool
	 */
	private function is_plugin_admin_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && strpos( $screen->id, 'jsdw-ai-chat' ) !== false;
	}

	/**
	 * Sanitized admin UI mode slug for body class (user_meta is source of truth).
	 *
	 * @return string dark-violet|warm-clay
	 */
	private function get_admin_ui_mode_slug() {
		if ( ! defined( 'JSDW_AI_CHAT_USER_META_ADMIN_UI_MODE' ) ) {
			return 'dark-violet';
		}
		$raw = get_user_meta( get_current_user_id(), JSDW_AI_CHAT_USER_META_ADMIN_UI_MODE, true );
		$raw = is_string( $raw ) ? $raw : '';
		return 'warm-clay' === $raw ? 'warm-clay' : 'dark-violet';
	}

	/**
	 * Body classes: plugin admin shell + dual-mode token scope + Design Studio chrome flag.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function admin_plugin_body_class( $classes ) {
		if ( ! $this->is_plugin_admin_screen() ) {
			return $classes;
		}
		$mode = $this->get_admin_ui_mode_slug();
		$classes = trim( $classes . ' jsdw-ai-chat-admin jsdw-ai-chat-admin--' . $mode );

		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'jsdw-ai-chat-design-studio' ) !== false ) {
			$classes = trim( $classes . ' jsdw-ai-chat-design-studio-admin' );
		}
		if ( $screen && strpos( $screen->id, 'jsdw-ai-chat-conversations' ) !== false ) {
			$classes = trim( $classes . ' jsdw-ai-chat-admin--conversations' );
		}

		return $classes;
	}

	/**
	 * Grouped sidebar nav for the Phase 7.2 app shell (presentation only).
	 *
	 * @return array<int, array{label: string, items: array<int, array{slug: string, label: string, url: string, cap: string}>}>
	 */
	private function get_admin_shell_nav_groups() {
		return array(
			array(
				'label' => __( 'Overview', 'jsdw-ai-chat' ),
				'items' => array(
					array(
						'slug'  => 'jsdw-ai-chat-dashboard',
						'label' => __( 'Dashboard', 'jsdw-ai-chat' ),
						'url'   => admin_url( 'admin.php?page=jsdw-ai-chat-dashboard' ),
						'cap'   => 'manage_ai_chat_widget',
					),
				),
			),
			array(
				'label' => __( 'Configuration', 'jsdw-ai-chat' ),
				'items' => array(
					array(
						'slug'  => 'jsdw-ai-chat-settings',
						'label' => __( 'Settings', 'jsdw-ai-chat' ),
						'url'   => admin_url( 'admin.php?page=jsdw-ai-chat-settings' ),
						'cap'   => 'manage_ai_chat_widget_settings',
					),
					array(
						'slug'  => 'jsdw-ai-chat-design-studio',
						'label' => __( 'Design Studio', 'jsdw-ai-chat' ),
						'url'   => admin_url( 'admin.php?page=jsdw-ai-chat-design-studio' ),
						'cap'   => 'manage_ai_chat_widget_settings',
					),
				),
			),
			array(
				'label' => __( 'Data', 'jsdw-ai-chat' ),
				'items' => array(
					array(
						'slug'  => 'jsdw-ai-chat-sources',
						'label' => __( 'Sources', 'jsdw-ai-chat' ),
						'url'   => admin_url( 'admin.php?page=jsdw-ai-chat-sources' ),
						'cap'   => 'manage_ai_chat_widget_index',
					),
					array(
						'slug'  => 'jsdw-ai-chat-conversations',
						'label' => __( 'Conversations', 'jsdw-ai-chat' ),
						'url'   => admin_url( 'admin.php?page=jsdw-ai-chat-conversations' ),
						'cap'   => 'manage_ai_chat_widget_conversations',
					),
				),
			),
			array(
				'label' => __( 'Operations', 'jsdw-ai-chat' ),
				'items' => array(
					array(
						'slug'  => 'jsdw-ai-chat-jobs',
						'label' => __( 'Jobs & Logs', 'jsdw-ai-chat' ),
						'url'   => admin_url( 'admin.php?page=jsdw-ai-chat-jobs' ),
						'cap'   => 'manage_ai_chat_widget_logs',
					),
				),
			),
			array(
				'label' => __( 'System', 'jsdw-ai-chat' ),
				'items' => array(
					array(
						'slug'  => 'jsdw-ai-chat-system-info',
						'label' => __( 'System Info', 'jsdw-ai-chat' ),
						'url'   => admin_url( 'admin.php?page=jsdw-ai-chat-system-info' ),
						'cap'   => 'manage_ai_chat_widget',
					),
				),
			),
		);
	}

	/**
	 * Current plugin admin screen slug for shell nav active state (GET `page`, else parse screen id).
	 *
	 * @return string e.g. jsdw-ai-chat-settings, or empty if unknown.
	 */
	private function get_admin_shell_current_page_slug() {
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$p = sanitize_text_field( wp_unslash( (string) $_GET['page'] ) );
			if ( '' !== $p && strpos( $p, 'jsdw-ai-chat' ) === 0 ) {
				return $p;
			}
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return '';
		}
		$screen = get_current_screen();
		if ( ! $screen || ! is_string( $screen->id ) ) {
			return '';
		}
		$id = $screen->id;
		if ( preg_match( '/^toplevel_page_(jsdw-ai-chat-[a-z0-9-]+)/', $id, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/_page_(jsdw-ai-chat-[a-z0-9-]+)/', $id, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Phase 7.2: wrap inner admin view in the app shell layout.
	 *
	 * @param string                $inner_view_path Absolute filesystem path to admin/views partial.
	 * @param array<string, mixed> $vars            Optional variables extracted into scope for the inner view (shell $jsdw_* keys are not overwritten).
	 */
	private function render_admin_shell( $inner_view_path, array $vars = array() ) {
		$jsdw_admin_inner_view   = $inner_view_path;
		$jsdw_shell_nav_groups   = $this->get_admin_shell_nav_groups();
		$jsdw_shell_current_page = $this->get_admin_shell_current_page_slug();
		$jsdw_shell_ui_mode      = $this->get_admin_ui_mode_slug();
		extract( $vars, EXTR_SKIP );
		include JSDW_AI_CHAT_PATH . 'admin/views/layout-admin-shell.php';
	}

	/**
	 * Phase 7.1: design tokens + foundation for all plugin admin screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_admin_foundation_assets( $hook_suffix ) {
		if ( strpos( (string) $hook_suffix, 'jsdw-ai-chat' ) === false ) {
			return;
		}

		$tokens_path = JSDW_AI_CHAT_PATH . 'admin/css/tokens-admin.css';
		$base_path   = JSDW_AI_CHAT_PATH . 'admin/css/foundation-admin.css';
		$ver         = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $tokens_path ) ) {
			$ver .= '.' . (string) filemtime( $tokens_path );
		}

		wp_enqueue_style(
			'jsdw-ai-chat-admin-tokens',
			JSDW_AI_CHAT_URL . 'admin/css/tokens-admin.css',
			array(),
			$ver
		);

		$base_ver = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $base_path ) ) {
			$base_ver .= '.' . (string) filemtime( $base_path );
		}

		wp_enqueue_style(
			'jsdw-ai-chat-admin-foundation',
			JSDW_AI_CHAT_URL . 'admin/css/foundation-admin.css',
			array( 'jsdw-ai-chat-admin-tokens' ),
			$base_ver
		);

		$shell_path = JSDW_AI_CHAT_PATH . 'admin/css/admin-shell.css';
		$shell_ver  = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $shell_path ) ) {
			$shell_ver .= '.' . (string) filemtime( $shell_path );
		}
		wp_enqueue_style(
			'jsdw-ai-chat-admin-shell',
			JSDW_AI_CHAT_URL . 'admin/css/admin-shell.css',
			array( 'jsdw-ai-chat-admin-foundation' ),
			$shell_ver
		);
	}

	/**
	 * Phase 7.3B: dashboard layout CSS (dashboard screen only).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	/**
	 * Sources registry screen: responsive table + management layout.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_sources_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'jsdw-ai-chat-sources' ) ) {
			return;
		}

		$path = JSDW_AI_CHAT_PATH . 'admin/css/sources-admin.css';
		$ver  = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $path ) ) {
			$ver .= '.' . (string) filemtime( $path );
		}

		wp_enqueue_style(
			'jsdw-ai-chat-sources',
			JSDW_AI_CHAT_URL . 'admin/css/sources-admin.css',
			array( 'jsdw-ai-chat-admin-shell' ),
			$ver
		);
	}

	/**
	 * Conversations inbox + thread UI.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_conversations_inbox_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'jsdw-ai-chat-conversations' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_ai_chat_widget_conversations' ) ) {
			return;
		}

		$css_path = JSDW_AI_CHAT_PATH . 'admin/css/conversations-inbox.css';
		$css_ver  = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $css_path ) ) {
			$css_ver .= '.' . (string) filemtime( $css_path );
		}
		wp_enqueue_style(
			'jsdw-ai-chat-conversations-inbox',
			JSDW_AI_CHAT_URL . 'admin/css/conversations-inbox.css',
			array( 'jsdw-ai-chat-admin-shell' ),
			$css_ver
		);

		$js_path = JSDW_AI_CHAT_PATH . 'admin/js/conversations-inbox.js';
		$js_ver  = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $js_path ) ) {
			$js_ver .= '.' . (string) filemtime( $js_path );
		}
		wp_enqueue_script(
			'jsdw-ai-chat-conversations-inbox',
			JSDW_AI_CHAT_URL . 'admin/js/conversations-inbox.js',
			array(),
			$js_ver,
			true
		);
		wp_localize_script(
			'jsdw-ai-chat-conversations-inbox',
			'JSDW_AI_CHAT_CONV',
			array(
				'restAgentJoin'    => esc_url_raw( rest_url( 'ai-chat-widget/v1/chat/agent-join' ) ),
				'restAgentReply'   => esc_url_raw( rest_url( 'ai-chat-widget/v1/chat/agent-reply' ) ),
				'restAgentRelease' => esc_url_raw( rest_url( 'ai-chat-widget/v1/chat/agent-release' ) ),
				'restInboxSummary' => esc_url_raw( rest_url( 'ai-chat-widget/v1/chat/inbox-summary' ) ),
				'restMarkReadTpl'  => esc_url_raw( rest_url( 'ai-chat-widget/v1/chat/conversations/CONV_ID/mark-read' ) ),
				'restConversationMessagesTpl' => esc_url_raw( rest_url( 'ai-chat-widget/v1/chat/conversations/CONV_ID/messages' ) ),
				'pollIntervalMs'   => 25000,
				'threadPollIntervalMs' => 3500,
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'strings'          => array(
					'sendError'    => __( 'Could not send message.', 'jsdw-ai-chat' ),
					'joinError'    => __( 'Could not join this conversation.', 'jsdw-ai-chat' ),
					'releaseError' => __( 'Could not end agent session.', 'jsdw-ai-chat' ),
					'sending'      => __( 'Sending…', 'jsdw-ai-chat' ),
					'joining'      => __( 'Joining…', 'jsdw-ai-chat' ),
					'pageTitle'    => __( 'Conversations', 'jsdw-ai-chat' ),
					'inboxHead'    => __( 'Inbox', 'jsdw-ai-chat' ),
					'liveShort'    => __( 'Live', 'jsdw-ai-chat' ),
					'unreadLabel'  => __( 'Unread visitor messages', 'jsdw-ai-chat' ),
					'visitor'      => __( 'Visitor', 'jsdw-ai-chat' ),
					'agent'        => __( 'Agent', 'jsdw-ai-chat' ),
					'assistant'    => __( 'Assistant', 'jsdw-ai-chat' ),
					'message'      => __( 'Message', 'jsdw-ai-chat' ),
					'answerStatus' => __( 'Answer status:', 'jsdw-ai-chat' ),
					'confidence'   => __( 'Confidence:', 'jsdw-ai-chat' ),
				),
			)
		);
	}

	public function enqueue_dashboard_assets( $hook_suffix ) {
		if ( 'toplevel_page_jsdw-ai-chat-dashboard' !== $hook_suffix ) {
			return;
		}

		$dash_path = JSDW_AI_CHAT_PATH . 'admin/css/dashboard-admin.css';
		$dash_ver  = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $dash_path ) ) {
			$dash_ver .= '.' . (string) filemtime( $dash_path );
		}

		wp_enqueue_style(
			'jsdw-ai-chat-dashboard',
			JSDW_AI_CHAT_URL . 'admin/css/dashboard-admin.css',
			array( 'jsdw-ai-chat-admin-shell' ),
			$dash_ver
		);
	}

	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_ai_chat_widget' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'jsdw-ai-chat' ) );
		}
		$health_report = $this->health->get_report();
		$this->render_admin_shell(
			JSDW_AI_CHAT_PATH . 'admin/views/page-dashboard.php',
			array(
				'health_report' => $health_report,
			)
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_ai_chat_widget_settings' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'jsdw-ai-chat' ) );
		}

		$nonce_action = 'jsdw_ai_chat_settings_action';
		$nonce_name   = 'jsdw_ai_chat_settings_nonce';

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['jsdw_ai_chat_settings_save'], $_POST[ $nonce_name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$valid = JSDW_AI_Chat_Security::verify_nonce( (string) $_POST[ $nonce_name ], $nonce_action ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! $valid ) {
				$this->logger->warning( 'settings_invalid_nonce', 'Invalid nonce on settings save.' );
				add_settings_error(
					'jsdw_ai_chat',
					'jsdw_settings_save',
					__( 'Invalid nonce.', 'jsdw-ai-chat' ),
					'error'
				);
			} else {
				$post = isset( $_POST['jsdw'] ) && is_array( $_POST['jsdw'] ) ? wp_unslash( $_POST['jsdw'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$merged = $this->merge_settings_from_request( $this->settings->get_all(), $post );
				$clean  = $this->settings->sanitize_settings( $merged );
				update_option( JSDW_AI_CHAT_OPTION_SETTINGS, $clean, false );
				add_settings_error(
					'jsdw_ai_chat',
					'jsdw_settings_save',
					__( 'Settings saved.', 'jsdw-ai-chat' ),
					'success'
				);
			}
		}

		$settings = $this->settings->get_all();
		$this->render_admin_shell(
			JSDW_AI_CHAT_PATH . 'admin/views/page-settings.php',
			array(
				'nonce_action' => $nonce_action,
				'nonce_name'   => $nonce_name,
				'settings'     => $settings,
			)
		);
	}

	/**
	 * Merge posted subset into current settings (existing keys only). Sanitization happens in sanitize_settings().
	 *
	 * @param array<string,mixed> $current Current settings.
	 * @param array<string,mixed> $post    $_POST['jsdw'].
	 * @return array<string,mixed>
	 */
	private function merge_settings_from_request( array $current, array $post ) {
		// Unchecked HTML checkboxes are omitted from POST; treat missing keys as false.
		$g = isset( $post['general'] ) && is_array( $post['general'] ) ? $post['general'] : array();
		$current['general']['enabled'] = ! empty( $g['enabled'] );

		$f = isset( $post['features'] ) && is_array( $post['features'] ) ? $post['features'] : array();
		$feature_keys                  = array( 'enable_rest', 'enable_cron', 'enable_queue', 'enable_chat_storage', 'enable_indexing', 'enable_ai', 'enable_widget', 'cleanup_on_uninstall' );
		foreach ( $feature_keys as $k ) {
			$current['features'][ $k ] = ! empty( $f[ $k ] );
		}

		if ( isset( $post['chat'] ) && is_array( $post['chat'] ) ) {
			$c = $post['chat'];
			if ( isset( $c['answer_mode'] ) ) {
				$current['chat']['answer_mode'] = sanitize_text_field( (string) $c['answer_mode'] );
			}
			$current['chat']['allow_public_query_endpoint'] = ! empty( $c['allow_public_query_endpoint'] );
			$current['chat']['allow_ai_phrase_assist']      = ! empty( $c['allow_ai_phrase_assist'] );
			$current['chat']['debug_trace_enabled']         = ! empty( $c['debug_trace_enabled'] );
			$current['chat']['clarification_enabled']       = ! empty( $c['clarification_enabled'] );
			$current['chat']['store_trace_snapshots']       = ! empty( $c['store_trace_snapshots'] );
			if ( isset( $c['min_query_length'] ) ) {
				$current['chat']['min_query_length'] = absint( $c['min_query_length'] );
			}
			if ( isset( $c['max_query_length'] ) ) {
				$current['chat']['max_query_length'] = absint( $c['max_query_length'] );
			}
			if ( isset( $c['answer_style'] ) ) {
				$current['chat']['answer_style'] = sanitize_text_field( (string) $c['answer_style'] );
			}
			if ( isset( $c['canned_responses'] ) && is_array( $c['canned_responses'] ) ) {
				$current['chat']['canned_responses'] = $c['canned_responses'];
			}
		}

		if ( isset( $post['widget_design'] ) && is_array( $post['widget_design'] ) && isset( $post['widget_design']['quickReplies'] ) && is_array( $post['widget_design']['quickReplies'] ) ) {
			if ( ! isset( $current['widget_design'] ) || ! is_array( $current['widget_design'] ) ) {
				$current['widget_design'] = $this->settings->get_default_widget_design();
			}
			$qr = array();
			foreach ( array( 0, 1, 2 ) as $i ) {
				$qr[] = isset( $post['widget_design']['quickReplies'][ $i ] ) ? $post['widget_design']['quickReplies'][ $i ] : '';
			}
			$current['widget_design']['quickReplies'] = $qr;
		}

		$p = isset( $post['privacy'] ) && is_array( $post['privacy'] ) ? $post['privacy'] : array();
		$current['privacy']['store_conversations'] = ! empty( $p['store_conversations'] );

		if ( isset( $post['logging'] ) && is_array( $post['logging'] ) ) {
			$current['logging']['enabled']           = ! empty( $post['logging']['enabled'] );
			$current['logging']['mirror_wp_debug']   = ! empty( $post['logging']['mirror_wp_debug'] );
			if ( isset( $post['logging']['minimum_log_level'] ) ) {
				$current['logging']['minimum_log_level'] = sanitize_text_field( (string) $post['logging']['minimum_log_level'] );
			}
		}

		$ix = isset( $post['indexing'] ) && is_array( $post['indexing'] ) ? $post['indexing'] : array();
		$current['indexing']['auto_reindex'] = ! empty( $ix['auto_reindex'] );

		if ( isset( $post['ai'] ) && is_array( $post['ai'] ) ) {
			if ( array_key_exists( 'provider', $post['ai'] ) ) {
				$current['ai']['provider'] = JSDW_AI_Chat_AI_Provider_Status::sanitize_provider( (string) $post['ai']['provider'] );
			}
			if ( ! empty( $post['ai']['openai_api_key_clear'] ) ) {
				$current['ai']['openai_api_key'] = '';
			} elseif ( isset( $post['ai']['openai_api_key_new'] ) && '' !== trim( (string) $post['ai']['openai_api_key_new'] ) ) {
				$current['ai']['openai_api_key'] = trim( (string) $post['ai']['openai_api_key_new'] );
			}
			if ( isset( $post['ai']['openai_model'] ) ) {
				$current['ai']['openai_model'] = sanitize_text_field( (string) $post['ai']['openai_model'] );
			}
		}

		if ( isset( $post['widget_ui'] ) && is_array( $post['widget_ui'] ) ) {
			$wu = $post['widget_ui'];
			$wui_bools = array( 'widget_enabled', 'show_sources', 'allow_reset_conversation', 'admin_debug_ui', 'auto_footer' );
			foreach ( $wui_bools as $wk ) {
				$current['widget_ui'][ $wk ] = ! empty( $wu[ $wk ] );
			}
			if ( array_key_exists( 'widget_position', $wu ) ) {
				$current['widget_ui']['widget_position'] = sanitize_text_field( (string) $wu['widget_position'] );
			}
			if ( array_key_exists( 'launcher_label', $wu ) ) {
				$current['widget_ui']['launcher_label'] = sanitize_text_field( (string) $wu['launcher_label'] );
			}
			if ( array_key_exists( 'welcome_message', $wu ) ) {
				$current['widget_ui']['welcome_message'] = sanitize_textarea_field( (string) $wu['welcome_message'] );
			}
			if ( array_key_exists( 'placeholder_text', $wu ) ) {
				$current['widget_ui']['placeholder_text'] = sanitize_text_field( (string) $wu['placeholder_text'] );
			}
		}

		return $current;
	}

	public function render_jobs_page() {
		if ( ! current_user_can( 'manage_ai_chat_widget_logs' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'jsdw-ai-chat' ) );
		}
		$health_report = $this->health->get_report();
		$this->render_admin_shell(
			JSDW_AI_CHAT_PATH . 'admin/views/page-jobs.php',
			array(
				'health_report' => $health_report,
			)
		);
	}

	public function render_sources_page() {
		if ( ! current_user_can( 'manage_ai_chat_widget_index' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'jsdw-ai-chat' ) );
		}

		$nonce_action = 'jsdw_ai_chat_sources_action';
		$nonce_name   = 'jsdw_ai_chat_sources_nonce';
		$notice       = '';

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST[ $nonce_name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$valid = JSDW_AI_Chat_Security::verify_nonce( (string) $_POST[ $nonce_name ], $nonce_action ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! $valid ) {
				$notice = __( 'Invalid nonce.', 'jsdw-ai-chat' );
			} elseif ( isset( $_POST['jsdw_rescan_sources'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$this->source_registry->queue_full_scan();
				$notice = __( 'Full source rescan queued.', 'jsdw-ai-chat' );
			} elseif ( isset( $_POST['jsdw_process_pending_content'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$pending = $this->source_repository->fetch_sources_pending_content_processing( 20, 0 );
				$n       = 0;
				foreach ( $pending as $prow ) {
					$sid = isset( $prow['id'] ) ? absint( $prow['id'] ) : 0;
					if ( $sid <= 0 ) {
						continue;
					}
					$jid = $this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS, array( 'source_id' => $sid ), 14 );
					if ( $jid > 0 ) {
						$n++;
						$this->logger->info( 'content_queued', 'Content process job queued from Sources admin.', array( 'source_id' => $sid, 'job_id' => $jid ) );
					}
				}
				$notice = sprintf(
					/* translators: %d: number of jobs queued */
					_n( 'Queued %d content processing job.', 'Queued %d content processing jobs.', $n, 'jsdw-ai-chat' ),
					$n
				);
			} elseif ( isset( $_POST['jsdw_process_pending_knowledge'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$pending = $this->source_repository->fetch_sources_pending_knowledge_processing( 20, 0 );
				$n       = 0;
				foreach ( $pending as $prow ) {
					$sid = isset( $prow['id'] ) ? absint( $prow['id'] ) : 0;
					if ( $sid <= 0 ) {
						continue;
					}
					$jid = $this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS, array( 'source_id' => $sid ), 14 );
					if ( $jid > 0 ) {
						$n++;
						$this->logger->info( 'knowledge_processing_queued', 'Knowledge job queued from Sources admin.', array( 'source_id' => $sid, 'job_id' => $jid ) );
					}
				}
				$notice = sprintf(
					/* translators: %d: number of jobs queued */
					_n( 'Queued %d knowledge processing job.', 'Queued %d knowledge processing jobs.', $n, 'jsdw-ai-chat' ),
					$n
				);
			} elseif ( isset( $_POST['jsdw_run_queue_now'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$this->cron->handle_queue_runner();
				if ( function_exists( 'spawn_cron' ) ) {
					spawn_cron();
				}
				$notice = __( 'Queue runner executed once. Background cron was nudged if available.', 'jsdw-ai-chat' );
			} elseif ( isset( $_POST['jsdw_queue_content_one'] ) && isset( $_POST['jsdw_source_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$sid = absint( wp_unslash( (string) $_POST['jsdw_source_id'] ) );
				if ( $sid > 0 ) {
					$jid = $this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_CONTENT_PROCESS, array( 'source_id' => $sid ), 14 );
					if ( $jid > 0 ) {
						$this->logger->info( 'content_queued', 'Content process job queued from Sources admin (single).', array( 'source_id' => $sid, 'job_id' => $jid ) );
						$notice = __( 'Content processing queued for this source.', 'jsdw-ai-chat' );
					}
				}
			} elseif ( isset( $_POST['jsdw_queue_knowledge_one'] ) && isset( $_POST['jsdw_source_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$sid = absint( wp_unslash( (string) $_POST['jsdw_source_id'] ) );
				if ( $sid > 0 ) {
					$jid = $this->queue->add_job( JSDW_AI_Chat_Job_Repository::TYPE_SOURCE_KNOWLEDGE_PROCESS, array( 'source_id' => $sid ), 14 );
					if ( $jid > 0 ) {
						$this->logger->info( 'knowledge_processing_queued', 'Knowledge job queued from Sources admin (single).', array( 'source_id' => $sid, 'job_id' => $jid ) );
						$notice = __( 'Knowledge processing queued for this source.', 'jsdw-ai-chat' );
					}
				}
			}
		}

		$filter_type = isset( $_GET['source_type'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['source_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_status = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['filter_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $filter_status && isset( $_GET['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filter_status = sanitize_key( wp_unslash( (string) $_GET['status'] ) );
		}
		$filter_content_status = isset( $_GET['filter_content'] ) ? sanitize_key( wp_unslash( (string) $_GET['filter_content'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_knowledge_status = isset( $_GET['filter_knowledge'] ) ? sanitize_key( wp_unslash( (string) $_GET['filter_knowledge'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_failed_any = ! empty( $_GET['filter_failed'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_needs_reindex = isset( $_GET['filter_reindex'] ) ? sanitize_key( wp_unslash( (string) $_GET['filter_reindex'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$filters = array();
		if ( '' !== $filter_type ) {
			$filters['source_type'] = $filter_type;
		}
		if ( '' !== $filter_status ) {
			$filters['status'] = $filter_status;
		}
		if ( '' !== $filter_content_status ) {
			$filters['content_processing_status'] = $filter_content_status;
		}
		if ( '' !== $filter_knowledge_status ) {
			$filters['knowledge_processing_status'] = $filter_knowledge_status;
		}
		if ( $filter_failed_any ) {
			$filters['failed_any'] = true;
		}
		if ( '1' === $filter_needs_reindex || '0' === $filter_needs_reindex ) {
			$filters['needs_reindex'] = ( '1' === $filter_needs_reindex );
		}

		$sources = $this->source_repository->list_sources( $filters, 100, 0 );
		$counts  = $this->source_repository->get_source_counts();
		$content_status_counts = $this->source_repository->get_content_processing_status_counts();
		$content_material_counts = $this->source_repository->get_material_content_change_counts();
		$knowledge_status_counts = $this->source_repository->get_knowledge_processing_status_counts();
		$knowledge_row_counts = array();
		foreach ( $sources as $srow ) {
			$sid = isset( $srow['id'] ) ? absint( $srow['id'] ) : 0;
			if ( $sid <= 0 ) {
				continue;
			}
			$knowledge_row_counts[ $sid ] = array(
				'chunks' => $this->chunk_repository->count_active_chunks_by_source( $sid ),
				'facts'  => $this->fact_repository->count_active_facts_by_source( $sid ),
			);
		}

		$health_report = $this->health->get_report();
		$disc          = isset( $health_report['discovery'] ) && is_array( $health_report['discovery'] ) ? $health_report['discovery'] : array();
		$source_rows   = isset( $disc['source_counts'] ) && is_array( $disc['source_counts'] ) ? $disc['source_counts'] : array();
		$lifecycle_totals = array();
		$total_sources    = 0;
		foreach ( $source_rows as $srow ) {
			if ( ! is_array( $srow ) ) {
				continue;
			}
			$st = isset( $srow['status'] ) ? (string) $srow['status'] : '';
			$c  = isset( $srow['count_total'] ) ? absint( $srow['count_total'] ) : 0;
			$total_sources += $c;
			if ( '' !== $st ) {
				$lifecycle_totals[ $st ] = ( isset( $lifecycle_totals[ $st ] ) ? $lifecycle_totals[ $st ] : 0 ) + $c;
			}
		}
		$pending_reindex = isset( $disc['pending_reindex'] ) ? absint( $disc['pending_reindex'] ) : 0;
		$visibility_counts = $this->source_repository->get_access_visibility_counts();

		$this->render_admin_shell(
			JSDW_AI_CHAT_PATH . 'admin/views/page-sources.php',
			array(
				'nonce_action'              => $nonce_action,
				'nonce_name'                => $nonce_name,
				'notice'                    => $notice,
				'filter_type'               => $filter_type,
				'filter_status'             => $filter_status,
				'filter_content_status'     => $filter_content_status,
				'filter_knowledge_status'   => $filter_knowledge_status,
				'filter_failed_any'         => $filter_failed_any,
				'filter_needs_reindex'      => $filter_needs_reindex,
				'sources'                   => $sources,
				'counts'                    => $counts,
				'content_status_counts'     => $content_status_counts,
				'content_material_counts'   => $content_material_counts,
				'knowledge_status_counts'   => $knowledge_status_counts,
				'knowledge_row_counts'      => $knowledge_row_counts,
				'health_report'             => $health_report,
				'lifecycle_totals'          => $lifecycle_totals,
				'total_sources'             => $total_sources,
				'pending_reindex'           => $pending_reindex,
				'visibility_counts'         => $visibility_counts,
			)
		);
	}

	public function render_system_info_page() {
		if ( ! current_user_can( 'manage_ai_chat_widget' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'jsdw-ai-chat' ) );
		}
		$health_report = $this->health->get_report();
		$this->render_admin_shell(
			JSDW_AI_CHAT_PATH . 'admin/views/page-system-info.php',
			array(
				'health_report' => $health_report,
			)
		);
	}

	public function render_conversations_page() {
		if ( ! current_user_can( 'manage_ai_chat_widget_conversations' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'jsdw-ai-chat' ) );
		}
		$selected_id = isset( $_GET['conversation_id'] ) ? absint( wp_unslash( (string) $_GET['conversation_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $selected_id > 0 ) {
			$this->conversation_service->mark_admin_inbox_read( $selected_id );
		}
		$conversations = $this->conversation_service->list_conversations( 100, 0 );
		$enriched_conv   = array();
		foreach ( $conversations as $crow ) {
			if ( ! is_array( $crow ) ) {
				continue;
			}
			$ast                          = $this->conversation_service->get_attention_state( $crow );
			$crow['attention_label']      = $this->conversation_service->attention_state_label( $ast );
			$crow['attention_state']      = $ast;
			$enriched_conv[]              = $crow;
		}
		$conversations = $enriched_conv;
		$selected      = $selected_id > 0 ? $this->conversation_service->get_conversation( $selected_id ) : null;
		$messages      = $selected_id > 0 ? $this->conversation_service->list_messages( $selected_id, 500, 0 ) : array();
		$settings      = $this->settings->get_all();
		$storage_on    = ! empty( $settings['privacy']['store_conversations'] ) && ! empty( $settings['features']['enable_chat_storage'] );
		$selected_list_row = null;
		if ( $selected_id > 0 ) {
			foreach ( $conversations as $r ) {
				if ( is_array( $r ) && isset( $r['id'] ) && absint( $r['id'] ) === $selected_id ) {
					$selected_list_row = $r;
					break;
				}
			}
		}
		$attention_slug  = is_array( $selected_list_row )
			? $this->conversation_service->get_attention_state( $selected_list_row )
			: 'neutral';
		$attention_label = $this->conversation_service->attention_state_label( $attention_slug );
		$inbox_unread_total = $this->conversation_service->count_conversations_admin_unread();
		$thread_max_message_id = 0;
		if ( $selected_id > 0 ) {
			if ( ! empty( $messages ) && is_array( $messages ) ) {
				foreach ( $messages as $m ) {
					if ( is_array( $m ) && isset( $m['id'] ) ) {
						$thread_max_message_id = max( $thread_max_message_id, absint( $m['id'] ) );
					}
				}
			}
			if ( $thread_max_message_id <= 0 ) {
				$thread_max_message_id = (int) $this->conversation_service->get_latest_message_id( $selected_id );
			}
		}
		$this->render_admin_shell(
			JSDW_AI_CHAT_PATH . 'admin/views/page-conversations.php',
			array(
				'selected_id'            => $selected_id,
				'conversations'          => $conversations,
				'selected'               => $selected,
				'messages'               => $messages,
				'storage_on'             => $storage_on,
				'attention_slug'         => $attention_slug,
				'attention_label'        => $attention_label,
				'inbox_unread_total'     => $inbox_unread_total,
				'thread_max_message_id'  => $thread_max_message_id,
			)
		);
	}

	/**
	 * Show unread thread count badge on Conversations submenu (admin only).
	 */
	public function badge_conversations_submenu_unread() {
		if ( ! current_user_can( 'manage_ai_chat_widget_conversations' ) ) {
			return;
		}
		global $submenu;
		if ( ! isset( $submenu['jsdw-ai-chat-dashboard'] ) || ! is_array( $submenu['jsdw-ai-chat-dashboard'] ) ) {
			return;
		}
		$n = $this->conversation_service->count_conversations_admin_unread();
		if ( $n <= 0 ) {
			return;
		}
		$badge = (string) min( 99, $n );
		foreach ( $submenu['jsdw-ai-chat-dashboard'] as $key => $item ) {
			if ( isset( $item[2] ) && 'jsdw-ai-chat-conversations' === $item[2] ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- append badge HTML to submenu title.
				$submenu['jsdw-ai-chat-dashboard'][ $key ][0] .= ' <span class="update-plugins count-jdw"><span class="plugin-count">' . esc_html( $badge ) . '</span></span>';
				break;
			}
		}
	}

	public function enqueue_design_studio_assets( $hook_suffix ) {
		// Typical hook: jsdw-ai-chat-dashboard_page_jsdw-ai-chat-design-studio (varies by WP context).
		if ( strpos( (string) $hook_suffix, 'jsdw-ai-chat-design-studio' ) === false ) {
			return;
		}
		if ( ! current_user_can( 'manage_ai_chat_widget_settings' ) ) {
			return;
		}

		wp_enqueue_style(
			'jsdw-ai-chat-design-studio-fonts',
			'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;1,9..40,400&family=Instrument+Sans:wght@400;600&family=Inter:wght@400;600&display=swap',
			array(),
			null
		);

		$css_path = JSDW_AI_CHAT_PATH . 'admin/css/design-studio.css';
		$js_path  = JSDW_AI_CHAT_PATH . 'admin/js/design-studio.js';
		$ver      = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $css_path ) ) {
			$ver .= '.' . (string) filemtime( $css_path );
		}

		wp_enqueue_style(
			'jsdw-ai-chat-design-studio',
			JSDW_AI_CHAT_URL . 'admin/css/design-studio.css',
			array(
				'jsdw-ai-chat-admin-shell',
				'jsdw-ai-chat-design-studio-fonts',
			),
			$ver
		);

		$js_ver = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $js_path ) ) {
			$js_ver .= '.' . (string) filemtime( $js_path );
		}
		wp_enqueue_script(
			'jsdw-ai-chat-design-studio',
			JSDW_AI_CHAT_URL . 'admin/js/design-studio.js',
			array(),
			$js_ver,
			true
		);

		$wd  = $this->settings->get_all();
		$design = isset( $wd['widget_design'] ) && is_array( $wd['widget_design'] ) ? $wd['widget_design'] : $this->settings->get_default_widget_design();
		$widget_ui = isset( $wd['widget_ui'] ) && is_array( $wd['widget_ui'] ) ? $wd['widget_ui'] : $this->settings->get_default_widget_ui();

		$save_debug = defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_ai_chat_widget_settings' );

		wp_localize_script(
			'jsdw-ai-chat-design-studio',
			'JSDW_AI_CHAT_DESIGN',
			array(
				'settings'    => $design,
				'widgetUi'    => $widget_ui,
				'restUrl'     => esc_url_raw( rest_url( 'ai-chat-widget/v1/settings/widget-design' ) ),
				'settingsUrl' => esc_url_raw( admin_url( 'admin.php?page=jsdw-ai-chat-settings' ) ),
				'wpPagesUrl'  => esc_url_raw( rest_url( 'wp/v2/pages' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'saveDebug'   => $save_debug,
				'i18n'        => array(
					'saved' => __( 'Settings saved.', 'jsdw-ai-chat' ),
					'error' => __( 'Could not save settings.', 'jsdw-ai-chat' ),
				),
			)
		);
	}

	public function render_design_studio_page() {
		if ( ! current_user_can( 'manage_ai_chat_widget_settings' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'jsdw-ai-chat' ) );
		}
		$this->render_admin_shell( JSDW_AI_CHAT_PATH . 'admin/views/page-design-studio.php' );
	}
}
