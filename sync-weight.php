<?php

require_once 'class-secrets.php';
require_once 'class-fitbit.php';

$secrets = new Secrets( __DIR__ . '/secrets.json' );

$fitbit = new Fitbit( $secrets );

echo $fitbit->get_weight_for_date( new DateTime() );

$secrets->save();