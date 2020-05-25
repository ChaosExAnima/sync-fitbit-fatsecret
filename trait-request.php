<?php

trait Request {
	private array $response_cache = [];

	private function base_request(
		string $path,
		string $method = 'GET',
		?array $args = null,
		bool $parse = true,
		bool $return_headers = false
	) {
		if ( ! empty( $args['params'] ) ) {
			$path .= '?' . http_build_query( $args['params'] );
		}

		$flat_headers = [];
		foreach ( $args['headers'] ?? [] as $name => $value ) {
			$flat_headers[] = "$name: $value";
		}

		$cache_key = md5( $path . $method . serialize( $flat_headers ) );

		if ( isset( $this->response_cache[ $cache_key ] ) ) {
			return $this->response_cache[ $cache_key ];
		}

		$ch = curl_init( $path );
		if ( 'POST' === $method ) {
			curl_setopt( $ch, CURLOPT_POST, 1 );
		} else if ( 'DELETE' === $method ) {
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
		}

		curl_setopt( $ch, CURLOPT_HTTPHEADER, $flat_headers );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

		if ( $return_headers ) {
			$headers = [];
			curl_setopt( $ch, CURLOPT_HEADERFUNCTION, function( $curl, $header ) use ( &$headers ) {
				$len = strlen( $header );
				$header = explode( ':', $header, 2 );
				if ( count( $header ) < 2 ) {
					return $len;
				}
				$headers[ trim( $header[0] ) ] = trim( $header[1] );

				return $len;
			} );
		}

		$response = curl_exec( $ch );
		curl_close( $ch );

		if ( ! $parse ) {
			if ( $return_headers ) {
				return compact( 'response', 'headers' );
			}
			return $response;
		}

		$response = json_decode( $response );

		if ( null === $response ) {
			throw new Exception( 'Got unparseable response.' );
		}

		$this->response_cache[ $cache_key ] = $response;

		if ( $return_headers ) {
			return compact( 'response', 'headers' );
		}
		return $response;
	}
}