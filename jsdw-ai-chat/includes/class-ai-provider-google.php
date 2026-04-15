<?php
/**
 * Google adapter (phrase assist not implemented in this release).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_AI_Provider_Google {

	/**
	 * @param string $text
	 * @param string $tone
	 * @return array{ok:bool,text?:string,error?:string,duration_ms?:int}
	 */
	public function refine_phrase( $text, $tone ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return array(
			'ok'          => false,
			'error'       => 'not_implemented',
			'duration_ms' => 0,
		);
	}
}
