<?php
/**
 * Log in form for the /login/ page.
 *
 * Same fields, names and hooks as WooCommerce's myaccount/form-login.php, so
 * WC_Form_Handler::process_login() handles it. Labels reuse WooCommerce's
 * strings, which its Arabic language pack already translates.
 *
 * Rendered by Account_Pages::render_login().
 *
 * @package NovaStoreCore
 *
 * @var array{register_url: string} $args
 */

defined( 'ABSPATH' ) || exit;

// These are WooCommerce's own form hooks, fired so other code (Account_Hooks, privacy note) still attaches.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

// phpcs:disable WordPress.Security.NonceVerification.Missing -- refills the field after a failed login; WooCommerce verified the nonce.
$nova_username = isset( $_POST['username'] ) && is_string( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Missing

do_action( 'woocommerce_before_customer_login_form' );
?>
<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

	<?php do_action( 'woocommerce_login_form_start' ); ?>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo esc_attr( $nova_username ); ?>" required aria-required="true" />
	</p>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
		<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
	</p>

	<?php do_action( 'woocommerce_login_form' ); ?>

	<p class="form-row">
		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
			<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
		</label>
		<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
		<input type="hidden" name="redirect" value="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" />
		<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php esc_html_e( 'Log in', 'woocommerce' ); ?></button>
	</p>
	<p class="woocommerce-LostPassword lost_password">
		<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
	</p>

	<?php do_action( 'woocommerce_login_form_end' ); ?>

</form>

<?php if ( $args['register_url'] ) : ?>
	<p class="nova-auth__switch">
		<?php esc_html_e( 'New to NOVA?', 'nova-store-core' ); ?>
		<a href="<?php echo esc_url( $args['register_url'] ); ?>"><?php esc_html_e( 'Create an account', 'nova-store-core' ); ?></a>
	</p>
<?php endif; ?>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
