<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Answer_Trace {
	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public function build_snapshot( array $payload ) {
		$query            = (string) ( $payload['query'] ?? '' );
		$normalized_query = (string) ( $payload['normalized_query'] ?? '' );
		$retrieval_stats  = isset( $payload['retrieval_stats'] ) && is_array( $payload['retrieval_stats'] ) ? $payload['retrieval_stats'] : array();
		$confidence       = (string) ( $payload['confidence'] ?? '' );
		$answer_status    = (string) ( $payload['answer_status'] ?? '' );
		$sources          = isset( $payload['sources'] ) && is_array( $payload['sources'] ) ? $payload['sources'] : array();
		$chunks           = isset( $payload['chunks'] ) && is_array( $payload['chunks'] ) ? $payload['chunks'] : array();
		$facts            = isset( $payload['facts'] ) && is_array( $payload['facts'] ) ? $payload['facts'] : array();
		$trace            = isset( $payload['trace'] ) && is_array( $payload['trace'] ) ? $payload['trace'] : array();
		$engine_version   = (string) ( $payload['engine_version'] ?? JSDW_AI_CHAT_DB_SCHEMA_VERSION );
		$sources          = array_slice( array_values( $sources ), 0, 20 );
		$chunks           = array_slice( array_values( $chunks ), 0, 20 );
		$facts            = array_slice( array_values( $facts ), 0, 20 );
		foreach ( $chunks as $idx => $chunk ) {
			if ( is_array( $chunk ) && isset( $chunk['snippet'] ) ) {
				$snippet = (string) $chunk['snippet'];
				$chunks[ $idx ]['snippet'] = function_exists( 'mb_substr' ) ? mb_substr( $snippet, 0, 500 ) : substr( $snippet, 0, 500 );
			}
		}
		foreach ( $facts as $idx => $fact ) {
			if ( is_array( $fact ) && isset( $fact['value'] ) ) {
				$value = (string) $fact['value'];
				$facts[ $idx ]['value'] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 500 ) : substr( $value, 0, 500 );
			}
		}

		return array(
			'query'            => $query,
			'normalized_query' => $normalized_query,
			'retrieval_stats'  => $retrieval_stats,
			'confidence'       => $confidence,
			'answer_status'    => $answer_status,
			'sources'          => $sources,
			'chunks'           => $chunks,
			'facts'            => $facts,
			'trace'            => $trace,
			'meta'             => array(
				'engine_version' => $engine_version,
				'timestamp'      => gmdate( 'c' ),
			),
		);
	}
}
