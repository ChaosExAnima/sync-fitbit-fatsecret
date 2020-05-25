<?php

class Logger {
	private DateTimeZone $tz;
	private bool $trace;

	public function __construct( DateTimeZone $tz = null, bool $trace = false ) {
		$this->tz    = $tz;
		$this->trace = $trace;
	}

	public function log( string $message ) : void {
		echo $this->add_timestamp( "$message\n" );
	}

	public function error( Throwable $error ) : void {
		if ( ! $this->trace ) {
			$this->log( 'ERROR: ' . $error->getMessage() );
		} else {
			$this->log( 'ERROR: ' . $error );
		}
	}

	private function add_timestamp( string $message ) : string {
		if ( ! $this->tz ) {
			return $message;
		}
		$now = new DateTimeImmutable( 'now', $this->tz );
		return $now->format( 'Y-m-d H:i:s' ) . " - $message";
	}
}
