<?php
/** Minimal dbDelta fixture used by the isolated schema migration test. */
function dbDelta( $query ) {
	global $wpdb;
	$wpdb->record_schema_query( $query );
}
