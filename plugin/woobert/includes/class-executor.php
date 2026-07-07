<?php
/**
 * Dispatches a model-emitted tool call against the WooCommerce REST API v3.
 *
 * The model returns a tool name + arguments; the executor reads that tool's
 * `x-woo` mapping (method, path, path_params, namespace), substitutes path
 * parameters, splits the remaining arguments into query string (GET) or JSON
 * body (write methods), and runs the request in-process via WooCommerce's own
 * rest_do_request, so it inherits the current admin user's capabilities and
 * needs no consumer key/secret round-trip.
 *
 * @package Woobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woobert_Executor {

	/**
	 * Execute a tool call.
	 *
	 * @param string $tool_name Function name emitted by the model.
	 * @param array  $arguments Arguments emitted by the model.
	 * @return array{ok:bool, status:int, data:mixed, request:array, error?:string}
	 */
	public function run( string $tool_name, array $arguments ): array {
		$tool = Woobert_Tools::find( $tool_name );
		if ( ! $tool ) {
			return array(
				'ok'     => false,
				'status' => 400,
				'error'  => sprintf( 'Unknown tool: %s', $tool_name ),
				'data'   => null,
			);
		}

		$mapping   = $tool['x-woo'] ?? array();
		$method    = strtoupper( $mapping['method'] ?? 'GET' );
		$namespace = $mapping['namespace'] ?? 'wc/v3';
		$path_tpl  = $mapping['path'] ?? '';
		$path_keys = $mapping['path_params'] ?? array();

		// Substitute {id} style path params and remove them from the payload.
		$path    = $path_tpl;
		$payload = $arguments;
		foreach ( $path_keys as $key ) {
			$value = isset( $payload[ $key ] ) ? rawurlencode( (string) $payload[ $key ] ) : '';
			$path  = str_replace( '{' . $key . '}', $value, $path );
			unset( $payload[ $key ] );
		}

		$route   = '/' . trim( $namespace, '/' ) . $path;
		$request = new WP_REST_Request( $method, $route );

		if ( 'GET' === $method ) {
			$request->set_query_params( $payload );
		} else {
			$request->set_body_params( $payload );
		}

		$response = rest_do_request( $request );
		$status   = $response->get_status();
		$data     = $response->get_data();

		return array(
			'ok'      => $status >= 200 && $status < 300,
			'status'  => $status,
			'data'    => $data,
			'request' => array(
				'method' => $method,
				'route'  => $route,
				'params' => $payload,
			),
		);
	}
}
