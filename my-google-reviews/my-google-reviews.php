<?php
/**
 * Plugin Name: My Google Reviews
 * Description: Fetches Google Business reviews via Places API and stores them locally. No external JS or CSS.
 * Version:     1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MGR_DIR', plugin_dir_path( __FILE__ ) );
define( 'MGR_VERSION', '1.0.0' );

require_once MGR_DIR . 'includes/schema.php';
require_once MGR_DIR . 'includes/api.php';
require_once MGR_DIR . 'includes/cron.php';
require_once MGR_DIR . 'includes/admin.php';

register_activation_hook( __FILE__, 'mgr_activate' );
register_deactivation_hook( __FILE__, 'mgr_deactivate' );

function mgr_activate() {
	mgr_create_table();
	mgr_schedule_cron();
}

function mgr_deactivate() {
	mgr_clear_cron();
}
