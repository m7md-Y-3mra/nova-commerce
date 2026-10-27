<?php
/**
 * Delivery helpers shared by product, checkout and theme integrations.
 *
 * @package NovaStoreCore
 */

namespace NovaStoreCore;

defined( 'ABSPATH' ) || exit;

/**
 * Pure business rules around shipping and dispatch; no output here.
 */
class Delivery {

	/**
	 * Lowest "minimum order amount" of any enabled Free shipping method.
	 *
	 * Reading it from WooCommerce shipping zones keeps a single source of
	 * truth: the label on the storefront always matches what checkout charges.
	 *
	 * @return float|null Threshold, or null when free shipping is not offered by amount.
	 */
	public static function free_shipping_threshold(): ?float {
		static $threshold = false;

		if ( false !== $threshold ) {
			return $threshold;
		}

		$zones   = \WC_Shipping_Zones::get_zones();
		$zones[] = array( 'zone_id' => 0 );
		$found   = array();

		foreach ( $zones as $zone_data ) {
			$zone = \WC_Shipping_Zones::get_zone( (int) $zone_data['zone_id'] );
			if ( ! $zone ) {
				continue;
			}
			foreach ( $zone->get_shipping_methods( true ) as $method ) {
				if ( 'free_shipping' !== $method->id ) {
					continue;
				}
				$requires = $method->get_option( 'requires' );
				$min      = (float) $method->get_option( 'min_amount', 0 );
				if ( in_array( $requires, array( 'min_amount', 'either' ), true ) && $min > 0 ) {
					$found[] = $min;
				}
			}
		}

		$threshold = $found ? min( $found ) : null;

		return $threshold;
	}

	/**
	 * Estimated dispatch date: N business days (Mon–Fri) from now, in the site timezone.
	 *
	 * @param int|null $days Business days; defaults to the configured setting.
	 */
	public static function estimated_dispatch_date( ?int $days = null ): \DateTimeImmutable {
		$days = $days ?? (int) Settings::get( 'dispatch_days' );
		$date = new \DateTimeImmutable( 'now', wp_timezone() );

		while ( $days > 0 ) {
			$date = $date->modify( '+1 day' );
			if ( (int) $date->format( 'N' ) < 6 ) {
				--$days;
			}
		}

		// Orders placed on a weekend with zero dispatch days still ship Monday.
		while ( (int) $date->format( 'N' ) >= 6 ) {
			$date = $date->modify( '+1 day' );
		}

		return $date;
	}
}
