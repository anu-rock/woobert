/**
 * Static command-bar actions: quick navigation to common WooCommerce pages,
 * plus an "About" entry. These render immediately without hitting the model.
 * The natural-language path is handled separately by the Ask Woobert result
 * (see command-bar.js).
 */

import { config } from './api';

/**
 * Build the default nav + about actions from the admin links passed by PHP.
 *
 * @param {Function} openAbout Callback to open the About panel.
 * @return {Array} kbar action definitions.
 */
export function defaultActions( openAbout ) {
	const { links } = config();
	const go = ( url ) => () => {
		if ( url ) {
			window.location.href = url;
		}
	};

	return [
		{
			id: 'orders',
			name: 'Go to Orders',
			shortcut: [ 'g', 'o' ],
			keywords: 'orders sales list',
			section: 'Navigate',
			perform: go( links.orders ),
		},
		{
			id: 'products',
			name: 'Go to Products',
			shortcut: [ 'g', 'p' ],
			keywords: 'products catalog inventory',
			section: 'Navigate',
			perform: go( links.products ),
		},
		{
			id: 'new-product',
			name: 'Add New Product',
			keywords: 'create product new',
			section: 'Navigate',
			perform: go( links.newProduct ),
		},
		{
			id: 'coupons',
			name: 'Go to Coupons',
			keywords: 'coupons discounts marketing',
			section: 'Navigate',
			perform: go( links.coupons ),
		},
		{
			id: 'customers',
			name: 'Go to Customers',
			keywords: 'customers people crm',
			section: 'Navigate',
			perform: go( links.customers ),
		},
		{
			id: 'reports',
			name: 'Go to Analytics',
			keywords: 'reports analytics revenue sales stats',
			section: 'Navigate',
			perform: go( links.reports ),
		},
		{
			id: 'settings',
			name: 'WooCommerce Settings',
			keywords: 'settings configuration',
			section: 'Navigate',
			perform: go( links.settings ),
		},
		{
			id: 'about',
			name: 'About Woobert',
			keywords: 'about woobert fern credits help',
			section: 'Help',
			perform: openAbout,
		},
	];
}
