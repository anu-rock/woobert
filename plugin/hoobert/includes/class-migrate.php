<?php
/**
 * Carries data across from the plugin's former name, "Woobert".
 *
 * The July 2026 rename changed the plugin folder, so WordPress installs Hoobert
 * as a new plugin rather than upgrading Woobert in place: the old plugin is left
 * deactivated and its settings and audit log would simply be orphaned. This
 * adopts them once, on first activation.
 *
 * The originals are left untouched on purpose, so reinstalling the old build
 * still finds its own data. Nothing here writes over anything Hoobert has
 * already saved.
 *
 * This is transitional. Delete the file, its require in hoobert.php, and the
 * activation hook once no install can still be coming from a Woobert build.
 *
 * @package Hoobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hoobert_Migrate {

	const LEGACY_OPTION     = 'woobert_options';
	const LEGACY_DB_VERSION = 'woobert_db_version';
	const LEGACY_TABLE      = 'woobert_history';
	const DONE_OPTION       = 'hoobert_adopted_legacy';

	/**
	 * Adopt Woobert's settings and command history, once.
	 */
	public static function maybe_adopt_legacy_data(): void {
		if ( get_option( self::DONE_OPTION ) ) {
			return;
		}

		self::adopt_settings();
		self::adopt_history();

		update_option( self::DONE_OPTION, '1' );
	}

	/**
	 * Copy the saved endpoint and API key over, unless Hoobert already has its own.
	 */
	private static function adopt_settings(): void {
		$legacy = get_option( self::LEGACY_OPTION );
		if ( ! is_array( $legacy ) || empty( $legacy ) ) {
			return;
		}

		$current = get_option( Hoobert_Settings::OPTION_NAME );
		if ( is_array( $current ) && ( ! empty( $current['endpoint'] ) || ! empty( $current['api_key'] ) ) ) {
			return;
		}

		update_option(
			Hoobert_Settings::OPTION_NAME,
			array(
				'endpoint' => (string) ( $legacy['endpoint'] ?? '' ),
				'api_key'  => (string) ( $legacy['api_key'] ?? '' ),
			)
		);
	}

	/**
	 * Copy the old audit log into the new table, skipping rows already there so a
	 * repeat run cannot duplicate history.
	 */
	private static function adopt_history(): void {
		global $wpdb;

		$legacy = $wpdb->prefix . self::LEGACY_TABLE;
		$table  = $wpdb->prefix . Hoobert_History::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are the site prefix plus a class constant.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );
		if ( $exists !== $legacy ) {
			return;
		}

		Hoobert_History::install();

		// The statement spans several lines, so the sniff has to be switched off
		// across the block rather than for a single line.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table names are the site prefix plus a class constant; no user input reaches this statement.
		$wpdb->query(
			"INSERT INTO `{$table}` (user_id, created_at, query, tool, request, status, ok, error)
			 SELECT user_id, created_at, query, tool, request, status, ok, error
			 FROM `{$legacy}`
			 WHERE NOT EXISTS ( SELECT 1 FROM `{$table}` LIMIT 1 )"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
