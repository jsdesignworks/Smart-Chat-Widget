<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Container {
	/**
	 * @var array<string, mixed>
	 */
	private $services = array();

	public function set( $id, $service ) {
		$this->services[ $id ] = $service;
	}

	public function get( $id ) {
		return isset( $this->services[ $id ] ) ? $this->services[ $id ] : null;
	}
}
