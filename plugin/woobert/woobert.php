<?php
/**
 * Plugin Name:       Woobert
 * Plugin URI:        https://woobert.fernfly.com
 * Description:       Agentic command bar for WooCommerce merchants. Get stuff done at the speed of thought.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Fernfly
 * License:           GPL-2.0-or-later
 * Text Domain:       woobert
 * WC requires at least: 8.0
 *
 * @package Woobert
 *
 * Press Cmd/Ctrl-K anywhere in wp-admin, type a request in plain English, and Woobert
 * maps it to a WooCommerce REST API v3 tool call and runs it, server-side, under the
 * current admin's capabilities. The natural-language step is handled by a small
 * function-calling model (Fern); tool schemas live in tools.json.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WOOBERT_VERSION', '0.1.0' );
define( 'WOOBERT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOOBERT_URL', plugin_dir_url( __FILE__ ) );

require_once WOOBERT_PATH . 'includes/class-tools.php';
require_once WOOBERT_PATH . 'includes/class-executor.php';
require_once WOOBERT_PATH . 'includes/class-fern-client.php';
require_once WOOBERT_PATH . 'includes/class-rest-proxy.php';
require_once WOOBERT_PATH . 'includes/class-settings.php';

/**
 * Boot the plugin once all plugins are loaded, so we can detect WooCommerce.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Woobert requires WooCommerce to be active.', 'woobert' ) . '</p></div>';
				}
			);
			return;
		}

		Woobert_Settings::init();
		( new Woobert_Rest_Proxy() )->register();
	}
);

/**
 * Enqueue the front-end bundles on every wp-admin screen and expose the config +
 * page context each needs (nonce, proxy route, current ids).
 *
 * Two entry points ship in parallel during the native-palette spike:
 *   - `woobert`         the kbar command bar (index.js)
 *   - `woobert-palette` the native WordPress command-palette integration (palette.js)
 * The shared `woobert` config global is localized on each so either can stand alone.
 */
add_action(
	'admin_enqueue_scripts',
	function () {
		$config = array(
			'root'    => esc_url_raw( rest_url( 'woobert/v1' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'context' => Woobert_Rest_Proxy::current_page_context(),
			'links'   => array(
				'orders'     => admin_url( 'edit.php?post_type=shop_order' ),
				'products'   => admin_url( 'edit.php?post_type=product' ),
				'newProduct' => admin_url( 'post-new.php?post_type=product' ),
				'coupons'    => admin_url( 'edit.php?post_type=shop_coupon' ),
				'customers'  => admin_url( 'admin.php?page=wc-admin&path=/customers' ),
				'reports'    => admin_url( 'admin.php?page=wc-admin&path=/analytics/overview' ),
				'settings'   => admin_url( 'admin.php?page=wc-settings' ),
			),
		);

		// Both entries import the same stylesheet; wp-scripts merges it into one file.
		wp_enqueue_style(
			'woobert',
			WOOBERT_URL . 'build/style-index.css',
			array(),
			WOOBERT_VERSION
		);

		// script handle => build basename.
		$entries = array(
			'woobert'         => 'index',
			'woobert-palette' => 'palette',
		);

		foreach ( $entries as $handle => $name ) {
			$asset_file = WOOBERT_PATH . "build/{$name}.asset.php";
			$asset      = file_exists( $asset_file )
				? require $asset_file
				: array(
					'dependencies' => array( 'wp-element', 'wp-i18n' ),
					'version'      => WOOBERT_VERSION,
				);

			wp_enqueue_script(
				$handle,
				WOOBERT_URL . "build/{$name}.js",
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_localize_script( $handle, 'woobert', $config );
		}
	}
);
