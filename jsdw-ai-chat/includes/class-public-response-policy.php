<?php
/**
 * Centralized shaping for non-debug (public-safe) chat API responses.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Public_Response_Policy {

	/**
	 * Strip fields that must never appear in public chat JSON responses.
	 *
	 * @param array<string,mixed> $formatted Formatter output (non-debug).
	 * @return array<string,mixed>
	 */
	public static function apply( array $formatted ) {
		$allowed = array(
			'query',
			'normalized_query',
			'response_mode',
			'confidence',
			'answer_status',
			'answer_text',
			'answer_type',
			'answer_strategy',
			'clarification_question',
			'generated_at',
			'used_ai_phrase_assist',
		);
		$out = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $formatted ) ) {
				$out[ $key ] = $formatted[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Remove internal evidence from snapshot stored or logged for public retrieval paths.
	 *
	 * @param array<string,mixed> $snapshot Snapshot from JSDW_AI_Chat_Answer_Trace::build_snapshot.
	 * @return array<string,mixed>
	 */
	public static function sanitize_snapshot_for_public_path( array $snapshot ) {
		$sources = isset( $snapshot['sources'] ) && is_array( $snapshot['sources'] ) ? $snapshot['sources'] : array();
		$clean   = array();
		foreach ( array_slice( array_values( $sources ), 0, 20 ) as $src ) {
			if ( ! is_array( $src ) ) {
				continue;
			}
			$clean[] = array(
				'title' => isset( $src['title'] ) ? (string) $src['title'] : '',
			);
		}
		$snapshot['sources'] = $clean;
		$snapshot['chunks']  = array();
		$snapshot['facts']   = array();
		if ( isset( $snapshot['trace'] ) && is_array( $snapshot['trace'] ) ) {
			$snapshot['trace'] = array(
				'hit_count' => isset( $snapshot['trace']['hit_count'] ) ? (int) $snapshot['trace']['hit_count'] : 0,
			);
		}
		if ( isset( $snapshot['retrieval_stats'] ) && is_array( $snapshot['retrieval_stats'] ) ) {
			$rs = $snapshot['retrieval_stats'];
			$snapshot['retrieval_stats'] = array(
				'hit_count' => isset( $rs['hit_count'] ) ? (int) $rs['hit_count'] : 0,
			);
		}
		return $snapshot;
	}
}
