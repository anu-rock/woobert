<?php
/**
 * Seeds a realistic WooCommerce sample dataset: categories, simple products,
 * one variable product with variations, customers, orders in a few statuses,
 * product reviews, and a coupon. Idempotent by SKU / email / code, safe to re-run.
 *
 * Single source of truth for two environments:
 *   - Local dev via WP-CLI (WordPress already bootstrapped):
 *       docker compose run --rm --entrypoint wp wpcli eval-file /scripts/seed-sample-data.php
 *   - WordPress Playground, fetched into the VFS and required by blueprint/blueprint.json
 *     (see that file's writeFile + runPHP steps).
 *
 * @package Woobert
 */

// Playground's runPHP starts with no WordPress loaded; WP-CLI eval-file already did.
if ( ! defined( 'ABSPATH' ) ) {
	require_once '/wordpress/wp-load.php';
}

if ( ! class_exists( 'WooCommerce' ) ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( 'WooCommerce must be active.' );
	}
	return;
}

// A fresh WooCommerce activation sets a one-time redirect transient that fires on the
// next admin request. Under Playground it would bounce the blueprint's landingPage to
// the WooCommerce home screen instead of Woobert, so clear it. Harmless under WP-CLI.
delete_transient( '_wc_activation_redirect' );

/**
 * Log a line only when running under WP-CLI; stays silent in Playground.
 */
function woobert_seed_log( string $message ): void {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( $message );
	}
}

/**
 * Create (or fetch) a product category by name.
 */
function woobert_category( string $name ): int {
	$term = term_exists( $name, 'product_cat' );
	if ( $term ) {
		return (int) $term['term_id'];
	}
	$created = wp_insert_term( $name, 'product_cat' );
	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

/**
 * Create a simple product unless one with the SKU already exists.
 */
function woobert_simple_product( array $args ): int {
	$existing = wc_get_product_id_by_sku( $args['sku'] );
	if ( $existing ) {
		return (int) $existing;
	}
	$product = new WC_Product_Simple();
	$product->set_name( $args['name'] );
	$product->set_sku( $args['sku'] );
	$product->set_regular_price( (string) $args['price'] );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( $args['stock'] ?? 50 );
	$product->set_status( 'publish' );
	$product->set_short_description( $args['blurb'] ?? '' );
	if ( ! empty( $args['category_id'] ) ) {
		$product->set_category_ids( array( $args['category_id'] ) );
	}
	return (int) $product->save();
}

// --- Categories -------------------------------------------------------------

$cat_apparel     = woobert_category( 'Apparel' );
$cat_accessories = woobert_category( 'Accessories' );
$cat_home        = woobert_category( 'Home & Living' );

// --- Simple products --------------------------------------------------------

$catalog = array(
	array( 'name' => 'Classic Cotton Hoodie', 'sku' => 'HOOD-001', 'price' => 49.99, 'stock' => 40, 'category_id' => $cat_apparel, 'blurb' => 'Heavyweight fleece hoodie.' ),
	array( 'name' => 'Merino Beanie', 'sku' => 'ACC-BEAN-1', 'price' => 24.00, 'stock' => 120, 'category_id' => $cat_accessories, 'blurb' => 'Soft merino wool beanie.' ),
	array( 'name' => 'Canvas Tote Bag', 'sku' => 'ACC-TOTE-1', 'price' => 18.50, 'stock' => 200, 'category_id' => $cat_accessories, 'blurb' => 'Sturdy everyday tote.' ),
	array( 'name' => 'Ceramic Pour-Over Mug', 'sku' => 'HOME-MUG-1', 'price' => 29.00, 'stock' => 75, 'category_id' => $cat_home, 'blurb' => 'Handmade stoneware mug.' ),
	array( 'name' => 'Linen Throw Blanket', 'sku' => 'HOME-BLNK-1', 'price' => 89.00, 'stock' => 25, 'category_id' => $cat_home, 'blurb' => 'Stonewashed linen throw.' ),
	array( 'name' => 'Everyday Crew Socks (3-pack)', 'sku' => 'APP-SOCK-3', 'price' => 16.00, 'stock' => 300, 'category_id' => $cat_apparel, 'blurb' => 'Cushioned cotton crew socks.' ),
	array( 'name' => 'Leather Card Holder', 'sku' => 'ACC-CARD-1', 'price' => 34.00, 'stock' => 60, 'category_id' => $cat_accessories, 'blurb' => 'Full-grain leather card holder.' ),
	array( 'name' => 'Scented Soy Candle', 'sku' => 'HOME-CNDL-1', 'price' => 22.00, 'stock' => 90, 'category_id' => $cat_home, 'blurb' => 'Hand-poured soy candle, cedar & sage.' ),
);

$product_ids = array();
foreach ( $catalog as $item ) {
	$product_ids[] = woobert_simple_product( $item );
}

// --- Variable product with variations --------------------------------------

$variable_sku = 'TEE-ORG-1';
$variable_id  = wc_get_product_id_by_sku( $variable_sku );
if ( ! $variable_id ) {
	$attr_color = new WC_Product_Attribute();
	$attr_color->set_name( 'Color' );
	$attr_color->set_options( array( 'Red', 'Blue', 'Green' ) );
	$attr_color->set_visible( true );
	$attr_color->set_variation( true );

	$attr_size = new WC_Product_Attribute();
	$attr_size->set_name( 'Size' );
	$attr_size->set_options( array( 'S', 'M', 'L' ) );
	$attr_size->set_visible( true );
	$attr_size->set_variation( true );

	$variable = new WC_Product_Variable();
	$variable->set_name( 'Organic Cotton Tee' );
	$variable->set_sku( $variable_sku );
	$variable->set_status( 'publish' );
	$variable->set_category_ids( array( $cat_apparel ) );
	$variable->set_attributes( array( $attr_color, $attr_size ) );
	$variable_id = (int) $variable->save();

	foreach ( array( 'Red', 'Blue' ) as $color ) {
		foreach ( array( 'M', 'L' ) as $size ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $variable_id );
			$variation->set_attributes(
				array(
					'color' => $color,
					'size'  => $size,
				)
			);
			$variation->set_regular_price( '32.00' );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( 20 );
			$variation->save();
		}
	}
}
$product_ids[] = $variable_id;

// --- Customers --------------------------------------------------------------

$customers = array(
	array( 'email' => 'jordan.lee@example.com', 'first' => 'Jordan', 'last' => 'Lee' ),
	array( 'email' => 'priya.patel@example.com', 'first' => 'Priya', 'last' => 'Patel' ),
	array( 'email' => 'sam.rivera@example.com', 'first' => 'Sam', 'last' => 'Rivera' ),
);

$customer_ids = array();
foreach ( $customers as $c ) {
	$user_id = email_exists( $c['email'] );
	if ( ! $user_id ) {
		$user_id = wc_create_new_customer( $c['email'], '', wp_generate_password( 16 ), array( 'first_name' => $c['first'], 'last_name' => $c['last'] ) );
	}
	if ( ! is_wp_error( $user_id ) ) {
		$customer_ids[] = (int) $user_id;
	}
}

// --- Orders -----------------------------------------------------------------

$order_plan = array(
	array( 'customer' => 0, 'items' => array( 0, 3 ), 'status' => 'completed' ),
	array( 'customer' => 1, 'items' => array( 1, 2, 5 ), 'status' => 'processing' ),
	array( 'customer' => 2, 'items' => array( 4 ), 'status' => 'on-hold' ),
	array( 'customer' => 0, 'items' => array( 7, 6 ), 'status' => 'completed' ),
	array( 'customer' => 1, 'items' => array( 0 ), 'status' => 'pending' ),
);

$existing_orders = wc_get_orders( array( 'limit' => 1, 'return' => 'ids' ) );
if ( empty( $existing_orders ) ) {
	foreach ( $order_plan as $plan ) {
		$order = wc_create_order();
		foreach ( $plan['items'] as $idx ) {
			$product = wc_get_product( $product_ids[ $idx ] );
			if ( $product ) {
				$order->add_product( $product, 1 );
			}
		}
		if ( isset( $customer_ids[ $plan['customer'] ] ) ) {
			$order->set_customer_id( $customer_ids[ $plan['customer'] ] );
			$user = get_userdata( $customer_ids[ $plan['customer'] ] );
			if ( $user ) {
				$order->set_billing_email( $user->user_email );
				$order->set_billing_first_name( $user->first_name );
				$order->set_billing_last_name( $user->last_name );
			}
		}
		$order->calculate_totals();
		$order->set_status( $plan['status'] );
		$order->save();
	}
	woobert_seed_log( 'Created ' . count( $order_plan ) . ' orders.' );
} else {
	woobert_seed_log( 'Orders already present, skipping order seed.' );
}

// --- Reviews ----------------------------------------------------------------

$reviews = array(
	array( 'product' => 0, 'author' => 'Jordan Lee', 'rating' => 5, 'text' => 'Incredibly warm and soft. My new favorite hoodie.' ),
	array( 'product' => 0, 'author' => 'Priya Patel', 'rating' => 4, 'text' => 'Great fit, runs slightly large.' ),
	array( 'product' => 3, 'author' => 'Sam Rivera', 'rating' => 5, 'text' => 'The mug keeps coffee hot for ages.' ),
	array( 'product' => 4, 'author' => 'Priya Patel', 'rating' => 3, 'text' => 'Beautiful but thinner than expected.' ),
);

foreach ( $reviews as $r ) {
	$product_id = $product_ids[ $r['product'] ];
	$dupe       = get_comments(
		array(
			'post_id' => $product_id,
			'author_email' => sanitize_title( $r['author'] ) . '@example.com',
			'count' => true,
		)
	);
	if ( $dupe ) {
		continue;
	}
	$comment_id = wp_insert_comment(
		array(
			'comment_post_ID'      => $product_id,
			'comment_author'       => $r['author'],
			'comment_author_email' => sanitize_title( $r['author'] ) . '@example.com',
			'comment_content'      => $r['text'],
			'comment_type'         => 'review',
			'comment_approved'     => 1,
		)
	);
	if ( $comment_id ) {
		add_comment_meta( $comment_id, 'rating', $r['rating'] );
	}
}

// --- Coupon -----------------------------------------------------------------

if ( ! wc_get_coupon_id_by_code( 'WELCOME10' ) ) {
	$coupon = new WC_Coupon();
	$coupon->set_code( 'WELCOME10' );
	$coupon->set_discount_type( 'percent' );
	$coupon->set_amount( 10 );
	$coupon->set_description( 'Welcome 10% off, seeded sample data.' );
	$coupon->save();
}

woobert_seed_log( 'Sample data seeded.' );
