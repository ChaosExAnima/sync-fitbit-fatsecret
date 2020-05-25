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

$script_args = getopt( '', [ 'date::', 'date_tz::' ] );

$date_tz = new DateTimeZone( $script_args['date_tz'] ?? 'America/New_York' );
$date    = new DateTimeImmutable( $script_args['date'] ?? 'now', $date_tz );
$logger  = new Logger( $date_tz, get_flag_set( 'debug' ) );
$check   = new HealthCheck();

$logger->log( 'Syncing weight and food diary for ' . $date->format( 'Y-m-d' ) . ':' );

try {
	$secrets   = new Secrets( __DIR__ . '/secrets.json' );
	$fitbit    = new Fitbit( $secrets );
	$fatsecret = new FatSecret( $secrets );

	if ( get_flag_set( 'check' ) ) {
		$check->init( $secrets );
	}

	$check->start();
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
	$logger->error( $err );
}
