<?php
/**
 * Phase 4: pack retrieval hits into traceable context for a future answer engine.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Answer_Context_Builder {

	/**
	 * @param array<int,array<string,mixed>> $hits From retriever
	 * @return array<string,mixed>
	 */
	public function build( array $hits ) {
		$sources = array();
		$chunks  = array();
		$facts   = array();
		$seen    = array();

		foreach ( $hits as $h ) {
			$sid = isset( $h['source_id'] ) ? absint( $h['source_id'] ) : 0;
			if ( $sid > 0 && empty( $seen[ 's' . $sid ] ) ) {
				$seen[ 's' . $sid ] = true;
				$sources[]          = array(
					'source_id'   => $sid,
					'title'       => isset( $h['source_title'] ) ? (string) $h['source_title'] : '',
					'source_url'  => isset( $h['source_url'] ) ? (string) $h['source_url'] : '',
					'content_version' => isset( $h['source_content_version'] ) ? absint( $h['source_content_version'] ) : 0,
				);
			}
			if ( isset( $h['chunk_id'] ) && absint( $h['chunk_id'] ) > 0 ) {
				$cid = 'c' . absint( $h['chunk_id'] );
				if ( empty( $seen[ $cid ] ) ) {
					$seen[ $cid ] = true;
					$chunks[]     = array(
						'chunk_id'          => absint( $h['chunk_id'] ),
						'source_id'         => $sid,
						'snippet'           => isset( $h['snippet'] ) ? (string) $h['snippet'] : '',
						'score'             => isset( $h['score'] ) ? (float) $h['score'] : 0.0,
						'hit_kind'          => isset( $h['hit_kind'] ) ? (string) $h['hit_kind'] : '',
					);
				}
			}
			if ( isset( $h['fact_id'] ) && absint( $h['fact_id'] ) > 0 ) {
				$fid = 'f' . absint( $h['fact_id'] );
				if ( empty( $seen[ $fid ] ) ) {
					$seen[ $fid ] = true;
					$facts[]      = array(
						'fact_id'   => absint( $h['fact_id'] ),
						'source_id' => $sid,
						'fact_type' => isset( $h['fact_type'] ) ? (string) $h['fact_type'] : '',
						'fact_key'  => isset( $h['fact_key'] ) ? (string) $h['fact_key'] : '',
						'value'     => isset( $h['fact_value'] ) ? (string) $h['fact_value'] : '',
						'score'     => isset( $h['score'] ) ? (float) $h['score'] : 0.0,
					);
				}
			}
		}

		return array(
			'sources' => $sources,
			'chunks'  => $chunks,
			'facts'   => $facts,
			'hit_count' => count( $hits ),
		);
	}
}
