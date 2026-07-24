#!/usr/bin/env node
/**
 * Render the WordPress.org directory assets from their sources in assets-src/.
 *
 * Outputs into .wordpress-org/ (the folder the deploy action copies to the SVN
 * `assets/` directory), at the exact filenames and dimensions wp.org requires:
 *
 *   icon-128x128.png  icon-256x256.png  icon.svg
 *   banner-772x250.png  banner-1544x500.png
 *
 * Sources:
 *   assets-src/hoobert-owl.svg  - the owl mark (vector)
 *   assets-src/hoobert-owl.png  - optional; the original raster art. When
 *                                 present it is used for the icons instead of
 *                                 the SVG, so the shipped icon is the artist's
 *                                 file rather than our vector redraw.
 *   assets-src/banner.html      - the banner layout, rendered at 1544x500
 *
 * Rasterising uses headless Chrome (no npm dependencies): each source is loaded
 * in a page sized to the target and screenshotted with a transparent backdrop.
 * `sips` handles the one downscale, so the 772x250 banner is always a clean
 * halving of the retina one.
 *
 * Usage:  node scripts/build-wporg-assets.mjs
 */

import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, copyFileSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const src = join(root, 'assets-src');
const out = join(root, '.wordpress-org');
const shipped = join(root, 'plugin', 'hoobert', 'assets');

const CHROME_CANDIDATES = [
	'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
	'/Applications/Chromium.app/Contents/MacOS/Chromium',
	'/usr/bin/google-chrome',
	'/usr/bin/chromium',
	'/usr/bin/chromium-browser',
];

/** First Chrome/Chromium on this machine, or an explicit CHROME_BIN override. */
function chrome() {
	const found = process.env.CHROME_BIN || CHROME_CANDIDATES.find( existsSync );
	if ( ! found || ! existsSync( found ) ) {
		throw new Error(
			'No Chrome/Chromium found. Install one, or set CHROME_BIN to its binary.'
		);
	}
	return found;
}

/** Screenshot a local page at an exact pixel size, transparent where unpainted. */
function shoot( url, width, height, target ) {
	execFileSync(
		chrome(),
		[
			'--headless',
			'--disable-gpu',
			'--hide-scrollbars',
			'--force-device-scale-factor=1',
			'--default-background-color=00000000',
			'--virtual-time-budget=3000',
			`--window-size=${ width },${ height }`,
			`--screenshot=${ target }`,
			url,
		],
		{ stdio: 'ignore' }
	);
	if ( ! existsSync( target ) ) {
		throw new Error( `Chrome produced no output for ${ target }` );
	}
	console.log( `  ${ target.replace( root + '/', '' ) }  ${ width }x${ height }` );
}

/** Resize in place with macOS sips, or Chrome-render again elsewhere. */
function resize( from, to, width, height ) {
	copyFileSync( from, to );
	execFileSync( '/usr/bin/sips', [ '-z', String( height ), String( width ), to ], {
		stdio: 'ignore',
	} );
	console.log( `  ${ to.replace( root + '/', '' ) }  ${ width }x${ height }` );
}

/** A page that paints one image at an exact size and nothing else. */
function imagePage( image, size ) {
	return `<!doctype html><meta charset="utf-8"><style>
		html,body{margin:0;padding:0;background:transparent}
		img{display:block;width:${ size }px;height:${ size }px}
	</style><img src="${ image }">`;
}

function main() {
	mkdirSync( out, { recursive: true } );
	mkdirSync( shipped, { recursive: true } );

	const owlSvg = join( src, 'hoobert-owl.svg' );
	const owlPng = join( src, 'hoobert-owl.png' );
	if ( ! existsSync( owlSvg ) ) {
		throw new Error( `Missing ${ owlSvg }` );
	}

	// Prefer the original raster art for the icons when it has been dropped in.
	const iconSource = existsSync( owlPng ) ? 'hoobert-owl.png' : 'hoobert-owl.svg';
	console.log( `Icon source: assets-src/${ iconSource }` );

	const scratch = join( tmpdir(), `hoobert-assets-${ process.pid }` );
	mkdirSync( scratch, { recursive: true } );

	try {
		// Icons. Render at 256 and downscale, so both share exact pixel geometry.
		const iconPage = join( src, '.icon.html' );
		writeFileSync( iconPage, imagePage( iconSource, 256 ) );
		shoot( `file://${ iconPage }`, 256, 256, join( out, 'icon-256x256.png' ) );
		resize(
			join( out, 'icon-256x256.png' ),
			join( out, 'icon-128x128.png' ),
			128,
			128
		);
		rmSync( iconPage, { force: true } );

		// icon.svg is served to modern browsers; the PNGs above are its fallback.
		copyFileSync( owlSvg, join( out, 'icon.svg' ) );
		console.log( '  .wordpress-org/icon.svg' );

		// The same mark ships inside the plugin, for the settings screen.
		copyFileSync( owlSvg, join( shipped, 'hoobert-owl.svg' ) );
		console.log( '  plugin/hoobert/assets/hoobert-owl.svg' );

		// Banner: render retina, halve for the standard size.
		shoot(
			`file://${ join( src, 'banner.html' ) }`,
			1544,
			500,
			join( out, 'banner-1544x500.png' )
		);
		resize(
			join( out, 'banner-1544x500.png' ),
			join( out, 'banner-772x250.png' ),
			772,
			250
		);
	} finally {
		rmSync( scratch, { recursive: true, force: true } );
	}

	console.log( '\nDone. Screenshots are added by hand as .wordpress-org/screenshot-N.png.' );
}

main();
