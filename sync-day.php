<?php
/**
 * Syncs nutrition and weight for a single day.
 */

require_once 'class-secrets.php';
require_once 'class-logger.php';
require_once 'class-health-check.php';
require_once 'class-fitbit.php';
require_once 'class-fatsecret.php';
require_once 'functions.php';

$script_args = getopt( '', [ 'date::', 'date_tz::', 'check' ] );

$date_tz = new DateTimeZone( $script_args['date_tz'] ?? 'America/New_York' );
$date    = new DateTimeImmutable( $script_args['date'] ?? 'now', $date_tz );

$secrets   = new Secrets( __DIR__ . '/secrets.json' );
$logger    = new Logger( $date_tz );
$logger->log( 'Syncing weight and food diary for ' . $date->format( 'Y-m-d' ) . ':' );

$check     = new HealthCheck();
$fitbit    = new Fitbit( $secrets );
$fatsecret = new FatSecret( $secrets );

if ( false === ( $script_args['check'] ?? null ) ) {
	$check->init( $secrets );
}

$check->start();
try {
	$secrets->save();

	$weight = sync_weight_for_date( $fitbit, $fatsecret, $date, $logger );
	if ( $weight ) {
		$logger->log( 'Synced weight.' );
	}

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
