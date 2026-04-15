<?php
/**
 * Phase 4: deterministic query normalization for keyword retrieval.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Query_Normalizer {

	/**
	 * @return array{normalized:string,terms:string[]}
	 */
	public function normalize( $query ) {
		$q = strtolower( (string) $query );
		$q = str_replace( array( "\r\n", "\r" ), ' ', $q );
		$q = preg_replace( '/[^\p{L}\p{N}\s@.:\-+]/u', ' ', $q );
		if ( null === $q ) {
			$q = '';
		}
		$q = preg_replace( '/\s+/u', ' ', $q );
		$q = trim( (string) $q );
		$terms = array_filter( array_unique( explode( ' ', $q ) ) );
		return array(
			'normalized' => $q,
			'terms'      => array_values( $terms ),
		);
	}
}
