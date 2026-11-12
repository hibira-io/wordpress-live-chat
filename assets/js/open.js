/* Opens the Hibira Live Chat panel from the page's own buttons and links:
 * .hibira-live-chat-open (the [hibira_live_chat] shortcode) and
 * href="#hibira-live-chat" (a menu item). data-topic="sales" marks a new
 * conversation as a sales inquiry. */
( function () {
	'use strict';
	var pending = null;
	function open( topic ) {
		var opts = 'sales' === topic ? { topic: 'sales' } : undefined;
		if ( window.HibiraLiveChat ) {
			window.HibiraLiveChat.open( opts );
		} else {
			pending = opts || {};
		}
	}
	window.addEventListener( 'hibira-livechat:ready', function () {
		if ( pending ) {
			window.HibiraLiveChat.open( pending.topic ? pending : undefined );
			pending = null;
		}
	} );
	document.addEventListener( 'click', function ( e ) {
		var el = e.target && e.target.closest ? e.target.closest( '.hibira-live-chat-open, a[href="#hibira-live-chat"]' ) : null;
		if ( ! el ) {
			return;
		}
		e.preventDefault();
		open( el.getAttribute( 'data-topic' ) );
	} );
}() );
