<?php
/**
 * Plugin Name:       Friendly Avatar Uploader
 * Plugin URI:        https://github.com/jackofall1232/friendly-avatar-uploader
 * Description:       Lets logged-in users upload a custom avatar from the front end via the [friendly_avatar_upload] shortcode. Replaces the Gravatar throughout WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            jackofall1232
 * Author URI:        https://github.com/jackofall1232
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       friendly-avatar-uploader
 * Domain Path:       /languages
 *
 * @package FriendlyAvatarUploader
 */

defined( 'ABSPATH' ) || exit;

define( 'FAU_VERSION', '1.0.0' );
define( 'FAU_META_KEY', 'friendly_custom_avatar' );
define( 'FAU_MAX_FILE_SIZE', 2 * 1024 * 1024 );
define( 'FAU_ALLOWED_TYPES', array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ) );
define( 'FAU_TARGET_SIZE', 300 );
define( 'FAU_PLUGIN_FILE', __FILE__ );
define( 'FAU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FAU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FAU_PLUGIN_DIR . 'includes/class-fau-avatar-filter.php';
require_once FAU_PLUGIN_DIR . 'includes/class-fau-shortcode.php';
require_once FAU_PLUGIN_DIR . 'includes/class-fau-ajax.php';
require_once FAU_PLUGIN_DIR . 'includes/class-fau-profile-page.php';

/**
 * Bootstrap the plugin once WordPress core is ready.
 */
add_action(
	'plugins_loaded',
	static function () {
		new FAU_Avatar_Filter();
		new FAU_Shortcode();
		new FAU_Ajax();
		new FAU_Profile_Page();
	}
);
