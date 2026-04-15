<?php
/**
 * Human-readable labels and pipeline hints for the Sources admin UI.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Source_Admin_Presenter {

	/**
	 * @param array<string,mixed> $row Source table row.
	 */
	public static function label_lifecycle_status( $row ) {
		$s = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
		$map = array(
			JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE   => __( 'Active', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::SOURCE_STATUS_INACTIVE => __( 'Inactive', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::SOURCE_STATUS_EXCLUDED => __( 'Excluded', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::SOURCE_STATUS_MISSING  => __( 'Missing', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::SOURCE_STATUS_DISABLED => __( 'Disabled', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::SOURCE_STATUS_PENDING  => __( 'Pending (discovery)', 'jsdw-ai-chat' ),
		);
		return isset( $map[ $s ] ) ? $map[ $s ] : $s;
	}

	/**
	 * @param array<string,mixed> $row Source table row.
	 */
	public static function label_content_status( $row ) {
		$s = isset( $row['content_processing_status'] ) ? sanitize_key( (string) $row['content_processing_status'] ) : '';
		$map = array(
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_PENDING     => __( 'Pending', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK          => __( 'OK', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_FAILED      => __( 'Failed', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNSUPPORTED => __( 'Unsupported', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNAVAILABLE => __( 'Unavailable', 'jsdw-ai-chat' ),
		);
		return isset( $map[ $s ] ) ? $map[ $s ] : $s;
	}

	/**
	 * @param array<string,mixed> $row Source table row.
	 */
	public static function label_knowledge_status( $row ) {
		$s = isset( $row['knowledge_processing_status'] ) ? sanitize_key( (string) $row['knowledge_processing_status'] ) : '';
		$map = array(
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_PENDING => __( 'Pending', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_READY   => __( 'Ready', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED  => __( 'Failed', 'jsdw-ai-chat' ),
		);
		return isset( $map[ $s ] ) ? $map[ $s ] : $s;
	}

	/**
	 * @param array<string,mixed> $row Source table row.
	 */
	public static function label_change_reason( $row ) {
		$r = isset( $row['change_reason'] ) ? sanitize_key( (string) $row['change_reason'] ) : '';
		if ( '' === $r ) {
			return '';
		}
		$map = array(
			JSDW_AI_Chat_DB::CHANGE_DISCOVERED_NEW    => __( 'Newly discovered', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CHANGE_SETTINGS_CHANGED  => __( 'Settings changed', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CHANGE_SOURCE_UPDATED    => __( 'Source updated', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CHANGE_SOURCE_REMOVED    => __( 'Source removed', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CHANGE_MANUALLY_EXCLUDED => __( 'Manually excluded', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CHANGE_MANUALLY_INCLUDED => __( 'Manually included', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CHANGE_RULE_EXCLUDED     => __( 'Excluded by rule', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CHANGE_VERIFY_MISSING    => __( 'Verification: missing', 'jsdw-ai-chat' ),
		);
		return isset( $map[ $r ] ) ? $map[ $r ] : $r;
	}

	/**
	 * @param array<string,mixed> $row Source table row.
	 */
	public static function label_content_reason( $row ) {
		$r = isset( $row['content_processing_reason'] ) ? sanitize_key( (string) $row['content_processing_reason'] ) : '';
		if ( '' === $r ) {
			return '';
		}
		$map = array(
			JSDW_AI_Chat_DB::CONTENT_REASON_NO_CHANGE            => __( 'No content change', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_REASON_TITLE_CHANGED        => __( 'Title changed', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_REASON_CONTENT_CHANGED      => __( 'Body changed', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_REASON_STRUCTURE_CHANGED    => __( 'Structure changed', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_REASON_METADATA_CHANGED     => __( 'Metadata changed', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE   => __( 'Source unavailable', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_REASON_UNSUPPORTED_TYPE     => __( 'Unsupported type', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_DB::CONTENT_REASON_NORMALIZATION_FAILED => __( 'Normalization failed', 'jsdw-ai-chat' ),
		);
		return isset( $map[ $r ] ) ? $map[ $r ] : $r;
	}

	/**
	 * @param array<string,mixed> $row Source table row.
	 */
	public static function label_knowledge_reason( $row ) {
		$r = isset( $row['knowledge_processing_reason'] ) ? sanitize_key( (string) $row['knowledge_processing_reason'] ) : '';
		if ( '' === $r ) {
			return '';
		}
		$map = array(
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_READY           => __( 'Ready', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_NO_CHANGE         => __( 'No change', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_CHUNK_FAILED      => __( 'Chunk step failed', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_FACT_FAILED       => __( 'Fact step failed', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_CONTENT_NOT_OK    => __( 'Content not ready', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_SOURCE_INACTIVE  => __( 'Source inactive', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_REASON_RULES_BLOCKED     => __( 'Blocked by rules', 'jsdw-ai-chat' ),
		);
		return isset( $map[ $r ] ) ? $map[ $r ] : $r;
	}

	/**
	 * @param array<string,mixed> $row Source table row.
	 */
	public static function label_access_visibility( $row ) {
		$v = isset( $row['access_visibility'] ) ? sanitize_key( (string) $row['access_visibility'] ) : '';
		$map = array(
			JSDW_AI_Chat_Source_Visibility::PUBLIC_VIS => __( 'Public', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Source_Visibility::INTERNAL     => __( 'Internal', 'jsdw-ai-chat' ),
			JSDW_AI_Chat_Source_Visibility::ADMIN_ONLY  => __( 'Admin only', 'jsdw-ai-chat' ),
		);
		return isset( $map[ $v ] ) ? $map[ $v ] : ( '' !== $v ? $v : __( 'Unknown', 'jsdw-ai-chat' ) );
	}

	/**
	 * One-line explanation of what the source is waiting on (honest, non-technical).
	 *
	 * @param array<string,mixed> $row Source table row.
	 */
	public static function get_pipeline_summary( array $row ) {
		$lifecycle = isset( $row['status'] ) ? (string) $row['status'] : '';
		if ( in_array( $lifecycle, array( JSDW_AI_Chat_DB::SOURCE_STATUS_EXCLUDED, JSDW_AI_Chat_DB::SOURCE_STATUS_DISABLED, JSDW_AI_Chat_DB::SOURCE_STATUS_MISSING, JSDW_AI_Chat_DB::SOURCE_STATUS_INACTIVE ), true ) ) {
			return sprintf(
				/* translators: %s: lifecycle label */
				__( 'Lifecycle: %s (not in the active processing queue).', 'jsdw-ai-chat' ),
				self::label_lifecycle_status( $row )
			);
		}

		$c = isset( $row['content_processing_status'] ) ? (string) $row['content_processing_status'] : '';
		$k = isset( $row['knowledge_processing_status'] ) ? (string) $row['knowledge_processing_status'] : '';

		if ( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_FAILED === $c ) {
			return __( 'Content processing failed; fix content before knowledge can complete.', 'jsdw-ai-chat' );
		}
		if ( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNSUPPORTED === $c || JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_UNAVAILABLE === $c ) {
			return __( 'Content cannot be processed for this source type or URL.', 'jsdw-ai-chat' );
		}

		if ( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_PENDING === $c ) {
			return __( 'Waiting for content extraction (queued or next cron run).', 'jsdw-ai-chat' );
		}

		if ( JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK === $c ) {
			if ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_FAILED === $k ) {
				return __( 'Content is OK; knowledge step failed.', 'jsdw-ai-chat' );
			}
			if ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_PENDING === $k ) {
				return __( 'Content is OK; waiting for chunks and facts (knowledge processing).', 'jsdw-ai-chat' );
			}
			if ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_READY === $k ) {
				return __( 'Content and knowledge are up to date.', 'jsdw-ai-chat' );
			}
		}

		if ( JSDW_AI_Chat_Knowledge_Constants::KNOWLEDGE_STATUS_PENDING === $k && JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK !== $c ) {
			return __( 'Knowledge stays pending until content processing reaches OK.', 'jsdw-ai-chat' );
		}

		return __( 'See content and knowledge badges for details.', 'jsdw-ai-chat' );
	}

	/**
	 * Whether knowledge actions should be offered for this row.
	 *
	 * @param array<string,mixed> $row Source table row.
	 */
	public static function can_queue_knowledge( array $row ) {
		$c = isset( $row['content_processing_status'] ) ? (string) $row['content_processing_status'] : '';
		$lifecycle = isset( $row['status'] ) ? (string) $row['status'] : '';
		return JSDW_AI_Chat_DB::SOURCE_STATUS_ACTIVE === $lifecycle
			&& JSDW_AI_Chat_DB::CONTENT_PROC_STATUS_OK === $c;
	}

	/**
	 * Best-effort "last activity" timestamp string for display.
	 *
	 * @param array<string,mixed> $row Source table row.
	 * @return string
	 */
	public static function last_activity_gmt( array $row ) {
		$candidates = array();
		foreach ( array( 'last_knowledge_processing_gmt', 'last_content_check_gmt', 'last_wp_modified_gmt', 'updated_at' ) as $k ) {
			if ( ! empty( $row[ $k ] ) ) {
				$candidates[ $k ] = strtotime( (string) $row[ $k ] );
			}
		}
		if ( empty( $candidates ) ) {
			return '';
		}
		arsort( $candidates );
		$best_key = array_key_first( $candidates );
		return isset( $row[ $best_key ] ) ? (string) $row[ $best_key ] : '';
	}
}
