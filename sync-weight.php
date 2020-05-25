<?php

require_once 'class-secrets.php';
require_once 'class-health-check.php';
require_once 'class-fitbit.php';
require_once 'class-fatsecret.php';

echo "Updating Fat Secret from FitBit...\n";

$script_args = getopt( '', [ 'date_start::', 'date_end::', 'date_tz::', 'check' ] );
$date_tz     = new DateTimeZone( $script_args['date_tz'] ?? 'America/New_York' );
$start_date  = new DateTimeImmutable( $script_args['date_start'] ?? 'today', $date_tz );
$end_date    = new DateTimeImmutable( $script_args['date_end'] ?? 'today', $date_tz );

$secrets   = new Secrets( __DIR__ . '/secrets.json' );
$fitbit    = new Fitbit( $secrets );
$fatsecret = new FatSecret( $secrets );
$check     = new HealthCheck();

if ( false === ( $script_args['check'] ?? null ) ) {
	$check->init( $secrets );
}

$check->start();
$secrets->save();

$updated      = 0;
$current_date = DateTime::createFromImmutable( $start_date );
do {
	try {
		$weights = $fitbit->get_weight_for_date_period( $current_date, '1m' );
	} catch ( Exception $err ) {
		$check->fail();
		throw $err;
	}
	$current_date->add( new DateInterval( 'P1M' ) );
	foreach ( $weights as $date => $kg ) {
		$current_day = new DateTimeImmutable( $date, $date_tz );
		if ( $start_date > $current_day ) {
			continue;
		}
		try {
			$fatsecret->update_weight_for_date( $current_day, $kg );
		} catch ( Exception $err ) {
			$check->fail();
			throw $err;
		}
		$updated++;
		echo "Updated weight on {$date}.\n";
	}
} while ( $current_date < $end_date );

$check->finish();

echo "Updated {$updated} days.\n";