<?php

/**
 * Syncs weight for a given date.
 *
 * @param Fitbit $fitbit
 * @param FatSecret $fat_secret
 * @param DateTimeInterface $date
 * @param Logger $logger
 * @return boolean True on success, false on no entry for date.
 */
function sync_weight_for_date( Fitbit $fitbit, FatSecret $fat_secret, DateTimeInterface $date, Logger $logger = null ) : bool {
	$weight = $fitbit->get_weight_for_date( $date, false );
	if ( ! $weight ) {
		$logger->log( 'No weight entry for date.' );
		return false;
	}

	$fat_secret->update_weight_for_date( $date, $weight );
	return true;
}

/**
 * Syncs food diary for a given date.
 *
 * @param Fitbit $fitbit
 * @param FatSecret $fat_secret
 * @param DateTimeInterface $date
 * @param Logger $logger
 * @return boolean True on success, false on no entry for date.
 */
function sync_food_for_date( Fitbit $fitbit, FatSecret $fat_secret, DateTimeInterface $date, Logger $logger = null ) : bool {
	$food_entries = $fat_secret->get_food_for_day( $date );
	if ( ! count( $food_entries ) ) {
		$logger->log( 'No entries for date.' );
		return false;
	}

	$fitbit->delete_food_entries_for_date( $date );
	$logger->log( 'Cleared food log for date.' );
	$food_units = $fitbit->get_food_units();
	foreach ( $food_entries as $food_entry ) {
		$food_entry->map_fitbit_unit_id( $food_units );
		$fitbit->add_food_for_date( $date, $food_entry );
		$logger->log( "Logged {$food_entry->name}." );
	}
	return true;
}
