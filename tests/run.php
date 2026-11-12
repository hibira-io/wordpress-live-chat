<?php
// php tests/run.php
require __DIR__ . '/bootstrap.php';

$failed = 0;
function check( $name, $ok ) {
	global $failed;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	if ( ! $ok ) {
		++$failed;
	}
}

check( 'workspace IDs', Hibira_LC::valid_workspace( 'ws_9l_rqicfgpbu1a' ) && ! Hibira_LC::valid_workspace( 'ws_' ) && ! Hibira_LC::valid_workspace( 'ws_a"b' ) && ! Hibira_LC::valid_workspace( 'abc' ) && ! Hibira_LC::valid_workspace( null ) );
check( 'colours', '#ff6600' === Hibira_LC::clean_color( ' #ff6600 ' ) && '' === Hibira_LC::clean_color( 'red' ) && '' === Hibira_LC::clean_color( '#ff6600;x' ) );
check( 'origin', 'https://shop.example' === Hibira_LC::origin_of( 'https://Shop.Example/blog/' ) && 'http://localhost:8080' === Hibira_LC::origin_of( 'http://localhost:8080/' ) && '' === Hibira_LC::origin_of( '/wp-admin/' ) );

check( 'connect on https', Hibira_LC::can_connect( 'https://shop.example/', 'https://shop.example/wp-admin/admin.php?page=hibira-live-chat' ) );
check( 'connect on localhost', Hibira_LC::can_connect( 'http://localhost:8080/', 'http://localhost:8080/wp-admin/' ) );
check( 'no connect on http', ! Hibira_LC::can_connect( 'http://shop.example/', 'http://shop.example/wp-admin/' ) );
check( 'no connect across origins', ! Hibira_LC::can_connect( 'https://shop.example/', 'https://admin.shop.example/wp-admin/' ) );

$u = Hibira_LC::connect_url( 'https://shop.example/', 'https://shop.example/wp-admin/admin.php?page=hibira-live-chat', 'abcdefgh12345678' );
parse_str( (string) parse_url( $u, PHP_URL_QUERY ), $q );
check( 'connect url', 0 === strpos( $u, 'https://chat.hibira.io/portal/connect?' ) && 'https://shop.example' === $q['site'] && 'https://shop.example/wp-admin/admin.php?page=hibira-live-chat' === $q['return_to'] && 'abcdefgh12345678' === $q['state'] && 'wordpress' === $q['platform'] );

// The same HMAC as pusher-server's identity check: hex(HMAC-SHA256(secret, user ID)).
check( 'user hash', hash_hmac( 'sha256', '42', 'lcid_test' ) === Hibira_LC::user_hash( 'lcid_test', 42 ) && 64 === strlen( Hibira_LC::user_hash( 'lcid_test', '42' ) ) );

$base = array( 'workspace' => 'ws_abc' );
check( 'plain attributes', array( 'data-workspace' => 'ws_abc' ) === Hibira_LC::attributes( $base ) );
$a = Hibira_LC::attributes( $base + array( 'color' => '#123456', 'lang' => 'site', 'locale' => 'zh_TW', 'launcher' => 'no' ) );
check( 'options', '#123456' === $a['data-color'] && 'zh-tw' === $a['data-lang'] && 'none' === $a['data-launcher'] );
check( 'browser language', ! isset( Hibira_LC::attributes( $base + array( 'lang' => '', 'locale' => 'ja' ) )['data-lang'] ) );
check( 'no user without secret', ! isset( Hibira_LC::attributes( $base, array( 'id' => '7' ), '' )['data-user-id'] ) );
$a = Hibira_LC::attributes( $base, array( 'id' => '7', 'name' => 'Aoi', 'email' => 'aoi@example.com' ), 'lcid_s' );
check( 'verified user', '7' === $a['data-user-id'] && Hibira_LC::user_hash( 'lcid_s', '7' ) === $a['data-user-hash'] && 'Aoi' === $a['data-user-name'] && 'aoi@example.com' === $a['data-user-email'] );
$a = Hibira_LC::attributes( $base, array( 'id' => '7' ), 'lcid_s' );
check( 'verified user without profile', isset( $a['data-user-hash'] ) && ! isset( $a['data-user-name'] ) && ! isset( $a['data-user-email'] ) );

$GLOBALS['hibira_test_options']['hibira_lc_workspace'] = 'ws_saved';
check( 'saved workspace', 'ws_saved' === Hibira_LC::workspace() );
check( 'settings url', 'https://shop.example/wp-admin/admin.php?page=hibira-live-chat' === Hibira_LC::settings_url() );

exit( $failed ? 1 : 0 );
