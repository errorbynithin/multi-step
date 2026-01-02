<?php
/**
 * Plugin Name: MultiStep Form Builder Pro
 * Description: Gravity Forms–like builder with multi-step support, conditional logic, notifications, and exports.
 * Version: 1.0.0
 * Author: Example Co
 * Text Domain: msfbp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MSFBP_VERSION', '1.0.0' );
define( 'MSFBP_PLUGIN_FILE', __FILE__ );
define( 'MSFBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSFBP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MSFBP_PLUGIN_DIR . 'includes/functions-helpers.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-activator.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-deactivator.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-db.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-conditional.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-validator.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-notifications.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-render.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-admin.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-settings.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-rest.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-entries.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-seeder.php';
require_once MSFBP_PLUGIN_DIR . 'includes/class-msfbp-plugin.php';

register_activation_hook( __FILE__, array( 'MSFBP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MSFBP_Deactivator', 'deactivate' ) );

function msfbp_run() {
	$plugin = new MSFBP_Plugin();
	$plugin->run();
}

msfbp_run();
