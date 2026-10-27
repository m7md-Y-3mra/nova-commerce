/**
 * NOVA Store theme behaviour: accessible mobile navigation.
 * No dependencies; loaded deferred in the footer.
 */
( function () {
	'use strict';

	var toggle = document.querySelector( '.menu-toggle' );
	var nav = document.getElementById( 'site-nav' );
	var header = document.getElementById( 'site-header' );

	if ( ! toggle || ! nav || ! header ) {
		return;
	}

	var mobile = window.matchMedia( '(max-width: 900px)' );

	function setOpen( open ) {
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		nav.classList.toggle( 'is-open', open );
		document.body.classList.toggle( 'nav-open', open );

		if ( open ) {
			// Position the panel directly under the sticky header.
			nav.style.setProperty( '--nav-top', header.getBoundingClientRect().bottom + 'px' );
			var first = nav.querySelector( 'input, a' );
			if ( first ) {
				first.focus();
			}
		}
	}

	toggle.addEventListener( 'click', function () {
		setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && nav.classList.contains( 'is-open' ) ) {
			setOpen( false );
			toggle.focus();
		}
	} );

	// Reset when resizing back to desktop so the panel never gets stuck open.
	mobile.addEventListener( 'change', function ( event ) {
		if ( ! event.matches ) {
			setOpen( false );
		}
	} );
}() );
