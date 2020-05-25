<?php

class Secrets {
	private ?string $secrets_path;
	private ?object $secrets;

	public function __construct( $secrets_path ) {
		$this->secrets_path = $secrets_path;

		if ( is_readable( $this->secrets_path ) ) {
			$raw_secrets = file_get_contents( $this->secrets_path );
			$this->secrets = json_decode( $raw_secrets );
		}
	}

	public function __get( $key ) : ?string {
		if ( empty( $this->secrets->{$key} ) ) {
			return null;
		}
		return $this->secrets->{$key};
	}

	public function __set( $key, $value ) : void {
		$this->secrets->{$key} = $value;
	}

	public function save() : void {
		if ( ! is_writable( $this->secrets_path ) ) {
			throw new Error( "Secrets file {$this->secrets_path} is not writable." );
		}

		$result = file_put_contents( $this->secrets_path, json_encode( $this->secrets ) );
		if ( false === $result ) {
			throw new Error( "Could not save secrets to {$this->secrets_path}." );
		}
	}
}
