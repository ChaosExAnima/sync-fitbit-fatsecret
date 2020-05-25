<?php

require_once 'class-secrets.php';
require_once 'class-fitbit.php';
require_once 'class-fatsecret.php';

$secrets = new Secrets( __DIR__ . '/secrets.json' );

$date_ranges = getopt( '', [ 'date_start::', 'date_end::', 'date_tz::' ] );

$date_tz    = new DateTimeZone( $date_ranges['date_tz'] ?? 'America/New_York' );
$start_date = new DateTimeImmutable( $date_ranges['date_start'] ?? 'now', $date_tz );
$end_date   = new DateTimeImmutable( $date_ranges['date_end'] ?? 'now', $date_tz );

$date_range = new DatePeriod( $start_date, new DateInterval( 'P1D' ), $end_date );

$fitbit    = new Fitbit( $secrets );
$fatsecret = new FatSecret( $secrets );

$secrets->save();

echo "Updating Fat Secret from FitBit...\n";

$updated      = 0;
$current_date = DateTime::createFromImmutable( $start_date );
do {
	$current_date_start = DateTimeImmutable::createFromMutable( $current_date );
	$current_date->add( new DateInterval( 'P31D' ) );
	$weights = $fitbit->get_weight_for_date_range( $current_date_start, $current_date );
	foreach ( $weights as $kg ) {
		$fatsecret->update_weight_for_date( $date, $kg );
		$updated++;
		$date_formatted = $date->format( 'Y-m-d' );
		echo "Updated weight on {$date_formatted}.\n";
	}
} while ( $current_date < $end_date );

echo "Updated {$updated} days.\n";