<?php
defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/db.php';
require_once get_template_directory() . '/inc/dashboard.php';
require_once get_template_directory() . '/inc/company-view.php';
require_once get_template_directory() . '/inc/companies.php';
require_once get_template_directory() . '/inc/contacts.php';

// --- Enqueue Bootstrap 5 on admin CRM pages ---
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( strpos( $hook, 'crm-' ) === false && $hook !== 'toplevel_page_crm-dashboard' ) {
        return;
    }
    wp_enqueue_style(
        'bootstrap5',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [],
        '5.3.3'
    );
    wp_enqueue_style( 'crm-admin', get_template_directory_uri() . '/assets/crm-admin.css', [ 'bootstrap5' ], '1.0.0' );
} );

// --- Admin menu ---
add_action( 'admin_menu', function () {
    add_menu_page(
        'CRM',
        'CRM',
        'manage_options',
        'crm-dashboard',
        'crm_dashboard_page',
        'dashicons-businessman',
        30
    );
    add_submenu_page( 'crm-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'crm-dashboard', 'crm_dashboard_page' );
    add_submenu_page( 'crm-dashboard', 'Companies',  'Companies',  'manage_options', 'crm-companies', 'crm_companies_page' );
    add_submenu_page( 'crm-dashboard', 'Contacts',   'Contacts',   'manage_options', 'crm-contacts',  'crm_contacts_page' );
} );

// --- Front-end: basic theme support ---
add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'bootstrap5',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [],
        '5.3.3'
    );
    wp_enqueue_style( 'crm-theme', get_stylesheet_uri(), [ 'bootstrap5' ], '1.0.0' );
} );
