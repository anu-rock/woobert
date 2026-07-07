/**
 * Standalone Woobert flow modal.
 *
 * The native WordPress command palette closes as soon as a command's callback
 * runs and offers no surface to render progress. So once "Ask Woobert" is
 * selected there, we drive the resolve -> (confirm) -> execute -> result flow in
 * this self-contained modal instead. It reuses the woobert/v1 proxy (api.js) and
 * the shared .woobert-* styling.
 */

import { useState, useEffect, useCallback, Fragment } from '@wordpress/element';
import { resolve, execute } from './api';

/**
 * Pretty-print a value as JSON, HTML-escape it (the data carries user-controlled
 * strings), then wrap keys/strings/numbers/booleans/null in classed spans. Returns
 * a safe HTML string, so escaping must happen before the spans are added.
 */
function highlightJson( value ) {
	const json = JSON.stringify( value, null, 2 );
	if ( json === undefined ) {
		return '';
	}
	const escaped = json
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
	return escaped.replace(
		/("(?:\\.|[^"\\])*"(\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g,
		( match ) => {
			let cls = 'number';
			if ( /^"/.test( match ) ) {
				cls = /:$/.test( match ) ? 'key' : 'string';
			} else if ( /true|false/.test( match ) ) {
				cls = 'boolean';
			} else if ( /null/.test( match ) ) {
				cls = 'null';
			}
			return `<span class="woobert-json-${ cls }">${ match }</span>`;
		}
	);
}

/**
 * Pretty-printed, syntax-highlighted JSON block. The HTML is built by
 * highlightJson, which escapes the payload before adding its own markup.
 */
function JsonBlock( { value } ) {
	return (
		<pre
			className="woobert-call-args"
			// eslint-disable-next-line react/no-danger
			dangerouslySetInnerHTML={ { __html: highlightJson( value ) } }
		/>
	);
}

/**
 * Shows the tool name + arguments the model chose.
 */
function CallPreview( { call } ) {
	return (
		<div className="woobert-call">
			<code className="woobert-call-name">{ call.name }</code>
			<JsonBlock value={ call.arguments } />
		</div>
	);
}

/**
 * Renders a tool's merchant-facing display payload (built server-side by the
 * executor): a labeled field list for a single object, or a column table for a
 * list. Falls back to a friendly empty message when a list has no rows.
 */
function DisplayView( { display } ) {
	if ( display.type === 'list' ) {
		if ( ! display.rows.length ) {
			return (
				<p className="woobert-result-summary">{ display.empty }</p>
			);
		}
		return (
			<div className="woobert-result">
				<p className="woobert-result-summary">
					{ display.count } result
					{ display.count === 1 ? '' : 's' }
				</p>
				<table className="woobert-table">
					<thead>
						<tr>
							{ display.columns.map( ( col ) => (
								<th key={ col }>{ col }</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ display.rows.map( ( row, r ) => (
							<tr key={ r }>
								{ row.map( ( cell, c ) => (
									<td key={ c }>{ cell }</td>
								) ) }
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		);
	}

	return (
		<div className="woobert-result">
			{ display.title && (
				<p className="woobert-result-title">{ display.title }</p>
			) }
			<dl className="woobert-fields">
				{ display.rows.map( ( row ) => (
					<Fragment key={ row.label }>
						<dt>{ row.label }</dt>
						<dd>{ row.value }</dd>
					</Fragment>
				) ) }
			</dl>
		</div>
	);
}

/**
 * The result view: the tool's merchant-facing display when it has one, else a
 * fallback to the raw (syntax-highlighted) response.
 */
function ResultPreview( { result } ) {
	if ( result?.display ) {
		return <DisplayView display={ result.display } />;
	}

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
			<JsonBlock value={ data } />
		</div>
	);
}

/**
 * Collapsed technical detail for merchants who want it: the tool call Fern
 * returned, the REST request the executor ran, the HTTP status, and any error.
 * Hidden behind a toggle so the default view stays free of developer noise.
 */
function DebugInfo( { call, result, error, response } ) {
	return (
		<details className="woobert-debug">
			<summary>Debug info</summary>
			<div className="woobert-debug-body">
				{ call && (
					<Fragment>
						<p className="woobert-debug-label">Fern returned</p>
						<CallPreview call={ call } />
					</Fragment>
				) }
				{ result?.request && (
					<Fragment>
						<p className="woobert-debug-label">Executor ran</p>
						<JsonBlock value={ result.request } />
					</Fragment>
				) }
				{ response !== undefined && (
					<Fragment>
						<p className="woobert-debug-label">Response</p>
						<JsonBlock value={ response } />
					</Fragment>
				) }
				{ result?.status != null && (
					<p className="woobert-debug-line">
						HTTP status: { result.status }
					</p>
				) }
				{ error && <p className="woobert-debug-line">Error: { error }</p> }
			</div>
		</details>
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
			// e.data is the executor result body (status, request) on a failed run.
			setFlow( { phase: 'error', error: e.message, call, result: e.data } );
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
							<p className="woobert-status is-ok">Done.</p>
							<ResultPreview result={ flow.result } />
							<DebugInfo
								call={ flow.call }
								result={ flow.result }
								response={
									flow.result?.display
										? flow.result?.data
										: undefined
								}
							/>
						</Fragment>
					) }

					{ flow.phase === 'error' &&
						( flow.call || flow.result ? (
							// A tool ran and failed: keep the merchant message generic,
							// tuck the real error + status into debug info.
							<Fragment>
								<p className="woobert-status is-error">
									An error occurred.
								</p>
								<DebugInfo
									call={ flow.call }
									result={ flow.result }
									error={ flow.error }
								/>
							</Fragment>
						) : (
							// No tool ran (e.g. no matching action): the message is
							// already merchant-friendly, so show it as-is.
							<p className="woobert-status is-error">
								{ flow.error }
							</p>
						) ) }
				</div>
			</div>
		</div>
	);
}
