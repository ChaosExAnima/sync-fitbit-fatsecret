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

foreach ( $food_entries as $food_entry ) {
	$food_entry->map_fitbit_unit_id( $food_units );
}
