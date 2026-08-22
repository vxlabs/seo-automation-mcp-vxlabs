<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Secrets {
	public static function encrypt( $value ) {
		if ( '' === (string) $value ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return 'sf1:sodium:' . base64_encode( $nonce . sodium_crypto_secretbox( (string) $value, $nonce, $key ) );
		}
		$iv  = random_bytes( 12 );
		$tag = '';
		$encrypted = openssl_encrypt( (string) $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false === $encrypted ? '' : 'sf1:gcm:' . base64_encode( $iv . $tag . $encrypted );
	}

	public static function decrypt( $value ) {
		if ( ! is_string( $value ) || 0 !== strpos( $value, 'sf1:' ) ) {
			return (string) $value;
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		if ( 0 === strpos( $value, 'sf1:sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$raw = base64_decode( substr( $value, 11 ), true );
			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) return '';
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
			return false === $plain ? '' : $plain;
		}
		if ( 0 === strpos( $value, 'sf1:gcm:' ) ) {
			$raw = base64_decode( substr( $value, 8 ), true );
			if ( false === $raw || strlen( $raw ) <= 28 ) return '';
			$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
			return false === $plain ? '' : $plain;
		}
		return '';
	}
}
