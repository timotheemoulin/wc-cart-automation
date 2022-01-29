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
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', WCCA_PLUGIN_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( WCCA_PLUGIN_FILE ) );
	}
}
