<?php

require_once 'trait-request.php';

class FatSecret {
	use Request;

	private const API_URL = 'https://platform.fatsecret.com/rest/server.api';

	private const TOKEN_REQUEST_URL = 'https://www.fatsecret.com/oauth/request_token';

	private const TOKEN_ACCESS_URL = 'https://www.fatsecret.com/oauth/access_token';

	private const CLIENT_KEY = '0d362a9c0dac42a1aba707faab016e15';

	private const SECONDS_IN_DAY = 86400;

	private ?Secrets $secrets = null;

	public function __construct( Secrets $secrets ) {
		$this->secrets = $secrets;
		$this->get_access_token();
	}

	public function update_weight_for_date( DateTimeInterface $date, float $weight ) : object {
		$params = [
			'current_weight_kg' => $weight,
			'comment'           => 'Updated via Fitbit',
			'date'              => floor( $date->getTimestamp() / self::SECONDS_IN_DAY ),
		];
		return $this->request( 'weight.update', 'POST', compact( 'params' ) );
	}

	private function get_default_params() : array {
		return [
			'format'                 => 'json',
			'oauth_consumer_key'     => self::CLIENT_KEY,
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => time(),
			'oauth_nonce'            => md5( uniqid() ),
			'oauth_version'          => '1.0',
		];
	}

	private function request( string $endpoint, string $method = 'GET', ?array $args = null ) : object {
		$params = array_merge( $args['params'] ?? [], $this->get_default_params(), [
			'method'      => $endpoint,
			'oauth_token' => $this->secrets->fatsecret_access_token,
		] );

		$params['oauth_signature'] = $this->get_oauth_signature( self::API_URL, $params, $method );

		$args['params'] = $params;

		$response = $this->base_request( self::API_URL, $method, $args );

		if ( isset( $response->error ) ) {
			throw new Exception( "Error from Fat Secret: {$response->error->message}." );
		}
		return $response;
	}

	private function get_access_token() : void {
		if ( $this->secrets->fatsecret_access_token ) {
			return;
		}

		if ( ! $this->secrets->fatsecret_request_token ) {
			$params = array_merge( $this->get_default_params(), [
				'oauth_callback' => 'oob',
			] );
			$params['oauth_signature'] = $this->get_oauth_signature( self::TOKEN_REQUEST_URL, $params );

			$request_token_raw = $this->base_request( self::TOKEN_REQUEST_URL, 'GET', compact( 'params' ), false );

			parse_str( $request_token_raw, $request_token );

			$this->secrets->fatsecret_request_token = $request_token['oauth_token'];
			$this->secrets->fatsecret_token_secret  = $request_token['oauth_token_secret'];
			$this->secrets->fatsecret_verifier      = null;
			$this->secrets->save();
		}

		$verifier = $this->secrets->fatsecret_verifier;
		if ( ! $verifier ) {
			echo "To get the token, go to https://www.fatsecret.com/oauth/authorize?oauth_token={$this->secrets->fatsecret_request_token}\n";
			die;
		}

		$params = array_merge( $this->get_default_params(), [
			'oauth_token'    => $this->secrets->fatsecret_request_token,
			'oauth_verifier' => $verifier,
		] );
		$params['oauth_signature'] = $this->get_oauth_signature( self::TOKEN_ACCESS_URL, $params );
		$access_token_raw = $this->base_request( self::TOKEN_ACCESS_URL, 'GET', compact( 'params' ), false );

		if ( 0 === strpos( $access_token_raw, 'Invalid / expired Token:' ) ) {
			$this->secrets->fatsecret_request_token = null;
			$this->secrets->fatsecret_verifier      = null;
			$this->get_access_token();
			return;
		}

		parse_str( $access_token_raw, $access_token );

		$this->secrets->fatsecret_token_secret  = $access_token['oauth_token_secret'];
		$this->secrets->fatsecret_access_token  = $access_token['oauth_token'];
		$this->secrets->fatsecret_request_token = null;
		$this->secrets->fatsecret_verifier      = null;
		$this->secrets->save();
	}

	private function get_oauth_signature( string $url, array $params, string $method = 'GET' ) : string {
		// First, sort the array.
		ksort( $params );

		// Next, implode the keys and values to build the param string.
		$param_pairs = [];
		foreach ( $params as $key => $value ) {
			$param_pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}
		$param_str = join( '&', $param_pairs );

		// Create the signature base.
		$base = $method . '&' . rawurlencode( $url ) . '&' . rawurlencode( $param_str );

		// Sign the base with the secret.
		$secret = rawurlencode( $this->secrets->fatsecret_secret ) . '&' . rawurlencode( $this->secrets->fatsecret_token_secret );
		return base64_encode( hash_hmac( 'sha1', $base, $secret, true ) );
	}
}