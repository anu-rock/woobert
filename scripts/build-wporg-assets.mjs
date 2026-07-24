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
 *   assets-src/hoobert-owl.png  - the original raster art. When present it is
 *                                 the mark for every output: icons, banner, and
 *                                 the copy shipped inside the plugin.
 *   assets-src/hoobert-owl.svg  - a vector redraw, used only as a stand-in when
 *                                 the original above is absent.
 *   assets-src/banner.html      - the banner layout, rendered at 1544x500. It
 *                                 references the .svg so it previews standalone;
 *                                 the render swaps in whichever mark applies.
 *
 * Rasterising uses headless Chrome (no npm dependencies): each source is loaded
 * in a page sized to the target and screenshotted with a transparent backdrop.
 * `sips` handles the one downscale, so the 772x250 banner is always a clean
 * halving of the retina one.
 *
 * Usage:  node scripts/build-wporg-assets.mjs
 */

import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, copyFileSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
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

	// One mark drives every output. The original raster art wins when it is
	// present; the vector redraw is only a stand-in for when it is not.
	const usingOriginal = existsSync( owlPng );
	const mark = usingOriginal ? 'hoobert-owl.png' : 'hoobert-owl.svg';
	console.log(
		`Mark: assets-src/${ mark }${ usingOriginal ? '' : '  (vector redraw, no original present)' }`
	);

	const scratch = join( tmpdir(), `hoobert-assets-${ process.pid }` );
	mkdirSync( scratch, { recursive: true } );

	try {
		// Icons. Render at 256 and downscale, so both share exact pixel geometry.
		const iconPage = join( src, '.icon.html' );
		writeFileSync( iconPage, imagePage( mark, 256 ) );
		shoot( `file://${ iconPage }`, 256, 256, join( out, 'icon-256x256.png' ) );
		resize(
			join( out, 'icon-256x256.png' ),
			join( out, 'icon-128x128.png' ),
			128,
			128
		);

		// icon.svg is served to modern browsers with the PNGs as its fallback, so
		// it may only exist when it is the same artwork. Shipping the redraw
		// alongside PNGs of the original would give the listing two icons.
		const iconSvg = join( out, 'icon.svg' );
		if ( usingOriginal ) {
			rmSync( iconSvg, { force: true } );
			console.log( '  .wordpress-org/icon.svg  (omitted: original is raster)' );
		} else {
			copyFileSync( owlSvg, iconSvg );
			console.log( '  .wordpress-org/icon.svg' );
		}

		// The settings screen gets the same mark, always as a PNG so the plugin
		// ships one predictable filename whichever source was used.
		writeFileSync( iconPage, imagePage( mark, 256 ) );
		shoot( `file://${ iconPage }`, 256, 256, join( shipped, 'hoobert-owl.png' ) );
		rmSync( join( shipped, 'hoobert-owl.svg' ), { force: true } );
		rmSync( iconPage, { force: true } );

		// Banner. Its source keeps a real filename so it still previews on its
		// own in a browser; swap in the chosen mark for the render.
		const bannerPage = join( src, '.banner.html' );
		writeFileSync(
			bannerPage,
			readFileSync( join( src, 'banner.html' ), 'utf8' ).replaceAll( 'hoobert-owl.svg', mark )
		);
		shoot( `file://${ bannerPage }`, 1544, 500, join( out, 'banner-1544x500.png' ) );
		rmSync( bannerPage, { force: true } );
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
