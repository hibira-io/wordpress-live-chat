<?php
/**
 * Live Chat in wp-admin: connect the site to a Hibira Live Chat workspace
 * (one click through chat.hibira.io, or by pasting the workspace ID), the
 * widget's options here, and links to the inbox and the workspace settings.
 *
 * @package Hibira_Live_Chat
 */

defined( 'ABSPATH' ) || exit;

class Hibira_LC_Admin {

	const PAGE  = 'hibira-live-chat';
	const CAP   = 'manage_options';
	const GROUP = 'hibira_lc';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'connected' ) );
		add_action( 'admin_post_hibira_lc_connect', array( __CLASS__, 'connect' ) );
		add_action( 'admin_post_hibira_lc_workspace', array( __CLASS__, 'save_workspace' ) );
		add_action( 'admin_post_hibira_lc_disconnect', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_menu_page( __( 'Hibira Live Chat', 'hibira-live-chat' ), __( 'Live Chat', 'hibira-live-chat' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ), 'dashicons-format-chat', 58 );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'hibira-lc-admin', HIBIRA_LC_URL . 'assets/css/admin.css', array(), HIBIRA_LC_VERSION );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".hibira-lc-color").wpColorPicker();});' );
	}

	public static function register() {
		$p     = Hibira_LC::PREFIX;
		$yesno = array(
			'sanitize_callback' => function ( $v ) {
				return 'yes' === $v ? 'yes' : 'no';
			},
		);
		register_setting( self::GROUP, $p . 'enabled', $yesno );
		register_setting( self::GROUP, $p . 'launcher', $yesno );
		register_setting( self::GROUP, $p . 'hide_for_staff', $yesno );
		register_setting( self::GROUP, $p . 'share_profile', $yesno );
		register_setting(
			self::GROUP,
			$p . 'color',
			array(
				'sanitize_callback' => array( 'Hibira_LC', 'clean_color' ),
			)
		);
		register_setting(
			self::GROUP,
			$p . 'lang',
			array(
				'sanitize_callback' => function ( $v ) {
					return 'site' === $v ? 'site' : '';
				},
			)
		);
		register_setting(
			self::GROUP,
			$p . 'identity_secret',
			array(
				'sanitize_callback' => function ( $v ) {
					$v = trim( sanitize_text_field( (string) $v ) );
					if ( '-' === $v ) {
						return '';
					}
					// An empty box keeps the saved secret; it is never printed back.
					return '' === $v ? (string) get_option( Hibira_LC::PREFIX . 'identity_secret', '' ) : $v;
				},
			)
		);
	}

	/** Sends the owner to chat.hibira.io to pick the workspace; they come back to connected(). */
	public static function connect() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'hibira-live-chat' ) );
		}
		check_admin_referer( 'hibira_lc_connect' );
		$back = Hibira_LC::settings_url();
		if ( ! Hibira_LC::can_connect( home_url( '/' ), $back ) ) {
			self::back( 'no_connect' );
		}
		$state = wp_generate_password( 32, false );
		set_transient( 'hibira_lc_state_' . get_current_user_id(), $state, 30 * MINUTE_IN_SECONDS );
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- chat.hibira.io is another site by design.
		wp_redirect( Hibira_LC::connect_url( home_url( '/' ), $back, $state ) );
		exit;
	}

	/** Back from chat.hibira.io with ?hibira_workspace=…&state=… (or ?hibira_error=denied). */
	public static function connected() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the state stands in for the nonce: it was made for this user and is checked below.
		if ( ! isset( $_GET['page'] ) || self::PAGE !== $_GET['page'] || ! isset( $_GET['state'] ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		$key   = 'hibira_lc_state_' . get_current_user_id();
		$want  = (string) get_transient( $key );
		$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
		if ( '' === $want || ! hash_equals( $want, $state ) ) {
			self::back( 'bad_state' );
		}
		delete_transient( $key );
		if ( isset( $_GET['hibira_error'] ) ) {
			self::back( 'denied' );
		}
		$ws = isset( $_GET['hibira_workspace'] ) ? sanitize_text_field( wp_unslash( $_GET['hibira_workspace'] ) ) : '';
		// phpcs:enable
		if ( ! Hibira_LC::valid_workspace( $ws ) ) {
			self::back( 'bad_workspace' );
		}
		update_option( Hibira_LC::PREFIX . 'workspace', $ws );
		self::back( 'connected' );
	}

	/** The workspace ID typed in by hand. */
	public static function save_workspace() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'hibira-live-chat' ) );
		}
		check_admin_referer( 'hibira_lc_workspace' );
		$ws = isset( $_POST['workspace'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['workspace'] ) ) ) : '';
		if ( ! Hibira_LC::valid_workspace( $ws ) ) {
			self::back( 'bad_workspace' );
		}
		update_option( Hibira_LC::PREFIX . 'workspace', $ws );
		self::back( 'saved_workspace' );
	}

	public static function disconnect() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'hibira-live-chat' ) );
		}
		check_admin_referer( 'hibira_lc_disconnect' );
		delete_option( Hibira_LC::PREFIX . 'workspace' );
		self::back( 'disconnected' );
	}

	private static function back( $notice ) {
		wp_safe_redirect( add_query_arg( 'hibira_lc', $notice, Hibira_LC::settings_url() ) );
		exit;
	}

	/** The notice for ?hibira_lc=…: text and whether it is good news. */
	private static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which message to show.
		$n = isset( $_GET['hibira_lc'] ) ? sanitize_key( wp_unslash( $_GET['hibira_lc'] ) ) : '';
		$all = array(
			'connected'       => array( __( 'Connected. The chat now shows on your site.', 'hibira-live-chat' ), true ),
			'saved_workspace' => array( __( 'Workspace saved. Make sure your site is in the workspace\'s sites (Settings › Widget on chat.hibira.io), or the chat will not show.', 'hibira-live-chat' ), true ),
			'disconnected'    => array( __( 'Disconnected. The chat no longer shows on your site.', 'hibira-live-chat' ), true ),
			'denied'          => array( __( 'Not connected: the request was cancelled on chat.hibira.io.', 'hibira-live-chat' ), false ),
			'bad_state'       => array( __( 'That sign-in link has expired. Press Sign in with Hibira again.', 'hibira-live-chat' ), false ),
			'bad_workspace'   => array( __( 'A workspace ID starts with ws_. Copy it from Settings › Widget on chat.hibira.io.', 'hibira-live-chat' ), false ),
			'no_connect'      => array( __( 'One-click connect needs your site on https. Paste the workspace ID instead.', 'hibira-live-chat' ), false ),
		);
		return isset( $all[ $n ] ) ? $all[ $n ] : null;
	}

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$ws     = Hibira_LC::workspace();
		$fixed  = defined( 'HIBIRA_LIVECHAT_WORKSPACE' );
		$host   = Hibira_LC::host();
		$notice = self::notice();
		$p      = Hibira_LC::PREFIX;

		echo '<div class="wrap hibira-lc">';
		echo '<h1 class="hibira-lc-title"><img src="' . esc_url( HIBIRA_LC_URL . 'assets/images/live-chat-icon.png' ) . '" width="32" height="32" alt=""> ' . esc_html__( 'Hibira Live Chat', 'hibira-live-chat' ) . '</h1>';
		echo '<p class="hibira-lc-lead">' . esc_html__( 'A chat bubble on every page of your site. Answer from the Hibira inbox; AI can answer common questions from your help articles, and messages are translated both ways.', 'hibira-live-chat' ) . '</p>';
		if ( $notice ) {
			echo '<div class="notice ' . ( $notice[1] ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . esc_html( $notice[0] ) . '</p></div>';
		}

		echo '<div class="hibira-lc-card">';
		echo '<h2>' . esc_html__( 'Workspace', 'hibira-live-chat' ) . '</h2>';
		if ( Hibira_LC::valid_workspace( $ws ) ) {
			/* translators: %s: the workspace ID, like ws_abc123 */
			echo '<p class="hibira-lc-ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . sprintf( esc_html__( 'Connected to %s.', 'hibira-live-chat' ), '<code>' . esc_html( $ws ) . '</code>' ) . '</p>';
			echo '<p class="hibira-lc-actions">';
			echo '<a class="button button-primary" href="' . esc_url( $host . '/inbox' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the inbox', 'hibira-live-chat' ) . '</a> ';
			echo '<a class="button" href="' . esc_url( $host . '/portal/widget' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Look and greeting', 'hibira-live-chat' ) . '</a> ';
			echo '<a class="button" href="' . esc_url( $host . '/portal/knowledge' ) . '" target="_blank" rel="noopener">' . esc_html__( 'AI answers', 'hibira-live-chat' ) . '</a> ';
			echo '<a class="button" href="' . esc_url( $host . '/portal/billing' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Plan', 'hibira-live-chat' ) . '</a>';
			echo '</p>';
			if ( ! $fixed ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="hibira_lc_disconnect">';
				wp_nonce_field( 'hibira_lc_disconnect' );
				echo '<button type="submit" class="button-link hibira-lc-danger">' . esc_html__( 'Disconnect', 'hibira-live-chat' ) . '</button>';
				echo '</form>';
			} else {
				echo '<p class="description">' . esc_html__( 'Set by HIBIRA_LIVECHAT_WORKSPACE in wp-config.php.', 'hibira-live-chat' ) . '</p>';
			}
		} else {
			if ( Hibira_LC::can_connect( home_url( '/' ), Hibira_LC::settings_url() ) ) {
				echo '<p>' . esc_html__( 'Sign in to Hibira Live Chat, choose Connect, and you come back here with the chat on your site. Your site is added to the workspace for you.', 'hibira-live-chat' ) . '</p>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="hibira_lc_connect">';
				wp_nonce_field( 'hibira_lc_connect' );
				echo '<button type="submit" class="button button-primary button-hero">' . esc_html__( 'Sign in with Hibira', 'hibira-live-chat' ) . '</button>';
				echo '</form>';
			}
			/* translators: %s: link to chat.hibira.io */
			echo '<p>' . sprintf( esc_html__( 'No account yet? Create a free one at %s.', 'hibira-live-chat' ), '<a href="' . esc_url( $host . '/?utm_source=wordpress&utm_medium=plugin&utm_campaign=live-chat' ) . '" target="_blank" rel="noopener">chat.hibira.io</a>' ) . '</p>';
			echo '<details class="hibira-lc-manual"' . ( Hibira_LC::can_connect( home_url( '/' ), Hibira_LC::settings_url() ) ? '' : ' open' ) . '><summary>' . esc_html__( 'Or paste a workspace ID', 'hibira-live-chat' ) . '</summary>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="hibira_lc_workspace">';
			wp_nonce_field( 'hibira_lc_workspace' );
			echo '<p><label for="hibira-lc-workspace">' . esc_html__( 'Workspace ID', 'hibira-live-chat' ) . '</label><br><input id="hibira-lc-workspace" name="workspace" class="regular-text code" placeholder="ws_…" pattern="ws_[A-Za-z0-9_\-]+" required> ';
			submit_button( __( 'Save', 'hibira-live-chat' ), 'secondary', 'submit', false );
			echo '</p>';
			/* translators: %s: the site address, like https://shop.example */
			echo '<p class="description">' . sprintf( esc_html__( 'Find it in Settings › Widget on chat.hibira.io, and add %s to the sites there.', 'hibira-live-chat' ), '<code>' . esc_html( Hibira_LC::origin_of( home_url( '/' ) ) ) . '</code>' ) . '</p>';
			echo '</form></details>';
		}
		echo '</div>';

		echo '<form method="post" action="options.php" class="hibira-lc-card">';
		settings_fields( self::GROUP );
		echo '<h2>' . esc_html__( 'On your site', 'hibira-live-chat' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';
		self::checkbox( 'enabled', 'yes', __( 'Show the chat', 'hibira-live-chat' ), __( 'Show the chat on your site', 'hibira-live-chat' ) );
		self::checkbox( 'launcher', 'yes', __( 'Chat button', 'hibira-live-chat' ), __( 'Show the round chat button in the corner', 'hibira-live-chat' ), __( 'Untick to open the chat only from your own buttons and links (see below).', 'hibira-live-chat' ) );
		self::checkbox( 'hide_for_staff', 'no', __( 'Your team', 'hibira-live-chat' ), __( 'Hide the chat from signed-in editors and administrators', 'hibira-live-chat' ) );
		echo '<tr><th scope="row"><label for="hibira-lc-color">' . esc_html__( 'Colour', 'hibira-live-chat' ) . '</label></th><td><input id="hibira-lc-color" name="' . esc_attr( $p . 'color' ) . '" class="hibira-lc-color" value="' . esc_attr( Hibira_LC::opt( 'color' ) ) . '"><p class="description">' . esc_html__( 'Empty uses the colour set on chat.hibira.io.', 'hibira-live-chat' ) . '</p></td></tr>';
		$lang = Hibira_LC::opt( 'lang' );
		echo '<tr><th scope="row"><label for="hibira-lc-lang">' . esc_html__( 'Language', 'hibira-live-chat' ) . '</label></th><td><select id="hibira-lc-lang" name="' . esc_attr( $p . 'lang' ) . '">';
		echo '<option value=""' . selected( $lang, '', false ) . '>' . esc_html__( 'The visitor\'s browser language', 'hibira-live-chat' ) . '</option>';
		echo '<option value="site"' . selected( $lang, 'site', false ) . '>' . esc_html__( 'The site language', 'hibira-live-chat' ) . '</option>';
		echo '</select></td></tr>';
		echo '</table>';

		echo '<h2>' . esc_html__( 'Signed-in customers', 'hibira-live-chat' ) . '</h2>';
		echo '<p>' . esc_html__( 'With an identity secret, customers who are signed in to your site are verified in the inbox, and keep one conversation on every device. Starter plan and up.', 'hibira-live-chat' ) . '</p>';
		echo '<table class="form-table" role="presentation">';
		if ( defined( 'HIBIRA_LIVECHAT_IDENTITY_SECRET' ) ) {
			echo '<tr><th scope="row">' . esc_html__( 'Identity secret', 'hibira-live-chat' ) . '</th><td>' . esc_html__( 'Set by HIBIRA_LIVECHAT_IDENTITY_SECRET in wp-config.php.', 'hibira-live-chat' ) . '</td></tr>';
		} else {
			$has = '' !== (string) Hibira_LC::opt( 'identity_secret' );
			echo '<tr><th scope="row"><label for="hibira-lc-secret">' . esc_html__( 'Identity secret', 'hibira-live-chat' ) . '</label></th><td><input id="hibira-lc-secret" name="' . esc_attr( $p . 'identity_secret' ) . '" type="password" class="regular-text code" autocomplete="off" placeholder="' . esc_attr( $has ? __( 'Saved. Leave empty to keep it.', 'hibira-live-chat' ) : 'lcid_…' ) . '">';
			/* translators: %s: link to the Live Chat widget settings */
			echo '<p class="description">' . sprintf( esc_html__( 'Create it under Verified visitors in %s. Type - and save to remove it.', 'hibira-live-chat' ), '<a href="' . esc_url( $host . '/portal/widget' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Settings › Widget', 'hibira-live-chat' ) . '</a>' ) . '</p></td></tr>';
		}
		self::checkbox( 'share_profile', 'yes', __( 'Name and email', 'hibira-live-chat' ), __( 'Show the customer\'s name and email to your team in the inbox', 'hibira-live-chat' ) );
		echo '</table>';
		submit_button();
		echo '</form>';

		echo '<div class="hibira-lc-card">';
		echo '<h2>' . esc_html__( 'Open the chat from your pages', 'hibira-live-chat' ) . '</h2>';
		echo '<ul class="hibira-lc-list">';
		/* translators: %s: the shortcode */
		echo '<li>' . sprintf( esc_html__( 'A button: %s', 'hibira-live-chat' ), '<code>[hibira_live_chat label="Chat with us"]</code>' ) . '</li>';
		/* translators: %s: the shortcode */
		echo '<li>' . sprintf( esc_html__( 'A sales question: %s', 'hibira-live-chat' ), '<code>[hibira_live_chat label="Talk to sales" topic="sales"]</code>' ) . '</li>';
		/* translators: %s: the link address */
		echo '<li>' . sprintf( esc_html__( 'A menu item or link to %s', 'hibira-live-chat' ), '<code>#hibira-live-chat</code>' ) . '</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}

	private static function checkbox( $name, $fallback, $title, $label, $help = '' ) {
		$id = 'hibira-lc-' . str_replace( '_', '-', $name );
		echo '<tr><th scope="row">' . esc_html( $title ) . '</th><td>';
		echo '<input type="hidden" name="' . esc_attr( Hibira_LC::PREFIX . $name ) . '" value="no">';
		echo '<label for="' . esc_attr( $id ) . '"><input id="' . esc_attr( $id ) . '" type="checkbox" name="' . esc_attr( Hibira_LC::PREFIX . $name ) . '" value="yes"' . checked( Hibira_LC::opt( $name, $fallback ), 'yes', false ) . '> ' . esc_html( $label ) . '</label>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}
}
