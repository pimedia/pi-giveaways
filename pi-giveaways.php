<?php
/**
 * Plugin Name: PI Giveaways
 * Plugin URI: https://wordpress.org/plugins/pi-giveaways/
 * Description: Create giveaways, collect entries, and manually award winners through a focused, privacy-aware workflow.
 * Version: 1.0.7
 * Author: Ali Qureshi
 * Author URI: https://www.parorrey.com/
 * Text Domain: pi-giveaways
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package PI_Giveaways
 */

defined( 'ABSPATH' ) || exit;

define( 'PIGIVEAWAYS_VERSION', '1.0.7' );
define( 'PIGIVEAWAYS_FILE', __FILE__ );
define( 'PIGIVEAWAYS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIGIVEAWAYS_URL', plugin_dir_url( __FILE__ ) );
define( 'PIGIVEAWAYS_PRO_URL', 'https://www.parorrey.com/solutions/pi-giveaways-pro/' );

require_once PIGIVEAWAYS_DIR . 'includes/class-pigi-activator.php';
require_once PIGIVEAWAYS_DIR . 'includes/class-pigi-plugin.php';

register_activation_hook( __FILE__, array( 'PIGI_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PIGI_Plugin', 'deactivate' ) );

PIGI_Plugin::instance();
