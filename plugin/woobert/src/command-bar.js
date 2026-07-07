/**
 * The command-bar React tree.
 *
 * Layers Woobert's natural-language flow on top of kbar's static command palette:
 *   1. As the merchant types, an "Ask Woobert" action is registered live from the
 *      current search text (useRegisterActions keyed on searchQuery).
 *   2. Selecting it POSTs to /woobert/v1/resolve, which picks a tool call.
 *   3. Read-only / safe calls run immediately; destructive ones (x-woo.confirm)
 *      show a confirm step first.
 *   4. /woobert/v1/execute runs the call against WooCommerce and we render the
 *      result. The bar is kept open across these phases via VisualState.
 */

import { useState, useCallback, useEffect, Fragment } from 'react';
import {
	KBarProvider,
	KBarPortal,
	KBarPositioner,
	KBarAnimator,
	KBarSearch,
	KBarResults,
	useMatches,
	useKBar,
	useRegisterActions,
	VisualState,
} from 'kbar';
import { defaultActions } from './actions';
import { resolve, execute } from './api';
import { AboutPanel } from './about';

/**
 * Registers a live "Ask Woobert" action reflecting the current search text.
 */
function AskWoobertAction( { onAsk } ) {
	const { searchQuery } = useKBar( ( state ) => ( { searchQuery: state.searchQuery } ) );
	const trimmed = ( searchQuery || '' ).trim();

	useRegisterActions(
		trimmed.length > 1
			? [
					{
						id: 'ask-woobert',
						name: `Ask Woobert: “${ trimmed }”`,
						section: 'Woobert',
						keywords: trimmed,
						priority: 1000,
						perform: () => onAsk( trimmed ),
					},
			  ]
			: [],
		[ trimmed ]
	);

	return null;
}

/**
 * Renders the matched command list.
 */
function Results() {
	const { results } = useMatches();
	return (
		<KBarResults
			items={ results }
			onRender={ ( { item, active } ) =>
				typeof item === 'string' ? (
					<div className="woobert-group">{ item }</div>
				) : (
					<div className={ `woobert-item ${ active ? 'is-active' : '' }` }>
						<span className="woobert-item-name">{ item.name }</span>
						{ item.shortcut?.length ? (
							<span className="woobert-item-shortcut">
								{ item.shortcut.map( ( sc ) => (
									<kbd key={ sc }>{ sc }</kbd>
								) ) }
							</span>
						) : null }
					</div>
				)
			}
		/>
	);
}

/**
 * The status surface shown while Woobert resolves/executes an action.
 */
function WoobertPanel( { state, onConfirm, onCancel } ) {
	if ( state.phase === 'idle' ) {
		return null;
	}

	return (
		<div className="woobert-panel">
			{ state.phase === 'resolving' && (
				<p className="woobert-status">Thinking…</p>
			) }

			{ ( state.phase === 'confirm' || state.phase === 'executing' ) && state.call && (
				<Fragment>
					<p className="woobert-status">
						{ state.phase === 'executing' ? 'Running…' : 'Confirm this action' }
					</p>
					<CallPreview call={ state.call } />
					{ state.phase === 'confirm' && (
						<div className="woobert-actions">
							<button className="woobert-btn is-primary" onClick={ onConfirm }>
								Run
							</button>
							<button className="woobert-btn" onClick={ onCancel }>
								Cancel
							</button>
						</div>
					) }
				</Fragment>
			) }

			{ state.phase === 'done' && (
				<Fragment>
					<p className="woobert-status is-ok">
						Done: { state.call?.name } · HTTP { state.result?.status }
					</p>
					<ResultPreview result={ state.result } />
				</Fragment>
			) }

			{ state.phase === 'error' && (
				<p className="woobert-status is-error">{ state.error }</p>
			) }
		</div>
	);
}

/**
 * Shows the tool name + arguments the model chose.
 */
function CallPreview( { call } ) {
	return (
		<div className="woobert-call">
			<code className="woobert-call-name">{ call.name }</code>
			<pre className="woobert-call-args">{ JSON.stringify( call.arguments, null, 2 ) }</pre>
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
 * The inner bar: search, results, Woobert flow. Must live inside KBarProvider.
 */
function Bar( { about, setAbout } ) {
	const [ flow, setFlow ] = useState( { phase: 'idle' } );
	const { query, visible } = useKBar( ( state ) => ( { visible: state.visualState } ) );

	// Reset the Woobert panel whenever the bar is fully hidden.
	useEffect( () => {
		if ( visible === VisualState.hidden ) {
			setFlow( { phase: 'idle' } );
		}
	}, [ visible ] );

	const doExecute = useCallback(
		async ( call ) => {
			setFlow( { phase: 'executing', call } );
			try {
				const result = await execute( call );
				setFlow( { phase: 'done', call, result } );
			} catch ( e ) {
				setFlow( { phase: 'error', error: e.message } );
			}
		},
		[]
	);

	const onAsk = useCallback(
		async ( q ) => {
			setFlow( { phase: 'resolving', query: q } );
			// kbar hides the portal after perform; reopen it to show progress.
			query.setVisualState( VisualState.showing );
			try {
				const { calls, reply } = await resolve( q );
				const call = calls && calls[ 0 ];
				if ( ! call ) {
					setFlow( { phase: 'error', error: reply || 'Woobert found no matching action.' } );
					return;
				}
				if ( call.confirm ) {
					setFlow( { phase: 'confirm', call } );
				} else {
					doExecute( call );
				}
			} catch ( e ) {
				setFlow( { phase: 'error', error: e.message } );
			}
		},
		[ query, doExecute ]
	);

	return (
		<Fragment>
			<AskWoobertAction onAsk={ onAsk } />
			<KBarPortal>
				<KBarPositioner className="woobert-positioner">
					<KBarAnimator className="woobert-animator">
						<KBarSearch
							className="woobert-search"
							defaultPlaceholder="Search or ask Woobert to do something…"
						/>
						{ flow.phase === 'idle' ? (
							<Results />
						) : (
							<WoobertPanel
								state={ flow }
								onConfirm={ () => doExecute( flow.call ) }
								onCancel={ () => setFlow( { phase: 'idle' } ) }
							/>
						) }
					</KBarAnimator>
				</KBarPositioner>
			</KBarPortal>
			{ about && <AboutPanel onClose={ () => setAbout( false ) } /> }
		</Fragment>
	);
}

/**
 * Top-level provider + About modal state.
 */
export default function CommandBar() {
	const [ about, setAbout ] = useState( false );
	const actions = defaultActions( () => setAbout( true ) );

	return (
		<KBarProvider actions={ actions } options={ { enableHistory: false, toggleShortcut: '$mod+Shift+KeyK' } }>
			<Bar about={ about } setAbout={ setAbout } />
		</KBarProvider>
	);
}
