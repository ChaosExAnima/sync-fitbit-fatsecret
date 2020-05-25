<?php

require_once 'trait-request.php';
require_once 'class-food-entry.php';

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

	public function get_food_for_day( DateTimeInterface $date ) : array {
		$params = [
			'date' => floor( $date->getTimestamp() / self::SECONDS_IN_DAY ),
		];
		$response = $this->request( 'food_entries.get', 'GET', compact( 'params' ) );

		if ( ! $response->food_entries ) {
			return [];
		}

		$food_entries = $response->food_entries->food_entry;
		if ( ! is_array( $food_entries ) ) {
			$food_entries = [ $food_entries ];
		}
		return array_map( [ $this, 'parse_food_entry' ], $food_entries );
	}

	private function parse_food_entry( object $raw_food ) : FoodEntry {
		[ 'name' => $unit_name, 'num' => $unit_num ] = $this->get_food_entry_units(
			intval( $raw_food->food_id ),
			intval( $raw_food->serving_id ),
			floatval( $raw_food->number_of_units )
		);

		$nutrition = [];
		foreach ( $raw_food as $name => $val ) {
			if ( 'calories' === $name ) {
				$name = 'kcals';
			} else if ( 'carbohydrate' === $name ) {
				$name = 'carbs';
			} else if ( 'saturated_fat' === $name ) {
				$name = 'sat_fat';
			}
			$name = str_replace( 'unsaturated', '', $name );
			$name = str_replace( 'vitamin', 'vit', $name );
			if ( is_numeric( $val ) ) {
				$nutrition[ $name ] = floatval( $val );
			}
		}

		return new FoodEntry(
			$raw_food->food_entry_name,
			new DateTimeImmutable( '@' . $raw_food->date_int * self::SECONDS_IN_DAY ),
			strtolower( $raw_food->meal ),
			$unit_num,
			$unit_name,
			$nutrition
		);
	}

	private function get_food_entry_units( int $food_id, int $food_entry_id, float $unit_num ) : array {
		$params = [
			'food_id' => $food_id,
		];
		$response = $this->request( 'food.get.v2', 'GET', compact( 'params' ) );

		$serving_types = $response->food->servings->serving;
		if ( ! is_array( $serving_types ) ) {
			$serving_types = [ $serving_types ];
		}

		foreach ( $serving_types as $serving_type ) {
			if ( $food_entry_id === intval( $serving_type->serving_id ) ) {
				if ( ! empty( $serving_type->metric_serving_unit ) ) {
					$num = 'g' === $serving_type->measurement_description
						? $unit_num
						: floatval( $serving_type->metric_serving_amount ) * $unit_num;
					return [
						'name' => $serving_type->metric_serving_unit,
						'num'  => $num,
					];
				}
				return [
					'name' => $serving_type->measurement_description,
					'num'  => floatval( $serving_type->number_of_units ) * $unit_num,
				];
			}
		}
		return [ 'name' => 'units', 'num' => $unit_num ];
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