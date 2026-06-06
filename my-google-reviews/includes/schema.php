<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mgr_create_table() {
	global $wpdb;

	$table   = $wpdb->prefix . 'google_reviews';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table (
		id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
		review_id   VARCHAR(255)    NOT NULL,
		author      VARCHAR(255)    NOT NULL DEFAULT '',
		photo_url   TEXT            NOT NULL DEFAULT '',
		rating      TINYINT         NOT NULL DEFAULT 0,
		body        TEXT            NOT NULL DEFAULT '',
		published   DATETIME        NOT NULL,
		hidden      TINYINT(1)      NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY   review_id (review_id)
	) $charset;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Store DB version so future updates can run ALTER TABLE if needed
	update_option( 'mgr_db_version', MGR_VERSION );
}
