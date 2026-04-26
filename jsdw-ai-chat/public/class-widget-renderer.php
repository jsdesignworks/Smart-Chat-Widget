<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Phase 6: front-end widget enqueue, localization, shortcode, optional footer mount.
 */
class JSDW_AI_Chat_Widget_Renderer {
	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	/**
	 * Shortcode was present on the page (even if it rendered nothing).
	 *
	 * @var bool
	 */
	private $shortcode_invoked = false;

	public function __construct( JSDW_AI_Chat_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_footer', array( $this, 'maybe_footer_mount' ), 20 );
	}

	public function register_shortcode() {
		add_shortcode( 'jsdw_ai_chat_widget', array( $this, 'render_shortcode' ) );
	}

	/**
	 * @return string
	 */
	public function render_shortcode() {
		$this->shortcode_invoked = true;
		if ( ! $this->should_output_mount() ) {
			return '';
		}
		return $this->get_mount_markup();
	}

	public function maybe_footer_mount() {
		$all = $this->settings->get_all();
		$wui = isset( $all['widget_ui'] ) && is_array( $all['widget_ui'] ) ? $all['widget_ui'] : array();
		if ( empty( $wui['auto_footer'] ) ) {
			return;
		}
		if ( $this->shortcode_invoked ) {
			return;
		}
		if ( ! $this->should_output_mount() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_mount_markup();
	}

	/**
	 * @return string
	 */
	private function get_mount_markup() {
		return '<div id="jsdw-ai-chat-widget-mount" class="jsdw-ai-chat-widget-mount" data-jsdw-widget-mount="1"></div>';
	}

	/**
	 * Whether widget shell may appear (assets + mount). Public users require public endpoint on.
	 */
	private function should_output_mount() {
		return $this->get_widget_runtime_mode() !== 'off';
	}

	/**
	 * off | live | admin_disabled
	 */
	private function get_widget_runtime_mode() {
		$all = $this->settings->get_all();
		if ( empty( $all['general']['enabled'] ) || empty( $all['features']['enable_widget'] ) ) {
			return 'off';
		}
		$wui = isset( $all['widget_ui'] ) && is_array( $all['widget_ui'] ) ? $all['widget_ui'] : array();
		if ( isset( $wui['widget_enabled'] ) && ! $wui['widget_enabled'] ) {
			return 'off';
		}
		$allow_public = ! empty( $all['chat']['allow_public_query_endpoint'] );
		$is_admin_cap = current_user_can( 'manage_ai_chat_widget' );
		if ( ! $allow_public && ! $is_admin_cap ) {
			return 'off';
		}
		if ( ! $allow_public && $is_admin_cap ) {
			return 'admin_disabled';
		}
		return 'live';
	}

	public function enqueue_assets() {
		$mode = $this->get_widget_runtime_mode();
		if ( 'off' === $mode ) {
			return;
		}

		$all = $this->settings->get_all();
		$wd  = isset( $all['widget_design'] ) && is_array( $all['widget_design'] ) ? $all['widget_design'] : array();
		$wui = isset( $all['widget_ui'] ) && is_array( $all['widget_ui'] ) ? $all['widget_ui'] : array();

		$base = JSDW_AI_CHAT_URL . 'public/';
		$ver  = JSDW_AI_CHAT_VERSION;
		$css_path = JSDW_AI_CHAT_PATH . 'public/css/widget.css';
		$js_path  = JSDW_AI_CHAT_PATH . 'public/js/widget.js';
		if ( file_exists( $css_path ) ) {
			$ver .= '.' . (string) filemtime( $css_path );
		}
		if ( file_exists( $js_path ) ) {
			$ver .= '.' . (string) filemtime( $js_path );
		}

		wp_enqueue_style(
			'jsdw-ai-chat-widget-fonts',
			'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600&family=Instrument+Sans:wght@400;600&family=Inter:wght@400;600&display=swap',
			array(),
			null
		);
		$tokens_path = JSDW_AI_CHAT_PATH . 'public/css/tokens-widget.css';
		$tokens_ver  = JSDW_AI_CHAT_VERSION;
		if ( file_exists( $tokens_path ) ) {
			$tokens_ver .= '.' . (string) filemtime( $tokens_path );
		}
		wp_enqueue_style(
			'jsdw-ai-chat-widget-tokens',
			$base . 'css/tokens-widget.css',
			array( 'jsdw-ai-chat-widget-fonts' ),
			$tokens_ver
		);
		wp_enqueue_style( 'jsdw-ai-chat-widget', $base . 'css/widget.css', array( 'jsdw-ai-chat-widget-tokens' ), $ver );
		wp_enqueue_script( 'jsdw-ai-chat-widget', $base . 'js/widget.js', array(), $ver, true );

		$rest_query   = rest_url( 'ai-chat-widget/v1/chat/query' );
		$rest_debug   = rest_url( 'ai-chat-widget/v1/chat/query-debug' );
		$rest_session = rest_url( 'ai-chat-widget/v1/chat/session-messages' );
		$rest_visit   = rest_url( 'ai-chat-widget/v1/chat/visitor-identity' );

		$welcome = '' !== (string) ( $wui['welcome_message'] ?? '' )
			? (string) $wui['welcome_message']
			: (string) ( $wd['welcomeMessage'] ?? '' );
		$placeholder = '' !== (string) ( $wui['placeholder_text'] ?? '' )
			? (string) $wui['placeholder_text']
			: (string) ( $wd['inputPlaceholder'] ?? '' );
		$position = '' !== (string) ( $wui['widget_position'] ?? '' )
			? (string) $wui['widget_position']
			: (string) ( $wd['position'] ?? 'bottom-right' );

		wp_localize_script(
			'jsdw-ai-chat-widget',
			'JSDW_AI_CHAT_WIDGET',
			array(
				'widgetDesign'        => $wd,
				'design'              => $wd,
				'widgetUi'            => $wui,
				'restUrl'             => esc_url_raw( $rest_query ),
				'restUrlDebug'        => esc_url_raw( $rest_debug ),
				'restSessionMessages' => esc_url_raw( $rest_session ),
				'restVisitorIdentity' => esc_url_raw( $rest_visit ),
				'nonce'               => wp_create_nonce( 'wp_rest' ),
				'runtimeMode'         => $mode,
				'requireVisitorIdentity' => ! empty( $all['chat']['require_visitor_identity_for_handoff'] ),
				'allowPublicQuery'    => ! empty( $all['chat']['allow_public_query_endpoint'] ),
				'isAdminCapable'      => current_user_can( 'manage_ai_chat_widget' ),
				'adminDebugUi'        => ! empty( $wui['admin_debug_ui'] ),
				'useDebugEndpoint'    => ! empty( $wui['admin_debug_ui'] ) && current_user_can( 'manage_ai_chat_widget' ),
				'showSources'         => ! empty( $wui['show_sources'] ),
				'allowReset'          => ! empty( $wui['allow_reset_conversation'] ),
				'launcherLabel'       => (string) ( $wui['launcher_label'] ?? '' ),
				'welcomeMessage'      => $welcome,
				'placeholderText'     => $placeholder,
				'widgetPosition'      => $position,
				'currentPostId'       => (int) get_queried_object_id(),
				'isLoggedIn'          => is_user_logged_in(),
				'branding'            => apply_filters( 'jsdw_ai_chat_widget_branding_text', __( 'Powered by JSDW AI Chat', 'jsdw-ai-chat' ) ),
				'debug'               => (bool) ( defined( 'JSDW_AI_CHAT_DEBUG' ) && JSDW_AI_CHAT_DEBUG && current_user_can( 'manage_ai_chat_widget' ) ),
				'strings'             => array(
					'adminDisabled'   => __( 'The public chat endpoint is disabled. Enable "Allow public query endpoint" in settings to use the widget for visitors.', 'jsdw-ai-chat' ),
					'send'            => __( 'Send message', 'jsdw-ai-chat' ),
					'open'            => __( 'Open chat', 'jsdw-ai-chat' ),
					'close'           => __( 'Close chat', 'jsdw-ai-chat' ),
					'reset'           => __( 'Reset conversation', 'jsdw-ai-chat' ),
					'loading'         => __( 'Thinking…', 'jsdw-ai-chat' ),
					'errorNetwork'    => __( 'Could not reach the server. Please try again.', 'jsdw-ai-chat' ),
					'errorResponse'   => __( 'Unexpected response from server.', 'jsdw-ai-chat' ),
					'liveAgentJoined' => __( 'A team member can now reply here when they are available.', 'jsdw-ai-chat' ),
					'visitorIdentityTitle' => __( 'Before we connect you with someone', 'jsdw-ai-chat' ),
					'visitorNameLabel'     => __( 'Your name', 'jsdw-ai-chat' ),
					'visitorEmailLabel'    => __( 'Email', 'jsdw-ai-chat' ),
					'visitorSave'          => __( 'Save', 'jsdw-ai-chat' ),
					'visitorSaving'        => __( 'Saving…', 'jsdw-ai-chat' ),
				),
			)
		);
	}
}
