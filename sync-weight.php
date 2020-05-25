<?php

require_once 'class-logger.php';
require_once 'class-health-check.php';
require_once 'class-secrets.php';
require_once 'class-fitbit.php';
require_once 'class-fatsecret.php';
require_once 'functions.php';

$script_args = getopt( '', [ 'date_start::', 'date_end::', 'date_tz::' ] );
$date_tz     = new DateTimeZone( $script_args['date_tz'] ?? 'America/New_York' );
$start_date  = new DateTimeImmutable( $script_args['date_start'] ?? 'today', $date_tz );
$end_date    = new DateTimeImmutable( $script_args['date_end'] ?? 'today', $date_tz );
$logger      = new Logger( $date_tz, get_flag_set( 'debug' ) );
$check       = new HealthCheck();

$logger->log( 'Syncing weight between ' . $start_date->format( 'Y-m-d' ) . ' and ' . $end_date->format( 'Y-m-d' ) . ':' );

try {
	$secrets   = new Secrets( __DIR__ . '/secrets.json' );
	$fitbit    = new Fitbit( $secrets );
	$fatsecret = new FatSecret( $secrets );

	if ( get_flag_set( 'check' ) ) {
		$check->init( $secrets );
	}

	$check->start();
	$secrets->save();

	$updated      = 0;
	$current_date = DateTime::createFromImmutable( $start_date );
	do {
		$weights = $fitbit->get_weight_for_date_period( $current_date, '1m' );
		$current_date->add( new DateInterval( 'P1M' ) );
		foreach ( $weights as $date => $kg ) {
			$current_day = new DateTimeImmutable( $date, $date_tz );
			if ( $start_date > $current_day ) {
				continue;
			}
			$fatsecret->update_weight_for_date( $current_day, $kg );
			$updated++;
			$logger->log( "Updated weight on {$date}." );
		}
	} while ( $current_date < $end_date );

	$check->finish();
	$logger->log( "Updated {$updated} days." );
} catch ( Exception $err ) {
	$check->fail();
	$logger->error( $err );
}
