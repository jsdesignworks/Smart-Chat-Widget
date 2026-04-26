<?php
/**
 * Phase 4: deterministic text chunking from normalized Phase-3 content.
 *
 * Strategy (see make_chunk_row for hashes):
 * - Paragraphs from body (blank-line split); headings in body match headings[] in order to set chunk.heading.
 * - Paragraphs longer than CHUNK_MAX_CHARS are split (sentence boundary, then hard split).
 * - Greedy merge into chunks: grow buffer until adding next para would exceed CHUNK_MAX or buffer >= CHUNK_MIN and we need to close.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Content_Chunker {
	const CHUNK_MIN_CHARS = 240;
	const CHUNK_MAX_CHARS = 3600;

	/**
	 * @param array<string,mixed> $package Keys: source_id, content_version, title, body, headings (string[])
	 * @return array<int,array<string,mixed>>
	 */
	public function chunk( array $package ) {
		$source_id = isset( $package['source_id'] ) ? absint( $package['source_id'] ) : 0;
		$version   = isset( $package['content_version'] ) ? absint( $package['content_version'] ) : 1;
		$title     = isset( $package['title'] ) ? trim( (string) $package['title'] ) : '';
		$body      = isset( $package['body'] ) ? (string) $package['body'] : '';
		$headings  = isset( $package['headings'] ) && is_array( $package['headings'] ) ? array_values( array_filter( array_map( 'trim', $package['headings'] ) ) ) : array();

		$paragraphs = $this->split_paragraphs( $body );
		if ( '' !== $title && empty( $paragraphs ) ) {
			$paragraphs = array( $title );
		}

		$expanded = array();
		foreach ( $paragraphs as $p ) {
			$p = trim( $p );
			if ( '' === $p ) {
				continue;
			}
			if ( strlen( $p ) > self::CHUNK_MAX_CHARS ) {
				foreach ( $this->hard_split( $p ) as $sp ) {
					$expanded[] = $sp;
				}
			} else {
				$expanded[] = $p;
			}
		}

		if ( empty( $expanded ) ) {
			return array();
		}

		$chunks         = array();
		$buffer         = '';
		$chunk_index    = 0;
		$heading_idx    = 0;
		$active_heading = '';

		foreach ( $expanded as $para ) {
			if ( $this->paragraph_is_next_document_heading( $para, $headings, $heading_idx ) ) {
				if ( '' !== $buffer ) {
					$chunks[] = $this->make_chunk_row( $buffer, $chunk_index, $title, $active_heading, $source_id, $version, $body );
					++$chunk_index;
					$buffer = '';
				}
				$active_heading = (string) $headings[ $heading_idx ];
				++$heading_idx;
				continue;
			}

			$candidate = '' === $buffer ? $para : $buffer . "\n\n" . $para;
			if ( strlen( $candidate ) <= self::CHUNK_MAX_CHARS ) {
				$buffer = $candidate;
				if ( strlen( $buffer ) >= self::CHUNK_MIN_CHARS ) {
					$chunks[] = $this->make_chunk_row( $buffer, $chunk_index, $title, $active_heading, $source_id, $version, $body );
					++$chunk_index;
					$buffer = '';
				}
				continue;
			}
			if ( '' !== $buffer ) {
				$chunks[] = $this->make_chunk_row( $buffer, $chunk_index, $title, $active_heading, $source_id, $version, $body );
				++$chunk_index;
				$buffer   = '';
			}
			if ( strlen( $para ) >= self::CHUNK_MIN_CHARS ) {
				$chunks[] = $this->make_chunk_row( $para, $chunk_index, $title, $active_heading, $source_id, $version, $body );
				++$chunk_index;
			} else {
				$buffer = $para;
			}
		}
		if ( '' !== $buffer ) {
			$chunks[] = $this->make_chunk_row( $buffer, $chunk_index, $title, $active_heading, $source_id, $version, $body );
		}

		return $chunks;
	}

	/**
	 * @param array<int,string> $headings
	 */
	private function paragraph_is_next_document_heading( $para, array $headings, $heading_idx ) {
		if ( $heading_idx >= count( $headings ) ) {
			return false;
		}
		$expected = isset( $headings[ $heading_idx ] ) ? (string) $headings[ $heading_idx ] : '';
		if ( '' === $expected ) {
			return false;
		}
		return $this->normalize_chunk_text( $para ) === $this->normalize_chunk_text( $expected );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function make_chunk_row( $text, $index, $title, $active_heading, $source_id, $version, $body ) {
		$text = trim( (string) $text );
		$norm = $this->normalize_chunk_text( $text );
		$th   = hash( 'sha256', $norm );
		$ch   = hash( 'sha256', $source_id . '|' . $version . '|' . $index . '|' . $th );
		$tok  = max( 1, (int) ceil( strlen( $norm ) / 4 ) );

		$heading = trim( (string) $active_heading );

		$pos_start = null;
		$pos_end   = null;
		if ( '' !== $body && '' !== $norm ) {
			$needle = substr( $norm, 0, min( 80, strlen( $norm ) ) );
			$p      = strpos( $body, $needle );
			if ( false !== $p ) {
				$pos_start = $p;
				$pos_end   = $p + strlen( $norm );
			}
		}

		return array(
			'chunk_index'     => $index,
			'section_label'   => ( 0 === (int) $index && '' !== $title ) ? $title : null,
			'heading'         => ( '' !== $heading ) ? $heading : null,
			'raw_text'        => $text,
			'normalized_text' => $norm,
			'text_hash'       => $th,
			'chunk_hash'      => $ch,
			'token_estimate'  => $tok,
			'position_start'  => $pos_start,
			'position_end'    => $pos_end,
		);
	}

	private function normalize_chunk_text( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = preg_replace( '/[ \t]+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * @return array<int,string>
	 */
	private function split_paragraphs( $body ) {
		$body = (string) $body;
		if ( '' === $body ) {
			return array();
		}
		$parts = preg_split( '/\n\s*\n+/u', $body );
		return is_array( $parts ) ? array_values( array_filter( array_map( 'trim', $parts ) ) ) : array();
	}

	/**
	 * @return array<int,string>
	 */
	private function hard_split( $text ) {
		$out   = array();
		$len   = strlen( $text );
		$start = 0;
		while ( $start < $len ) {
			$take  = min( self::CHUNK_MAX_CHARS, $len - $start );
			$slice = substr( $text, $start, $take );
			if ( $start + $take < $len ) {
				$break = strrpos( $slice, '. ' );
				if ( false !== $break && $break > self::CHUNK_MIN_CHARS / 2 ) {
					$slice = substr( $text, $start, $break + 1 );
				}
			}
			$out[] = $slice;
			$start += strlen( $slice );
		}
		return $out;
	}
}
