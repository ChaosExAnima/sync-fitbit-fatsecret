<?php

require_once 'class-secrets.php';
require_once 'class-fitbit.php';
require_once 'class-fatsecret.php';
require_once 'class-logger.php';

$args = getopt( '', [ 'date::', 'date_tz::', 'check' ] );

$date_tz = new DateTimeZone( $args['date_tz'] ?? 'America/New_York' );
$date    = new DateTimeImmutable( $args['date'] ?? 'now', $date_tz );
$logger  = new Logger( $date_tz );
$check   = new HealthCheck();

$logger->log( 'Syncing food diary for ' . $date->format( 'Y-m-d' ) . ':' );

try {
	$secrets   = new Secrets( __DIR__ . '/secrets.json' );
	$fitbit    = new Fitbit( $secrets );
	$fatsecret = new FatSecret( $secrets );

	if ( false === ( $script_args['check'] ?? null ) ) {
		$check->init( $secrets );
	}
	$check->start();
	$secrets->save();

	$food = sync_food_for_date( $fitbit, $fatsecret, $date, $logger );
	if ( $food ) {
		$logger->log( 'Synced food.' );
	}
	$check->finish();
	$logger->log( 'Complete!' );
} catch ( Exception $err ) {
	$check->fail();
	throw $err;
}
