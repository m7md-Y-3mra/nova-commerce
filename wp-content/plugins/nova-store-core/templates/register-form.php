<?php
/**
 * Create account form for the /register/ page.
 *
 * Same fields, names and hooks as the register column of WooCommerce's
 * myaccount/form-login.php, so WC_Form_Handler::process_registration() handles
 * it, Account_Hooks adds the name fields and WooCommerce adds the privacy note.
 *
 * Rendered by Account_Pages::render_register().
 *
 * @package NovaStoreCore
 *
 * @var array{login_url: string} $args
 */

defined( 'ABSPATH' ) || exit;

// These are WooCommerce's own form hooks, fired so other code (Account_Hooks, privacy note) still attaches.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

// phpcs:disable WordPress.Security.NonceVerification.Missing -- refills fields after a failed submit; WooCommerce verified the nonce.
$nova_username = isset( $_POST['username'] ) && is_string( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
$nova_email    = isset( $_POST['email'] ) && is_string( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Missing
?>
<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >

	<?php do_action( 'woocommerce_register_form_start' ); ?>

	<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo esc_attr( $nova_username ); ?>" required aria-required="true" />
		</p>
	<?php endif; ?>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
		<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo esc_attr( $nova_email ); ?>" required aria-required="true" />
	</p>

	<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
			<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woocommerce' ); ?></p>
	<?php endif; ?>

	<?php do_action( 'woocommerce_register_form' ); ?>

	<p class="woocommerce-form-row form-row">
		<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
		<input type="hidden" name="redirect" value="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" />
		<button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Register', 'woocommerce' ); ?></button>
	</p>

	<?php do_action( 'woocommerce_register_form_end' ); ?>

</form>

<p class="nova-auth__switch">
	<?php esc_html_e( 'Already have an account?', 'nova-store-core' ); ?>
	<a href="<?php echo esc_url( $args['login_url'] ); ?>"><?php esc_html_e( 'Log in', 'nova-store-core' ); ?></a>
</p>
