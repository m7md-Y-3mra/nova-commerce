<?php
/**
 * Create the Log in (/login/) and Create account (/register/) pages.
 *
 * Idempotent: finds each page by its stored ID, then by slug, and only creates
 * it when missing. Run by seed.php on a fresh install and by the alwaysdata
 * installer on every deploy (`wp eval-file seed/account-pages.php`), because the
 * live site was seeded before these pages existed.
 *
 * @package NovaStoreSeed
 */

defined( 'ABSPATH' ) || exit;

foreach ( array(
	'nova_store_login_page_id'    => array( 'login', 'Log in', 'تسجيل الدخول', '[nova_login_form]' ),
	'nova_store_register_page_id' => array( 'register', 'Create account', 'إنشاء حساب', '[nova_register_form]' ),
) as $nova_option => list( $nova_slug, $nova_title, $nova_ar_title, $nova_shortcode ) ) {
	$nova_id = (int) get_option( $nova_option );
	if ( $nova_id <= 0 || 'page' !== get_post_type( $nova_id ) ) {
		$nova_page = get_page_by_path( $nova_slug );
		$nova_id   = $nova_page ? (int) $nova_page->ID : 0;
	}
	if ( $nova_id <= 0 ) {
		$nova_id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $nova_slug,
				'post_title'   => $nova_title,
				'post_content' => '<!-- wp:shortcode -->' . $nova_shortcode . '<!-- /wp:shortcode -->',
			)
		);
	}
	if ( $nova_id <= 0 ) {
		WP_CLI::error( "Could not create the {$nova_slug} page." );
	}
	update_option( $nova_option, $nova_id );
	update_post_meta( $nova_id, '_nova_ar_title', $nova_ar_title );
}
WP_CLI::log( 'Account pages ready (login, register).' );
