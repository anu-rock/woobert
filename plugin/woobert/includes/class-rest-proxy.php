<?php
/**
 * Registers the plugin's own admin-only REST routes under woobert/v1.
 *
 * The browser command bar never talks to the inference endpoint or WooCommerce directly. It calls:
 *   POST /woobert/v1/resolve  { query, context }  -> the tool call(s) the model chose (no execution)
 *   POST /woobert/v1/execute  { name, arguments }  -> runs one tool call against WC REST v3
 *
 * Splitting resolve from execute lets the UI preview the action (and prompt for
 * confirmation on destructive tools) before anything is run. Both routes require
 * manage_woocommerce, so the browser holds no API keys.
 *
 * @package Woobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woobert_Rest_Proxy {

	/**
	 * Register REST routes on rest_api_init.
	 */
	public function register(): void {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'woobert/v1',
					'/resolve',
					array(
						'methods'             => 'POST',
						'permission_callback' => array( $this, 'can_manage' ),
						'callback'            => array( $this, 'resolve' ),
					)
				);

				register_rest_route(
					'woobert/v1',
					'/execute',
					array(
						'methods'             => 'POST',
						'permission_callback' => array( $this, 'can_manage' ),
						'callback'            => array( $this, 'execute' ),
					)
				);

				register_rest_route(
					'woobert/v1',
					'/history',
					array(
						'methods'             => 'GET',
						'permission_callback' => array( $this, 'can_manage' ),
						'callback'            => array( $this, 'history' ),
					)
				);

				register_rest_route(
					'woobert/v1',
					'/history/clear',
					array(
						'methods'             => 'POST',
						'permission_callback' => array( $this, 'can_manage' ),
						'callback'            => array( $this, 'clear_history' ),
					)
				);
			}
		);
	}

	/**
	 * Only store managers may drive the command bar.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Resolve a natural-language query to tool call(s). No execution.
	 */
	public function resolve( WP_REST_Request $request ) {
		$query   = trim( (string) $request->get_param( 'query' ) );
		$context = (array) ( $request->get_param( 'context' ) ?? array() );

		if ( '' === $query ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => __( 'Empty query.', 'woobert' ) ), 400 );
		}

		$result = ( new Woobert_Fern_Client() )->infer( $query, $context );
		if ( ! $result['ok'] ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => $result['error'] ?? 'inference failed' ), 502 );
		}

		// Annotate each call with the tool's confirm flag + human summary.
		$calls = array_map(
			static function ( $call ) {
				$tool                = Woobert_Tools::find( $call['name'] );
				$call['confirm']     = (bool) ( $tool['x-woo']['confirm'] ?? false );
				$call['description']  = $tool['function']['description'] ?? '';
				return $call;
			},
			$result['calls']
		);

		return new WP_REST_Response(
			array(
				'ok'    => true,
				'calls' => $calls,
				'reply' => $result['reply'] ?? '',
			),
			200
		);
	}

	/**
	 * Execute a single resolved tool call against WooCommerce.
	 */
	public function execute( WP_REST_Request $request ) {
		$name      = (string) $request->get_param( 'name' );
		$arguments = (array) ( $request->get_param( 'arguments' ) ?? array() );
		$query     = trim( (string) $request->get_param( 'query' ) );

		if ( '' === $name ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => __( 'Missing tool name.', 'woobert' ) ), 400 );
		}

		$result = ( new Woobert_Executor() )->run( $name, $arguments );
		$status = $result['ok'] ? 200 : ( $result['status'] ?: 500 );

		Woobert_History::record(
			array(
				'query'   => $query,
				'tool'    => $name,
				'request' => $result['request'] ?? array(
					'method' => '',
					'route'  => '',
					'params' => $arguments,
				),
				'status'  => $result['status'] ?? $status,
				'ok'      => (bool) $result['ok'],
				'error'   => $result['ok'] ? '' : self::error_message( $result ),
			)
		);

		return new WP_REST_Response( $result, $status );
	}

	/**
	 * Return the current user's executed-command history.
	 */
	public function history( WP_REST_Request $request ) {
		return new WP_REST_Response( array( 'ok' => true, 'entries' => Woobert_History::all() ), 200 );
	}

	/**
	 * Clear the current user's history.
	 */
	public function clear_history( WP_REST_Request $request ) {
		Woobert_History::clear();
		return new WP_REST_Response( array( 'ok' => true, 'entries' => array() ), 200 );
	}

	/**
	 * Pull a human-readable error message out of a failed executor result. The
	 * real WooCommerce message is nested in the error body it returns as `data`.
	 */
	private static function error_message( array $result ): string {
		if ( ! empty( $result['error'] ) ) {
			return (string) $result['error'];
		}
		$data = $result['data'] ?? null;
		if ( is_array( $data ) ) {
			if ( isset( $data['message'] ) ) {
				return (string) $data['message'];
			}
			if ( isset( $data['data']['message'] ) ) {
				return (string) $data['data']['message'];
			}
		}
		return sprintf( 'HTTP %d', (int) ( $result['status'] ?? 0 ) );
	}

	/**
	 * Best-effort page context so "this order/product" resolves to a real id.
	 * Reads the id/post query params of the current wp-admin screen.
	 */
	public static function current_page_context(): array {
		$context = array( 'shop_domain' => wp_parse_url( home_url(), PHP_URL_HOST ) );

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only context hints.
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : ( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		// phpcs:enable

		if ( $screen && $id ) {
			if ( in_array( $screen->post_type, array( 'shop_order', 'shop_order_placehold' ), true ) || 'woocommerce_page_wc-orders' === $screen->id ) {
				$context['current_order_id'] = $id;
			} elseif ( 'product' === $screen->post_type ) {
				$context['current_product_id'] = $id;
			}
		}

		return $context;
	}
}
