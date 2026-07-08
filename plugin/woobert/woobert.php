<?php
/**
 * Plugin Name:       Woobert
 * Plugin URI:        https://woobert.fernfly.com
 * Description:       Agentic command bar for WooCommerce merchants. Get stuff done at the speed of thought.
 * Version:           0.1.0
 * Requires at least: 7.0
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
require_once WOOBERT_PATH . 'includes/class-history.php';
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
 * Enqueue the command-palette bundle on every wp-admin screen and expose the
 * config + page context the front-end needs (nonce, proxy route, current ids).
 */
add_action(
	'admin_enqueue_scripts',
	function () {
		$asset_file = WOOBERT_PATH . 'build/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array( 'wp-commands', 'wp-data', 'wp-element' ),
				'version'      => WOOBERT_VERSION,
			);

		wp_enqueue_script(
			'woobert',
			WOOBERT_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'woobert',
			WOOBERT_URL . 'build/style-index.css',
			array(),
			$asset['version']
		);

		wp_localize_script(
			'woobert',
			'woobert',
			array(
				'root'    => esc_url_raw( rest_url( 'woobert/v1' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'context' => Woobert_Rest_Proxy::current_page_context(),
			)
		);
	}
);
