/**
 * Woobert query-history modal.
 *
 * Opened from the "Woobert: Query history" command. Lists the merchant's most
 * recent executed commands (newest first): the natural-language query, the
 * WooCommerce REST request it ran, whether it succeeded, and any error. Data
 * comes from the woobert/v1 proxy (api.js); it shares the .woobert-* styling.
 */

import { useState, useEffect, useCallback, Fragment } from '@wordpress/element';
import { history as fetchHistory, clearHistory } from './api';

/**
 * Format a unix timestamp (seconds) as a short local date-time string.
 */
function formatTime( seconds ) {
	if ( ! seconds ) {
		return '';
	}
	return new Date( seconds * 1000 ).toLocaleString();
}

/**
 * Render an argument value for display: scalars as-is, objects/arrays as JSON.
 */
function formatArg( value ) {
	if ( value === null || value === undefined ) {
		return '-';
	}
	if ( typeof value === 'object' ) {
		return JSON.stringify( value );
	}
	return String( value );
}

/**
 * Collapsed-by-default list of the request's arguments (name -> value).
 * Renders nothing when the request carried no params.
 */
function ArgsList( { params } ) {
	const names = params ? Object.keys( params ) : [];
	if ( ! names.length ) {
		return null;
	}
	return (
		<details className="woobert-history-args">
			<summary>
				{ names.length } argument{ names.length === 1 ? '' : 's' }
			</summary>
			<dl className="woobert-fields">
				{ names.map( ( name ) => (
					<Fragment key={ name }>
						<dt>{ name }</dt>
						<dd>{ formatArg( params[ name ] ) }</dd>
					</Fragment>
				) ) }
			</dl>
		</details>
	);
}

/**
 * One history row: query, outcome badge, the request that ran, and any error.
 */
function HistoryEntry( { entry } ) {
	const { method, route, params } = entry.request || {};
	return (
		<li className="woobert-history-item">
			<div className="woobert-history-top">
				<span className="woobert-history-query">
					{ entry.query || <em>(no query)</em> }
				</span>
				<span
					className={ `woobert-badge ${
						entry.ok ? 'is-ok' : 'is-error'
					}` }
				>
					{ entry.ok
						? 'Success'
						: `Failed${
								entry.status ? ` · ${ entry.status }` : ''
						  }` }
				</span>
			</div>
			<div className="woobert-history-req">
				<code className="woobert-call-name">{ entry.tool }</code>
				{ ( method || route ) && (
					<span className="woobert-history-route">
						{ method } { route }
					</span>
				) }
			</div>
			{ ! entry.ok && entry.error && (
				<p className="woobert-history-error">{ entry.error }</p>
			) }
			<ArgsList params={ params } />
			<span className="woobert-history-time">
				{ formatTime( entry.time ) }
			</span>
		</li>
	);
}

/**
 * Modal that loads and renders the current user's command history.
 *
 * @param {Object}   props
 * @param {Function} props.onClose Close/dismiss the modal.
 */
export function WoobertHistoryModal( { onClose } ) {
	const [ state, setState ] = useState( { phase: 'loading' } );

	const load = useCallback( async () => {
		setState( { phase: 'loading' } );
		try {
			const { entries } = await fetchHistory();
			setState( { phase: 'ready', entries: entries || [] } );
		} catch ( e ) {
			setState( { phase: 'error', error: e.message } );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const doClear = useCallback( async () => {
		try {
			await clearHistory();
			setState( { phase: 'ready', entries: [] } );
		} catch ( e ) {
			setState( { phase: 'error', error: e.message } );
		}
	}, [] );

	// Esc closes the modal.
	useEffect( () => {
		const onKey = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ onClose ] );

	const entries = state.entries || [];

	return (
		<div
			className="woobert-positioner"
			onMouseDown={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onClose();
				}
			} }
		>
			<div className="woobert-animator">
				<div className="woobert-flow-head woobert-history-head">
					<span>Woobert history</span>
					{ state.phase === 'ready' && entries.length > 0 && (
						<button
							className="woobert-btn woobert-history-clear"
							onClick={ doClear }
						>
							Clear
						</button>
					) }
				</div>
				<div className="woobert-panel">
					{ state.phase === 'loading' && (
						<p className="woobert-status">Loading…</p>
					) }

					{ state.phase === 'error' && (
						<p className="woobert-status is-error">
							{ state.error }
						</p>
					) }

					{ state.phase === 'ready' && entries.length === 0 && (
						<p className="woobert-status">
							No commands yet. Press ⌘K / Ctrl-K and ask Woobert
							to do something.
						</p>
					) }

					{ state.phase === 'ready' && entries.length > 0 && (
						<ul className="woobert-history-list">
							{ entries.map( ( entry, i ) => (
								<HistoryEntry key={ i } entry={ entry } />
							) ) }
						</ul>
					) }
				</div>
			</div>
		</div>
	);
}
