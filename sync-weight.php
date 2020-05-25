<?php

require_once 'class-secrets.php';
require_once 'class-fitbit.php';
require_once 'class-fatsecret.php';

$secrets = new Secrets( __DIR__ . '/secrets.json' );

$date = new DateTime( 'now', new DateTimeZone( 'America/New_York' ) );

$fitbit = new Fitbit( $secrets );
$kg = $fitbit->get_weight_for_date( $date );

$fatsecret = new FatSecret( $secrets );
$fatsecret->update_weight_for_date( $date, $kg );

$secrets->save();