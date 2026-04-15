<?php
/**
 * Phase 4: deterministic keyword retrieval over chunks and facts.
 *
 * Ranking: title match > heading > section > body; fact exact key/value boost; higher source_content_version preferred.
 * Per chunk/fact id, the highest score across query terms is kept (deduplicated).
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Knowledge_Retriever {

	/**
	 * @var JSDW_AI_Chat_DB
	 */
	private $db;

	/**
	 * @var JSDW_AI_Chat_Chunk_Repository
	 */
	private $chunks;

	/**
	 * @var JSDW_AI_Chat_Fact_Repository
	 */
	private $facts;

	/**
	 * @var JSDW_AI_Chat_Source_Repository
	 */
	private $sources;

	/**
	 * @var JSDW_AI_Chat_Query_Normalizer
	 */
	private $normalizer;

	public function __construct(
		JSDW_AI_Chat_DB $db,
		JSDW_AI_Chat_Chunk_Repository $chunks,
		JSDW_AI_Chat_Fact_Repository $facts,
		JSDW_AI_Chat_Source_Repository $sources,
		JSDW_AI_Chat_Query_Normalizer $normalizer
	) {
		$this->db         = $db;
		$this->chunks     = $chunks;
		$this->facts      = $facts;
		$this->sources    = $sources;
		$this->normalizer = $normalizer;
	}

	/**
	 * @param string $retrieval_context One of JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_*.
	 * @return array<int,array<string,mixed>>
	 */
	public function search( $query, $limit = 30, $retrieval_context = JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_INTERNAL ) {
		$n     = $this->normalizer->normalize( $query );
		$terms = $n['terms'];
		$lim   = min( 100, max( 1, absint( $limit ) ) );

		if ( empty( $terms ) ) {
			return array();
		}

		$chunk_best = array();
		$fact_best  = array();

		foreach ( $terms as $term ) {
			if ( strlen( $term ) < 2 ) {
				continue;
			}
			$crows = $this->chunks->search_chunks_text( $term, $lim, $retrieval_context );
			foreach ( $crows as $row ) {
				$hit = $this->score_chunk_hit( $row, $term, $n['normalized'] );
				$cid = isset( $hit['chunk_id'] ) ? absint( $hit['chunk_id'] ) : 0;
				if ( $cid <= 0 ) {
					continue;
				}
				$sc = isset( $hit['score'] ) ? (float) $hit['score'] : 0.0;
				if ( ! isset( $chunk_best[ $cid ] ) || $sc > (float) $chunk_best[ $cid ]['score'] ) {
					$chunk_best[ $cid ] = $hit;
				}
			}
			$frows = $this->facts->search_facts_value( $term, $lim, $retrieval_context );
			foreach ( $frows as $row ) {
				$hit = $this->score_fact_hit( $row, $term );
				$fid = isset( $hit['fact_id'] ) ? absint( $hit['fact_id'] ) : 0;
				if ( $fid <= 0 ) {
					continue;
				}
				$sc = isset( $hit['score'] ) ? (float) $hit['score'] : 0.0;
				if ( ! isset( $fact_best[ $fid ] ) || $sc > (float) $fact_best[ $fid ]['score'] ) {
					$fact_best[ $fid ] = $hit;
				}
			}
		}

		$hits = array_merge( array_values( $chunk_best ), array_values( $fact_best ) );

		usort(
			$hits,
			function ( $a, $b ) {
				$sa = isset( $a['score'] ) ? (float) $a['score'] : 0;
				$sb = isset( $b['score'] ) ? (float) $b['score'] : 0;
				if ( $sa === $sb ) {
					return 0;
				}
				return ( $sa > $sb ) ? -1 : 1;
			}
		);

		return array_slice( $hits, 0, $lim );
	}

	/**
	 * Deterministic stats for JSDW_AI_Chat_Confidence_Policy::evaluate().
	 *
	 * @param array<int,array<string,mixed>> $hits From search() (already deduplicated).
	 * @return array<string,mixed>
	 */
	public function aggregate_hit_stats( array $hits ) {
		$best    = 0.0;
		$by_src  = array();
		$has_title = false;

		foreach ( $hits as $h ) {
			$best = max( $best, isset( $h['score'] ) ? (float) $h['score'] : 0.0 );
			if ( ! empty( $h['matched_source_title'] ) ) {
				$has_title = true;
			}
			$sid = isset( $h['source_id'] ) ? absint( $h['source_id'] ) : 0;
			if ( $sid > 0 ) {
				$by_src[ $sid ] = isset( $by_src[ $sid ] ) ? $by_src[ $sid ] + 1 : 1;
			}
		}

		$count      = count( $hits );
		$distinct   = count( $by_src );
		$max_per    = 0;
		foreach ( $by_src as $c ) {
			$max_per = max( $max_per, absint( $c ) );
		}

		return array(
			'hit_count'               => $count,
			'best_score'              => $best,
			'has_title_hit'           => $has_title,
			'distinct_source_count'   => $distinct,
			'max_hits_per_source'     => $max_per,
			'ambiguous'               => $count > 8,
		);
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function score_chunk_hit( array $row, $term, $full_norm ) {
		$text = isset( $row['normalized_text'] ) ? (string) $row['normalized_text'] : '';
		$hd   = isset( $row['heading'] ) ? (string) $row['heading'] : '';
		$sec  = isset( $row['section_label'] ) ? (string) $row['section_label'] : '';
		$st   = isset( $row['source_title'] ) ? strtolower( (string) $row['source_title'] ) : '';
		$ver  = isset( $row['source_content_version'] ) ? absint( $row['source_content_version'] ) : 1;

		$score = 1.0 + min( 3.0, $ver / 100.0 );
		$tl    = strtolower( $term );
		$title_match = ( $st !== '' && strpos( $st, $tl ) !== false );
		$hd_match    = ( $hd !== '' && strpos( strtolower( $hd ), $tl ) !== false );
		$sec_match   = ( $sec !== '' && strpos( strtolower( $sec ), $tl ) !== false );
		$body_match  = ( strpos( strtolower( $text ), $tl ) !== false );
		$full_norm_match = ( $full_norm !== '' && strpos( strtolower( $text ), $full_norm ) !== false );

		if ( $title_match ) {
			$score += 20.0;
		}
		if ( $hd_match ) {
			$score += 12.0;
		}
		if ( $sec_match ) {
			$score += 8.0;
		}
		if ( $body_match ) {
			$score += 4.0;
		}
		if ( $full_norm_match ) {
			$score += 6.0;
		}

		$kind = JSDW_AI_Chat_Knowledge_Constants::HIT_KIND_CHUNK_BODY;
		if ( $title_match && ! $hd_match && ! $sec_match && ! $body_match && ! $full_norm_match ) {
			$kind = JSDW_AI_Chat_Knowledge_Constants::HIT_KIND_SOURCE_TITLE;
		} elseif ( $hd_match ) {
			$kind = JSDW_AI_Chat_Knowledge_Constants::HIT_KIND_CHUNK_HEADING;
		} elseif ( $sec_match ) {
			$kind = JSDW_AI_Chat_Knowledge_Constants::HIT_KIND_CHUNK_SECTION;
		}

		return array(
			'score'                  => $score,
			'hit_kind'               => $kind,
			'chunk_id'               => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
			'source_id'              => isset( $row['source_id'] ) ? absint( $row['source_id'] ) : 0,
			'source_title'           => isset( $row['source_title'] ) ? (string) $row['source_title'] : '',
			'source_url'             => isset( $row['source_url'] ) ? (string) $row['source_url'] : '',
			'source_content_version' => $ver,
			'snippet'                => mb_substr( $text, 0, 400 ),
			'fact_id'                => 0,
			'matched_source_title'   => $title_match,
		);
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function score_fact_hit( array $row, $term ) {
		$val = isset( $row['fact_value'] ) ? strtolower( (string) $row['fact_value'] ) : '';
		$key = isset( $row['fact_key'] ) ? strtolower( (string) $row['fact_key'] ) : '';
		$tl  = strtolower( $term );
		$st  = isset( $row['source_title'] ) ? strtolower( (string) $row['source_title'] ) : '';
		$title_match = ( $st !== '' && strpos( $st, $tl ) !== false );

		$score = 5.0;
		if ( $val === $tl || $key === $tl ) {
			$score += 25.0;
		} elseif ( strpos( $val, $tl ) !== false || strpos( $key, $tl ) !== false ) {
			$score += 10.0;
		} elseif ( $title_match ) {
			$score += 6.0;
		}
		$ver = isset( $row['source_content_version'] ) ? absint( $row['source_content_version'] ) : 1;
		$score += min( 3.0, $ver / 100.0 );

		return array(
			'score'                => $score,
			'hit_kind'             => JSDW_AI_Chat_Knowledge_Constants::HIT_KIND_FACT,
			'fact_id'              => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
			'fact_type'            => isset( $row['fact_type'] ) ? (string) $row['fact_type'] : '',
			'fact_key'             => isset( $row['fact_key'] ) ? (string) $row['fact_key'] : '',
			'fact_value'           => isset( $row['fact_value'] ) ? (string) $row['fact_value'] : '',
			'source_id'            => isset( $row['source_id'] ) ? absint( $row['source_id'] ) : 0,
			'source_title'         => isset( $row['source_title'] ) ? (string) $row['source_title'] : '',
			'source_url'           => isset( $row['source_url'] ) ? (string) $row['source_url'] : '',
			'chunk_id'             => 0,
			'matched_source_title' => $title_match,
		);
	}
}
