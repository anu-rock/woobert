/**
 * The "About" panel: what Woobert is and how it works. Reached from the
 * palette's "About Woobert" action.
 */

import { Fragment } from 'react';

export function AboutPanel( { onClose } ) {
	return (
		<div className="woobert-about-overlay" onClick={ onClose } role="presentation">
			<div
				className="woobert-about"
				onClick={ ( e ) => e.stopPropagation() }
				role="dialog"
				aria-label="About Woobert"
			>
				<h2>Woobert</h2>
				<p className="woobert-about-tag">
					Run your whole store from one prompt.
				</p>
				<p>
					Type what you want in plain English, like “refund order 1042”, “add a Large/Red
					variation to this product at 54.99”, or “who are my top customers this month”.
					Woobert turns it into the right WooCommerce action and runs it, under your own
					admin session. No menu hunting, no keys in the browser.
				</p>
				<p>
					Woobert is powered by <strong>Fern</strong>, a family of tiny function-calling
					models by{ ' ' }
					<a href="https://fernfly.com" target="_blank" rel="noreferrer">
						Fernfly
					</a>
					. The model maps your request to a WooCommerce REST API call; the plugin runs it
					server-side and shows you the result.
				</p>
				<button className="woobert-btn is-primary" onClick={ onClose }>
					Close
				</button>
			</div>
		</div>
	);
}
