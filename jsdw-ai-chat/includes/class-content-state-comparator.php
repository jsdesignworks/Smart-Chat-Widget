<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Content_State_Comparator {
	/**
	 * @param array<string,string|null> $previous
	 * @param array<string,string>       $next
	 * @return array<string,mixed>
	 */
	public function compare( array $previous, array $next ) {
		$keys = array( 'title_hash', 'content_hash', 'structure_hash', 'metadata_hash' );
		$prev_empty = true;
		foreach ( $keys as $k ) {
			if ( ! empty( $previous[ $k ] ) ) {
				$prev_empty = false;
				break;
			}
		}

		if ( $prev_empty ) {
			return array(
				'outcome'              => 'baseline',
				'primary_reason'       => JSDW_AI_Chat_DB::CONTENT_REASON_CONTENT_CHANGED,
				'material_for_reindex' => true,
				'changed'              => array( 'title' => true, 'content' => true, 'structure' => true, 'metadata' => true ),
			);
		}

		$changed = array(
			'title'     => $this->field_changed( $previous, $next, 'title_hash' ),
			'content'   => $this->field_changed( $previous, $next, 'content_hash' ),
			'structure' => $this->field_changed( $previous, $next, 'structure_hash' ),
			'metadata'  => $this->field_changed( $previous, $next, 'metadata_hash' ),
		);

		$any = $changed['title'] || $changed['content'] || $changed['structure'] || $changed['metadata'];
		if ( ! $any ) {
			return array(
				'outcome'              => 'no_change',
				'primary_reason'       => JSDW_AI_Chat_DB::CONTENT_REASON_NO_CHANGE,
				'material_for_reindex' => false,
				'changed'              => $changed,
			);
		}

		$material = $changed['title'] || $changed['content'] || $changed['structure'];
		$primary  = JSDW_AI_Chat_DB::CONTENT_REASON_METADATA_CHANGED;
		if ( $changed['title'] ) {
			$primary = JSDW_AI_Chat_DB::CONTENT_REASON_TITLE_CHANGED;
		} elseif ( $changed['content'] ) {
			$primary = JSDW_AI_Chat_DB::CONTENT_REASON_CONTENT_CHANGED;
		} elseif ( $changed['structure'] ) {
			$primary = JSDW_AI_Chat_DB::CONTENT_REASON_STRUCTURE_CHANGED;
		} elseif ( $changed['metadata'] ) {
			$primary = JSDW_AI_Chat_DB::CONTENT_REASON_METADATA_CHANGED;
		}

		return array(
			'outcome'              => 'changed',
			'primary_reason'       => $primary,
			'material_for_reindex' => $material,
			'changed'              => $changed,
		);
	}

	/**
	 * @param array<string,string|null> $previous
	 * @param array<string,string>      $next
	 */
	private function field_changed( array $previous, array $next, $key ) {
		$a = isset( $previous[ $key ] ) ? (string) $previous[ $key ] : '';
		$b = isset( $next[ $key ] ) ? (string) $next[ $key ] : '';
		return $a !== $b;
	}
}
