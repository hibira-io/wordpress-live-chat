<?php
// Just enough of WordPress for Hibira_LC's pure functions.
define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['hibira_test_options'] = array();

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function apply_filters( $hook, $value ) {
	return $value;
}

function get_option( $name, $fallback = false ) {
	return array_key_exists( $name, $GLOBALS['hibira_test_options'] ) ? $GLOBALS['hibira_test_options'][ $name ] : $fallback;
}

function admin_url( $path = '' ) {
	return 'https://shop.example/wp-admin/' . $path;
}

require dirname( __DIR__ ) . '/includes/class-hibira-lc.php';
