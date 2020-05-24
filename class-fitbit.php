<?php

class Fitbit {
	const CLIENTID = '22BF8N';
	const CLIENTSECRET = '7a6047b31ddec318eddd224b93c5c60a';
	const CODE = '601a5202e84fa3a44c0075cf102e40c4af31debe';
	const CALLBACK = 'https://echonyc.name';
	const API_ROOT = 'https://api.fitbit.com';

	private ?Secrets $secrets = null;

	private ?string $token = null;

	private array $response_cache = [];

	public function __construct( Secrets $secrets ) {
		$this->secrets = $secrets;
		$this->set_token();
	}

	public function get_weight_for_date( DateTime $date ) : float {
		$date = $date->format( 'Y-m-d' );
		$response = $this->request( "/1/user/-/body/log/weight/date/{$date}.json" );

		if ( ! isset( $response->weight ) ) {
			throw new Error( 'Missing weight response!' );
		}

		if ( 0 === count( $response->weight ) ) {
			return 0;
		}

		return array_sum( array_column( $response->weight, 'weight' ) ) / count( $response->weight );
	}

	public function add_food_for_date() : bool {
		return false;
	}

	private function set_token() : void {
		$basicauthtoken = base64_encode( self::CLIENTID . ':' . self::CLIENTSECRET );
		$refresh_token  = $this->secrets->fitbit_refresh;

		$params = [
			'client_id' => self::CLIENTID,
		];
		if ( $refresh_token ) {
			$params['grant_type']    = 'refresh_token';
			$params['refresh_token'] = $refresh_token;
		} else {
			$params['grant_type']   = 'authorization_code';
			$params['redirect_uri'] = self::CALLBACK;
			$params['code']         = self::CODE;
		}

		$headers = [
			'Content-Type'  => 'application/x-www-form-urlencoded',
			'Authorization' => "Basic $basicauthtoken",
		];
		$response = $this->request( '/oauth2/token', 'POST', compact( 'headers', 'params' ) );

		if ( isset( $response->success ) && ! $response->success ) {
			if ( 'invalid_grant' === $response->errors[0]->errorType ) {
				echo 'New grant needed. Visit https://www.fitbit.com/oauth2/authorize?' .
					'client_id=' . self::CLIENTID . '&redirect_uri=' . self::CALLBACK .
					'&response_type=code&scope=weight+nutrition+profile';
				return;
			}
			throw new Error( $response->errors[0]->message );
		}

		$this->token = $response->access_token;

		$this->secrets->fitbit_refresh = $response->refresh_token;
	}

	private function request( string $path, string $method = 'GET', ?array $args = null ) : object {
		$full_url = self::API_ROOT . $path;
		if ( ! empty( $args['params'] ) ) {
			$full_url .= '?' . http_build_query( $args['params'] );
		}

		$headers = [
			'Accept-Language' => 'en_US',
		];
		if ( $this->token ) {
			$headers['Authorization'] = "Bearer {$this->token}";
		}
		if ( ! empty( $args['headers'] ) ) {
			$headers = array_merge( $headers, $args['headers'] );
		}

		$flat_headers = [];
		foreach ( $headers as $name => $value ) {
			$flat_headers[] = "$name: $value";
		}

		$cache_key = md5( $full_url . $method . serialize( $flat_headers ) );

		if ( isset( $this->response_cache[ $cache_key ] ) ) {
			return $this->response_cache[ $cache_key ];
		}

		$ch = curl_init( $full_url );
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
