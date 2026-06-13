<?php
defined( 'ABSPATH' ) || exit;

function crm_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $companies = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}crm_companies (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_name VARCHAR(200) NOT NULL,
        email      VARCHAR(200) DEFAULT '',
        phone      VARCHAR(50)  DEFAULT '',
        website    VARCHAR(300) DEFAULT '',
        linkedin   VARCHAR(300) DEFAULT '',
        facebook   VARCHAR(300) DEFAULT '',
        instagram  VARCHAR(300) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    $contacts = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}crm_contacts (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        first_name VARCHAR(100) NOT NULL,
        last_name  VARCHAR(100) NOT NULL,
        email      VARCHAR(200) DEFAULT '',
        phone      VARCHAR(50)  DEFAULT '',
        linkedin   VARCHAR(300) DEFAULT '',
        notes      TEXT         DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    $pivot = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}crm_company_contact (
        company_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (company_id, contact_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $companies );
    dbDelta( $contacts );
    dbDelta( $pivot );
}
register_activation_hook( get_template_directory() . '/functions.php', 'crm_create_tables' );

// Also run on init in case activation hook missed (e.g. theme switch).
add_action( 'after_switch_theme', 'crm_create_tables' );
