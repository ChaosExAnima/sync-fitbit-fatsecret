<?php
/**
 * Integrates with HealthChecks.io.
 */

require_once 'trait-request.php';

class HealthCheck {
	use Request;

	private const HOST = 'https://hc-ping.com/';

	private ?string $check_id = null;

	public function init( Secrets $secrets ) : void {
		if ( ! $secrets->health_check_id ) {
			throw new Exception( 'Health Check ID not set.' );
		}
		$this->check_id = $secrets->health_check_id;
	}

	public function start() : void {
		$this->request( '/start' );
	}

	public function fail() : void {
		$this->request( '/fail' );
	}

	public function finish() : void {
		$this->request();
	}

	private function request( string $path = '' ) : void {
		if ( ! $this->check_id ) {
			return;
		}
		$response = $this->base_request( self::HOST . $this->check_id . $path, 'GET', null, false );
		if ( 'OK' !== $response ) {
			throw new Exception( 'Invalid response to health check: ' . $response );
		}
	}
}