<?php
/**
 * Storefront language selection (English / Arabic).
 *
 * How the language is chosen, per request:
 *   1. `?lang=en|ar` in the URL (shareable links, also works for the REST API),
 *   2. otherwise the `nova_lang` cookie remembered from a previous choice,
 *   3. otherwise English.
 *
 * The choice is applied through the core `locale` filter, so WordPress core,
 * WooCommerce (official language pack), the theme and this plugin all load
 * their Arabic translations and WordPress switches to RTL automatically.
 * wp-admin is never affected; staff keep their own profile language.
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Language detection and switcher data.
 */
class Languages {

	const COOKIE  = 'nova_lang';
	const QUERY   = 'lang';
	const DEFAULT = 'en';

	/**
	 * Resolved language code for this request.
	 *
	 * @var string|null
	 */
	private static $current = null;

	/**
	 * Supported languages. Keys are the public codes used in URLs and cookies.
	 *
	 * @return array<string, array{locale: string, native: string, hreflang: string}>
	 */
	public static function available(): array {
		return array(
			'en' => array(
				'locale'   => 'en_US',
				'native'   => 'English',
				'hreflang' => 'en',
			),
			'ar' => array(
				'locale'   => 'ar',
				'native'   => 'العربية',
				'hreflang' => 'ar',
			),
		);
	}

	/**
	 * Register filters that must exist before WordPress loads translations.
	 * Called directly from the main plugin file (before `plugins_loaded`).
	 */
	public static function register_early(): void {
		add_filter( 'locale', array( __CLASS__, 'filter_locale' ) );
	}

	/**
	 * Register the remaining hooks.
	 */
	public function register(): void {
		add_action( 'init', array( __CLASS__, 'remember_choice' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_hreflang' ), 2 );
		add_filter( 'nova_store_languages', array( __CLASS__, 'switcher_items' ) );

		// Keep cached cart fragments per language so the header never shows the other language.
		add_filter(
			'woocommerce_cart_fragment_name',
			static fn( $name ) => $name . '_' . self::current()
		);
	}

	/**
	 * Language code for the current request ("en" or "ar").
	 */
	public static function current(): string {
		if ( null !== self::$current ) {
			return self::$current;
		}

		$languages = self::available();
		$code      = self::DEFAULT;

		// Read-only preference, not a privileged action: no nonce needed (like a currency switcher).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::QUERY ] ) && is_string( $_GET[ self::QUERY ] ) ) {
			$requested = sanitize_key( wp_unslash( $_GET[ self::QUERY ] ) );
			if ( isset( $languages[ $requested ] ) ) {
				$code = $requested;
			}
		} elseif ( isset( $_COOKIE[ self::COOKIE ] ) && is_string( $_COOKIE[ self::COOKIE ] ) ) {
			$saved = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			if ( isset( $languages[ $saved ] ) ) {
				$code = $saved;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		self::$current = $code;

		return $code;
	}

	/**
	 * True on storefront requests rendered in Arabic.
	 */
	public static function is_arabic(): bool {
		return 'ar' === self::current() && ! is_admin();
	}

	/**
	 * Filter: storefront locale. Admin screens keep the site/user locale.
	 *
	 * @param string $locale Locale determined by WordPress.
	 * @return string
	 */
	public static function filter_locale( $locale ) {
		if ( is_admin() ) {
			return $locale;
		}
		return self::available()[ self::current() ]['locale'];
	}

	/**
	 * Persist an explicit `?lang=` choice for one year.
	 */
	public static function remember_choice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only; value validated in current().
		if ( is_admin() || ! isset( $_GET[ self::QUERY ] ) || headers_sent() ) {
			return;
		}

		$code  = self::current();
		$saved = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( $code === $saved ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$code,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $code;
	}

	/**
	 * URL of the current page in another language.
	 *
	 * Built from the configured home URL (never the Host header) plus the request path.
	 *
	 * @param string $code Language code.
	 */
	public static function url_for( string $code ): string {
		$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$parts   = wp_parse_url( home_url() );
		$origin  = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );

		return add_query_arg( self::QUERY, $code, $origin . $request );
	}

	/**
	 * Filter callback: data for the theme's language switcher.
	 *
	 * @return array<string, array{native: string, url: string, current: bool, hreflang: string}>
	 */
	public static function switcher_items(): array {
		$items = array();
		foreach ( self::available() as $code => $language ) {
			$items[ $code ] = array(
				'native'   => $language['native'],
				'hreflang' => $language['hreflang'],
				'url'      => self::url_for( $code ),
				'current'  => self::current() === $code,
			);
		}
		return $items;
	}

	/**
	 * <link rel="alternate" hreflang> tags so search engines see both versions.
	 */
	public static function print_hreflang(): void {
		// Cart, checkout and account URLs are private (order keys) and not indexable.
		if ( is_cart() || is_checkout() || is_account_page() || is_404() ) {
			return;
		}
		foreach ( self::available() as $code => $language ) {
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
				esc_attr( $language['hreflang'] ),
				esc_url( self::url_for( $code ) )
			);
		}
		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( remove_query_arg( self::QUERY, self::url_for( self::DEFAULT ) ) )
		);
	}
}
