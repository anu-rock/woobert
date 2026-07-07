/**
 * Fetch wrappers around the plugin's own admin REST routes (woobert/v1).
 * The browser never sees the inference API key. The PHP proxy holds it.
 */

/* global woobert */

/**
 * Read the runtime config injected by wp_localize_script.
 */
export function config() {
	return typeof woobert !== 'undefined' ? woobert : { root: '', nonce: '', context: {}, links: {} };
}

/**
 * POST helper that attaches the REST nonce.
 */
async function post( path, payload ) {
	const { root, nonce } = config();
	const res = await fetch( `${ root }${ path }`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: JSON.stringify( payload ),
	} );

	let data = null;
	try {
		data = await res.json();
	} catch ( e ) {
		data = null;
	}

	if ( ! res.ok ) {
		const message = ( data && ( data.error || data.message ) ) || `HTTP ${ res.status }`;
		throw new Error( message );
	}
	return data;
}

/**
 * Ask Woobert to translate a natural-language query into tool call(s). No execution.
 *
 * @param {string} query Merchant's request.
 * @return {Promise<{ok:boolean, calls:Array}>} Resolved tool calls with confirm flags.
 */
export function resolve( query ) {
	const { context } = config();
	return post( '/resolve', { query, context } );
}

/**
 * Execute a single resolved tool call against WooCommerce.
 *
 * @param {{name:string, arguments:object}} call The tool call to run.
 * @return {Promise<object>} Executor result { ok, status, data, request }.
 */
export function execute( call ) {
	return post( '/execute', { name: call.name, arguments: call.arguments } );
}
