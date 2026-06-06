<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'mgr_daily_sync', 'mgr_sync_reviews' );

function mgr_schedule_cron() {
	if ( ! wp_next_scheduled( 'mgr_daily_sync' ) ) {
		wp_schedule_event( time(), 'daily', 'mgr_daily_sync' );
	}
}

function mgr_clear_cron() {
	$timestamp = wp_next_scheduled( 'mgr_daily_sync' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'mgr_daily_sync' );
	}
}
