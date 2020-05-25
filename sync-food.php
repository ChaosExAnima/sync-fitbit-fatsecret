<?php

require_once 'class-secrets.php';
require_once 'class-fitbit.php';
require_once 'class-fatsecret.php';


$args = getopt( '', [ 'date::', 'date_tz::' ] );

$date_tz = new DateTimeZone( $args['date_tz'] ?? 'America/New_York' );
$date    = new DateTimeImmutable( $args['date'] ?? 'now', $date_tz );

$secrets   = new Secrets( __DIR__ . '/secrets.json' );
$fitbit    = new Fitbit( $secrets );
$fatsecret = new FatSecret( $secrets );

$secrets->save();


$food_units   = $fitbit->get_food_units();
$food_entries = $fatsecret->get_food_for_day( $date );

echo 'Syncing food diary for ' . $date->format( 'Y-m-d' ) . ":\n";
$fitbit->delete_food_entries_for_date( $date );
echo "Deleted old data.\n";
foreach ( $food_entries as $food_entry ) {
	$food_entry->map_fitbit_unit_id( $food_units );
	$fitbit->add_food_for_date( $date, $food_entry );
	echo "Logged {$food_entry->name}.\n";
}
echo "Done!\n";
