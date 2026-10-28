<?php
/**
 * Separate Log in (/login/) and Create account (/register/) pages.
 *
 * | Hook                           | Type      | Runs                                                   |
 * |--------------------------------|-----------|--------------------------------------------------------|
 * | [nova_login_form]              | shortcode | Content of the Log in page                             |
 * | [nova_register_form]           | shortcode | Content of the Create account page                     |
 * | template_redirect              | action    | Sends visitors to the right page for their login state |
 * | woocommerce_is_account_page    | filter    | Treats both pages as account pages (scripts, no-cache) |
 * | nova_store_account_url         | filter    | Header account icon: Log in page when logged out       |
 *
 * WooCommerce still processes both forms (WC_Form_Handler on wp_loaded): nonce,
 * password check, account creation and login. The pages only render the forms,
 * which keep WooCommerce's field names and hooks.
 *
 * The pages are created by seed/account-pages.php; their IDs live in options.
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the login/register page hooks.
 */
class Account_Pages {

	const OPTION_LOGIN    = 'nova_store_login_page_id';
	const OPTION_REGISTER = 'nova_store_register_page_id';

	/**
	 * Hook into WordPress and WooCommerce.
	 */
	public function register(): void {
		add_shortcode( 'nova_login_form', array( $this, 'render_login' ) );
		add_shortcode( 'nova_register_form', array( $this, 'render_register' ) );
		add_action( 'template_redirect', array( $this, 'redirect' ) );
		add_filter( 'woocommerce_is_account_page', array( $this, 'is_account_page' ) );
		add_filter( 'nova_store_account_url', array( $this, 'account_url' ) );
	}

	/**
	 * Page ID for "login" or "register" (0 when the page is missing).
	 *
	 * @param string $which "login" or "register".
	 */
	public static function page_id( string $which ): int {
		$id = (int) get_option( 'login' === $which ? self::OPTION_LOGIN : self::OPTION_REGISTER );
		return ( $id > 0 && 'publish' === get_post_status( $id ) ) ? $id : 0;
	}

	/**
	 * Page URL, or My Account when the page is missing (that page still has both forms).
	 *
	 * @param string $which "login" or "register".
	 */
	public static function url( string $which ): string {
		$id = self::page_id( $which );
		return $id ? (string) get_permalink( $id ) : wc_get_page_permalink( 'myaccount' );
	}

	/**
	 * Log in page content.
	 */
	public function render_login(): string {
		if ( is_user_logged_in() ) {
			return '';
		}
		// WooCommerce prints this inside the My Account shortcode, which logged-out visitors no longer see.
		if ( ! empty( $_GET['password-reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
			wc_add_notice( __( 'Your password has been reset successfully.', 'woocommerce' ) );
		}
		return $this->render( 'login-form.php', array( 'register_url' => self::page_id( 'register' ) ? self::url( 'register' ) : '' ) );
	}

	/**
	 * Create account page content.
	 */
	public function render_register(): string {
		if ( is_user_logged_in() ) {
			return '';
		}
		if ( 'yes' !== get_option( 'woocommerce_enable_myaccount_registration' ) ) {
			return '<div class="woocommerce"><p>' . esc_html__( 'Registration is closed.', 'nova-store-core' ) . '</p></div>';
		}
		return $this->render( 'register-form.php', array( 'login_url' => self::url( 'login' ) ) );
	}

	/**
	 * Logged-in visitors skip the forms; logged-out visitors of My Account land on Log in.
	 *
	 * Only GET requests: WooCommerce has already handled any POSTed form by now,
	 * and a failed one must render its error on the same page.
	 */
	public function redirect(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'get' !== $method || ! is_page() ) {
			return;
		}

		$page = (int) get_queried_object_id();
		if ( is_user_logged_in() ) {
			if ( $page && in_array( $page, array( self::page_id( 'login' ), self::page_id( 'register' ) ), true ) ) {
				wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
				exit;
			}
			return;
		}

		// Lost/reset password stays on My Account (WooCommerce's endpoint).
		if ( wc_get_page_id( 'myaccount' ) === $page && self::page_id( 'login' ) && ! is_wc_endpoint_url( 'lost-password' ) ) {
			$url = self::url( 'login' );
			if ( ! empty( $_GET['password-reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
				$url = add_query_arg( 'password-reset', 'true', $url );
			}
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Both pages count as account pages: password strength script, no-cache
	 * headers, the woocommerce-account body class and no hreflang links.
	 *
	 * @param bool $is_account Whether WooCommerce already thinks so.
	 */
	public function is_account_page( $is_account ): bool {
		if ( $is_account ) {
			return true;
		}
		$ids = array_filter( array( self::page_id( 'login' ), self::page_id( 'register' ) ) );
		return $ids && is_page( $ids );
	}

	/**
	 * Header account link: Log in page for visitors, My Account once logged in.
	 *
	 * @param string $url Default URL (My Account).
	 */
	public function account_url( $url ): string {
		return is_user_logged_in() ? (string) $url : self::url( 'login' );
	}

	/**
	 * Render a form template inside the .woocommerce wrapper, notices first.
	 *
	 * @param string $template File in templates/.
	 * @param array  $args     Read by the template as $args.
	 */
	private function render( string $template, array $args ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is read by the template.
		ob_start();
		echo '<div class="woocommerce nova-auth">';
		woocommerce_output_all_notices();
		include NOVA_STORE_CORE_DIR . 'templates/' . $template;
		echo '</div>';
		return (string) ob_get_clean();
	}
}
