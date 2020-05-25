<?php

require_once 'trait-request.php';

class Fitbit {
	use Request;

	private const API_ROOT = 'https://api.fitbit.com';

	private const CLIENTID = '22BF8N';

	private ?Secrets $secrets = null;

	private ?string $token = null;

	public function __construct( Secrets $secrets ) {
		$this->secrets = $secrets;
		$this->set_token();
	}

	public function get_weight_for_date( DateTimeInterface $date, bool $throw_on_empty = true ) : float {
		$date     = $date->format( 'Y-m-d' );
		$response = $this->request( "/1/user/-/body/log/weight/date/{$date}.json" );

		if ( ! isset( $response->weight ) ) {
			throw new Error( 'Missing weight response!' );
		}

		if ( 0 === count( $response->weight ) ) {
			if ( $throw_on_empty ) {
				throw new Exception( "No weight entries for date {$date}." );
			}
			return 0;
		}

		return array_sum( array_column( $response->weight, 'weight' ) ) / count( $response->weight );
	}

	public function add_food_for_date() : bool {
		return false;
	}

	private function set_token() : void {
		$basicauthtoken = base64_encode( self::CLIENTID . ':' . $this->secrets->fitbit_secret );
		$refresh_token  = $this->secrets->fitbit_refresh;

		if ( ! $this->secrets->fitbit_code ) {
			echo 'Grant needed. Visit https://www.fitbit.com/oauth2/authorize?' .
				'client_id=' . self::CLIENTID . '&redirect_uri=' . $this->secrets->fitbit_redirect .
				'&response_type=code&scope=weight+nutrition+profile' . "\n";
			exit( 1 );
		}

		$params = [
			'client_id' => self::CLIENTID,
		];
		if ( $refresh_token ) {
			$params['grant_type']    = 'refresh_token';
			$params['refresh_token'] = $refresh_token;
		} else {
			$params['grant_type']   = 'authorization_code';
			$params['redirect_uri'] = $this->secrets->fitbit_redirect;
			$params['code']         = $this->secrets->fitbit_code;
		}

		$headers = [
			'Content-Type'  => 'application/x-www-form-urlencoded',
			'Authorization' => "Basic $basicauthtoken",
		];
		$response = $this->request( '/oauth2/token', 'POST', compact( 'headers', 'params' ) );

		if ( isset( $response->success ) && ! $response->success ) {
			if ( 'invalid_grant' === $response->errors[0]->errorType ) {
				$this->secrets->fitbit_refresh = null;
				$this->secrets->fitbit_code = null;
				$this->secrets->save();
				echo 'New grant needed. Visit https://www.fitbit.com/oauth2/authorize?' .
					'client_id=' . self::CLIENTID . '&redirect_uri=' . $this->secrets->fitbit_redirect .
					'&response_type=code&scope=weight+nutrition+profile' . "\n";
				exit( 1 );
			}
			throw new Error( $response->errors[0]->message );
		}

		$this->token = $response->access_token;

		$this->secrets->fitbit_refresh = $response->refresh_token;
		$this->secrets->save();
	}

	private function request( string $path, string $method = 'GET', ?array $args = null ) : object {
		$headers = [];
		if ( $this->token ) {
			$headers['Authorization'] = "Bearer {$this->token}";
		}
		$args['headers'] = array_merge( $headers, $args['headers'] ?? [] );
		$response = $this->base_request( self::API_ROOT . $path, $method, $args );

		if ( isset( $response->errors ) ) {
			foreach ( $response->errors as $error ) {
				echo "Error from Fitbit: {$error->message}\n";
			}
			die;
		}

		return $response;
	}
}
