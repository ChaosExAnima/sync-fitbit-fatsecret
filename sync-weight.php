<?php

require_once 'class-secrets.php';
require_once 'class-fitbit.php';
require_once 'class-fatsecret.php';

$secrets = new Secrets( __DIR__ . '/secrets.json' );

$date_ranges = getopt( '', [ 'date_start::', 'date_end::', 'date_tz::' ] );

$date_tz    = new DateTimeZone( $date_ranges['date_tz'] ?? 'America/New_York' );
$start_date = new DateTimeImmutable( $date_ranges['date_start'] ?? 'now', $date_tz );
$end_date   = new DateTimeImmutable( $date_ranges['date_end'] ?? 'now', $date_tz );

$fitbit    = new Fitbit( $secrets );
$fatsecret = new FatSecret( $secrets );

$secrets->save();

echo "Updating Fat Secret from FitBit...\n";

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
		echo "Updated weight on {$date}.\n";
	}
} while ( $current_date < $end_date );

echo "Updated {$updated} days.\n";