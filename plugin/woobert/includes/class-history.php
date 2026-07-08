<?php
/**
 * Per-user history of executed Woobert commands.
 *
 * Each time a resolved tool call is run through /execute, one entry is recorded
 * against the current admin: the natural-language query, the WooCommerce REST
 * request that ran, whether it succeeded, and any error. History is stored in
 * user meta (so each merchant sees only their own) as a ring buffer capped at
 * LIMIT entries, newest first.
 *
 * @package Woobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woobert_History {

	const META_KEY = 'woobert_history';
	const LIMIT    = 25;

	/**
	 * Record one executed command for the current user. Silently no-ops when
	 * there is no logged-in user (there always is on the admin REST routes).
	 *
	 * @param array $entry {
	 *     @type string $query   The merchant utterance.
	 *     @type string $tool    Tool name the model chose.
	 *     @type array  $request The REST request that ran (method, route, params).
	 *     @type int    $status  HTTP status returned by WooCommerce.
	 *     @type bool   $ok       Whether the request succeeded.
	 *     @type string $error   Error message when it failed ('' otherwise).
	 * }
	 */
	public static function record( array $entry ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$entry = array(
			'time'    => time(),
			'query'   => (string) ( $entry['query'] ?? '' ),
			'tool'    => (string) ( $entry['tool'] ?? '' ),
			'request' => (array) ( $entry['request'] ?? array() ),
			'status'  => (int) ( $entry['status'] ?? 0 ),
			'ok'      => (bool) ( $entry['ok'] ?? false ),
			'error'   => (string) ( $entry['error'] ?? '' ),
		);

		$history = self::all( $user_id );
		array_unshift( $history, $entry );
		$history = array_slice( $history, 0, self::LIMIT );

		update_user_meta( $user_id, self::META_KEY, $history );
	}

	/**
	 * All recorded entries for a user (defaults to the current user), newest first.
	 *
	 * @param int $user_id Optional user id; 0 uses the current user.
	 * @return array<int,array>
	 */
	public static function all( int $user_id = 0 ): array {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		$history = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Clear the current user's history.
	 */
	public static function clear(): void {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_user_meta( $user_id, self::META_KEY );
		}
	}
}
