<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL safety checks for crawler / rendered-URL fetches.
 *
 * Blocks loopback, link-local (incl. cloud-provider metadata endpoints like
 * 169.254.169.254), and RFC1918 / unique-local addresses. Refuses redirects
 * by default so a public URL cannot be used to pivot to an internal target.
 */
class JSDW_AI_Chat_URL_Safety {

	const ALLOWED_SCHEMES = array( 'http', 'https' );

	/**
	 * Validate that a URL is safe for outbound fetching from server-side code.
	 *
	 * @param string $url
	 * @return string|WP_Error Normalized URL on success.
	 */
	public static function validate_external_url( $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url ) {
			return new WP_Error( 'jsdw_url_empty', __( 'URL is empty.', 'jsdw-ai-chat' ) );
		}
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'jsdw_url_malformed', __( 'URL is not well-formed.', 'jsdw-ai-chat' ) );
		}

		$parts  = wp_parse_url( $url );
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';

		if ( ! in_array( $scheme, self::ALLOWED_SCHEMES, true ) ) {
			return new WP_Error( 'jsdw_url_scheme', __( 'Only http and https URLs are allowed.', 'jsdw-ai-chat' ) );
		}
		if ( '' === $host ) {
			return new WP_Error( 'jsdw_url_host', __( 'URL is missing a host.', 'jsdw-ai-chat' ) );
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return new WP_Error( 'jsdw_url_userinfo', __( 'Embedded credentials are not allowed in fetch URLs.', 'jsdw-ai-chat' ) );
		}

		$ips = self::resolve_host( $host );
		if ( empty( $ips ) ) {
			return new WP_Error( 'jsdw_url_dns', __( 'Hostname could not be resolved.', 'jsdw-ai-chat' ) );
		}
		foreach ( $ips as $ip ) {
			if ( self::is_blocked_ip( $ip ) ) {
				return new WP_Error(
					'jsdw_url_blocked_ip',
					/* translators: %s: blocked IP address */
					sprintf( __( 'URL resolves to a blocked address (%s).', 'jsdw-ai-chat' ), $ip )
				);
			}
		}

		return $url;
	}

	/**
	 * Validated wrapper around wp_remote_get. Forces sslverify and disables
	 * redirects so a public URL cannot pivot to an internal target.
	 *
	 * @param string              $url
	 * @param array<string,mixed> $args Extra wp_remote_get args. sslverify and
	 *                                  redirection are forced.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function safe_remote_get( $url, array $args = array() ) {
		$validated = self::validate_external_url( $url );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$forced = array(
			'sslverify'    => true,
			'redirection'  => 0,
			'timeout'      => isset( $args['timeout'] ) ? (int) $args['timeout'] : 15,
			'reject_unsafe_urls' => true,
		);
		$merged = array_merge( $args, $forced );

		return wp_remote_get( $validated, $merged );
	}

	/**
	 * Resolve a host to a list of IPs. If the host is already an IP literal,
	 * returns it as a single-element array.
	 *
	 * @param string $host
	 * @return string[]
	 */
	private static function resolve_host( $host ) {
		// Strip IPv6 brackets if present.
		$bare = trim( $host, '[]' );

		if ( filter_var( $bare, FILTER_VALIDATE_IP ) ) {
			return array( $bare );
		}

		$ipv4 = gethostbynamel( $host );
		if ( is_array( $ipv4 ) && ! empty( $ipv4 ) ) {
			return $ipv4;
		}

		// IPv6 fallback via dns_get_record.
		if ( function_exists( 'dns_get_record' ) ) {
			$records = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $records ) ) {
				$out = array();
				foreach ( $records as $rec ) {
					if ( isset( $rec['ipv6'] ) ) {
						$out[] = (string) $rec['ipv6'];
					}
				}
				if ( ! empty( $out ) ) {
					return $out;
				}
			}
		}

		return array();
	}

	/**
	 * True if the IP falls in a range we refuse to fetch from server-side:
	 * loopback, link-local (incl. cloud metadata endpoint 169.254.169.254),
	 * RFC1918 / unique-local, CGNAT, or unspecified.
	 *
	 * @param string $ip
	 * @return bool
	 */
	private static function is_blocked_ip( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return true; // Unparseable address — refuse.
		}

		// Reject anything that isn't routable on the public internet. PHP's
		// FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE catches most
		// cases (private, reserved, loopback). The link-local /16 used by
		// cloud metadata endpoints is included in the reserved set.
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return true;
		}

		return false;
	}
}
