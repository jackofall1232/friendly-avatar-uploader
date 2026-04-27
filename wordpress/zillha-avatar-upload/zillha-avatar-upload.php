<?php
/**
 * Plugin Name:       ZillHa Avatar Upload
 * Plugin URI:        https://zillha.com/
 * Description:       Lets logged-in users upload a custom avatar from the front end via the [zillha_avatar_upload] shortcode. Replaces the Gravatar throughout WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            ZillHa
 * Author URI:        https://zillha.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zillha-avatar-upload
 * Domain Path:       /languages
 *
 * @package ZillHa\AvatarUpload
 */

defined( 'ABSPATH' ) || exit;

define( 'ZAU_VERSION', '1.0.0' );
define( 'ZAU_META_KEY', 'zillha_custom_avatar' );
define( 'ZAU_MAX_FILE_SIZE', 2 * 1024 * 1024 );
define( 'ZAU_ALLOWED_TYPES', array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ) );
define( 'ZAU_TARGET_SIZE', 300 );
define( 'ZAU_PLUGIN_FILE', __FILE__ );
define( 'ZAU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZAU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ZAU_PLUGIN_DIR . 'includes/class-zau-avatar-filter.php';
require_once ZAU_PLUGIN_DIR . 'includes/class-zau-shortcode.php';
require_once ZAU_PLUGIN_DIR . 'includes/class-zau-ajax.php';

/**
 * Bootstrap the plugin once WordPress core is ready.
 */
add_action(
	'plugins_loaded',
	static function () {
		new ZAU_Avatar_Filter();
		new ZAU_Shortcode();
		new ZAU_Ajax();
	}
);
