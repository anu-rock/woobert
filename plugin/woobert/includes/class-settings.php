<?php
/**
 * Settings page + typed getters for the Woobert inference connection.
 *
 * The endpoint URL and API key are stored as plugin options, set under
 * WooCommerce -> Woobert. That settings page is the single source of config.
 *
 * @package Woobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woobert_Settings {

	const OPTION_GROUP = 'woobert_settings';
	const OPTION_NAME  = 'woobert_options';

	/**
	 * Wire up the admin menu + registered settings.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Full inference endpoint URL, e.g. https://fernfly.com/api/p/27/infer.
	 * The plugin POSTs the merchant utterance to this URL as-is.
	 */
	public static function endpoint(): string {
		return untrailingslashit( (string) ( self::opts()['endpoint'] ?? '' ) );
	}

	/**
	 * API key sent as X-Api-Key with each inference request.
	 */
	public static function api_key(): string {
		return (string) ( self::opts()['api_key'] ?? '' );
	}

	/**
	 * Saved options array.
	 */
	private static function opts(): array {
		$opts = get_option( self::OPTION_NAME, array() );
		return is_array( $opts ) ? $opts : array();
	}

	/**
	 * Add the settings screen under the WooCommerce menu.
	 */
	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Woobert', 'woobert' ),
			__( 'Woobert', 'woobert' ),
			'manage_woocommerce',
			'woobert',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the option + fields.
	 */
	public static function register(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	/**
	 * Sanitize saved settings.
	 */
	public static function sanitize( $input ): array {
		return array(
			'endpoint' => esc_url_raw( (string) ( $input['endpoint'] ?? '' ) ),
			'api_key'  => sanitize_text_field( (string) ( $input['api_key'] ?? '' ) ),
		);
	}

	/**
	 * Render the settings form.
	 */
	public static function render(): void {
		$opts = self::opts();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Woobert', 'woobert' ); ?></h1>
			<p><?php esc_html_e( 'Connect Woobert to your inference endpoint. Press ⌘K / Ctrl-K anywhere in wp-admin to open the command bar.', 'woobert' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="woobert-endpoint"><?php esc_html_e( 'Inference endpoint URL', 'woobert' ); ?></label></th>
						<td><input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[endpoint]" id="woobert-endpoint" type="url" class="regular-text" placeholder="https://fernfly.com/api/p/27/infer" value="<?php echo esc_attr( $opts['endpoint'] ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="woobert-key"><?php esc_html_e( 'API key', 'woobert' ); ?></label></th>
						<td><input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]" id="woobert-key" type="password" class="regular-text" autocomplete="off" value="<?php echo esc_attr( $opts['api_key'] ?? '' ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
