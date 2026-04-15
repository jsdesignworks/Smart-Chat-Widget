<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Source_Rules {
	const REASON_SOURCE_TYPE_DISABLED      = 'source_type_disabled';
	const REASON_EXCLUDED_POST_TYPE        = 'excluded_post_type';
	const REASON_EXCLUDED_POST_ID          = 'excluded_post_id';
	const REASON_BLOCKED_URL_PATTERN       = 'blocked_url_pattern';
	const REASON_PRIVATE_DISALLOWED        = 'private_content_disallowed';
	const REASON_PASSWORD_DISALLOWED       = 'password_protected_disallowed';
	const REASON_DRAFT_DISALLOWED          = 'draft_content_disallowed';
	const REASON_ALLOWED_BY_MANUAL_RULE    = 'allowed_by_manual_rule';
	const REASON_EXCLUDED_BY_MANUAL_RULE   = 'excluded_by_manual_rule';
	const REASON_ALLOWED_BY_POST_TYPE      = 'allowed_by_post_type';
	const REASON_ALLOWED_BY_SOURCE_TYPE    = 'allowed_by_source_type';
	const REASON_EXCLUDED_TAXONOMY_TERM    = 'excluded_taxonomy_term';
	const REASON_ALLOWED_BY_URL_PATTERN    = 'allowed_by_url_pattern';
	const REASON_MANUAL_SOURCE_DISABLED    = 'manual_source_disabled';

	/**
	 * @param array<string,mixed> $candidate
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	public function evaluate_candidate( array $candidate, array $settings ) {
		$sources     = isset( $settings['sources'] ) && is_array( $settings['sources'] ) ? $settings['sources'] : array();
		$source_type = isset( $candidate['source_type'] ) ? (string) $candidate['source_type'] : '';
		$enabled     = array_map( 'strval', isset( $sources['enabled_source_types'] ) ? (array) $sources['enabled_source_types'] : array() );

		if ( ! in_array( $source_type, $enabled, true ) ) {
			return $this->deny( $source_type, self::REASON_SOURCE_TYPE_DISABLED, 'Source type is disabled.', 'enabled_source_types' );
		}

		if ( 'manual' === $source_type ) {
			if ( isset( $candidate['manual_enabled'] ) && ! $candidate['manual_enabled'] ) {
				return $this->deny( $source_type, self::REASON_MANUAL_SOURCE_DISABLED, 'Manual source disabled.', 'manual.enabled' );
			}
			$allow = isset( $candidate['allow_behavior'] ) ? (string) $candidate['allow_behavior'] : 'allow';
			if ( 'deny' === $allow ) {
				return $this->deny( $source_type, self::REASON_EXCLUDED_BY_MANUAL_RULE, 'Manual source marked as deny.', 'manual.allow_behavior' );
			}
			return $this->allow( $source_type, self::REASON_ALLOWED_BY_MANUAL_RULE, 'Manual source explicitly allowed.', 'manual.allow_behavior' );
		}

		if ( isset( $candidate['post_id'] ) ) {
			return $this->evaluate_post_candidate( $candidate, $sources );
		}

		if ( 'taxonomy' === $source_type && isset( $candidate['term_key'] ) ) {
			$excluded = array_map( 'strval', (array) $sources['excluded_taxonomy_terms'] );
			if ( in_array( (string) $candidate['term_key'], $excluded, true ) ) {
				return $this->deny( $source_type, self::REASON_EXCLUDED_TAXONOMY_TERM, 'Taxonomy term is excluded.', 'excluded_taxonomy_terms' );
			}
		}

		if ( isset( $candidate['source_url'] ) && is_string( $candidate['source_url'] ) ) {
			$url_decision = $this->evaluate_url( $candidate['source_url'], $sources, $source_type );
			if ( ! $url_decision['allowed'] ) {
				return $url_decision;
			}
		}

		return $this->allow( $source_type, self::REASON_ALLOWED_BY_SOURCE_TYPE, 'Source type allowed.', 'enabled_source_types' );
	}

	/**
	 * @param array<string,mixed> $candidate
	 * @param array<string,mixed> $sources
	 * @return array<string,mixed>
	 */
	private function evaluate_post_candidate( array $candidate, array $sources ) {
		$source_type  = isset( $candidate['source_type'] ) ? (string) $candidate['source_type'] : 'post';
		$post_id      = absint( $candidate['post_id'] );
		$post_type    = isset( $candidate['post_type'] ) ? (string) $candidate['post_type'] : '';
		$post_status  = isset( $candidate['post_status'] ) ? (string) $candidate['post_status'] : '';
		$has_password = ! empty( $candidate['has_password'] );

		$excluded_post_ids = array_map( 'absint', (array) $sources['excluded_post_ids'] );
		if ( in_array( $post_id, $excluded_post_ids, true ) ) {
			return $this->deny( $source_type, self::REASON_EXCLUDED_POST_ID, 'Post ID excluded by settings.', 'excluded_post_ids' );
		}

		$included_post_ids = array_map( 'absint', (array) $sources['included_post_ids'] );
		if ( ! empty( $included_post_ids ) && ! in_array( $post_id, $included_post_ids, true ) ) {
			return $this->deny( $source_type, self::REASON_EXCLUDED_POST_ID, 'Post ID not in include list.', 'included_post_ids' );
		}

		$excluded_post_types = array_map( 'strval', (array) $sources['excluded_post_types'] );
		if ( in_array( $post_type, $excluded_post_types, true ) ) {
			return $this->deny( $source_type, self::REASON_EXCLUDED_POST_TYPE, 'Post type excluded by settings.', 'excluded_post_types' );
		}

		$included_post_types = array_map( 'strval', (array) $sources['included_post_types'] );
		if ( ! empty( $included_post_types ) && ! in_array( $post_type, $included_post_types, true ) ) {
			return $this->deny( $source_type, self::REASON_EXCLUDED_POST_TYPE, 'Post type not in include list.', 'included_post_types' );
		}

		if ( 'private' === $post_status && empty( $sources['include_private_content'] ) ) {
			return $this->deny( $source_type, self::REASON_PRIVATE_DISALLOWED, 'Private content disabled in settings.', 'include_private_content' );
		}

		if ( in_array( $post_status, array( 'draft', 'future', 'pending' ), true ) && empty( $sources['include_drafts'] ) ) {
			return $this->deny( $source_type, self::REASON_DRAFT_DISALLOWED, 'Draft-like statuses disabled in settings.', 'include_drafts' );
		}

		if ( $has_password && empty( $sources['include_password_protected_content'] ) ) {
			return $this->deny( $source_type, self::REASON_PASSWORD_DISALLOWED, 'Password protected content disabled in settings.', 'include_password_protected_content' );
		}

		$url = isset( $candidate['source_url'] ) ? (string) $candidate['source_url'] : '';
		if ( '' !== $url ) {
			$url_decision = $this->evaluate_url( $url, $sources, $source_type );
			if ( ! $url_decision['allowed'] ) {
				return $url_decision;
			}
		}

		return $this->allow( $source_type, self::REASON_ALLOWED_BY_POST_TYPE, 'Post candidate allowed by source rules.', 'post_rules' );
	}

	/**
	 * @param array<string,mixed> $sources
	 * @return array<string,mixed>
	 */
	private function evaluate_url( $url, array $sources, $source_type ) {
		$blocked = array_map( 'strval', (array) $sources['blocked_url_patterns'] );
		foreach ( $blocked as $pattern ) {
			if ( $this->matches_pattern( $url, $pattern ) ) {
				return $this->deny( $source_type, self::REASON_BLOCKED_URL_PATTERN, 'URL matched blocked pattern.', $pattern );
			}
		}

		$allowed = array_map( 'strval', (array) $sources['allowed_url_patterns'] );
		if ( ! empty( $allowed ) ) {
			foreach ( $allowed as $pattern ) {
				if ( $this->matches_pattern( $url, $pattern ) ) {
					return $this->allow( $source_type, self::REASON_ALLOWED_BY_URL_PATTERN, 'URL matched allowed pattern.', $pattern );
				}
			}

			return $this->deny( $source_type, self::REASON_BLOCKED_URL_PATTERN, 'URL did not match allowed patterns.', 'allowed_url_patterns' );
		}

		return $this->allow( $source_type, self::REASON_ALLOWED_BY_SOURCE_TYPE, 'URL accepted by default.', 'default' );
	}

	private function matches_pattern( $value, $pattern ) {
		$value   = (string) $value;
		$pattern = trim( (string) $pattern );
		if ( '' === $pattern ) {
			return false;
		}

		$regex = '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/i';
		return (bool) preg_match( $regex, $value );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function allow( $source_type, $reason_code, $reason_message, $matched_rule ) {
		return array(
			'allowed'        => true,
			'reason_code'    => (string) $reason_code,
			'reason_message' => (string) $reason_message,
			'matched_rule'   => (string) $matched_rule,
			'source_type'    => (string) $source_type,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function deny( $source_type, $reason_code, $reason_message, $matched_rule ) {
		return array(
			'allowed'        => false,
			'reason_code'    => (string) $reason_code,
			'reason_message' => (string) $reason_message,
			'matched_rule'   => (string) $matched_rule,
			'source_type'    => (string) $source_type,
		);
	}
}
