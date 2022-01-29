<?php

namespace WCCA;

use WC_Order;

/**
 * Main class. Init everything that needs to be done and is used as a global accessor with wcca().
 */
class WCCA {
	protected static ?WCCA $_instance = null;

	public function __construct() {
		// Init the admin
		new WCCA_Admin();

		// Handles CPT init
		new Cpt_Automation();
	}

	public static function instance(): self {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url(): string {
		return untrailingslashit( plugins_url( '/', WCCA_PLUGIN_FILE ) );
	}

	/**
	 * Should the current cart be restored after the WCCA checkout?
	 *
	 * @return false|mixed|void
	 */
	public function should_restore_cart_after_checkout() {
		return get_option( 'wcca_restore_cart_after_checkout' );
	}

	/**
	 * Keep the old cart for a maximum of 24 hours (or as defined).
	 *
	 * @return false|mixed|void
	 */
	public function keep_old_cart_for_hours() {
		return get_option( 'wcca_keep_old_cart_for_hours', 24 );
	}

	/**
	 * Should the current cart be merged with the WCCA?
	 *
	 * @param int $wcca_ID
	 *
	 * @return bool
	 */
	public function wcca_should_add_to_current_cart( int $wcca_ID ): bool {
		return get_post_meta( $wcca_ID, 'wcca_add_to_current_cart', true );
	}

	/**
	 * Add the current user to the list of openings.
	 *
	 * @param ?int $wcca_ID
	 */
	public function add_customer_wcca_opening( ?int $wcca_ID = null ) {
		if ( empty( $wcca_ID ) ) {
			if ( empty( $wcca_ID = ( $_COOKIE[ 'wcca' ] ?? null ) ) ) {
				// something wrong happened and the trace of the user has been lost
				return;
			}
		}

		$openings = get_option( 'wcca_openings_' . $wcca_ID, [] );
		if ( empty( $current_user = WC()->session->get_customer_unique_id() ) ) {
			// we should not get here, but just in case, consider that is some anonymous visitor
			$current_user = 0;
		}

		$openings[ $current_user ]   = $openings[ $current_user ] ?? [];
		$openings[ $current_user ][] = time();

		setcookie( 'wcca', $wcca_ID );
		$_COOKIE[ 'wcca' ] = $wcca_ID;

		update_option( 'wcca_openings_' . $wcca_ID, $openings );
	}

	/**
	 * Add the current order to the list of orders.
	 *
	 * @param WC_Order|null $order
	 * @param ?int          $wcca_ID
	 */
	public function add_order_to_wcca( WC_Order $order = null, ?int $wcca_ID = null ) {
		if ( empty( $wcca_ID ) ) {
			if ( empty( $wcca_ID = ( $_COOKIE[ 'wcca' ] ?? null ) ) ) {
				// something wrong happened and the trace of the user has been lost
				return;
			}
		}

		setcookie( 'wcca', null );
		$_COOKIE[ 'wcca' ] = null;

		$orders = get_option( 'wcca_orders_' . $wcca_ID, [] );

		$orders[ $order->get_id() ] = time();

		update_option( 'wcca_orders_' . $wcca_ID, $orders );
	}
}
