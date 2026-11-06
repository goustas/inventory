<?php
/**
 * Plugin Name:       CAPO Inventory
 * Plugin URI:        https://www.pixelcrafters.com.cy
 * Description:       IT equipment inventory for the Cyprus Agricultural Payments Organisation: asset register, employee change requests with approval, audit log and CSV export.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            George Oustas, Pixelcrafters.com.cy
 * Author URI:        https://www.pixelcrafters.com.cy
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       capo-inventory
 * Domain Path:       /languages
 *
 * @package CAPO_Inventory
 */

defined( 'ABSPATH' ) || exit;

define( 'CAPO_INV_VERSION', '1.0.0' );
define( 'CAPO_INV_DB_VERSION', '1' );
define( 'CAPO_INV_FILE', __FILE__ );
define( 'CAPO_INV_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAPO_INV_URL', plugin_dir_url( __FILE__ ) );

require_once CAPO_INV_DIR . 'includes/class-capo-inv-fields.php';
require_once CAPO_INV_DIR . 'includes/class-capo-inv-install.php';
require_once CAPO_INV_DIR . 'includes/class-capo-inv-lists.php';
require_once CAPO_INV_DIR . 'includes/class-capo-inv-assets.php';
require_once CAPO_INV_DIR . 'includes/class-capo-inv-audit.php';
require_once CAPO_INV_DIR . 'includes/class-capo-inv-requests.php';
require_once CAPO_INV_DIR . 'includes/class-capo-inv-export.php';
require_once CAPO_INV_DIR . 'includes/class-capo-inv-plugin.php';

register_activation_hook( __FILE__, array( 'CAPO_Inv_Install', 'activate' ) );

CAPO_Inv_Plugin::instance();
