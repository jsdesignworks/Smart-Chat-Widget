<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Answer_Formatter {
	/**
	 * @param array<string,mixed> $engine_result
	 * @param bool $debug
	 * @return array<string,mixed>
	 */
	public function format( array $engine_result, $debug = false ) {
		$out = array(
			'query'          => (string) ( $engine_result['query'] ?? '' ),
			'normalized_query' => (string) ( $engine_result['normalized_query'] ?? '' ),
			'response_mode'  => (string) ( $engine_result['response_mode'] ?? 'strict_local_only' ),
			'confidence'     => (string) ( $engine_result['confidence'] ?? '' ),
			'answer_status'  => (string) ( $engine_result['answer_status'] ?? '' ),
			'answer_text'    => (string) ( $engine_result['answer_text'] ?? '' ),
			'answer_type'    => (string) ( $engine_result['answer_type'] ?? '' ),
			'answer_strategy'=> (string) ( $engine_result['answer_strategy'] ?? '' ),
			'clarification_question' => (string) ( $engine_result['clarification_question'] ?? '' ),
			'generated_at'   => (string) ( $engine_result['generated_at'] ?? gmdate( 'c' ) ),
			'used_ai_phrase_assist' => ! empty( $engine_result['used_ai_phrase_assist'] ),
		);

		if ( $debug ) {
			$out['retrieval_stats'] = isset( $engine_result['retrieval_stats'] ) && is_array( $engine_result['retrieval_stats'] ) ? $engine_result['retrieval_stats'] : array();
			$out['sources'] = isset( $engine_result['sources'] ) && is_array( $engine_result['sources'] ) ? $engine_result['sources'] : array();
			$out['chunks']  = isset( $engine_result['chunks'] ) && is_array( $engine_result['chunks'] ) ? $engine_result['chunks'] : array();
			$out['facts']   = isset( $engine_result['facts'] ) && is_array( $engine_result['facts'] ) ? $engine_result['facts'] : array();
			$out['trace']   = isset( $engine_result['trace'] ) && is_array( $engine_result['trace'] ) ? $engine_result['trace'] : array();
			$out['guard']   = isset( $engine_result['guard'] ) && is_array( $engine_result['guard'] ) ? $engine_result['guard'] : array();
			return $out;
		}

		return JSDW_AI_Chat_Public_Response_Policy::apply( $out );
	}
}
