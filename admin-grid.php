<?php

/**
 * Plugin Name: Admin Posts Grid
 * Description: Display admin posts list as a grid of cards. Several themes available and per-user preferences.
 * Version: 1.0.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Flavio Iulita
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$version = 'version:1.0.5';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CHERITTO_ADMIN_GRID_VERSION', str_replace( 'version:', '', $version ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cheritto-admin-grid-activator.php
 */
function activate_cheritto_admin_grid() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cheritto-admin-grid-activator.php';
	$activator = new Cheritto_Admin_Grid_Activator;
	$activator->activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cheritto-admin-grid-deactivator.php
 */
function deactivate_cheritto_admin_grid() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cheritto-admin-grid-deactivator.php';
	$deactivator = new Cheritto_Admin_Grid_Deactivator;
	$deactivator->deactivate();
}

/**
 * The code that runs during plugin uninstall.
 * This action is documented in uninstall.php
 */
function uninstall_cheritto_admin_grid() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cheritto-admin-grid-uninstall.php';
	$uninstaller = new Cheritto_Admin_Grid_Uninstall;
	$uninstaller->uninstall();
}

register_activation_hook( __FILE__, 'activate_cheritto_admin_grid' );
register_deactivation_hook( __FILE__, 'deactivate_cheritto_admin_grid' );
register_uninstall_hook( __FILE__, 'uninstall_cheritto_admin_grid' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-cheritto-admin-grid.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_cheritto_admin_grid($version) {

	$plugin = new Cheritto_Admin_Grid($version);
	$plugin->run();

}

run_cheritto_admin_grid($version);
