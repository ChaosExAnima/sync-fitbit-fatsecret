<?php

trait Request {
	private array $response_cache = [];

	private function base_request( string $path, string $method = 'GET', ?array $args = null ) : object {
		if ( ! empty( $args['params'] ) ) {
			$path .= '?' . http_build_query( $args['params'] );
		}

		$headers = [];
		if ( ! empty( $args['headers'] ) ) {
			$headers = array_merge( $headers, $args['headers'] );
		}

		$flat_headers = [];
		foreach ( $headers as $name => $value ) {
			$flat_headers[] = "$name: $value";
		}

		$cache_key = md5( $path . $method . serialize( $flat_headers ) );

		if ( isset( $this->response_cache[ $cache_key ] ) ) {
			return $this->response_cache[ $cache_key ];
		}

		$ch = curl_init( $path );
		if ( 'POST' === $method ) {
			curl_setopt( $ch, CURLOPT_POST, 1 );
		}

		curl_setopt( $ch, CURLOPT_HTTPHEADER, $flat_headers );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$response = curl_exec( $ch );
		curl_close( $ch );

		$parsed_response = json_decode( $response );

		$this->response_cache[ $cache_key ] = $parsed_response;

		return $parsed_response;
	}
}