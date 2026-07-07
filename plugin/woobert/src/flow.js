/**
 * Standalone Woobert flow modal.
 *
 * The native WordPress command palette closes as soon as a command's callback
 * runs and offers no surface to render progress. So once "Ask Woobert" is
 * selected there, we drive the resolve -> (confirm) -> execute -> result flow in
 * this self-contained modal instead. It reuses the woobert/v1 proxy (api.js) and
 * the shared .woobert-* styling, so it looks and behaves like the kbar panel.
 */

import { useState, useEffect, useCallback, Fragment } from '@wordpress/element';
import { resolve, execute } from './api';

/**
 * Shows the tool name + arguments the model chose.
 */
function CallPreview( { call } ) {
	return (
		<div className="woobert-call">
			<code className="woobert-call-name">{ call.name }</code>
			<pre className="woobert-call-args">
				{ JSON.stringify( call.arguments, null, 2 ) }
			</pre>
		</div>
	);
}

/**
 * Compact rendering of the executor result (count for lists, id for single objects).
 */
function ResultPreview( { result } ) {
	const data = result?.data;
	let summary = '';
	if ( Array.isArray( data ) ) {
		summary = `${ data.length } result${ data.length === 1 ? '' : 's' }`;
	} else if ( data && typeof data === 'object' ) {
		summary = data.id ? `#${ data.id }` : 'ok';
	}
	return (
		<div className="woobert-result">
			{ summary && <p className="woobert-result-summary">{ summary }</p> }
			<pre className="woobert-call-args">
				{ JSON.stringify( data, null, 2 )?.slice( 0, 1200 ) }
			</pre>
		</div>
	);
}

/**
 * Modal that runs one Woobert request end to end for the given query.
 *
 * @param {Object}   props
 * @param {string}   props.query   The merchant utterance selected in the palette.
 * @param {Function} props.onClose Close/dismiss the modal.
 */
export function WoobertFlowModal( { query, onClose } ) {
	const [ flow, setFlow ] = useState( { phase: 'resolving' } );

	const doExecute = useCallback( async ( call ) => {
		setFlow( { phase: 'executing', call } );
		try {
			const result = await execute( call );
			setFlow( { phase: 'done', call, result } );
		} catch ( e ) {
			setFlow( { phase: 'error', error: e.message } );
		}
	}, [] );

	// Kick off resolution whenever the query changes.
	useEffect( () => {
		let active = true;
		setFlow( { phase: 'resolving' } );
		( async () => {
			try {
				const { calls, reply } = await resolve( query );
				if ( ! active ) {
					return;
				}
				const call = calls && calls[ 0 ];
				if ( ! call ) {
					setFlow( {
						phase: 'error',
						error: reply || 'Woobert found no matching action.',
					} );
					return;
				}
				if ( call.confirm ) {
					setFlow( { phase: 'confirm', call } );
				} else {
					doExecute( call );
				}
			} catch ( e ) {
				if ( active ) {
					setFlow( { phase: 'error', error: e.message } );
				}
			}
		} )();
		return () => {
			active = false;
		};
	}, [ query, doExecute ] );

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
				<div className="woobert-flow-head">
					Ask Woobert: <span className="q">“{ query }”</span>
				</div>
				<div className="woobert-panel">
					{ flow.phase === 'resolving' && (
						<p className="woobert-status">Thinking…</p>
					) }

					{ ( flow.phase === 'confirm' ||
						flow.phase === 'executing' ) &&
						flow.call && (
							<Fragment>
								<p className="woobert-status">
									{ flow.phase === 'executing'
										? 'Running…'
										: 'Confirm this action' }
								</p>
								<CallPreview call={ flow.call } />
								{ flow.phase === 'confirm' && (
									<div className="woobert-actions">
										<button
											className="woobert-btn is-primary"
											onClick={ () =>
												doExecute( flow.call )
											}
										>
											Run
										</button>
										<button
											className="woobert-btn"
											onClick={ onClose }
										>
											Cancel
										</button>
									</div>
								) }
							</Fragment>
						) }

					{ flow.phase === 'done' && (
						<Fragment>
							<p className="woobert-status is-ok">
								Done: { flow.call?.name } · HTTP{ ' ' }
								{ flow.result?.status }
							</p>
							<ResultPreview result={ flow.result } />
						</Fragment>
					) }

					{ flow.phase === 'error' && (
						<p className="woobert-status is-error">
							{ flow.error }
						</p>
					) }
				</div>
			</div>
		</div>
	);
}
