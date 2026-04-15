<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Content_Normalizer {
	/**
	 * @param array<string,mixed> $built
	 * @return array<string,mixed>|WP_Error
	 */
	public function normalize( array $built ) {
		$title = isset( $built['title'] ) ? (string) $built['title'] : '';
		$body  = isset( $built['body_html'] ) ? (string) $built['body_html'] : '';
		$meta  = isset( $built['metadata'] ) && is_array( $built['metadata'] ) ? $built['metadata'] : array();

		$title_norm = $this->normalize_text( $title );
		$body_text  = $this->html_to_text( $body );
		$body_norm  = $this->normalize_text( $body_text );

		ksort( $meta );
		$meta_str = wp_json_encode( $meta );
		if ( false === $meta_str ) {
			return new WP_Error( 'jsdw_normalize_meta', 'Could not encode metadata.' );
		}
		$meta_norm = $this->normalize_text( $meta_str );

		$headings = $this->extract_headings( $body );

		return array(
			'title'    => $title_norm,
			'body'     => $body_norm,
			'metadata' => $meta_norm,
			'headings' => $headings,
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function extract_headings( $html ) {
		$html = (string) $html;
		if ( '' === $html ) {
			return array();
		}
		if ( ! preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
			return array();
		}
		$out = array();
		foreach ( $matches as $m ) {
			$text = isset( $m[2] ) ? $this->normalize_text( wp_strip_all_tags( (string) $m[2] ) ) : '';
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}
		return $out;
	}

	private function html_to_text( $html ) {
		$html = (string) $html;
		if ( '' === $html ) {
			return '';
		}
		$html = preg_replace( '@<(script|style)[^>]*>.*?</\1>@is', '', $html );
		if ( null === $html ) {
			return '';
		}
		return wp_strip_all_tags( $html );
	}

	private function normalize_text( $text ) {
		$text = (string) $text;
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/[ \t]+/u', ' ', $text );
		if ( null === $text ) {
			return '';
		}
		$text = preg_replace( '/\n{3,}/u', "\n\n", $text );
		if ( null === $text ) {
			return '';
		}
		return trim( $text );
	}
}
