/**
 * Woobert entry point.
 *
 * Registers an "Ask Woobert" command on WordPress core's command palette
 * (`core/commands`, the ⌘K / Ctrl-K bar WP mounts across wp-admin) and mounts the
 * flow modal. The palette's command loader mirrors the typed text into a single
 * command; selecting it hands the utterance to the standalone Woobert flow modal
 * (flow.js), which runs the resolve/confirm/execute cycle the palette can't host.
 */

import { store as commandsStore } from '@wordpress/commands';
import { dispatch } from '@wordpress/data';
import { createRoot, useState, useEffect, useMemo } from '@wordpress/element';
import { WoobertFlowModal } from './flow';
import { WoobertHistoryModal } from './history';
import './style.css';

// Inline icon (avoids pulling in the bundled @wordpress/icons package).
// A friendly owl helper: ear tufts, wide bespectacled eyes, small beak, feet.
const woobertIcon = (
	<svg
		viewBox="0 0 24 24"
		width="24"
		height="24"
		aria-hidden="true"
		focusable="false"
	>
		<g
			fill="none"
			stroke="currentColor"
			strokeWidth="1.3"
			strokeLinecap="round"
			strokeLinejoin="round"
		>
			{ /* Ear tufts */ }
			<path d="M8 5.5 9.5 7.5M16 5.5 14.5 7.5" />
			{ /* Body */ }
			<ellipse cx="12" cy="13" rx="6.5" ry="7" />
			{ /* Eyes */ }
			<circle cx="9.5" cy="11" r="1.9" />
			<circle cx="14.5" cy="11" r="1.9" />
			<path d="M11.6 11h.8" />
			{ /* Beak */ }
			<path d="M11.1 12.6 12.9 12.6 12 14Z" />
			{ /* Feet */ }
			<path d="M10 20v1.2M14 20v1.2" />
		</g>
	</svg>
);

// A simple clock face: stands in for recent query history.
const historyIcon = (
	<svg
		viewBox="0 0 24 24"
		width="24"
		height="24"
		aria-hidden="true"
		focusable="false"
	>
		<g
			fill="none"
			stroke="currentColor"
			strokeWidth="1.3"
			strokeLinecap="round"
			strokeLinejoin="round"
		>
			<circle cx="12" cy="12" r="8.5" />
			<path d="M12 7.5V12l3 2" />
		</g>
	</svg>
);

// Set by the mounted controller; let palette commands open our modals.
let openFlow = null;
let openHistory = null;

/**
 * Command loader: reflects the current palette search into an "Ask Woobert"
 * command. The palette calls this hook with the live `search` text.
 */
function useAskWoobertCommands( { search } ) {
	const trimmed = ( search || '' ).trim();

	return useMemo(
		() => ( {
			isLoading: false,
			commands:
				trimmed.length > 1
					? [
							{
								name: 'woobert/ask',
								label: `Ask Woobert: “${ trimmed }”`,
								icon: woobertIcon,
								callback: ( { close } ) => {
									// Palette can't host the flow; close it and open our modal.
									close();
									if ( openFlow ) {
										openFlow( trimmed );
									}
								},
							},
					  ]
					: [],
		} ),
		[ trimmed ]
	);
}

/**
 * Invisible root that owns Woobert's modals and exposes openers to palette
 * commands. Only one modal is shown at a time (`view` holds which, plus its data).
 */
function PaletteController() {
	const [ view, setView ] = useState( null );

	useEffect( () => {
		openFlow = ( q ) => setView( { type: 'ask', query: q } );
		openHistory = () => setView( { type: 'history' } );
		return () => {
			openFlow = null;
			openHistory = null;
		};
	}, [] );

	const close = () => setView( null );

	if ( view?.type === 'ask' ) {
		return <WoobertFlowModal query={ view.query } onClose={ close } />;
	}
	if ( view?.type === 'history' ) {
		return <WoobertHistoryModal onClose={ close } />;
	}
	return null;
}

/**
 * Register the command loader and mount the controller. No-ops gracefully if the
 * command palette store is unavailable (WP older than the palette API).
 */
export function init() {
	const commands = dispatch( commandsStore );
	if ( ! commands || ! commands.registerCommandLoader ) {
		return;
	}
	commands.registerCommandLoader( {
		name: 'woobert/ask',
		hook: useAskWoobertCommands,
	} );

	// A static command, always listed in the palette, that opens the history modal.
	if ( commands.registerCommand ) {
		commands.registerCommand( {
			name: 'woobert/history',
			label: 'Woobert: Query history',
			icon: historyIcon,
			callback: ( { close } ) => {
				close();
				if ( openHistory ) {
					openHistory();
				}
			},
		} );
	}

	const container = document.createElement( 'div' );
	container.id = 'woobert-palette-root';
	document.body.appendChild( container );
	createRoot( container ).render( <PaletteController /> );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
