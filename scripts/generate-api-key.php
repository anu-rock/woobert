<?php
/**
 * Mints a WooCommerce REST API key pair (consumer key/secret) for the admin user
 * and prints it to stdout. Idempotent: if a key with our description already
 * exists, it is left alone (the secret is only shown once, at creation).
 *
 * These keys let you exercise the same REST v3 endpoints the command bar drives,
 * e.g. from curl or Postman. The plugin itself does not need them, it executes
 * in-process with the current admin session.
 *
 * Invoked by scripts/setup.sh, or directly via:
 *   docker compose run --rm --entrypoint wp wpcli eval-file /scripts/generate-api-key.php
 *
 * @package Woobert
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce must be active.' );
}

global $wpdb;

$user = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( empty( $user ) ) {
	WP_CLI::error( 'No administrator user found.' );
}
$user_id = (int) $user[0]->ID;

// Idempotent: keep the existing key. Its secret is unrecoverable (stored hashed),
// so it can only be shown at creation; delete the row to mint a fresh pair.
$description = 'Woobert';
$existing    = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT truncated_key FROM {$wpdb->prefix}woocommerce_api_keys WHERE description = %s LIMIT 1",
		$description
	)
);
if ( $existing ) {
	WP_CLI::log( sprintf( 'REST API key already exists (…%s). Delete it to mint a fresh pair.', $existing ) );
	return;
}

$consumer_key    = 'ck_' . wc_rand_hash();
$consumer_secret = 'cs_' . wc_rand_hash();

$inserted = $wpdb->insert(
	$wpdb->prefix . 'woocommerce_api_keys',
	array(
		'user_id'         => $user_id,
		'description'     => $description,
		'permissions'     => 'read_write',
		'consumer_key'    => wc_api_hash( $consumer_key ),
		'consumer_secret' => $consumer_secret,
		'truncated_key'   => substr( $consumer_key, -7 ),
	),
	array( '%d', '%s', '%s', '%s', '%s', '%s' )
);

if ( ! $inserted ) {
	WP_CLI::error( 'Failed to insert API key.' );
}

// Shown once. Copy these now; the secret cannot be retrieved later.
WP_CLI::log( '' );
WP_CLI::log( 'WooCommerce REST API credentials (read_write), shown once:' );
WP_CLI::log( '  WC_CONSUMER_KEY=' . $consumer_key );
WP_CLI::log( '  WC_CONSUMER_SECRET=' . $consumer_secret );
