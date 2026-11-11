<?php
/**
 * Removes the plugin's options. The workspace and its conversations stay on
 * Hibira Live Chat.
 *
 * @package Hibira_Live_Chat
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'workspace', 'enabled', 'launcher', 'hide_for_staff', 'share_profile', 'color', 'lang', 'identity_secret' ) as $hibira_lc_name ) {
	delete_option( 'hibira_lc_' . $hibira_lc_name );
}
