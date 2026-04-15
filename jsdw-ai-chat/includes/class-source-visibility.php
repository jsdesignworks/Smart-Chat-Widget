<?php
/**
 * Source access classification for public vs internal vs admin-only retrieval.
 *
 * @package JSDW_AI_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Source_Visibility {

	const PUBLIC_VIS     = 'public';
	const INTERNAL       = 'internal';
	const ADMIN_ONLY     = 'admin_only';

	/**
	 * SQL fragment (trusted — values are fixed constants) for chunk/fact search JOIN on sources.
	 *
	 * @param string $context One of JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_*.
	 */
	public static function sql_where_access_visibility( $context ) {
		switch ( $context ) {
			case JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_PUBLIC_SAFE:
				return " AND s.access_visibility = 'public' ";
			case JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_INTERNAL:
				return " AND s.access_visibility IN ('public','internal') ";
			case JSDW_AI_Chat_Knowledge_Constants::RETRIEVAL_ADMIN_DEBUG:
			default:
				return " AND s.access_visibility IN ('public','internal','admin_only') ";
		}
	}

	/**
	 * Whether a URL must never be treated as public web content.
	 *
	 * @param string $url Absolute or relative URL.
	 */
	public static function is_url_blocked_for_public( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return false;
		}
		$lower = strtolower( $url );
		$needles = array(
			'/wp-admin',
			'wp-admin.php',
			'/wp-login',
			'wp-login.php',
			'xmlrpc.php',
			'/wp-json/',
			'wp-cron.php',
		);
		foreach ( $needles as $n ) {
			if ( strpos( $lower, $n ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Compute access_visibility for a discovery candidate or a persisted row subset.
	 *
	 * @param array<string,mixed> $candidate   Discovery candidate or DB row fields.
	 * @param array<string,mixed> $settings    Plugin settings (sources section used).
	 */
	public static function compute_for_candidate( array $candidate, array $settings ) {
		if ( ! empty( $candidate['access_visibility'] ) ) {
			$v = sanitize_key( (string) $candidate['access_visibility'] );
			if ( in_array( $v, array( self::PUBLIC_VIS, self::INTERNAL, self::ADMIN_ONLY ), true ) ) {
				return $v;
			}
		}

		$sources = isset( $settings['sources'] ) && is_array( $settings['sources'] ) ? $settings['sources'] : array();
		$type    = isset( $candidate['source_type'] ) ? (string) $candidate['source_type'] : '';
		$url     = isset( $candidate['source_url'] ) ? (string) $candidate['source_url'] : '';

		if ( self::is_url_blocked_for_public( $url ) ) {
			return self::ADMIN_ONLY;
		}

		if ( 'manual' === $type ) {
			$mv = isset( $candidate['manual_access_visibility'] ) ? sanitize_key( (string) $candidate['manual_access_visibility'] ) : '';
			if ( self::PUBLIC_VIS === $mv ) {
				return self::PUBLIC_VIS;
			}
			if ( self::ADMIN_ONLY === $mv ) {
				return self::ADMIN_ONLY;
			}
			return self::INTERNAL;
		}

		if ( 'rendered_url' === $type ) {
			return self::INTERNAL;
		}

		if ( 'menu' === $type ) {
			return self::INTERNAL;
		}

		if ( 'taxonomy' === $type ) {
			if ( '' !== $url && ! self::is_url_blocked_for_public( $url ) ) {
				return self::PUBLIC_VIS;
			}
			return self::INTERNAL;
		}

		$flags = isset( $candidate['visibility_flags'] ) && is_array( $candidate['visibility_flags'] )
			? $candidate['visibility_flags']
			: array();
		$status       = isset( $flags['status'] ) ? (string) $flags['status'] : '';
		$has_password = ! empty( $flags['has_password'] );

		if ( 'publish' !== $status || $has_password ) {
			return self::INTERNAL;
		}

		if ( ! empty( $sources['include_custom_fields'] ) ) {
			$allowed = isset( $sources['allowed_custom_field_keys'] ) ? array_filter( (array) $sources['allowed_custom_field_keys'] ) : array();
			if ( empty( $allowed ) && in_array( $type, array( 'post', 'page', 'cpt' ), true ) ) {
				return self::INTERNAL;
			}
		}

		if ( in_array( $type, array( 'post', 'page', 'cpt' ), true ) ) {
			if ( ! self::is_url_blocked_for_public( $url ) ) {
				return self::PUBLIC_VIS;
			}
			return self::INTERNAL;
		}

		return self::INTERNAL;
	}

	/**
	 * Sanitize a stored value from DB.
	 *
	 * @param string|null $value Raw column value.
	 */
	public static function sanitize_stored( $value ) {
		$v = sanitize_key( (string) $value );
		if ( in_array( $v, array( self::PUBLIC_VIS, self::INTERNAL, self::ADMIN_ONLY ), true ) ) {
			return $v;
		}
		return self::INTERNAL;
	}
}
