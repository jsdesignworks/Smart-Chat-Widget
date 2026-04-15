<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Content_Fingerprint {
	/**
	 * @param array<string,mixed> $normalized
	 * @return array<string,string>
	 */
	public function hash( array $normalized ) {
		$title    = isset( $normalized['title'] ) ? (string) $normalized['title'] : '';
		$body     = isset( $normalized['body'] ) ? (string) $normalized['body'] : '';
		$metadata = isset( $normalized['metadata'] ) ? (string) $normalized['metadata'] : '';
		$headings = isset( $normalized['headings'] ) && is_array( $normalized['headings'] ) ? $normalized['headings'] : array();

		$structure = implode( "\n", array_map( 'strval', $headings ) );

		return array(
			'title_hash'     => hash( 'sha256', $title ),
			'content_hash'   => hash( 'sha256', $body ),
			'structure_hash' => hash( 'sha256', $structure ),
			'metadata_hash'  => hash( 'sha256', $metadata ),
		);
	}
}
