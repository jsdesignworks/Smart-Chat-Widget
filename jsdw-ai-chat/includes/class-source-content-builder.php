<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JSDW_AI_Chat_Source_Content_Builder {
	/**
	 * @var JSDW_AI_Chat_Source_Repository
	 */
	private $repository;

	public function __construct( JSDW_AI_Chat_Source_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * @param array<string,mixed> $source_row
	 * @return array<string,mixed>
	 */
	public function build( array $source_row ) {
		$type = isset( $source_row['source_type'] ) ? (string) $source_row['source_type'] : '';

		switch ( $type ) {
			case 'post':
			case 'page':
			case 'cpt':
				return $this->build_post( $source_row );
			case 'taxonomy':
				return $this->build_taxonomy( $source_row );
			case 'menu':
				return $this->build_menu( $source_row );
			case 'manual':
				return $this->build_manual( $source_row );
			case 'rendered_url':
				return $this->build_rendered_url( $source_row );
			default:
				return $this->unsupported( JSDW_AI_Chat_DB::CONTENT_REASON_UNSUPPORTED_TYPE );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build_post( array $source_row ) {
		$pid = isset( $source_row['source_object_id'] ) ? absint( $source_row['source_object_id'] ) : 0;
		if ( $pid <= 0 ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}
		$post = get_post( $pid );
		if ( ! $post instanceof WP_Post ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}

		$ctx = array();
		if ( ! empty( $source_row['discovery_context'] ) ) {
			$decoded = json_decode( (string) $source_row['discovery_context'], true );
			if ( is_array( $decoded ) ) {
				$ctx = $decoded;
			}
		}

		$GLOBALS['post'] = $post;
		setup_postdata( $post );
		$content = apply_filters( 'the_content', $post->post_content );
		wp_reset_postdata();

		return array(
			'status'             => 'ok',
			'title'              => get_the_title( $post ),
			'body_html'          => is_string( $content ) ? $content : '',
			'metadata'           => array(
				'post_status'  => (string) $post->post_status,
				'post_type'    => (string) $post->post_type,
				'post_modified'=> (string) $post->post_modified_gmt,
				'context'      => $ctx,
			),
			'extraction_method'  => 'wp_post_the_content',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build_taxonomy( array $source_row ) {
		$tid = isset( $source_row['source_object_id'] ) ? absint( $source_row['source_object_id'] ) : 0;
		$key = isset( $source_row['source_key'] ) ? (string) $source_row['source_key'] : '';
		$taxonomy = 'category';
		if ( preg_match( '/^taxonomy:([^:]+):(\d+)$/', $key, $m ) ) {
			$taxonomy = (string) $m[1];
			$tid      = absint( $m[2] );
		}
		if ( $tid <= 0 ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}
		$term = get_term( $tid, $taxonomy );
		if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}

		$desc = term_description( $tid, $taxonomy );
		$desc = is_string( $desc ) ? $desc : '';

		return array(
			'status'            => 'ok',
			'title'             => (string) $term->name,
			'body_html'         => $desc,
			'metadata'          => array(
				'taxonomy' => (string) $term->taxonomy,
				'slug'     => (string) $term->slug,
				'term_id'  => (int) $term->term_id,
			),
			'extraction_method' => 'wp_term_description',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build_menu( array $source_row ) {
		$mid = isset( $source_row['source_object_id'] ) ? absint( $source_row['source_object_id'] ) : 0;
		if ( $mid <= 0 ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}
		$items = wp_get_nav_menu_items( $mid );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return array(
				'status'            => 'ok',
				'title'             => isset( $source_row['title'] ) ? (string) $source_row['title'] : '',
				'body_html'         => '',
				'metadata'          => array( 'menu_id' => $mid, 'items' => array() ),
				'extraction_method' => 'wp_nav_menu_items',
			);
		}
		$lines = array();
		foreach ( $items as $item ) {
			if ( ! isset( $item->title, $item->url ) ) {
				continue;
			}
			$lines[] = trim( (string) $item->title ) . ' ' . trim( (string) $item->url );
		}
		$body = '<div>' . esc_html( implode( "\n", $lines ) ) . '</div>';

		return array(
			'status'            => 'ok',
			'title'             => isset( $source_row['title'] ) ? (string) $source_row['title'] : '',
			'body_html'         => $body,
			'metadata'          => array(
				'menu_id'     => $mid,
				'item_count'  => count( $items ),
			),
			'extraction_method' => 'wp_nav_menu_items',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build_manual( array $source_row ) {
		$mid = isset( $source_row['source_object_id'] ) ? absint( $source_row['source_object_id'] ) : 0;
		if ( $mid <= 0 ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}
		$manual = $this->repository->get_manual_source_by_id( $mid );
		if ( ! is_array( $manual ) ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}

		$notes = isset( $manual['source_notes'] ) ? (string) $manual['source_notes'] : '';
		$url   = isset( $manual['source_url'] ) ? (string) $manual['source_url'] : '';
		$title = isset( $manual['title'] ) ? (string) $manual['title'] : '';

		$html = '<div class="manual-body">' . wp_kses_post( $notes ) . '</div>';
		if ( '' !== $url ) {
			$html .= '<p class="manual-url">' . esc_html( $url ) . '</p>';
		}

		return array(
			'status'            => 'ok',
			'title'             => $title,
			'body_html'         => $html,
			'metadata'          => array(
				'manual_id'      => $mid,
				'source_key'     => isset( $manual['source_key'] ) ? (string) $manual['source_key'] : '',
				'allow_behavior' => isset( $manual['allow_behavior'] ) ? (string) $manual['allow_behavior'] : '',
				'enabled'        => ! empty( $manual['enabled'] ),
			),
			'extraction_method' => 'manual_source_fields',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build_rendered_url( array $source_row ) {
		$url = isset( $source_row['source_url'] ) ? (string) $source_row['source_url'] : '';
		if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $this->unsupported( JSDW_AI_Chat_DB::CONTENT_REASON_UNSUPPORTED_TYPE );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'redirection' => 3,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) ) {
			return $this->unavailable( JSDW_AI_Chat_DB::CONTENT_REASON_SOURCE_UNAVAILABLE );
		}

		return array(
			'status'            => 'ok',
			'title'             => isset( $source_row['title'] ) ? (string) $source_row['title'] : '',
			'body_html'         => $body,
			'metadata'          => array(
				'fetched_url' => $url,
				'http_code'   => $code,
			),
			'extraction_method' => 'http_get',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function unsupported( $reason ) {
		return array(
			'status'      => 'unsupported',
			'reason_code' => (string) $reason,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function unavailable( $reason ) {
		return array(
			'status'      => 'unavailable',
			'reason_code' => (string) $reason,
		);
	}
}
