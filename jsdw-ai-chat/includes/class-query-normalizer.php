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
	 * Common English terms that add noise to substring (LIKE) retrieval.
	 *
	 * @var array<string,bool>
	 */
	private static $stopwords = null;

	/**
	 * @return array<string,bool>
	 */
	private static function stopword_set() {
		if ( null !== self::$stopwords ) {
			return self::$stopwords;
		}
		$words = array(
			'a', 'an', 'and', 'are', 'as', 'at', 'be', 'been', 'but', 'by', 'can', 'could', 'did', 'do', 'does',
			'doing', 'for', 'from', 'had', 'has', 'have', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its',
			'just', 'may', 'me', 'might', 'my', 'no', 'not', 'of', 'on', 'or', 'our', 'please', 'so', 'some',
			'such', 'tell', 'than', 'that', 'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this',
			'to', 'too', 'up', 'us', 'very', 'want', 'was', 'we', 'were', 'what', 'when', 'where', 'which',
			'who', 'whom', 'why', 'will', 'with', 'would', 'you', 'your', 'yours',
		);
		self::$stopwords = array_fill_keys( $words, true );
		return self::$stopwords;
	}

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
		$raw_terms = array_filter( array_unique( explode( ' ', $q ) ) );
		$stops     = self::stopword_set();
		$terms     = array();
		foreach ( $raw_terms as $t ) {
			$t = (string) $t;
			if ( '' === $t || isset( $stops[ $t ] ) ) {
				continue;
			}
			$terms[] = $t;
			if ( strlen( $t ) >= 5 && 's' === substr( $t, -1 ) && 's' !== substr( $t, -2, 1 ) ) {
				$singular = substr( $t, 0, -1 );
				if ( strlen( $singular ) >= 2 && ! isset( $stops[ $singular ] ) ) {
					$terms[] = $singular;
				}
			}
		}
		$terms = array_values( array_unique( $terms ) );
		return array(
			'normalized' => $q,
			'terms'      => $terms,
		);
	}
}
