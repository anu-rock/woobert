/**
 * Extend the default @wordpress/scripts webpack config to build two entry
 * points: the kbar command bar (index) and the native command-palette
 * integration (palette). Each emits its own build/<name>.js,
 * build/<name>.asset.php, and build/style-<name>.css.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/index.js',
		palette: './src/palette.js',
	},
};
