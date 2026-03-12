<?php
/**
 * Plugin Name: SavedPixel Update Disabler
 * Plugin URI: https://github.com/savedpixel
 * Description: Control WordPress update checks, core auto-update channels, outbound HTTP requests, and mail behavior.
 * Version: 1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Byron Jacobs
 * Author URI: https://github.com/savedpixel
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: savedpixel-update-disabler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/savedpixel-admin-shared.php';
require_once __DIR__ . '/includes/class-savedpixel-update-disabler.php';

savedpixel_register_admin_preview_asset(
	plugin_dir_url( __FILE__ ) . 'assets/css/savedpixel-admin-preview.css',
	SavedPixel_Update_Disabler::VERSION,
	array( 'savedpixel', 'savedpixel-update-disabler' )
);

SavedPixel_Update_Disabler::bootstrap();

register_activation_hook( __FILE__, array( 'SavedPixel_Update_Disabler', 'activate' ) );
