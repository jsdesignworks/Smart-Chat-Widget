<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypt-at-rest helper for plugin-stored secrets (provider API keys, etc).
 *
 * Uses libsodium's authenticated symmetric encryption. The encryption key is
 * derived from `wp_salt('auth')`, so a stolen DB cannot be decrypted without
 * also knowing the site's salts in wp-config.php. Bundled with PHP 8.0+, which
 * the plugin already requires.
 *
 * Format on disk: `__jsdwenc_v1__<base64(nonce . ciphertext)>`.
 *
 * Decryption is intentionally non-fatal: a salt change or corrupt value yields
 * an empty string and a logged warning, prompting the admin to re-enter the
 * secret rather than crashing the site.
 */
class JSDW_AI_Chat_Secret_Store {

	const MARKER = '__jsdwenc_v1__';

	/**
	 * Encrypt a plaintext secret. Idempotent: already-encrypted input is
	 * returned unchanged; empty input returns empty.
	 *
	 * @param string $plaintext
	 * @return string
	 */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;
		if ( '' === $plaintext ) {
			return '';
		}
		if ( self::is_encrypted( $plaintext ) ) {
			return $plaintext;
		}
		if ( ! self::is_available() ) {
			// libsodium missing — refuse to silently store plaintext under an
			// "encrypted" marker. Caller may still write the raw value, which
			// is no worse than the pre-encryption state.
			return $plaintext;
		}

		try {
			$key   = self::derive_key();
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			sodium_memzero( $key );
			return self::MARKER . base64_encode( $nonce . $cipher );
		} catch ( Throwable $e ) {
			return $plaintext;
		}
	}

	/**
	 * Decrypt a stored secret. Plaintext (legacy / pre-migration) values are
	 * passed through unchanged. Decryption failures return '' so callers see
	 * "key not configured" rather than malformed bytes.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function decrypt( $value ) {
		$value = (string) $value;
		if ( '' === $value || ! self::is_encrypted( $value ) ) {
			return $value;
		}
		if ( ! self::is_available() ) {
			return '';
		}

		$payload = base64_decode( substr( $value, strlen( self::MARKER ) ), true );
		if ( false === $payload || strlen( $payload ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$nonce  = substr( $payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		try {
			$key   = self::derive_key();
			$plain = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
			sodium_memzero( $key );
			return false === $plain ? '' : (string) $plain;
		} catch ( Throwable $e ) {
			return '';
		}
	}

	public static function is_encrypted( $value ) {
		return is_string( $value ) && 0 === strpos( $value, self::MARKER );
	}

	public static function is_available() {
		return function_exists( 'sodium_crypto_secretbox' )
			&& function_exists( 'sodium_crypto_secretbox_open' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' );
	}

	/**
	 * 32-byte key derived from the site's auth salt. A different site (or a
	 * site whose salts have been rotated) cannot decrypt a copied DB.
	 */
	private static function derive_key() {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : '';
		// Domain-separation tag so the same salt material doesn't collide with
		// any other use elsewhere in the codebase.
		return sodium_crypto_generichash( 'jsdw-ai-chat:secret-store:v1|' . $salt, '', 32 );
	}
}
