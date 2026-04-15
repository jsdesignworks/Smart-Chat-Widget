<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Public {
	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	/**
	 * @var JSDW_AI_Chat_Widget_Renderer
	 */
	private $widget_renderer;

	public function __construct( JSDW_AI_Chat_Settings $settings, JSDW_AI_Chat_Widget_Renderer $widget_renderer ) {
		$this->settings        = $settings;
		$this->widget_renderer = $widget_renderer;
	}

	public function enqueue_assets() {
		$this->widget_renderer->enqueue_assets();
	}
}
