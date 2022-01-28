<?php

namespace WCCA;

class WC_Cart_Automation {
	protected static ?WC_Cart_Automation $_instance = null;

	public static function instance(): self {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public static function admin_menu(): void {
		add_submenu_page(
			'woocommerce-marketing',
			__( 'Cart automation', WCCA_PLUGIN_NAME ),
			__( 'Cart automation', WCCA_PLUGIN_NAME ),
			'manage_woocommerce',
			'wcca',
			[ WCCA_Admin::class, 'dashboard' ]
		);
	}

	public function init() {
		add_action( 'admin_menu', [ __CLASS__, 'admin_menu' ] );
	}
}
