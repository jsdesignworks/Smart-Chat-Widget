<?php
/**
 * Phase 4: conservative deterministic fact extraction (no AI).
 *
 * Extracts: page title, headings list, URLs, emails, phone-like strings,
 * FAQ lines starting with Q:/A: (case-insensitive), optional hours lines matching a strict weekday pattern.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Fact_Extractor {
	/**
	 * @param array<string,mixed> $norm Normalized package: title, body, headings[]
	 * @return array<int,array<string,mixed>> Fact rows for Fact_Repository::insert_fact_batch (chunk_id filled later)
	 */
	public function extract( array $norm ) {
		$facts = array();
		$title = isset( $norm['title'] ) ? trim( (string) $norm['title'] ) : '';
		if ( '' !== $title ) {
			$facts[] = $this->row(
				JSDW_AI_Chat_Knowledge_Constants::FACT_TYPE_TITLE,
				'title',
				$title
			);
		}
		if ( ! empty( $norm['headings'] ) && is_array( $norm['headings'] ) ) {
			$i = 0;
			foreach ( $norm['headings'] as $h ) {
				$h = trim( (string) $h );
				if ( '' === $h ) {
					continue;
				}
				$facts[] = $this->row(
					JSDW_AI_Chat_Knowledge_Constants::FACT_TYPE_HEADING,
					'heading_' . $i,
					$h
				);
				++$i;
			}
		}
		$body = isset( $norm['body'] ) ? (string) $norm['body'] : '';
		$this->extract_patterns( $body, $facts );

		foreach ( $facts as $k => $f ) {
			$facts[ $k ]['fact_hash'] = hash( 'sha256', ( $f['fact_type'] ?? '' ) . '|' . ( $f['fact_key'] ?? '' ) . '|' . ( $f['fact_value'] ?? '' ) );
		}

		return $facts;
	}

	/**
	 * @param array<int,array<string,mixed>> $facts
	 */
	private function extract_patterns( $body, array &$facts ) {
		if ( '' === $body ) {
			return;
		}
		if ( preg_match_all( '#https?://[^\s]+#u', $body, $m ) ) {
			foreach ( array_unique( $m[0] ) as $i => $url ) {
				$facts[] = $this->row( JSDW_AI_Chat_Knowledge_Constants::FACT_TYPE_URL, 'url_' . $i, $url );
			}
		}
		if ( preg_match_all( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $body, $em ) ) {
			foreach ( array_unique( $em[0] ) as $i => $eml ) {
				$facts[] = $this->row( JSDW_AI_Chat_Knowledge_Constants::FACT_TYPE_EMAIL, 'email_' . $i, $eml );
			}
		}
		if ( preg_match_all( '/\+?\d[\d\s().-]{8,}\d/', $body, $ph ) ) {
			foreach ( array_unique( $ph[0] ) as $i => $phone ) {
				$digits = preg_replace( '/\D+/', '', $phone );
				if ( strlen( $digits ) >= 10 ) {
					$facts[] = $this->row( JSDW_AI_Chat_Knowledge_Constants::FACT_TYPE_PHONE, 'phone_' . $i, $phone );
				}
			}
		}
		$lines = preg_split( '/\R/u', $body );
		if ( is_array( $lines ) ) {
			foreach ( $lines as $li => $line ) {
				$line = trim( (string) $line );
				if ( preg_match( '/^Q\s*[:.)-]\s*(.+)$/iu', $line, $mm ) ) {
					$facts[] = $this->row( JSDW_AI_Chat_Knowledge_Constants::FACT_TYPE_FAQ_Q, 'faq_q_' . $li, $mm[1] );
				}
				if ( preg_match( '/^A\s*[:.)-]\s*(.+)$/iu', $line, $mm ) ) {
					$facts[] = $this->row( JSDW_AI_Chat_Knowledge_Constants::FACT_TYPE_FAQ_A, 'faq_a_' . $li, $mm[1] );
				}
				if ( preg_match( '/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)(day)?(\s*-\s*(Mon|Tue|Wed|Thu|Fri|Sat|Sun)(day)?)?\s+\d{1,2}:\d{2}\s*(am|pm)?\s*-\s*\d{1,2}:\d{2}\s*(am|pm)?/iu', $line ) ) {
					$facts[] = $this->row( JSDW_AI_Chat_Knowledge_Constants::FACT_TYPE_HOURS, 'hours_' . $li, $line );
				}
			}
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function row( $type, $key, $value ) {
		return array(
			'fact_type'  => $type,
			'fact_key'   => sanitize_text_field( (string) $key ),
			'fact_value' => (string) $value,
			'chunk_id'   => null,
		);
	}
}
