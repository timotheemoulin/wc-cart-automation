<?php

namespace WCCA;

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
	 * @param int $post_ID
	 *
	 * @return bool
	 */
	public function wcca_should_add_to_current_cart( int $post_ID ): bool {
		return get_post_meta( $post_ID, 'wcca_add_to_current_cart', true );
	}
}
