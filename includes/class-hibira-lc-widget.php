<?php
/**
 * Prints the Live Chat widget on the site's pages, and lets buttons and
 * links open it: the [hibira_live_chat] shortcode, any element with the
 * class hibira-live-chat-open, and links to #hibira-live-chat (a menu item
 * works).
 *
 * @package Hibira_Live_Chat
 */

defined( 'ABSPATH' ) || exit;

class Hibira_LC_Widget {

	const HANDLE = 'hibira-live-chat';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'tag' ), 10, 2 );
		add_shortcode( 'hibira_live_chat', array( __CLASS__, 'shortcode' ) );
	}

	/** Whether the widget shows on this request. */
	public static function showing() {
		$on = 'no' !== Hibira_LC::opt( 'enabled', 'yes' ) && Hibira_LC::valid_workspace( Hibira_LC::workspace() );
		if ( $on && 'yes' === Hibira_LC::opt( 'hide_for_staff', 'no' ) && current_user_can( 'edit_posts' ) ) {
			$on = false;
		}
		/**
		 * Filters whether the Live Chat widget shows on this page.
		 *
		 * @param bool $on Whether it shows.
		 */
		return (bool) apply_filters( 'hibira_lc_show', $on );
	}

	public static function enqueue() {
		if ( ! self::showing() ) {
			return;
		}
		wp_enqueue_script(
			self::HANDLE,
			Hibira_LC::host() . '/w/v1.js',
			array(),
			HIBIRA_LC_VERSION,
			array(
				'strategy'  => 'async',
				'in_footer' => true,
			)
		);
		wp_enqueue_script( self::HANDLE . '-open', HIBIRA_LC_URL . 'assets/js/open.js', array(), HIBIRA_LC_VERSION, array( 'in_footer' => true ) );
	}

	/** Adds the workspace and options to the widget's script tag. */
	public static function tag( $tag, $handle ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}
		$attrs = '';
		foreach ( self::attributes() as $k => $v ) {
			$attrs .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		return preg_replace( '/<script\b/', '<script' . $attrs, $tag, 1 );
	}

	/** The attributes for this page and visitor. */
	public static function attributes() {
		$user   = null;
		$secret = Hibira_LC::identity_secret();
		if ( '' !== $secret && is_user_logged_in() ) {
			$u    = wp_get_current_user();
			$user = array( 'id' => (string) $u->ID );
			if ( 'no' !== Hibira_LC::opt( 'share_profile', 'yes' ) ) {
				$user['name']  = $u->display_name;
				$user['email'] = $u->user_email;
			}
		}
		$a = Hibira_LC::attributes(
			array(
				'workspace' => Hibira_LC::workspace(),
				'color'     => Hibira_LC::opt( 'color' ),
				'lang'      => Hibira_LC::opt( 'lang' ),
				'launcher'  => Hibira_LC::opt( 'launcher', 'yes' ),
				'locale'    => determine_locale(),
			),
			$user,
			$secret
		);
		/**
		 * Filters the Live Chat script tag's data- attributes.
		 *
		 * @param array $a Attribute name => value.
		 */
		return (array) apply_filters( 'hibira_lc_attributes', $a );
	}

	/**
	 * [hibira_live_chat label="Chat with us" topic="sales"]: a button that opens the chat.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public static function shortcode( $atts ) {
		$atts  = shortcode_atts(
			array(
				'label' => __( 'Chat with us', 'hibira-live-chat' ),
				'topic' => '',
			),
			$atts,
			'hibira_live_chat'
		);
		$topic = 'sales' === $atts['topic'] ? ' data-topic="sales"' : '';
		return '<button type="button" class="hibira-live-chat-open wp-element-button"' . $topic . '>' . esc_html( $atts['label'] ) . '</button>';
	}
}
