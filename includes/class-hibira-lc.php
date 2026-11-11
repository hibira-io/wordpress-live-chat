<?php
/**
 * Settings and the small pieces of logic the widget and the settings page
 * share. Nothing here talks to Hibira: the plugin only prints the widget's
 * script tag; the chat itself runs on chat.hibira.io.
 *
 * @package Hibira_Live_Chat
 */

defined( 'ABSPATH' ) || exit;

class Hibira_LC {

	const PREFIX = 'hibira_lc_';

	/** Where Live Chat runs. The hibira_lc_host filter points it elsewhere (tests, a local server). */
	public static function host() {
		$host = function_exists( 'apply_filters' ) ? apply_filters( 'hibira_lc_host', 'https://chat.hibira.io' ) : 'https://chat.hibira.io';
		return rtrim( (string) $host, '/' );
	}

	/** An option of this plugin. */
	public static function opt( $name, $fallback = '' ) {
		return get_option( self::PREFIX . $name, $fallback );
	}

	/** The workspace ID: HIBIRA_LIVECHAT_WORKSPACE in wp-config.php, else the saved one. */
	public static function workspace() {
		if ( defined( 'HIBIRA_LIVECHAT_WORKSPACE' ) ) {
			return (string) HIBIRA_LIVECHAT_WORKSPACE;
		}
		return (string) self::opt( 'workspace' );
	}

	/** The identity secret (lcid_…) for verified visitors, if any. */
	public static function identity_secret() {
		if ( defined( 'HIBIRA_LIVECHAT_IDENTITY_SECRET' ) ) {
			return (string) HIBIRA_LIVECHAT_IDENTITY_SECRET;
		}
		return (string) self::opt( 'identity_secret' );
	}

	/** Whether s looks like a workspace ID (ws_…). */
	public static function valid_workspace( $s ) {
		return is_string( $s ) && 1 === preg_match( '/^ws_[A-Za-z0-9_-]{1,64}$/', $s );
	}

	/** A colour the widget accepts (#rgb … #rrggbbaa), or ''. */
	public static function clean_color( $s ) {
		$s = trim( (string) $s );
		return 1 === preg_match( '/^#[0-9a-fA-F]{3,8}$/', $s ) ? $s : '';
	}

	/** scheme://host[:port] of a URL, lower case, or '' when it has none. */
	public static function origin_of( $url ) {
		$p = wp_parse_url( (string) $url );
		if ( empty( $p['scheme'] ) || empty( $p['host'] ) ) {
			return '';
		}
		$o = strtolower( $p['scheme'] ) . '://' . strtolower( $p['host'] );
		return isset( $p['port'] ) ? $o . ':' . (int) $p['port'] : $o;
	}

	/** Whether one click can connect: the site and this settings page share an https origin (or localhost). */
	public static function can_connect( $site, $back ) {
		$o = self::origin_of( $site );
		if ( '' === $o || self::origin_of( $back ) !== $o ) {
			return false;
		}
		if ( 0 === strpos( $o, 'https://' ) ) {
			return true;
		}
		$h = wp_parse_url( $o, PHP_URL_HOST );
		return 'localhost' === $h || '127.0.0.1' === $h;
	}

	/** The Live Chat page that adds the site to a workspace and comes back with its ID. */
	public static function connect_url( $site, $back, $state ) {
		return self::host() . '/portal/connect?' . http_build_query(
			array(
				'site'      => self::origin_of( $site ),
				'return_to' => $back,
				'state'     => $state,
				'platform'  => 'wordpress',
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/** hex(HMAC-SHA256(identity secret, user ID)): what the widget checks for a verified visitor. */
	public static function user_hash( $secret, $user_id ) {
		return hash_hmac( 'sha256', (string) $user_id, (string) $secret );
	}

	/**
	 * The script tag's data- attributes.
	 *
	 * @param array      $o    workspace, color, lang ('' browser, 'site' site language), launcher ('yes'/'no'), locale.
	 * @param array|null $user id, name, email of the signed-in user, or null.
	 * @param string     $secret identity secret; with a user, adds the verified-visitor attributes.
	 */
	public static function attributes( array $o, $user = null, $secret = '' ) {
		$a = array( 'data-workspace' => $o['workspace'] );
		$c = self::clean_color( isset( $o['color'] ) ? $o['color'] : '' );
		if ( '' !== $c ) {
			$a['data-color'] = $c;
		}
		if ( isset( $o['lang'] ) && 'site' === $o['lang'] && ! empty( $o['locale'] ) ) {
			$a['data-lang'] = strtolower( str_replace( '_', '-', (string) $o['locale'] ) );
		}
		if ( isset( $o['launcher'] ) && 'no' === $o['launcher'] ) {
			$a['data-launcher'] = 'none';
		}
		if ( is_array( $user ) && '' !== (string) $secret && ! empty( $user['id'] ) ) {
			$a['data-user-id']   = (string) $user['id'];
			$a['data-user-hash'] = self::user_hash( $secret, $user['id'] );
			if ( ! empty( $user['name'] ) ) {
				$a['data-user-name'] = (string) $user['name'];
			}
			if ( ! empty( $user['email'] ) ) {
				$a['data-user-email'] = (string) $user['email'];
			}
		}
		return $a;
	}

	/** This plugin's settings page. */
	public static function settings_url() {
		return admin_url( 'admin.php?page=hibira-live-chat' );
	}
}
