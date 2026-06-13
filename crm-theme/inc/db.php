<?php
defined( 'ABSPATH' ) || exit;

define( 'CRM_DB_VERSION', '1.1' );

function crm_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // dbDelta safely adds new columns to existing tables — existing data is untouched.
    $companies = "CREATE TABLE {$wpdb->prefix}crm_companies (
        id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_name VARCHAR(200) NOT NULL,
        email        VARCHAR(200) NOT NULL DEFAULT '',
        phone        VARCHAR(50)  NOT NULL DEFAULT '',
        website      VARCHAR(300) NOT NULL DEFAULT '',
        linkedin     VARCHAR(300) NOT NULL DEFAULT '',
        facebook     VARCHAR(300) NOT NULL DEFAULT '',
        instagram    VARCHAR(300) NOT NULL DEFAULT '',
        converted    TINYINT(1)   NOT NULL DEFAULT 1,
        active       TINYINT(1)   NOT NULL DEFAULT 1,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    $contacts = "CREATE TABLE {$wpdb->prefix}crm_contacts (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        first_name VARCHAR(100) NOT NULL,
        last_name  VARCHAR(100) NOT NULL,
        email      VARCHAR(200) NOT NULL DEFAULT '',
        phone      VARCHAR(50)  NOT NULL DEFAULT '',
        linkedin   VARCHAR(300) NOT NULL DEFAULT '',
        notes      TEXT         NOT NULL,
        converted  TINYINT(1)   NOT NULL DEFAULT 1,
        active     TINYINT(1)   NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    $pivot = "CREATE TABLE {$wpdb->prefix}crm_company_contact (
        company_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (company_id, contact_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $companies );
    dbDelta( $contacts );
    dbDelta( $pivot );

    update_option( 'crm_db_version', CRM_DB_VERSION );
}

// Run on theme activation.
add_action( 'after_switch_theme', 'crm_create_tables' );

// Run on init if db version is behind — handles migrations for already-active installs.
add_action( 'init', function () {
    if ( get_option( 'crm_db_version' ) !== CRM_DB_VERSION ) {
        crm_create_tables();
    }
} );
