<?php
/**
 * Seeds a realistic WooCommerce sample dataset: a refund-capable sample gateway,
 * tax rates, categories, simple products, one variable product with a full
 * variation matrix, registered customers and guests, orders spread over ~90 days
 * in every status the tool set exposes, partial and full refunds, product reviews
 * (including one awaiting moderation), and two coupons.
 *
 * Every seeded object is recorded in the `hoobert_seed_index` option, so re-runs
 * adopt what already exists instead of duplicating it. Keying on the index rather
 * than on user-editable fields (SKU, title) means a merchant editing a seeded
 * product through Hoobert cannot cause the next run to create a second copy.
 *
 * Single source of truth for two environments:
 *   - Local dev via WP-CLI (WordPress already bootstrapped):
 *       docker compose run --rm --entrypoint wp wpcli eval-file /scripts/seed-sample-data.php
 *   - WordPress Playground, fetched into the VFS and required by blueprint/blueprint.json
 *     (see that file's writeFile + runPHP steps).
 *
 * @package Hoobert
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
// the WooCommerce home screen instead of Hoobert, so clear it. Harmless under WP-CLI.
delete_transient( '_wc_activation_redirect' );

defined( 'HOOBERT_SEED_INDEX' ) || define( 'HOOBERT_SEED_INDEX', 'hoobert_seed_index' );
defined( 'HOOBERT_SEED_GATEWAY' ) || define( 'HOOBERT_SEED_GATEWAY', 'hoobert_sample' );

/**
 * Log a line only when running under WP-CLI; stays silent in Playground.
 */
function hoobert_seed_log( string $message ): void {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( $message );
	}
}

/**
 * Look up an object previously created by this seeder. Returns 0 when the key is
 * unknown or the object it pointed at has since been deleted.
 */
function hoobert_seed_id( string $key ): int {
	$index = get_option( HOOBERT_SEED_INDEX, array() );
	return isset( $index[ $key ] ) ? (int) $index[ $key ] : 0;
}

/**
 * Record an object under a stable seed key and return its id unchanged.
 */
function hoobert_seed_remember( string $key, int $id ): int {
	$index         = get_option( HOOBERT_SEED_INDEX, array() );
	$index[ $key ] = $id;
	update_option( HOOBERT_SEED_INDEX, $index, false );
	return $id;
}

/**
 * Resolve a seeded post, verifying it still exists. Falls back to adopting a
 * matching SKU so stores seeded by an earlier version are not duplicated.
 */
function hoobert_seed_post( string $key, string $sku = '' ): int {
	$id = hoobert_seed_id( $key );
	if ( $id && get_post( $id ) ) {
		return $id;
	}
	if ( '' !== $sku ) {
		$existing = wc_get_product_id_by_sku( $sku );
		if ( $existing ) {
			return hoobert_seed_remember( $key, (int) $existing );
		}
	}
	return 0;
}

/**
 * A timestamp N days ago, at a fixed hour, so re-runs on the same day are stable.
 * A negative $days moves into the future, which coupon expiry dates rely on.
 */
function hoobert_days_ago( int $days, int $hour = 11 ): int {
	$day = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
	return (int) strtotime( $day . sprintf( ' %02d:20:00', $hour ) );
}

// --- Sample payment gateway --------------------------------------------------

/**
 * Install a sample gateway as a must-use plugin.
 *
 * WooCommerce refunds route through the order's gateway whenever `api_refund` is
 * true, and the WC REST controller treats a missing `api_refund` as true. No core
 * gateway (cod, bacs, cheque) declares refund support, so on a store with only
 * offline gateways every `refund_order` call fails. A gateway must be registered
 * on every request, not just while seeding, so this is written to mu-plugins
 * rather than hooked here. It belongs to the sample dataset, not to the plugin.
 */
function hoobert_install_sample_gateway(): void {
	$dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$source = <<<'PHP'
<?php
/**
 * Plugin Name: Hoobert Sample Payments
 * Description: A payment gateway that approves payments and refunds locally, installed by Hoobert's sample-data seeder so refund journeys work on a store with no real gateway. Not part of the Hoobert plugin.
 * Version: 1.0.0
 *
 * @package Hoobert
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'hoobert_sample_gateway_init', 11 );

/**
 * Define and register the gateway once WooCommerce's base class is available.
 */
function hoobert_sample_gateway_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) || class_exists( 'Hoobert_Sample_Gateway' ) ) {
		return;
	}

	/**
	 * An offline gateway that reports refund support, so partial and full refunds
	 * succeed without contacting an external payment service.
	 */
	class Hoobert_Sample_Gateway extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'hoobert_sample';
			$this->method_title       = __( 'Sample Payments', 'hoobert' );
			$this->method_description = __( 'Approves payments and refunds locally. Intended for sample and development stores.', 'hoobert' );
			$this->title              = __( 'Sample Payments', 'hoobert' );
			$this->has_fields         = false;
			$this->supports           = array( 'products', 'refunds' );

			$this->init_form_fields();
			$this->init_settings();
			$this->enabled = $this->get_option( 'enabled', 'yes' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'hoobert' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Sample Payments', 'hoobert' ),
					'default' => 'yes',
				),
			);
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			$order->payment_complete( 'sample-' . $order_id );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		/**
		 * Record the refund. Returning true tells WooCommerce the money moved.
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return new WP_Error( 'hoobert_sample_refund', __( 'Order not found.', 'hoobert' ) );
			}

			$order->add_order_note(
				sprintf(
					/* translators: 1: refund amount, 2: optional reason. */
					__( 'Sample Payments refunded %1$s.%2$s', 'hoobert' ),
					wc_format_decimal( $amount, wc_get_price_decimals() ),
					$reason ? ' ' . sprintf( __( 'Reason: %s', 'hoobert' ), $reason ) : ''
				)
			);

			return true;
		}
	}

	add_filter(
		'woocommerce_payment_gateways',
		function ( $gateways ) {
			$gateways[] = 'Hoobert_Sample_Gateway';
			return $gateways;
		}
	);
}
PHP;

	$file = $dir . '/hoobert-sample-payments.php';
	if ( ! file_exists( $file ) || file_get_contents( $file ) !== $source ) {
		file_put_contents( $file, $source );
	}

	// Enable it so it shows as an active gateway, matching a real store.
	update_option(
		'woocommerce_' . HOOBERT_SEED_GATEWAY . '_settings',
		array(
			'enabled' => 'yes',
			'title'   => 'Sample Payments',
		)
	);
}

hoobert_install_sample_gateway();

// --- Taxes ------------------------------------------------------------------

/**
 * Insert a state tax rate once. Real stores charge tax only where they have nexus,
 * so only some customers are taxed, which keeps the sales report honest.
 */
function hoobert_tax_rate( string $key, array $rate ): void {
	$id = hoobert_seed_id( $key );
	if ( $id && WC_Tax::_get_tax_rate( $id ) ) {
		return;
	}
	hoobert_seed_remember( $key, (int) WC_Tax::_insert_tax_rate( $rate ) );
}

hoobert_tax_rate(
	'tax-us-ca',
	array(
		'tax_rate_country'  => 'US',
		'tax_rate_state'    => 'CA',
		'tax_rate'          => '8.5000',
		'tax_rate_name'     => 'CA Sales Tax',
		'tax_rate_priority' => 1,
		'tax_rate_compound' => 0,
		'tax_rate_shipping' => 1,
		'tax_rate_order'    => 0,
		'tax_rate_class'    => '',
	)
);

hoobert_tax_rate(
	'tax-us-ny',
	array(
		'tax_rate_country'  => 'US',
		'tax_rate_state'    => 'NY',
		'tax_rate'          => '4.0000',
		'tax_rate_name'     => 'NY Sales Tax',
		'tax_rate_priority' => 1,
		'tax_rate_compound' => 0,
		'tax_rate_shipping' => 1,
		'tax_rate_order'    => 1,
		'tax_rate_class'    => '',
	)
);

update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_tax_based_on', 'billing' );
update_option( 'woocommerce_prices_include_tax', 'no' );

// --- Categories -------------------------------------------------------------

/**
 * Create (or fetch) a product category by name.
 */
function hoobert_category( string $name ): int {
	$term = term_exists( $name, 'product_cat' );
	if ( $term ) {
		return (int) $term['term_id'];
	}
	$created = wp_insert_term( $name, 'product_cat' );
	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

$cat_apparel     = hoobert_category( 'Apparel' );
$cat_accessories = hoobert_category( 'Accessories' );
$cat_home        = hoobert_category( 'Home & Living' );

// --- Simple products --------------------------------------------------------

/**
 * Create a simple product unless this seed key already resolves to one.
 */
function hoobert_simple_product( array $args ): int {
	$existing = hoobert_seed_post( 'product:' . $args['sku'], $args['sku'] );
	if ( $existing ) {
		return $existing;
	}

	$product = new WC_Product_Simple();
	$product->set_name( $args['name'] );
	$product->set_sku( $args['sku'] );
	$product->set_regular_price( (string) $args['price'] );
	if ( ! empty( $args['sale_price'] ) ) {
		$product->set_sale_price( (string) $args['sale_price'] );
	}
	$product->set_manage_stock( true );
	$product->set_stock_quantity( $args['stock'] ?? 50 );
	$product->set_status( 'publish' );
	$product->set_short_description( $args['blurb'] ?? '' );
	$product->set_description( $args['description'] ?? '' );
	$product->set_weight( (string) ( $args['weight'] ?? '' ) );
	$product->set_date_created( hoobert_days_ago( 120 ) );
	if ( ! empty( $args['category_id'] ) ) {
		$product->set_category_ids( array( $args['category_id'] ) );
	}

	$id = (int) $product->save();
	update_post_meta( $id, '_hoobert_seed', $args['sku'] );

	return hoobert_seed_remember( 'product:' . $args['sku'], $id );
}

$catalog = array(
	array(
		'name'        => 'Classic Cotton Hoodie',
		'sku'         => 'HOOD-001',
		'price'       => 49.99,
		'stock'       => 40,
		'weight'      => 1.4,
		'category_id' => $cat_apparel,
		'blurb'       => 'Heavyweight fleece hoodie.',
		'description' => 'A 400gsm brushed-back fleece hoodie cut for everyday wear. Double-stitched shoulder seams, a lined hood, and a kangaroo pocket deep enough for a phone. Pre-shrunk, so it keeps its shape after washing.',
	),
	array(
		'name'        => 'Merino Beanie',
		'sku'         => 'ACC-BEAN-1',
		'price'       => 24.00,
		'stock'       => 120,
		'weight'      => 0.2,
		'category_id' => $cat_accessories,
		'blurb'       => 'Soft merino wool beanie.',
		'description' => 'Knitted from 100% extra-fine merino, which regulates warmth without itching. A folded brim doubles the layer over the ears. Machine washable on a wool cycle.',
	),
	array(
		'name'        => 'Canvas Tote Bag',
		'sku'         => 'ACC-TOTE-1',
		'price'       => 18.50,
		'stock'       => 200,
		'weight'      => 0.4,
		'category_id' => $cat_accessories,
		'blurb'       => 'Sturdy everyday tote.',
		'description' => 'Cut from 12oz cotton canvas with reinforced handles and a boxed base that keeps the bag upright when loaded. Holds a laptop, a water bottle, and a week of groceries.',
	),
	array(
		'name'        => 'Ceramic Pour-Over Mug',
		'sku'         => 'HOME-MUG-1',
		'price'       => 29.00,
		'stock'       => 75,
		'weight'      => 0.7,
		'category_id' => $cat_home,
		'blurb'       => 'Handmade stoneware mug.',
		'description' => 'Thrown and glazed by hand, so no two are identical. The thick stoneware wall holds heat far longer than a thin porcelain cup. Dishwasher and microwave safe. Holds 340ml.',
	),
	array(
		'name'        => 'Linen Throw Blanket',
		'sku'         => 'HOME-BLNK-1',
		'price'       => 89.00,
		'stock'       => 25,
		'weight'      => 1.1,
		'category_id' => $cat_home,
		'blurb'       => 'Stonewashed linen throw.',
		'description' => 'European flax, stonewashed until it drapes like something you have owned for years. Breathable enough for summer, layered easily in winter. 130 x 180cm with a hand-knotted fringe.',
	),
	array(
		'name'        => 'Everyday Crew Socks (3-pack)',
		'sku'         => 'APP-SOCK-3',
		'price'       => 16.00,
		'sale_price'  => 13.60,
		'stock'       => 300,
		'weight'      => 0.3,
		'category_id' => $cat_apparel,
		'blurb'       => 'Cushioned cotton crew socks.',
		'description' => 'Combed cotton with a cushioned footbed and a ribbed cuff that stays up through a full day. Sold in a three-pack of charcoal, navy, and oatmeal.',
	),
	array(
		'name'        => 'Leather Card Holder',
		'sku'         => 'ACC-CARD-1',
		'price'       => 34.00,
		'stock'       => 60,
		'weight'      => 0.1,
		'category_id' => $cat_accessories,
		'blurb'       => 'Full-grain leather card holder.',
		'description' => 'Vegetable-tanned full-grain leather that darkens with use. Four card slots and a centre pocket for folded notes. Saddle-stitched by hand so a single broken thread will not unravel the seam.',
	),
	array(
		'name'        => 'Scented Soy Candle',
		'sku'         => 'HOME-CNDL-1',
		'price'       => 22.00,
		'stock'       => 90,
		'weight'      => 0.5,
		'category_id' => $cat_home,
		'blurb'       => 'Hand-poured soy candle, cedar & sage.',
		'description' => 'Hand-poured soy wax with a cotton wick, scented with cedarwood and clary sage. Burns clean for roughly 45 hours. Reusable amber glass vessel.',
	),
);

$products = array();
foreach ( $catalog as $item ) {
	$products[ $item['sku'] ] = hoobert_simple_product( $item );
}

// --- Variable product with a full variation matrix ---------------------------

$variable_id = hoobert_seed_post( 'product:TEE-ORG-1', 'TEE-ORG-1' );

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
	$variable->set_sku( 'TEE-ORG-1' );
	$variable->set_status( 'publish' );
	$variable->set_category_ids( array( $cat_apparel ) );
	$variable->set_attributes( array( $attr_color, $attr_size ) );
	$variable->set_short_description( 'GOTS-certified organic cotton tee.' );
	$variable->set_description( 'A midweight tee in GOTS-certified organic cotton, knitted to hold its shape through repeated washing. Side-seamed with a taped neck. Runs true to size.' );
	$variable->set_date_created( hoobert_days_ago( 120 ) );
	$variable_id = (int) $variable->save();

	update_post_meta( $variable_id, '_hoobert_seed', 'TEE-ORG-1' );
	hoobert_seed_remember( 'product:TEE-ORG-1', $variable_id );

	// Stock and price vary across the matrix, and one variation is sold out,
	// so stock and pricing tools have something non-uniform to act on.
	$stock_by_size = array( 'S' => 12, 'M' => 20, 'L' => 16 );
	foreach ( array( 'Red', 'Blue', 'Green' ) as $color ) {
		foreach ( array( 'S', 'M', 'L' ) as $size ) {
			$code  = strtoupper( substr( $color, 0, 1 ) ) . $size;
			$stock = ( 'Green' === $color && 'S' === $size ) ? 0 : $stock_by_size[ $size ];

			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $variable_id );
			$variation->set_sku( 'TEE-ORG-1-' . $code );
			$variation->set_attributes(
				array(
					'color' => $color,
					'size'  => $size,
				)
			);
			$variation->set_regular_price( '32.00' );
			if ( 'Green' === $color ) {
				$variation->set_sale_price( '27.20' );
			}
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( $stock );
			$variation->set_date_created( hoobert_days_ago( 120 ) );
			$variation_id = (int) $variation->save();

			update_post_meta( $variation_id, '_hoobert_seed', 'TEE-ORG-1-' . $code );
			hoobert_seed_remember( 'product:TEE-ORG-1-' . $code, $variation_id );
		}
	}

	WC_Product_Variable::sync( $variable_id );
}

$products['TEE-ORG-1'] = $variable_id;
foreach ( array( 'RS', 'RM', 'RL', 'BS', 'BM', 'BL', 'GS', 'GM', 'GL' ) as $code ) {
	$products[ 'TEE-ORG-1-' . $code ] = hoobert_seed_post( 'product:TEE-ORG-1-' . $code, 'TEE-ORG-1-' . $code );
}

// --- Coupons ----------------------------------------------------------------

/**
 * Create a coupon once, keyed by code.
 */
function hoobert_coupon( array $args ): int {
	$existing = wc_get_coupon_id_by_code( $args['code'] );
	if ( $existing ) {
		return (int) $existing;
	}

	$coupon = new WC_Coupon();
	$coupon->set_code( $args['code'] );
	$coupon->set_discount_type( $args['type'] );
	$coupon->set_amount( $args['amount'] );
	$coupon->set_description( $args['description'] );
	if ( isset( $args['expires'] ) ) {
		$coupon->set_date_expires( $args['expires'] );
	}
	if ( isset( $args['minimum'] ) ) {
		$coupon->set_minimum_amount( (string) $args['minimum'] );
	}
	if ( isset( $args['usage_limit'] ) ) {
		$coupon->set_usage_limit( $args['usage_limit'] );
	}
	if ( isset( $args['limit_per_user'] ) ) {
		$coupon->set_usage_limit_per_user( $args['limit_per_user'] );
	}

	return (int) $coupon->save();
}

hoobert_coupon(
	array(
		'code'           => 'WELCOME10',
		'type'           => 'percent',
		'amount'         => 10,
		'description'    => '10% off a first order.',
		'expires'        => hoobert_days_ago( -60 ),
		'minimum'        => 25,
		'usage_limit'    => 500,
		'limit_per_user' => 1,
	)
);

// An expired coupon, so list_coupons and update_coupon have a realistic second row.
hoobert_coupon(
	array(
		'code'        => 'SPRING15',
		'type'        => 'fixed_cart',
		'amount'      => 15,
		'description' => 'Spring promotion, $15 off orders over $75.',
		'expires'     => hoobert_days_ago( 20 ),
		'minimum'     => 75,
	)
);

// --- Customers --------------------------------------------------------------

$customers = array(
	'jordan' => array(
		'email'     => 'jordan.lee@example.com',
		'first'     => 'Jordan',
		'last'      => 'Lee',
		'address_1' => '1180 Alder Avenue',
		'city'      => 'Berkeley',
		'state'     => 'CA',
		'postcode'  => '94702',
		'phone'     => '510-555-0143',
	),
	'priya'  => array(
		'email'     => 'priya.patel@example.com',
		'first'     => 'Priya',
		'last'      => 'Patel',
		'address_1' => '88 Grand Street',
		'address_2' => 'Apt 4R',
		'city'      => 'Brooklyn',
		'state'     => 'NY',
		'postcode'  => '11211',
		'phone'     => '718-555-0192',
	),
	'sam'    => array(
		'email'     => 'sam.rivera@example.com',
		'first'     => 'Sam',
		'last'      => 'Rivera',
		'address_1' => '2401 Rio Grande Street',
		'city'      => 'Austin',
		'state'     => 'TX',
		'postcode'  => '78705',
		'phone'     => '512-555-0177',
	),
);

/**
 * Build prefixed address props for a person, ready for WC_Data::set_props().
 *
 * Props are prefixed rather than passed to the legacy set_address(), which writes
 * straight to post meta and so misses orders stored in HPOS tables. set_props()
 * skips any setter a given object lacks (shipping has no email, for instance).
 */
function hoobert_address_props( array $person, string $type ): array {
	$address = array(
		'first_name' => $person['first'],
		'last_name'  => $person['last'],
		'address_1'  => $person['address_1'],
		'address_2'  => $person['address_2'] ?? '',
		'city'       => $person['city'],
		'state'      => $person['state'],
		'postcode'   => $person['postcode'],
		'country'    => 'US',
		'email'      => $person['email'],
		'phone'      => $person['phone'] ?? '',
	);

	$props = array();
	foreach ( $address as $key => $value ) {
		$props[ $type . '_' . $key ] = $value;
	}

	return $props;
}

$customer_ids = array();
foreach ( $customers as $slug => $person ) {
	$user_id = email_exists( $person['email'] );

	if ( ! $user_id ) {
		$user_id = wc_create_new_customer(
			$person['email'],
			'',
			wp_generate_password( 16 ),
			array(
				'first_name' => $person['first'],
				'last_name'  => $person['last'],
			)
		);
	}

	if ( is_wp_error( $user_id ) ) {
		continue;
	}

	$user_id  = (int) $user_id;
	$customer = new WC_Customer( $user_id );
	$customer->set_props(
		array_merge(
			array(
				'first_name'   => $person['first'],
				'last_name'    => $person['last'],
				'display_name' => $person['first'] . ' ' . $person['last'],
				'date_created' => hoobert_days_ago( 100 ),
			),
			hoobert_address_props( $person, 'billing' ),
			hoobert_address_props( $person, 'shipping' )
		)
	);
	$customer->save();

	$customer_ids[ $slug ] = $user_id;
	hoobert_seed_remember( 'customer:' . $slug, $user_id );
}

// Guests, who check out without an account. Real stores have plenty.
$guests = array(
	'alex' => array(
		'email'     => 'alex.chen@example.com',
		'first'     => 'Alex',
		'last'      => 'Chen',
		'address_1' => '500 Pine Street',
		'city'      => 'Seattle',
		'state'     => 'WA',
		'postcode'  => '98101',
		'phone'     => '206-555-0110',
	),
	'dana' => array(
		'email'     => 'dana.whitfield@example.com',
		'first'     => 'Dana',
		'last'      => 'Whitfield',
		'address_1' => '77 Ocean Boulevard',
		'city'      => 'San Diego',
		'state'     => 'CA',
		'postcode'  => '92101',
		'phone'     => '619-555-0166',
	),
);

// --- Orders -----------------------------------------------------------------

/**
 * Seed one order: addresses, line items, a shipping line, a payment method, an
 * optional coupon, and dates that sit in the past. Statuses are applied last so
 * WooCommerce runs its own stock and paid-date transitions, as on a real store.
 */
function hoobert_order( string $key, array $plan, array $products, array $people ): int {
	$existing = hoobert_seed_id( $key );
	if ( $existing && wc_get_order( $existing ) ) {
		return $existing;
	}

	$person  = $people[ $plan['person'] ];
	$created = hoobert_days_ago( $plan['days_ago'] );

	// Resolve every line item first. A half-populated order is worse than none,
	// so bail if the catalog is missing anything this order needs.
	$line_items = array();
	foreach ( $plan['items'] as $sku => $qty ) {
		$product = empty( $products[ $sku ] ) ? null : wc_get_product( $products[ $sku ] );
		if ( ! $product ) {
			hoobert_seed_log( "  ! {$key} skipped: product {$sku} not found." );
			return 0;
		}
		$line_items[] = array( $product, $qty );
	}

	$order = wc_create_order( array( 'customer_id' => $plan['customer_id'] ?? 0 ) );
	if ( is_wp_error( $order ) ) {
		hoobert_seed_log( "  ! {$key} skipped: " . $order->get_error_message() );
		return 0;
	}

	$order->set_props(
		array_merge(
			hoobert_address_props( $person, 'billing' ),
			hoobert_address_props( $person, 'shipping' )
		)
	);
	$order->set_created_via( 'checkout' );
	$order->set_payment_method( HOOBERT_SEED_GATEWAY );
	$order->set_payment_method_title( 'Sample Payments' );

	foreach ( $line_items as $line ) {
		$order->add_product( $line[0], $line[1] );
	}

	$shipping = new WC_Order_Item_Shipping();
	$shipping->set_method_id( $plan['shipping'] > 0 ? 'flat_rate' : 'free_shipping' );
	$shipping->set_method_title( $plan['shipping'] > 0 ? 'Flat rate' : 'Free shipping' );
	$shipping->set_total( (string) $plan['shipping'] );
	$order->add_item( $shipping );

	if ( ! empty( $plan['coupon'] ) ) {
		$applied = $order->apply_coupon( $plan['coupon'] );
		if ( is_wp_error( $applied ) ) {
			hoobert_seed_log( "  ! coupon {$plan['coupon']} rejected on {$key}: " . $applied->get_error_message() );
		}
	}

	$order->calculate_totals( true );
	$order->set_date_created( $created );

	// Set the paid date before the status transition, otherwise WooCommerce
	// stamps it with the current time when the order enters a paid status.
	if ( in_array( $plan['status'], array( 'processing', 'completed' ), true ) ) {
		$order->set_date_paid( $created + HOUR_IN_SECONDS );
		$order->set_transaction_id( 'sample-' . strtoupper( wp_generate_password( 10, false ) ) );
	}
	if ( 'completed' === $plan['status'] ) {
		$order->set_date_completed( $created + DAY_IN_SECONDS );
	}

	$order->update_meta_data( '_hoobert_seed', $key );

	// Saving with the final status lets WooCommerce run its own transition hooks,
	// which reduce stock and, for 'refunded', create the matching full refund.
	$order->set_status( $plan['status'] );
	$order->save();

	return hoobert_seed_remember( $key, (int) $order->get_id() );
}

$people = array_merge( $customers, $guests );

$order_plan = array(
	'order:01' => array(
		'person'      => 'jordan',
		'customer_id' => $customer_ids['jordan'] ?? 0,
		'items'       => array( 'HOOD-001' => 1, 'HOME-MUG-1' => 1 ),
		'shipping'    => 5.95,
		'status'      => 'completed',
		'days_ago'    => 86,
	),
	'order:02' => array(
		'person'      => 'priya',
		'customer_id' => $customer_ids['priya'] ?? 0,
		'items'       => array( 'ACC-BEAN-1' => 2, 'ACC-TOTE-1' => 1 ),
		'shipping'    => 5.95,
		'status'      => 'completed',
		'days_ago'    => 74,
	),
	'order:03' => array(
		'person'   => 'alex',
		'items'    => array( 'APP-SOCK-3' => 3 ),
		'shipping' => 5.95,
		'status'   => 'completed',
		'days_ago' => 63,
	),
	'order:04' => array(
		'person'      => 'sam',
		'customer_id' => $customer_ids['sam'] ?? 0,
		'items'       => array( 'HOME-BLNK-1' => 1 ),
		'shipping'    => 0,
		'status'      => 'completed',
		'days_ago'    => 55,
	),
	'order:05' => array(
		'person'      => 'jordan',
		'customer_id' => $customer_ids['jordan'] ?? 0,
		'items'       => array( 'HOME-CNDL-1' => 2, 'ACC-CARD-1' => 1 ),
		'shipping'    => 5.95,
		'status'      => 'completed',
		'days_ago'    => 44,
		'coupon'      => 'WELCOME10',
	),
	'order:06' => array(
		'person'      => 'priya',
		'customer_id' => $customer_ids['priya'] ?? 0,
		'items'       => array( 'TEE-ORG-1-BM' => 1 ),
		'shipping'    => 5.95,
		'status'      => 'cancelled',
		'days_ago'    => 37,
	),
	'order:07' => array(
		'person'      => 'sam',
		'customer_id' => $customer_ids['sam'] ?? 0,
		'items'       => array( 'HOME-MUG-1' => 1, 'APP-SOCK-3' => 1 ),
		'shipping'    => 5.95,
		'status'      => 'refunded',
		'days_ago'    => 30,
	),
	'order:08' => array(
		'person'      => 'jordan',
		'customer_id' => $customer_ids['jordan'] ?? 0,
		'items'       => array( 'TEE-ORG-1-RL' => 2 ),
		'shipping'    => 0,
		'status'      => 'completed',
		'days_ago'    => 22,
	),
	'order:09' => array(
		'person'   => 'dana',
		'items'    => array( 'ACC-BEAN-1' => 1 ),
		'shipping' => 5.95,
		'status'   => 'failed',
		'days_ago' => 15,
	),
	'order:10' => array(
		'person'      => 'priya',
		'customer_id' => $customer_ids['priya'] ?? 0,
		'items'       => array( 'HOOD-001' => 1, 'APP-SOCK-3' => 2 ),
		'shipping'    => 5.95,
		'status'      => 'processing',
		'days_ago'    => 9,
	),
	'order:11' => array(
		'person'      => 'sam',
		'customer_id' => $customer_ids['sam'] ?? 0,
		'items'       => array( 'ACC-CARD-1' => 1, 'HOME-CNDL-1' => 1 ),
		'shipping'    => 5.95,
		'status'      => 'on-hold',
		'days_ago'    => 4,
	),
	'order:12' => array(
		'person'      => 'jordan',
		'customer_id' => $customer_ids['jordan'] ?? 0,
		'items'       => array( 'ACC-TOTE-1' => 1 ),
		'shipping'    => 5.95,
		'status'      => 'pending',
		'days_ago'    => 1,
	),
);

$order_ids = array();
foreach ( $order_plan as $key => $plan ) {
	$order_ids[ $key ] = hoobert_order( $key, $plan, $products, $people );
}

// --- Refunds ----------------------------------------------------------------

/**
 * Record a partial refund against an order, back-dated to sit near the order.
 *
 * `refund_payment` stays false: the sample gateway is a must-use plugin that is
 * not loaded inside this seeding request, and there is no real money to move.
 */
function hoobert_partial_refund( string $key, int $order_id, string $amount, string $reason, int $days_ago ): void {
	if ( hoobert_seed_id( $key ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || $order->get_total_refunded() > 0 ) {
		return;
	}

	$refund = wc_create_refund(
		array(
			'order_id'       => $order_id,
			'amount'         => $amount,
			'reason'         => $reason,
			'refund_payment' => false,
			'restock_items'  => true,
		)
	);

	if ( is_wp_error( $refund ) ) {
		hoobert_seed_log( '  ! refund failed: ' . $refund->get_error_message() );
		return;
	}

	$refund->set_date_created( hoobert_days_ago( $days_ago ) );
	$refund->save();
	hoobert_seed_remember( $key, (int) $refund->get_id() );
}

// A completed order that kept its status but returned part of the money.
if ( ! empty( $order_ids['order:04'] ) ) {
	hoobert_partial_refund( 'refund:04', $order_ids['order:04'], '15.00', 'Damaged in transit', 52 );
}

// --- Reviews ----------------------------------------------------------------

/**
 * Insert a review tied to a real reviewer. Reviews from customers who bought the
 * product are marked verified, which is what WooCommerce shows in the admin.
 */
function hoobert_review( string $key, int $product_id, array $args ): void {
	if ( hoobert_seed_id( $key ) ) {
		return;
	}

	$timestamp = hoobert_days_ago( $args['days_ago'] );
	$comment_id = wp_insert_comment(
		array(
			'comment_post_ID'      => $product_id,
			'comment_author'       => $args['author'],
			'comment_author_email' => $args['email'],
			'comment_content'      => $args['text'],
			'comment_type'         => 'review',
			'comment_approved'     => $args['approved'] ? 1 : 0,
			'user_id'              => $args['user_id'] ?? 0,
			'comment_date'         => wp_date( 'Y-m-d H:i:s', $timestamp ),
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $timestamp ),
		)
	);

	if ( ! $comment_id ) {
		return;
	}

	add_comment_meta( $comment_id, 'rating', $args['rating'] );
	add_comment_meta( $comment_id, 'verified', $args['verified'] ? 1 : 0 );
	add_comment_meta( $comment_id, '_hoobert_seed', $key );
	hoobert_seed_remember( $key, (int) $comment_id );
}

$reviews = array(
	'review:01' => array(
		'sku'      => 'HOOD-001',
		'author'   => 'Jordan Lee',
		'email'    => 'jordan.lee@example.com',
		'customer' => 'jordan',
		'rating'   => 5,
		'verified' => true,
		'approved' => true,
		'days_ago' => 80,
		'text'     => 'Incredibly warm and soft. My new favorite hoodie.',
	),
	'review:02' => array(
		'sku'      => 'ACC-BEAN-1',
		'author'   => 'Priya Patel',
		'email'    => 'priya.patel@example.com',
		'customer' => 'priya',
		'rating'   => 4,
		'verified' => true,
		'approved' => true,
		'days_ago' => 68,
		'text'     => 'Warm without any itch. The brim is a little loose after a month.',
	),
	'review:03' => array(
		'sku'      => 'HOME-BLNK-1',
		'author'   => 'Sam Rivera',
		'email'    => 'sam.rivera@example.com',
		'customer' => 'sam',
		'rating'   => 3,
		'verified' => true,
		'approved' => true,
		'days_ago' => 50,
		'text'     => 'Beautiful but thinner than expected. Arrived with a small tear.',
	),
	'review:04' => array(
		'sku'      => 'HOME-CNDL-1',
		'author'   => 'Jordan Lee',
		'email'    => 'jordan.lee@example.com',
		'customer' => 'jordan',
		'rating'   => 5,
		'verified' => true,
		'approved' => true,
		'days_ago' => 38,
		'text'     => 'Cedar and sage, exactly as described. Burned evenly to the base.',
	),
	'review:05' => array(
		'sku'      => 'HOME-MUG-1',
		'author'   => 'Sam Rivera',
		'email'    => 'sam.rivera@example.com',
		'customer' => 'sam',
		'rating'   => 5,
		'verified' => true,
		'approved' => true,
		'days_ago' => 26,
		'text'     => 'The mug keeps coffee hot for ages. Worth the price.',
	),
	'review:06' => array(
		'sku'      => 'HOOD-001',
		'author'   => 'Priya Patel',
		'email'    => 'priya.patel@example.com',
		'customer' => 'priya',
		'rating'   => 4,
		'verified' => true,
		'approved' => true,
		'days_ago' => 5,
		'text'     => 'Great fit, runs slightly large. Order a size down.',
	),
	// Awaiting moderation, so moderate_product_review has something to approve.
	'review:07' => array(
		'sku'      => 'ACC-TOTE-1',
		'author'   => 'Riley Brooks',
		'email'    => 'riley.brooks@example.com',
		'customer' => null,
		'rating'   => 2,
		'verified' => false,
		'approved' => false,
		'days_ago' => 2,
		'text'     => 'Handles frayed within a fortnight. Expected better for the price.',
	),
);

$reviewed_products = array();
foreach ( $reviews as $key => $review ) {
	$product_id = $products[ $review['sku'] ] ?? 0;
	if ( ! $product_id ) {
		continue;
	}

	hoobert_review(
		$key,
		$product_id,
		array(
			'author'   => $review['author'],
			'email'    => $review['email'],
			'text'     => $review['text'],
			'rating'   => $review['rating'],
			'verified' => $review['verified'],
			'approved' => $review['approved'],
			'days_ago' => $review['days_ago'],
			'user_id'  => $review['customer'] ? ( $customer_ids[ $review['customer'] ] ?? 0 ) : 0,
		)
	);

	$reviewed_products[ $product_id ] = true;
}

// wp_insert_comment does not recalculate a product's rating aggregates, so the
// average, rating counts, and review count must be rebuilt explicitly.
foreach ( array_keys( $reviewed_products ) as $product_id ) {
	WC_Comments::clear_transients( $product_id );
}

// --- Analytics backfill ------------------------------------------------------

/**
 * Populate the wc_order_stats / wc_customer_lookup tables that the wc-analytics
 * reports read.
 *
 * WooCommerce normally fills these from an Action Scheduler job queued on order
 * save. Nothing drains that queue during WP-CLI seeding or a Playground boot, so
 * the reports would otherwise show every customer with zero orders and zero spend.
 */
function hoobert_sync_analytics( array $order_ids, array $customer_ids ): void {
	$orders_scheduler    = 'Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler';
	$customers_scheduler = 'Automattic\WooCommerce\Internal\Admin\Schedulers\CustomersScheduler';

	foreach ( $order_ids as $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			continue;
		}

		// Refunds are their own records in the stats table.
		$targets = array( (int) $order_id );
		foreach ( $order->get_refunds() as $refund ) {
			$targets[] = (int) $refund->get_id();
		}

		foreach ( $targets as $target ) {
			if ( class_exists( $orders_scheduler ) ) {
				$orders_scheduler::import( $target );
				continue;
			}

			// Older WooCommerce: drive the report data stores directly.
			$stores = array(
				'Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' => 'sync_order',
				'Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore'    => 'sync_order_customer',
				'Automattic\WooCommerce\Admin\API\Reports\Products\DataStore'     => 'sync_order_products',
				'Automattic\WooCommerce\Admin\API\Reports\Coupons\DataStore'      => 'sync_order_coupons',
				'Automattic\WooCommerce\Admin\API\Reports\Taxes\DataStore'        => 'sync_order_taxes',
			);
			foreach ( $stores as $class => $method ) {
				if ( class_exists( $class ) && method_exists( $class, $method ) ) {
					call_user_func( array( $class, $method ), $target );
				}
			}
		}
	}

	// Refresh each customer's lookup row so country, city, and last-active are set.
	if ( class_exists( $customers_scheduler ) ) {
		foreach ( $customer_ids as $customer_id ) {
			$customers_scheduler::import( $customer_id );
		}
	}
}

hoobert_sync_analytics( array_filter( $order_ids ), array_values( $customer_ids ) );

// The legacy wc/v3/reports/* endpoints cache their aggregates in transients.
wc_delete_shop_order_transients();

hoobert_seed_log(
	sprintf(
		'Seeded %d products, %d customers, %d orders, %d reviews, 2 coupons.',
		count( $products ),
		count( $customer_ids ),
		count( array_filter( $order_ids ) ),
		count( $reviews )
	)
);
hoobert_seed_log( 'Sample data seeded.' );
