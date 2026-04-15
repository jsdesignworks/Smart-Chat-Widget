<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Source_Discovery {
	/**
	 * @var JSDW_AI_Chat_Settings
	 */
	private $settings;

	/**
	 * @var JSDW_AI_Chat_Source_Rules
	 */
	private $rules;

	/**
	 * @var JSDW_AI_Chat_Source_Repository
	 */
	private $repository;

	public function __construct( JSDW_AI_Chat_Settings $settings, JSDW_AI_Chat_Source_Rules $rules, JSDW_AI_Chat_Source_Repository $repository ) {
		$this->settings   = $settings;
		$this->rules      = $rules;
		$this->repository = $repository;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function discover_all() {
		$candidates = array_merge(
			$this->discover_post_candidates(),
			$this->discover_taxonomy_candidates(),
			$this->discover_menu_candidates(),
			$this->discover_manual_candidates(),
			$this->discover_rendered_url_candidates()
		);

		return $candidates;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function discover_single_post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$candidate = $this->build_post_candidate( $post );
		return null === $candidate ? array() : array( $candidate );
	}

	private function discover_post_candidates() {
		$settings = $this->settings->get_all();
		$sources  = isset( $settings['sources'] ) ? $settings['sources'] : array();
		$included = array_filter( array_map( 'sanitize_key', (array) $sources['included_post_types'] ) );

		if ( empty( $included ) ) {
			$included = array_values( get_post_types( array( 'public' => true ), 'names' ) );
		}

		$statuses = array( 'publish' );
		if ( ! empty( $sources['include_drafts'] ) ) {
			$statuses = array_merge( $statuses, array( 'draft', 'future', 'pending' ) );
		}
		if ( ! empty( $sources['include_private_content'] ) ) {
			$statuses[] = 'private';
		}

		$candidates = array();
		$paged      = 1;
		do {
			$query = new WP_Query(
				array(
					'post_type'      => $included,
					'post_status'    => array_values( array_unique( $statuses ) ),
					'posts_per_page' => 200,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'paged'          => $paged,
					'no_found_rows'  => false,
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $post ) {
				$candidate = $this->build_post_candidate( $post );
				if ( null !== $candidate ) {
					$candidates[] = $candidate;
				}
			}
			$paged++;
		} while ( $paged <= (int) $query->max_num_pages );

		wp_reset_postdata();
		return $candidates;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function build_post_candidate( WP_Post $post ) {
		$url = get_permalink( $post );
		$base = array(
			'source_type'          => in_array( $post->post_type, array( 'post', 'page' ), true ) ? $post->post_type : 'cpt',
			'source_object_id'     => (int) $post->ID,
			'post_id'              => (int) $post->ID,
			'post_type'            => (string) $post->post_type,
			'post_status'          => (string) $post->post_status,
			'has_password'         => ! empty( $post->post_password ),
			'source_key'           => 'post:' . (int) $post->ID,
			'source_url'           => is_string( $url ) ? $url : '',
			'title'                => get_the_title( $post ),
			'last_wp_modified_gmt' => (string) $post->post_modified_gmt,
			'discovery_context'    => array(
				'origin' => 'wp_post',
			),
			'visibility_flags'     => array(
				'status'       => (string) $post->post_status,
				'has_password' => ! empty( $post->post_password ),
			),
		);
		return $base;
	}

	private function discover_taxonomy_candidates() {
		$settings = $this->settings->get_all();
		$sources  = isset( $settings['sources'] ) ? $settings['sources'] : array();
		$enabled  = array_map( 'strval', (array) $sources['enabled_source_types'] );
		if ( ! in_array( 'taxonomy', $enabled, true ) ) {
			return array();
		}

		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		$candidates = array();
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$candidates[] = array(
					'source_type'          => 'taxonomy',
					'source_object_id'     => (int) $term->term_id,
					'term_key'             => $taxonomy . ':' . (int) $term->term_id,
					'source_key'           => 'taxonomy:' . $taxonomy . ':' . (int) $term->term_id,
					'source_url'           => (string) get_term_link( $term ),
					'title'                => (string) $term->name,
					'last_wp_modified_gmt' => current_time( 'mysql', true ),
					'discovery_context'    => array( 'origin' => 'wp_taxonomy', 'taxonomy' => $taxonomy ),
					'visibility_flags'     => array(),
				);
			}
		}
		return $candidates;
	}

	private function discover_menu_candidates() {
		$settings = $this->settings->get_all();
		$sources  = isset( $settings['sources'] ) ? $settings['sources'] : array();
		if ( empty( $sources['include_menus'] ) ) {
			return array();
		}

		$menus = wp_get_nav_menus();
		if ( ! is_array( $menus ) ) {
			return array();
		}

		$candidates = array();
		foreach ( $menus as $menu ) {
			$candidates[] = array(
				'source_type'          => 'menu',
				'source_object_id'     => (int) $menu->term_id,
				'source_key'           => 'menu:' . (int) $menu->term_id,
				'source_url'           => '',
				'title'                => (string) $menu->name,
				'last_wp_modified_gmt' => current_time( 'mysql', true ),
				'discovery_context'    => array( 'origin' => 'wp_menu' ),
				'visibility_flags'     => array(),
			);
		}
		return $candidates;
	}

	private function discover_manual_candidates() {
		$settings = $this->settings->get_all();
		$sources  = isset( $settings['sources'] ) ? $settings['sources'] : array();
		if ( empty( $sources['allow_manual_sources'] ) ) {
			return array();
		}

		$manual_sources = $this->repository->list_manual_sources();
		$candidates     = array();
		foreach ( $manual_sources as $manual ) {
			$mav = isset( $manual['access_visibility'] ) ? sanitize_key( (string) $manual['access_visibility'] ) : JSDW_AI_Chat_Source_Visibility::INTERNAL;
			if ( ! in_array( $mav, array( JSDW_AI_Chat_Source_Visibility::PUBLIC_VIS, JSDW_AI_Chat_Source_Visibility::INTERNAL, JSDW_AI_Chat_Source_Visibility::ADMIN_ONLY ), true ) ) {
				$mav = JSDW_AI_Chat_Source_Visibility::INTERNAL;
			}
			$candidates[] = array(
				'source_type'               => 'manual',
				'source_object_id'          => isset( $manual['id'] ) ? absint( $manual['id'] ) : null,
				'source_key'                => 'manual:' . sanitize_text_field( (string) $manual['source_key'] ),
				'source_url'                => isset( $manual['source_url'] ) ? (string) $manual['source_url'] : '',
				'title'                     => isset( $manual['title'] ) ? (string) $manual['title'] : '',
				'allow_behavior'            => isset( $manual['allow_behavior'] ) ? (string) $manual['allow_behavior'] : 'allow',
				'manual_enabled'            => ! empty( $manual['enabled'] ),
				'authority_level'           => isset( $manual['authority_override'] ) && '' !== (string) $manual['authority_override'] ? absint( $manual['authority_override'] ) : absint( $sources['manual_source_authority_override'] ),
				'last_wp_modified_gmt'      => current_time( 'mysql', true ),
				'discovery_context'         => array( 'origin' => 'manual_source' ),
				'visibility_flags'          => array(),
				'manual_access_visibility'  => $mav,
			);
		}
		return $candidates;
	}

	private function discover_rendered_url_candidates() {
		$settings = $this->settings->get_all();
		$sources  = isset( $settings['sources'] ) ? $settings['sources'] : array();
		if ( empty( $sources['include_rendered_url_rules'] ) ) {
			return array();
		}

		$patterns   = array_map( 'strval', (array) $sources['allowed_url_patterns'] );
		$candidates = array();
		foreach ( $patterns as $pattern ) {
			if ( '' === $pattern ) {
				continue;
			}
			$candidates[] = array(
				'source_type'          => 'rendered_url',
				'source_object_id'     => null,
				'source_key'           => 'rendered_url:' . md5( $pattern ),
				'source_url'           => $pattern,
				'title'                => 'Rendered URL Pattern',
				'last_wp_modified_gmt' => current_time( 'mysql', true ),
				'discovery_context'    => array( 'origin' => 'rendered_url_rules' ),
				'visibility_flags'     => array(),
			);
		}
		return $candidates;
	}
}
