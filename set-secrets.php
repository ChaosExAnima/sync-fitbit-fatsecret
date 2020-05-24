<?php

require_once 'class-secrets.php';

$secret_args = getopt( '', [
	'fitbit_secret::',
	'fitbit_code::',
	'fitbit_redirect::',
] );

echo "Setting the following secrets:\n";

$secrets = new Secrets( __DIR__ . '/secrets.json' );

foreach ( $secret_args as $arg => $value ) {
	if ( $value ) {
		echo "$arg\n";
		$secrets->{$arg} = $value;
	}
}
