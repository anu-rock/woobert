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
	 *
	 * The key field renders empty rather than echoing the stored secret, so an
	 * empty submission means "leave it alone", not "clear it". Clearing is done
	 * with the explicit checkbox.
	 */
	public static function sanitize( $input ): array {
		$submitted = sanitize_text_field( (string) ( $input['api_key'] ?? '' ) );
		$clearing  = ! empty( $input['api_key_clear'] );

		if ( '' === $submitted && ! $clearing ) {
			$submitted = self::api_key();
		}

		return array(
			'endpoint' => esc_url_raw( (string) ( $input['endpoint'] ?? '' ) ),
			'api_key'  => $clearing ? '' : $submitted,
		);
	}

	/**
	 * Render the settings form.
	 */
	public static function render(): void {
		$opts = self::opts();
		?>
		<div class="wrap">
			<h1 class="woobert-title">
				<img src="<?php echo esc_url( WOOBERT_URL . 'assets/woobert-owl.svg' ); ?>" alt="" width="40" height="40" />
				<?php esc_html_e( 'Woobert', 'woobert' ); ?>
			</h1>
			<style>
				.woobert-title { display: flex; align-items: center; gap: 10px; }
				.woobert-credit { margin-top: 32px; color: #646970; font-size: 12px; }
			</style>
			<div class="card">
				<p><strong><?php esc_html_e( 'Run your whole store from one prompt.', 'woobert' ); ?></strong></p>
				<p>
					<?php esc_html_e( 'Type what you want in plain English, like "refund order 1042", "add a Large/Red variation to this product at 54.99", or "who are my top customers this month". Woobert turns it into the right WooCommerce action and runs it, under your own admin session. No menu hunting, no keys in the browser.', 'woobert' ); ?>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: link to the Fernfly website. */
						wp_kses(
							__( 'Woobert is powered by <strong>Fern</strong>, a family of tiny function-calling models by %s. The model maps your request to a WooCommerce REST API call; the plugin runs it server-side and shows you the result.', 'woobert' ),
							array( 'strong' => array() )
						),
						'<a href="' . esc_url( 'https://fernfly.com' ) . '" target="_blank" rel="noreferrer">Fernfly</a>'
					);
					?>
				</p>
			</div>
			<p><?php esc_html_e( 'Connect Woobert to your inference endpoint. Press ⌘K / Ctrl-K anywhere in wp-admin and pick "Ask Woobert" to open the command palette.', 'woobert' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="woobert-endpoint"><?php esc_html_e( 'Inference endpoint URL', 'woobert' ); ?></label></th>
						<td><input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[endpoint]" id="woobert-endpoint" type="url" class="regular-text" placeholder="https://fernfly.com/api/p/27/infer" value="<?php echo esc_attr( $opts['endpoint'] ?? '' ); ?>" /></td>
					</tr>
					<?php $has_key = '' !== self::api_key(); ?>
					<tr>
						<th scope="row"><label for="woobert-key"><?php esc_html_e( 'API key', 'woobert' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]" id="woobert-key" type="password" class="regular-text" autocomplete="off" value=""
								placeholder="<?php echo esc_attr( $has_key ? __( 'Saved. Enter a new key to replace it.', 'woobert' ) : __( 'Paste your project API key', 'woobert' ) ); ?>" />
							<?php if ( $has_key ) : ?>
								<p class="description">
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key_clear]" value="1" />
										<?php esc_html_e( 'Remove the saved key', 'woobert' ); ?>
									</label>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php self::render_history(); ?>
			<?php self::render_credits(); ?>
		</div>
		<?php
	}

	/**
	 * Artwork attribution, required by the icon's Flaticon licence.
	 */
	public static function render_credits(): void {
		?>
		<p class="woobert-credit">
			<a href="https://www.flaticon.com/free-icons/funny-owl" title="funny owl icons" target="_blank" rel="noreferrer">Funny owl icons created by agustrisana - Flaticon</a>
		</p>
		<?php
	}

	/**
	 * Render the store-wide audit log: every Woobert command any admin has run,
	 * newest first, with the request, outcome, arguments, and any error.
	 */
	public static function render_history(): void {
		$entries = Woobert_History::all();
		?>
		<h2><?php esc_html_e( 'Command history', 'woobert' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Every command run through Woobert, across all admins. Newest first.', 'woobert' ); ?>
		</p>
		<?php if ( empty( $entries ) ) : ?>
			<p><?php esc_html_e( 'No commands recorded yet.', 'woobert' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'woobert' ); ?></th>
					<th><?php esc_html_e( 'User', 'woobert' ); ?></th>
					<th><?php esc_html_e( 'Query', 'woobert' ); ?></th>
					<th><?php esc_html_e( 'Request', 'woobert' ); ?></th>
					<th><?php esc_html_e( 'Result', 'woobert' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$names = array();
				foreach ( $entries as $entry ) :
					$user_id = $entry['user_id'];
					if ( ! isset( $names[ $user_id ] ) ) {
						$user              = $user_id ? get_userdata( $user_id ) : null;
						$names[ $user_id ] = $user ? $user->display_name : sprintf( '#%d', $user_id );
					}
					$method = $entry['request']['method'] ?? '';
					$route  = $entry['request']['route'] ?? '';
					$params = $entry['request']['params'] ?? array();
					?>
					<tr>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $entry['time'] ) ); ?></td>
						<td><?php echo esc_html( $names[ $user_id ] ); ?></td>
						<td><?php echo $entry['query'] ? esc_html( $entry['query'] ) : '<em>&mdash;</em>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal fallback markup. ?></td>
						<td>
							<code><?php echo esc_html( trim( $method . ' ' . $route ) ); ?></code>
							<?php if ( ! empty( $params ) ) : ?>
								<details>
									<summary><?php esc_html_e( 'Arguments', 'woobert' ); ?></summary>
									<pre style="white-space:pre-wrap;margin:6px 0 0;"><?php echo esc_html( (string) wp_json_encode( $params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
								</details>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $entry['ok'] ) : ?>
								<span style="color:#207b28;font-weight:600;"><?php esc_html_e( 'Success', 'woobert' ); ?></span>
							<?php else : ?>
								<span style="color:#b32d2e;font-weight:600;">
									<?php
									/* translators: %d: HTTP status code. */
									echo esc_html( $entry['status'] ? sprintf( __( 'Failed (%d)', 'woobert' ), $entry['status'] ) : __( 'Failed', 'woobert' ) );
									?>
								</span>
								<?php if ( $entry['error'] ) : ?>
									<div class="description"><?php echo esc_html( $entry['error'] ); ?></div>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
