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
	 * @param array  $args
	 *
	 * @throws Exception
	 */
	public static function the_custom_field_admin( string $option, string $label, string $type = 'text', array $args = [] ): void {
		$required = $args[ 'required' ] ?? false;

		switch ( $type ) {
			case 'text':
			case 'url':
			case 'phone':
			case 'email':
			case 'number':
				$default_value = $_REQUEST[ 'wcca_' . $option ] ?? get_post_meta( get_the_ID(), 'wcca_' . $option, true ) ?: null;

				$html = sprintf(
					'<input type="%s" name="wcca_%s" id="wcca_%s" value="%s" %s>',
					$type,
					$option,
					$option,
					$default_value,
					$required ? 'required' : ''
				);
				break;
			case 'textarea':
				$default_value = $_REQUEST[ 'wcca_' . $option ] ?? get_post_meta( get_the_ID(), 'wcca_' . $option, true ) ?: null;

				$html = sprintf( '<textarea name="wcca_%s">%s</textarea>', $option, $default_value );
				break;
			case 'checkbox':
			case 'select':
			case 'select2':
				$single        = $args[ 'single' ] ?? true;
				$default_value = $_REQUEST[ 'wcca_' . $option ] ?? get_post_meta( get_the_ID(), 'wcca_' . $option, $single ) ?: [];

				if ( $post_type = $args[ 'post_type' ] ?? null ) {
					$query   = new WP_Query( [
						'post_type'           => $post_type,
						'limit'               => - 1,
						'post_status__not_in' => [ 'trash' ],
					] );
					$choices = [];
					foreach ( $query->posts as $product ) {
						$choices[ $product->ID ] = $product->post_title;
					}
				} else {
					$choices = $args[ 'choices' ] ?? [];
				}

				$html = '';
				if ( 'checkbox' === $type ) {
					foreach ( $choices as $key => $value ) {
						$selected = in_array( $key, (array) $default_value );
						$html     .= sprintf(
							'<input type="checkbox" name="wcca_%s%s%s" id="wcca_%s_%s" value="%s" %s><label for="wcca_%s_%s">%s</label>',
							$single ? '' : '[',
							$option,
							$single ? '' : ']',
							$option,
							$key,
							$key,
							( $selected ? 'checked' : '' ),
							$option,
							$key,
							$value
						);
					}
				} else {
					$html .= sprintf( '<select name="wcca_%s%s" id="wcca_%s" %s %s>',
						$option,
						$single ? '' : '[]',
						$option,
						( $args[ 'required' ] ?? false ) ? 'required' : '',
						$single ? '' : 'multiple'
					);

					if ( $single ) {
						$html .= '<option>' . __( 'Select a product', WCCA_PLUGIN_NAME ) . '</option>';
					}

					foreach ( $choices as $key => $value ) {
						$selected = in_array( $key, (array) $default_value );
						$html     .= sprintf(
							'<option value="%s" %s>%s</option>',
							$key,
							( $selected ? 'selected' : '' ),
							$value
						);
					}

					$html .= '</select>';
				}
				break;
			default:
				throw new Exception( sprintf( __( 'Unrecognized option field of type %s.' ), $type ) );
		}

		printf(
			'<div class="wcca-field"><label class="wcca-label" for="wcca_%s">%s%s</label><div class="wcca-input wcca-input-%s">%s</div></div>',
			$option,
			$label,
			$required ? '&nbsp;<sup>*</sup>' : '',
			$type,
			$html
		);
	}
}
