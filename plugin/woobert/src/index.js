/**
 * Entry point: mount the command bar into a portal container appended to <body>
 * on every wp-admin screen. Opens with ⌘K / Ctrl-K (kbar's default binding).
 */

import { createRoot } from '@wordpress/element';
import CommandBar from './command-bar';
import './style.css';

function mount() {
	if ( document.getElementById( 'woobert-root' ) ) {
		return;
	}
	const container = document.createElement( 'div' );
	container.id = 'woobert-root';
	document.body.appendChild( container );
	createRoot( container ).render( <CommandBar /> );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
