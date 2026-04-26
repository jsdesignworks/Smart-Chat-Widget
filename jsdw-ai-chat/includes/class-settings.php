<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Settings {
	public function get_defaults() {
		return array(
			'general'  => array(
				'enabled' => true,
			),
			'sources'  => array(
				'enabled_source_types'                 => array( 'post', 'page', 'taxonomy', 'menu', 'manual', 'rendered_url' ),
				'included_post_types'                  => array(),
				'excluded_post_types'                  => array(),
				'included_post_ids'                    => array(),
				'excluded_post_ids'                    => array(),
				'included_taxonomy_terms'              => array(),
				'excluded_taxonomy_terms'              => array(),
				'include_menus'                        => true,
				'include_nav_menu_items'               => false,
				'include_custom_fields'                => false,
				'allowed_custom_field_keys'            => array(),
				'blocked_custom_field_keys'            => array(),
				'include_rendered_url_rules'           => false,
				'allowed_url_patterns'                 => array(),
				'blocked_url_patterns'                 => array(),
				'include_drafts'                       => false,
				'include_private_content'              => false,
				'include_password_protected_content'   => false,
				'allow_manual_sources'                 => true,
				'manual_source_authority_override'     => 100,
				'default_source_authority_by_type'     => array(
					'manual'       => 100,
					'settings'     => 90,
					'post'         => 80,
					'page'         => 80,
					'cpt'          => 75,
					'taxonomy'     => 60,
					'menu'         => 50,
					'rendered_url' => 40,
				),
			),
			'ai'       => array(
				'provider'       => '',
				'openai_api_key' => '',
				'openai_model'   => 'gpt-4o-mini',
			),
			'chat'     => array(
				'require_visitor_identity_for_handoff' => false,
				'answer_mode'                 => 'strict_local_only',
				'allow_public_query_endpoint' => false,
				'allow_ai_phrase_assist'      => false,
				'min_query_length'            => 2,
				'max_query_length'            => 500,
				'query_throttle_per_minute'   => 30,
				'debug_trace_enabled'         => false,
				'answer_style'                => 'neutral',
				'clarification_enabled'       => true,
				'store_trace_snapshots'       => true,
				'canned_responses'            => $this->get_default_canned_responses(),
			),
			'indexing' => array(
				'auto_reindex' => false,
			),
			'privacy'  => array(
				'store_conversations' => true,
			),
			'logging'  => array(
				'enabled'            => true,
				'mirror_wp_debug'    => false,
				'minimum_log_level'  => 'info',
			),
			'features' => array(
				'enable_rest'         => true,
				'enable_cron'         => true,
				'enable_queue'        => true,
				'enable_chat_storage' => true,
				'enable_indexing'     => false,
				'enable_ai'           => false,
				'enable_widget'       => true,
				'cleanup_on_uninstall'=> false,
			),
			// Widget Design Studio (camelCase keys; distinct from discovery change_reason).
			'widget_design' => $this->get_default_widget_design(),
			'widget_ui'     => $this->get_default_widget_ui(),
		);
	}

	/**
	 * Phase 6 widget integration (front-end behavior).
	 *
	 * @return array<string,mixed>
	 */
	public function get_default_widget_ui() {
		return array(
			'widget_enabled'           => true,
			'widget_position'          => '',
			'launcher_label'           => '',
			'welcome_message'          => '',
			'placeholder_text'         => '',
			'show_sources'             => false,
			'allow_reset_conversation' => true,
			'admin_debug_ui'           => false,
			'auto_footer'              => true,
		);
	}

	/**
	 * Default widget appearance/behavior for Design Studio and front-end widget shell.
	 *
	 * @return array<string,mixed>
	 */
	public function get_default_widget_design() {
		return array(
			'theme'                 => 'violet',
			'primaryColor'          => '#6c63ff',
			'chatBg'                => '#ffffff',
			'botBubbleColor'        => '#f0f0f5',
			'fontFamily'            => 'Instrument Sans',
			'fontSize'              => 13,
			'borderRadius'          => 18,
			'chatWidth'             => 360,
			'chatHeight'            => 520,
			'widgetSize'            => 56,
			'widgetIcon'            => '💬',
			'position'              => 'bottom-right',
			'defaultState'          => 'open',
			'autoOpenDelay'         => 5,
			'openTrigger'           => 'page-load',
			'animation'             => 'slide',
			'animationSpeed'        => 0.3,
			'showOnMobile'          => true,
			'showOnDesktop'         => true,
			'hideOnPages'           => false,
			'hideOnPageIds'         => array(),
			'loggedInOnly'          => false,
			'showBadge'             => true,
			'showQuickReplies'      => true,
			'showTypingIndicator'   => true,
			'soundEnabled'          => false,
			'showTimestamps'        => true,
			'showBranding'          => true,
			'botName'               => 'Aria',
			'statusText'            => 'Online · Typically replies instantly',
			'botAvatar'             => '🤖',
			'welcomeMessage'        => 'Hi there 👋 I\'m Aria! How can I help you today?',
			'quickReplies'          => array( '📦 Track my order', '💬 Talk to support', '📋 View FAQ' ),
			'inputPlaceholder'      => 'Type a message...',
		);
	}

	public function ensure_defaults() {
		$current = get_option( JSDW_AI_CHAT_OPTION_SETTINGS );
		if ( ! is_array( $current ) ) {
			update_option( JSDW_AI_CHAT_OPTION_SETTINGS, $this->get_defaults(), false );
			return;
		}

		$merged = wp_parse_args( $current, $this->get_defaults() );
		update_option( JSDW_AI_CHAT_OPTION_SETTINGS, $this->sanitize_settings( $merged ), false );
	}

	public function get_all() {
		$settings = get_option( JSDW_AI_CHAT_OPTION_SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$merged = wp_parse_args( $settings, $this->get_defaults() );
		$wd     = isset( $merged['widget_design'] ) && is_array( $merged['widget_design'] ) ? $merged['widget_design'] : array();
		$merged['widget_design'] = wp_parse_args( $wd, $this->get_default_widget_design() );
		$wu     = isset( $merged['widget_ui'] ) && is_array( $merged['widget_ui'] ) ? $merged['widget_ui'] : array();
		$merged['widget_ui']     = wp_parse_args( $wu, $this->get_default_widget_ui() );

		$merged['chat'] = isset( $merged['chat'] ) && is_array( $merged['chat'] ) ? $merged['chat'] : array();
		$merged['chat'] = wp_parse_args( $merged['chat'], $this->get_defaults()['chat'] );
		$merged['chat']['canned_responses'] = $this->merge_canned_responses_defaults(
			isset( $merged['chat']['canned_responses'] ) && is_array( $merged['chat']['canned_responses'] ) ? $merged['chat']['canned_responses'] : array()
		);

		$merged['ai'] = isset( $merged['ai'] ) && is_array( $merged['ai'] ) ? $merged['ai'] : array();
		$merged['ai'] = wp_parse_args( $merged['ai'], $this->get_defaults()['ai'] );

		return $merged;
	}

	/**
	 * Empty override slots (blank = use built-in library defaults).
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_default_canned_responses() {
		$tones = array( 'concise' => '', 'neutral' => '', 'friendly' => '' );
		return array(
			'no_match'               => $tones,
			'low_confidence'         => $tones,
			'requires_clarification' => $tones,
			'guard_rejected'         => $tones,
			'failed'                 => $tones,
			'unsupported'            => $tones,
		);
	}

	/**
	 * @param array<string, mixed> $stored
	 * @return array<string, array<string, string>>
	 */
	private function merge_canned_responses_defaults( array $stored ) {
		$base = $this->get_default_canned_responses();
		foreach ( $base as $state => $tones ) {
			if ( ! isset( $stored[ $state ] ) || ! is_array( $stored[ $state ] ) ) {
				continue;
			}
			foreach ( array( 'concise', 'neutral', 'friendly' ) as $t ) {
				if ( array_key_exists( $t, $stored[ $state ] ) ) {
					$base[ $state ][ $t ] = (string) $stored[ $state ][ $t ];
				}
			}
		}
		return $base;
	}

	public function sanitize_settings( $settings ) {
		$defaults = $this->get_defaults();
		$clean    = wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );

		$clean['general']['enabled']                    = ! empty( $clean['general']['enabled'] );
		$clean['indexing']['auto_reindex']              = ! empty( $clean['indexing']['auto_reindex'] );
		$clean['privacy']['store_conversations']        = ! empty( $clean['privacy']['store_conversations'] );
		$clean['logging']['enabled']                    = ! empty( $clean['logging']['enabled'] );
		$clean['logging']['mirror_wp_debug']            = ! empty( $clean['logging']['mirror_wp_debug'] );
		$clean['logging']['minimum_log_level']          = sanitize_text_field( (string) $clean['logging']['minimum_log_level'] );
		$clean['features']['enable_rest']               = ! empty( $clean['features']['enable_rest'] );
		$clean['features']['enable_cron']               = ! empty( $clean['features']['enable_cron'] );
		$clean['features']['enable_queue']              = ! empty( $clean['features']['enable_queue'] );
		$clean['features']['enable_chat_storage']       = ! empty( $clean['features']['enable_chat_storage'] );
		$clean['features']['enable_indexing']           = ! empty( $clean['features']['enable_indexing'] );
		$clean['features']['enable_ai']                 = ! empty( $clean['features']['enable_ai'] );
		$clean['features']['enable_widget']             = ! empty( $clean['features']['enable_widget'] );
		$clean['features']['cleanup_on_uninstall']      = ! empty( $clean['features']['cleanup_on_uninstall'] );
		$clean['ai']['provider']                        = JSDW_AI_Chat_AI_Provider_Status::sanitize_provider( $clean['ai']['provider'] ?? '' );
		$key_in                                         = isset( $clean['ai']['openai_api_key'] ) ? (string) $clean['ai']['openai_api_key'] : '';
		$key_in                                         = trim( str_replace( array( "\r", "\n" ), '', $key_in ) );
		if ( strlen( $key_in ) > 256 ) {
			$key_in = substr( $key_in, 0, 256 );
		}
		$clean['ai']['openai_api_key']                  = $key_in;
		$model_in                                       = isset( $clean['ai']['openai_model'] ) ? (string) $clean['ai']['openai_model'] : '';
		$clean['ai']['openai_model']                    = preg_match( '/^[a-zA-Z0-9._-]{1,80}$/', $model_in ) ? $model_in : JSDW_AI_Chat_AI_Provider_OpenAI::DEFAULT_MODEL;
		$allowed_answer_modes = array( 'strict_local_only', 'local_with_optional_ai_phrase', 'debug_trace' );
		$clean['chat']['answer_mode'] = sanitize_text_field( (string) $clean['chat']['answer_mode'] );
		if ( ! in_array( $clean['chat']['answer_mode'], $allowed_answer_modes, true ) ) {
			$clean['chat']['answer_mode'] = 'strict_local_only';
		}
		$allowed_answer_styles = array( 'concise', 'neutral', 'friendly' );
		$clean['chat']['answer_style'] = sanitize_text_field( (string) $clean['chat']['answer_style'] );
		if ( ! in_array( $clean['chat']['answer_style'], $allowed_answer_styles, true ) ) {
			$clean['chat']['answer_style'] = 'neutral';
		}
		$clean['chat']['require_visitor_identity_for_handoff'] = ! empty( $clean['chat']['require_visitor_identity_for_handoff'] );
		$clean['chat']['allow_public_query_endpoint'] = ! empty( $clean['chat']['allow_public_query_endpoint'] );
		$clean['chat']['allow_ai_phrase_assist']      = ! empty( $clean['chat']['allow_ai_phrase_assist'] );
		$phrase_provider = JSDW_AI_Chat_AI_Provider_Status::sanitize_provider( (string) ( $clean['ai']['provider'] ?? '' ) );
		$phrase_ok       = ( JSDW_AI_Chat_AI_Provider_Status::PROVIDER_OPENAI === $phrase_provider && '' !== trim( (string) ( $clean['ai']['openai_api_key'] ?? '' ) ) );
		if ( $clean['chat']['allow_ai_phrase_assist'] && ! $phrase_ok ) {
			$clean['chat']['allow_ai_phrase_assist'] = false;
		}
		$clean['chat']['debug_trace_enabled']         = ! empty( $clean['chat']['debug_trace_enabled'] );
		$clean['chat']['clarification_enabled']       = ! empty( $clean['chat']['clarification_enabled'] );
		$clean['chat']['store_trace_snapshots']       = ! empty( $clean['chat']['store_trace_snapshots'] );
		$clean['chat']['min_query_length']            = max( 1, min( 50, absint( $clean['chat']['min_query_length'] ) ) );
		$clean['chat']['max_query_length']            = max( 10, min( 4000, absint( $clean['chat']['max_query_length'] ) ) );
		if ( $clean['chat']['max_query_length'] < $clean['chat']['min_query_length'] ) {
			$clean['chat']['max_query_length'] = max( $clean['chat']['min_query_length'], 10 );
		}
		$clean['chat']['query_throttle_per_minute'] = max( 5, min( 300, absint( $clean['chat']['query_throttle_per_minute'] ?? 30 ) ) );
		$cr_in = isset( $clean['chat']['canned_responses'] ) && is_array( $clean['chat']['canned_responses'] ) ? $clean['chat']['canned_responses'] : array();
		$clean['chat']['canned_responses'] = $this->sanitize_canned_responses( $cr_in );
		$clean['sources']['enabled_source_types']       = array_values( array_unique( array_map( 'sanitize_text_field', (array) $clean['sources']['enabled_source_types'] ) ) );
		$clean['sources']['included_post_types']        = array_values( array_unique( array_map( 'sanitize_text_field', (array) $clean['sources']['included_post_types'] ) ) );
		$clean['sources']['excluded_post_types']        = array_values( array_unique( array_map( 'sanitize_text_field', (array) $clean['sources']['excluded_post_types'] ) ) );
		$clean['sources']['included_post_ids']          = array_values( array_unique( array_filter( array_map( 'absint', (array) $clean['sources']['included_post_ids'] ) ) ) );
		$clean['sources']['excluded_post_ids']          = array_values( array_unique( array_filter( array_map( 'absint', (array) $clean['sources']['excluded_post_ids'] ) ) ) );
		$clean['sources']['included_taxonomy_terms']    = array_values( array_unique( array_map( 'sanitize_text_field', (array) $clean['sources']['included_taxonomy_terms'] ) ) );
		$clean['sources']['excluded_taxonomy_terms']    = array_values( array_unique( array_map( 'sanitize_text_field', (array) $clean['sources']['excluded_taxonomy_terms'] ) ) );
		$clean['sources']['include_menus']              = ! empty( $clean['sources']['include_menus'] );
		$clean['sources']['include_nav_menu_items']     = ! empty( $clean['sources']['include_nav_menu_items'] );
		$clean['sources']['include_custom_fields']      = ! empty( $clean['sources']['include_custom_fields'] );
		$clean['sources']['allowed_custom_field_keys']  = array_values( array_unique( array_map( 'sanitize_key', (array) $clean['sources']['allowed_custom_field_keys'] ) ) );
		$clean['sources']['blocked_custom_field_keys']  = array_values( array_unique( array_map( 'sanitize_key', (array) $clean['sources']['blocked_custom_field_keys'] ) ) );
		$clean['sources']['include_rendered_url_rules'] = ! empty( $clean['sources']['include_rendered_url_rules'] );
		$clean['sources']['allowed_url_patterns']       = array_values( array_unique( array_map( 'sanitize_text_field', (array) $clean['sources']['allowed_url_patterns'] ) ) );
		$clean['sources']['blocked_url_patterns']       = array_values( array_unique( array_map( 'sanitize_text_field', (array) $clean['sources']['blocked_url_patterns'] ) ) );
		$clean['sources']['include_drafts']             = ! empty( $clean['sources']['include_drafts'] );
		$clean['sources']['include_private_content']    = ! empty( $clean['sources']['include_private_content'] );
		$clean['sources']['include_password_protected_content'] = ! empty( $clean['sources']['include_password_protected_content'] );
		$clean['sources']['allow_manual_sources']       = ! empty( $clean['sources']['allow_manual_sources'] );
		$clean['sources']['manual_source_authority_override'] = absint( $clean['sources']['manual_source_authority_override'] );
		$authority_map = is_array( $clean['sources']['default_source_authority_by_type'] ) ? $clean['sources']['default_source_authority_by_type'] : array();
		$clean_map = array();
		foreach ( $authority_map as $type => $value ) {
			$clean_map[ sanitize_text_field( (string) $type ) ] = absint( $value );
		}
		$clean['sources']['default_source_authority_by_type'] = $clean_map;

		$wd_in = isset( $clean['widget_design'] ) && is_array( $clean['widget_design'] ) ? $clean['widget_design'] : array();
		$clean['widget_design'] = $this->sanitize_widget_design( $wd_in );

		$wu_in = isset( $clean['widget_ui'] ) && is_array( $clean['widget_ui'] ) ? $clean['widget_ui'] : array();
		$clean['widget_ui']     = $this->sanitize_widget_ui( $wu_in );

		return $clean;
	}

	/**
	 * @param array<string,mixed> $widget_ui
	 * @return array<string,mixed>
	 */
	public function sanitize_widget_ui( array $widget_ui ) {
		$base = $this->get_default_widget_ui();
		$u    = wp_parse_args( $widget_ui, $base );

		$u['widget_enabled']           = ! empty( $u['widget_enabled'] );
		$u['show_sources']             = ! empty( $u['show_sources'] );
		$u['allow_reset_conversation'] = ! empty( $u['allow_reset_conversation'] );
		$u['admin_debug_ui']           = ! empty( $u['admin_debug_ui'] );
		$u['auto_footer']              = ! empty( $u['auto_footer'] );

		$positions = array( '', 'bottom-right', 'bottom-left', 'top-right', 'top-left' );
		$pos       = (string) ( $u['widget_position'] ?? '' );
		$u['widget_position'] = in_array( $pos, $positions, true ) ? $pos : '';

		$u['launcher_label']   = substr( sanitize_text_field( (string) ( $u['launcher_label'] ?? '' ) ), 0, 80 );
		$u['welcome_message']  = substr( sanitize_textarea_field( (string) ( $u['welcome_message'] ?? '' ) ), 0, 500 );
		$u['placeholder_text'] = substr( sanitize_text_field( (string) ( $u['placeholder_text'] ?? '' ) ), 0, 100 );

		return $u;
	}

	/**
	 * @param array<string, mixed> $in Raw canned_responses from settings.
	 * @return array<string, array<string, string>>
	 */
	private function sanitize_canned_responses( array $in ) {
		$base   = $this->get_default_canned_responses();
		$out    = $base;
		$canned = new JSDW_AI_Chat_Canned_Responses();
		foreach ( $base as $state => $tones ) {
			if ( ! isset( $in[ $state ] ) || ! is_array( $in[ $state ] ) ) {
				continue;
			}
			foreach ( array( 'concise', 'neutral', 'friendly' ) as $t ) {
				if ( array_key_exists( $t, $in[ $state ] ) ) {
					$val = substr( sanitize_textarea_field( (string) $in[ $state ][ $t ] ), 0, 500 );
					if ( '' === trim( $val ) ) {
						$out[ $state ][ $t ] = '';
						continue;
					}
					$recommended = $canned->get_recommended_text( $state, $t );
					$out[ $state ][ $t ] = ( trim( $val ) === trim( $recommended ) ) ? '' : $val;
				}
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $widget_design
	 * @return array<string,mixed>
	 */
	public function sanitize_widget_design( array $widget_design ) {
		$base = $this->get_default_widget_design();
		$d    = wp_parse_args( $widget_design, $base );

		$themes = array( 'violet', 'midnight', 'forest', 'coral', 'ocean', 'slate', 'custom' );
		$d['theme'] = in_array( (string) $d['theme'], $themes, true ) ? (string) $d['theme'] : 'violet';

		$d['primaryColor']   = $this->sanitize_hex( $d['primaryColor'], (string) $base['primaryColor'] );
		$d['chatBg']         = $this->sanitize_hex( $d['chatBg'], (string) $base['chatBg'] );
		$d['botBubbleColor'] = $this->sanitize_hex( $d['botBubbleColor'], (string) $base['botBubbleColor'] );

		$fonts = array( 'Instrument Sans', 'Inter', 'DM Sans', 'Georgia', 'System UI' );
		$d['fontFamily'] = in_array( (string) $d['fontFamily'], $fonts, true ) ? (string) $d['fontFamily'] : 'Instrument Sans';

		$d['fontSize']     = max( 11, min( 16, intval( $d['fontSize'] ?? 13 ) ) );
		$d['borderRadius'] = max( 0, min( 28, intval( $d['borderRadius'] ?? 18 ) ) );
		$d['chatWidth']    = max( 280, min( 440, intval( $d['chatWidth'] ?? 360 ) ) );
		if ( 0 !== ( $d['chatWidth'] % 10 ) ) {
			$d['chatWidth'] = (int) ( round( $d['chatWidth'] / 10 ) * 10 );
		}
		$d['chatHeight'] = max( 380, min( 640, intval( $d['chatHeight'] ?? 520 ) ) );
		if ( 0 !== ( $d['chatHeight'] % 10 ) ) {
			$d['chatHeight'] = (int) ( round( $d['chatHeight'] / 10 ) * 10 );
		}
		$ws = intval( $d['widgetSize'] ?? 56 );
		$d['widgetSize'] = max( 40, min( 72, $ws - ( $ws % 4 ) ) );

		$d['widgetIcon'] = $this->sanitize_single_emoji( $d['widgetIcon'], (string) $base['widgetIcon'] );

		$positions = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' );
		$d['position'] = in_array( (string) $d['position'], $positions, true ) ? (string) $d['position'] : 'bottom-right';

		$states = array( 'open', 'closed' );
		$d['defaultState'] = in_array( (string) $d['defaultState'], $states, true ) ? (string) $d['defaultState'] : 'open';

		$d['autoOpenDelay'] = max( 0, min( 30, intval( $d['autoOpenDelay'] ?? 0 ) ) );

		$ot = (string) $d['openTrigger'];
		if ( 'scroll-half' === $ot ) {
			$ot = 'scroll-50';
		}
		$d['openTrigger'] = $ot;
		$triggers         = array( 'page-load', 'scroll-50', 'exit-intent', 'button-only', 'time-delay' );
		$d['openTrigger'] = in_array( (string) $d['openTrigger'], $triggers, true ) ? (string) $d['openTrigger'] : 'page-load';

		$anims = array( 'slide', 'fade', 'pop' );
		$d['animation'] = in_array( (string) $d['animation'], $anims, true ) ? (string) $d['animation'] : 'slide';

		$d['animationSpeed'] = max( 0.1, min( 0.8, floatval( $d['animationSpeed'] ?? 0.3 ) ) );

		$d['showOnMobile']        = ! empty( $d['showOnMobile'] );
		$d['showOnDesktop']       = ! empty( $d['showOnDesktop'] );
		$d['hideOnPages']          = ! empty( $d['hideOnPages'] );
		$d['loggedInOnly']         = ! empty( $d['loggedInOnly'] );
		$d['showBadge']            = ! empty( $d['showBadge'] );
		$d['showQuickReplies']     = ! empty( $d['showQuickReplies'] );
		$d['showTypingIndicator']  = ! empty( $d['showTypingIndicator'] );
		$d['soundEnabled']         = ! empty( $d['soundEnabled'] );
		$d['showTimestamps']       = ! empty( $d['showTimestamps'] );
		$d['showBranding']         = ! empty( $d['showBranding'] );

		$d['hideOnPageIds'] = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', (array) ( $d['hideOnPageIds'] ?? array() ) )
				)
			)
		);

		$d['botName']          = substr( sanitize_text_field( (string) ( $d['botName'] ?? 'Assistant' ) ), 0, 60 );
		$d['statusText']       = substr( sanitize_text_field( (string) ( $d['statusText'] ?? '' ) ), 0, 100 );
		$d['botAvatar']        = $this->sanitize_single_emoji( $d['botAvatar'], (string) $base['botAvatar'] );
		$d['welcomeMessage']   = substr( sanitize_textarea_field( (string) ( $d['welcomeMessage'] ?? '' ) ), 0, 500 );
		$d['inputPlaceholder'] = substr( sanitize_text_field( (string) ( $d['inputPlaceholder'] ?? '' ) ), 0, 100 );

		$qr_in = isset( $d['quickReplies'] ) && is_array( $d['quickReplies'] ) ? $d['quickReplies'] : array();
		$qr    = array();
		foreach ( $qr_in as $r ) {
			$qr[] = substr( sanitize_text_field( (string) $r ), 0, 80 );
			if ( count( $qr ) >= 3 ) {
				break;
			}
		}
		$d['quickReplies'] = array_slice( $qr, 0, 3 );

		return $d;
	}

	private function sanitize_hex( $value, $fallback ) {
		$s = is_string( $value ) ? trim( $value ) : '';
		if ( preg_match( '/^#([0-9a-fA-F]{6})$/', $s ) ) {
			return strtolower( $s );
		}
		$fb = is_string( $fallback ) ? $fallback : '#000000';
		return preg_match( '/^#([0-9a-fA-F]{6})$/', $fb ) ? strtolower( $fb ) : '#000000';
	}

	private function sanitize_single_emoji( $value, $fallback ) {
		$s = is_string( $value ) ? trim( $value ) : '';
		if ( function_exists( 'mb_substr' ) ) {
			$s = mb_substr( $s, 0, 8, 'UTF-8' );
		} else {
			$s = substr( $s, 0, 12 );
		}
		if ( '' === $s ) {
			return $fallback;
		}
		return $s;
	}
}
