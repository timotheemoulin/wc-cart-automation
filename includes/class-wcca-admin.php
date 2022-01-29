<?php

namespace WCCA;

use Exception;
use WP_Query;

/**
 * Handles the WCCA admin features (menu, styles, options, ...).
 */
class WCCA_Admin {
	public function __construct() {
		// options menu
		add_action( 'admin_menu', [ __CLASS__, 'admin_menu' ] );

		// Custom style
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );
	}


	public static function admin_menu(): void {
		add_submenu_page(
			'woocommerce-marketing',
			__( 'Automation options', WCCA_PLUGIN_NAME ),
			__( 'Automation options', WCCA_PLUGIN_NAME ),
			'manage_woocommerce',
			'wcca-options',
			[ WCCA_Admin::class, 'dashboard' ],
			'wcca-10'
		);
	}

	public static function admin_enqueue_scripts(): void {
		wp_enqueue_style( 'wcca-admin', wcca()->plugin_url() . '/css/admin.css' );
	}

	/**
	 * @throws Exception
	 */
	public static function dashboard(): void {
		printf( '<h1>%s</h1>', __( 'Cart Automation configuration', WCCA_PLUGIN_NAME ) );
		echo '<h2>Stats</h2>';

		$active   = ( new WP_Query( [ 'post_type' => 'wcca', 'post_status' => 'publish' ] ) )->post_count;
		$inactive = ( new WP_Query( [ 'post_type' => 'wcca', 'post_status' => [ 'draft', 'pending' ] ] ) )->post_count;
		$future   = ( new WP_Query( [ 'post_type' => 'wcca', 'post_status' => 'future' ] ) )->post_count;

		printf( '<p>%s<br>%s<br>%s</p>',
			sprintf( _x( 'Active automation : %s', WCCA_PLUGIN_NAME, $active ), $active ),
			sprintf( _x( 'Inactive automation : %s', WCCA_PLUGIN_NAME, $inactive ), $inactive ),
			sprintf( _x( 'Scheduled automation : %s', WCCA_PLUGIN_NAME, $future ), $future ),
		);
	}

	/**
	 * @param string $option
	 * @param string $label
	 * @param string $type
	 * @param mixed  $default
	 * @param bool   $single
	 *
	 * @throws Exception
	 */
	public static function the_custom_field_admin( string $option, string $label, string $type = 'text', $default = '', bool $single = true ): void {
		$value = $_REQUEST[ 'wcca_' . $option ] ?? get_post_meta( get_the_ID(), 'wcca_' . $option, $single ) ?: $default;

		switch ( $type ) {
			case 'text':
			case 'url':
			case 'phone':
			case 'email':
			case 'number':
				$html = sprintf( '<input type="%s" name="wcca_%s" id="wcca_%s" value="%s">', $type, $option, $option, $value );
				break;
			case 'textarea':
				$html = sprintf( '<textarea name="wcca_%s">%s</textarea>', $option, $value );
				break;
			default:
				throw new Exception( sprintf( __( 'Unrecognized option field of type %s.' ), $type ) );
		}

		printf( '<div class="wcca-field"><label for="wcca_%s">%s</label>%s</div>', $option, $label, $html );
	}
}
