<?php
/**
 * Plugin Name:       Hibira Live Chat
 * Plugin URI:        https://chat.hibira.io/?utm_source=wordpress&utm_medium=plugin&utm_campaign=live-chat
 * Description:       Talk to your visitors and customers from a chat bubble on every page, with AI answers from your own help articles, translation and a shared inbox on the web and on phones.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Hibira
 * Author URI:        https://hibira.io/?utm_source=wordpress&utm_medium=plugin&utm_campaign=live-chat
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hibira-live-chat
 *
 * @package Hibira_Live_Chat
 */

defined( 'ABSPATH' ) || exit;

define( 'HIBIRA_LC_VERSION', '0.1.0' );
define( 'HIBIRA_LC_FILE', __FILE__ );
define( 'HIBIRA_LC_DIR', plugin_dir_path( __FILE__ ) );
define( 'HIBIRA_LC_URL', plugin_dir_url( __FILE__ ) );

require_once HIBIRA_LC_DIR . 'includes/class-hibira-lc.php';
require_once HIBIRA_LC_DIR . 'includes/class-hibira-lc-widget.php';

Hibira_LC_Widget::init();

if ( is_admin() ) {
	require_once HIBIRA_LC_DIR . 'includes/class-hibira-lc-admin.php';
	Hibira_LC_Admin::init();
}

add_filter(
	'plugin_action_links_' . plugin_basename( HIBIRA_LC_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( Hibira_LC::settings_url() ) . '">' . esc_html__( 'Settings', 'hibira-live-chat' ) . '</a>' );
		return $links;
	}
);
