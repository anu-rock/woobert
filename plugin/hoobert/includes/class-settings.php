<?php
/**
 * Settings page + typed getters for the Hoobert inference connection.
 *
 * The endpoint URL and API key are stored as plugin options, set under
 * WooCommerce -> Hoobert. That settings page is the single source of config.
 *
 * @package Hoobert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hoobert_Settings {

	const OPTION_GROUP = 'hoobert_settings';
	const OPTION_NAME  = 'hoobert_options';

	/**
	 * Wire up the admin menu + registered settings.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
	}

	/**
	 * Offer suggested wording for the site's privacy policy, since the plugin
	 * sends the merchant's typed request to a third-party inference endpoint.
	 */
	public static function privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text for stores using Hoobert.', 'hoobert' ) . '</p>'
			. '<p>' . esc_html__( 'When a store administrator runs a command through Hoobert, the text they typed and the id of the order or product on the screen they are viewing are sent to the inference endpoint configured under WooCommerce -> Hoobert, so that the request can be matched to a WooCommerce action. No customer records, order contents, or site credentials are sent. Requests are only made when an administrator runs a command. Each command an administrator runs is recorded in a log stored on this site.', 'hoobert' ) . '</p>';

		wp_add_privacy_policy_content( 'Hoobert', wp_kses_post( $content ) );
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
			__( 'Hoobert', 'hoobert' ),
			__( 'Hoobert', 'hoobert' ),
			'manage_woocommerce',
			'hoobert',
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
			<h1 class="hoobert-title">
				<img src="<?php echo esc_url( HOOBERT_URL . 'assets/hoobert-owl.png' ); ?>" alt="" width="40" height="40" />
				<?php esc_html_e( 'Hoobert', 'hoobert' ); ?>
			</h1>
			<style>
				.hoobert-title { display: flex; align-items: center; gap: 10px; }
				.hoobert-credit { margin-top: 32px; color: #646970; font-size: 12px; }
			</style>
			<div class="card">
				<p><strong><?php esc_html_e( 'Run your whole store from one prompt.', 'hoobert' ); ?></strong></p>
				<p>
					<?php esc_html_e( 'Type what you want in plain English, like "refund order 1042", "add a Large/Red variation to this product at 54.99", or "who are my top customers this month". Hoobert turns it into the right WooCommerce action and runs it, under your own admin session. No menu hunting, no keys in the browser.', 'hoobert' ); ?>
				</p>
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: %s: link to the Fernfly website. */
							__( 'Hoobert is powered by <strong>Fern</strong>, a family of tiny function-calling models by %s. The model maps your request to a WooCommerce REST API call; the plugin runs it server-side and shows you the result.', 'hoobert' ),
							array( 'strong' => array() )
						),
						'<a href="' . esc_url( 'https://fernfly.com' ) . '" target="_blank" rel="noreferrer">Fernfly</a>'
					);
					?>
				</p>
			</div>

			<h2><?php esc_html_e( 'Connect a model', 'hoobert' ); ?></h2>
			<p><?php esc_html_e( 'Hoobert needs a Fernfly project to read your requests. Setting one up takes a few minutes:', 'hoobert' ); ?></p>
			<ol>
				<li>
					<?php
					printf(
						wp_kses(
							/* translators: %s: link to the Fernfly sign-up page. */
							__( 'Create a free account at %s and start a new project.', 'hoobert' ),
							array()
						),
						'<a href="' . esc_url( 'https://fernfly.com' ) . '" target="_blank" rel="noreferrer">fernfly.com</a>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Choose the WooCommerce template in the new project wizard for a zero-configuration flow.', 'hoobert' ); ?></li>
				<li><?php esc_html_e( 'Train the project, then deploy it. Fernfly generates the training data for you.', 'hoobert' ); ?></li>
				<li><?php esc_html_e( 'Copy the project\'s infer URL and API key into the fields below, and save.', 'hoobert' ); ?></li>
			</ol>
			<p class="description">
				<?php
				printf(
					wp_kses(
						/* translators: 1: terms of service link, 2: privacy policy link. */
						__( 'Using Hoobert sends your typed request and the current screen\'s order or product id to Fernfly. Nothing else leaves your store, and requests are only sent when you run a command. See Fernfly\'s %1$s and %2$s.', 'hoobert' ),
						array()
					),
					'<a href="' . esc_url( 'https://fernfly.com/terms-of-service' ) . '" target="_blank" rel="noreferrer">' . esc_html__( 'terms of service', 'hoobert' ) . '</a>',
					'<a href="' . esc_url( 'https://fernfly.com/privacy-policy' ) . '" target="_blank" rel="noreferrer">' . esc_html__( 'privacy policy', 'hoobert' ) . '</a>'
				);
				?>
			</p>

			<p><?php esc_html_e( 'Once both fields are saved, press ⌘K / Ctrl-K anywhere in wp-admin and pick "Ask Hoobert".', 'hoobert' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hoobert-endpoint"><?php esc_html_e( 'Inference endpoint URL', 'hoobert' ); ?></label></th>
						<td><input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[endpoint]" id="hoobert-endpoint" type="url" class="regular-text" placeholder="https://fernfly.com/api/p/27/infer" value="<?php echo esc_attr( $opts['endpoint'] ?? '' ); ?>" /></td>
					</tr>
					<?php $has_key = '' !== self::api_key(); ?>
					<tr>
						<th scope="row"><label for="hoobert-key"><?php esc_html_e( 'API key', 'hoobert' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]" id="hoobert-key" type="password" class="regular-text" autocomplete="off" value=""
								placeholder="<?php echo esc_attr( $has_key ? __( 'Saved. Enter a new key to replace it.', 'hoobert' ) : __( 'Paste your project API key', 'hoobert' ) ); ?>" />
							<?php if ( $has_key ) : ?>
								<p class="description">
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key_clear]" value="1" />
										<?php esc_html_e( 'Remove the saved key', 'hoobert' ); ?>
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
		<p class="hoobert-credit">
			<a href="https://www.flaticon.com/free-icons/funny-owl" title="funny owl icons" target="_blank" rel="noreferrer">Funny owl icons created by agustrisana - Flaticon</a>
		</p>
		<?php
	}

	/**
	 * Render the store-wide audit log: every Hoobert command any admin has run,
	 * newest first, with the request, outcome, arguments, and any error.
	 */
	public static function render_history(): void {
		$entries = Hoobert_History::all();
		?>
		<h2><?php esc_html_e( 'Command history', 'hoobert' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Every command run through Hoobert, across all admins. Newest first.', 'hoobert' ); ?>
		</p>
		<?php if ( empty( $entries ) ) : ?>
			<p><?php esc_html_e( 'No commands recorded yet.', 'hoobert' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'hoobert' ); ?></th>
					<th><?php esc_html_e( 'User', 'hoobert' ); ?></th>
					<th><?php esc_html_e( 'Query', 'hoobert' ); ?></th>
					<th><?php esc_html_e( 'Request', 'hoobert' ); ?></th>
					<th><?php esc_html_e( 'Result', 'hoobert' ); ?></th>
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
									<summary><?php esc_html_e( 'Arguments', 'hoobert' ); ?></summary>
									<pre style="white-space:pre-wrap;margin:6px 0 0;"><?php echo esc_html( (string) wp_json_encode( $params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
								</details>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $entry['ok'] ) : ?>
								<span style="color:#207b28;font-weight:600;"><?php esc_html_e( 'Success', 'hoobert' ); ?></span>
							<?php else : ?>
								<span style="color:#b32d2e;font-weight:600;">
									<?php
									/* translators: %d: HTTP status code. */
									echo esc_html( $entry['status'] ? sprintf( __( 'Failed (%d)', 'hoobert' ), $entry['status'] ) : __( 'Failed', 'hoobert' ) );
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
