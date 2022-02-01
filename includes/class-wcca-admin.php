<?php

namespace WCCA;

use Exception;
use WP_Query;

/**
 * Handles the WCCA admin features (menu, styles, options, ...).
 */
class WCCA_Admin {
	public function __construct() {
		// dashboard
		add_action( 'wp_dashboard_setup', [ __CLASS__, 'wp_dashboard_setup' ] );

		// Custom style
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );

		// Add stats in the admin list
		add_filter( 'manage_wcca_posts_columns', [ __CLASS__, 'manage_wcca_posts_columns' ] );
		add_action( 'manage_wcca_posts_custom_column', [ __CLASS__, 'manage_wcca_posts_custom_column' ], 10, 2 );
	}

	public static function manage_wcca_posts_custom_column( $column, $post_id ): void {
		if ( 'openings' === $column ) {
			$openings = get_option( 'wcca_openings_' . $post_id, [] );
			$count    = count( $openings );
			printf( _x( '<span title="unique %s">u : %s</span>', 'unique', WCCA_PLUGIN_NAME ), $count, $count );
			echo ' / ';
			$count = count( $openings, COUNT_RECURSIVE );
			printf( _x( '<span title="all %s">a : %s</span>', 'all', WCCA_PLUGIN_NAME ), $count, $count );
		} else if ( 'orders' === $column ) {
			$orders = get_option( 'wcca_orders_' . $post_id, [] );
			echo count( $orders );
		}
	}

	public static function manage_wcca_posts_columns( array $columns ): array {
		$columns[ 'openings' ] = __( 'Openings', WCCA_PLUGIN_NAME );
		$columns[ 'orders' ]   = __( 'Orders', WCCA_PLUGIN_NAME );

		return $columns;
	}


	/**
	 * Enqueue the admin styles.
	 */
	public static function admin_enqueue_scripts(): void {
		wp_enqueue_style( 'wcca-admin', wcca()->plugin_url() . '/css/admin.css' );
	}

	public static function wp_dashboard_setup(): void {
		add_meta_box(
			'wcca-stats',
			__( 'Cart automation', WCCA_PLUGIN_NAME ),
			[ __CLASS__, 'dashboard_render_stats' ],
			'dashboard',
			'normal',
			'high'
		);
	}

	/**
	 * @throws Exception
	 */
	public static function dashboard_render_stats(): void {
		printf( '<h1>%s</h1>', __( 'Cart Automation configuration', WCCA_PLUGIN_NAME ) );

		$active = ( new WP_Query( [ 'post_type' => 'wcca', 'post_status' => 'publish' ] ) )->post_count;
		printf( '<p>' . _n( 'Active automation : %s', 'Active automations : %s', $active, WCCA_PLUGIN_NAME ) . '</p>', $active );

		$stati = get_post_stati();
		unset( $stati[ 'auto-draft' ], $stati[ 'revision' ], $stati[ 'publish' ] );
		$inactive = ( new WP_Query( [ 'post_type' => 'wcca', 'post_status' => $stati ] ) )->post_count;

		printf( '<p>' . _n( 'Inactive automation : %s', 'Inactive automations : %s', $inactive, WCCA_PLUGIN_NAME ) . '</p>', $inactive );

		printf( '<p><a href="%s">%s</a></p>', admin_url( 'edit.php?post_type=wcca' ), __( 'View Automations', WCCA_PLUGIN_NAME ) );
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
				$default_value = esc_attr( $default_value );

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
				$default_value = esc_attr( $default_value );

				$html = sprintf( '<textarea name="wcca_%s">%s</textarea>', $option, $default_value );
				break;
			case 'radio':
			case 'checkbox':
			case 'select':
			case 'select2':
				$single        = $args[ 'single' ] ?? true;
				$default_value = $_REQUEST[ 'wcca_' . $option ] ?? get_post_meta( get_the_ID(), 'wcca_' . $option, $single );
				$default_value = esc_attr( $default_value );

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
				if ( in_array( $type, [ 'checkbox', 'radio' ] ) ) {
					if ( $single ) {
						$type = 'radio';
					} else {
						$type = 'checkbox';
					}

					foreach ( $choices as $key => $value ) {
						$selected = in_array( $key, (array) $default_value );
						$html     .= sprintf(
							'<label for="wcca_%s_%s"><input type="%s" name="wcca_%s%s%s" id="wcca_%s_%s" value="%s" %s>&nbsp;%s</label>',
							$option,
							$key,
							$type,
							$single ? '' : '[',
							$option,
							$single ? '' : ']',
							$option,
							$key,
							$key,
							( $selected ? 'checked' : '' ),
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
						$html .= '<option>' . __( '- Select -', WCCA_PLUGIN_NAME ) . '</option>';
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
