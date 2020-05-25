<?php

class Logger {
	private DateTimeZone $tz;

	public function __construct( DateTimeZone $tz = null ) {
		$this->tz = $tz;
	}

	public function log( string $message ) {
		echo $this->add_timestamp( "$message\n" );
	}

	private function add_timestamp( string $message ) : string {
		if ( ! $this->tz ) {
			return $message;
		}
		$now = new DateTimeImmutable( 'now', $this->tz );
		return $now->format( 'Y-m-d H:i:s' ) . " - $message";
	}
}
