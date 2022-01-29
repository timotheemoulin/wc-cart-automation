<?php

namespace WCCA;

use Exception;
use WP_Post;

/**
 * This class handles the CPT management (admin registration, fields, and every related calculation).
 */
class Cpt_Automation {
	private static array $fields = [];

	public function __construct() {
		static::add_field( 'token', __( 'Unique token' ) );

		// WCCA Custom Post Type
		add_action( 'init', [ __CLASS__, 'register_cpt' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post_wcca', [ __CLASS__, 'save_post' ], 10, 3 );
	}

	/**
	 * @param string $option
	 * @param string $label
	 * @param string $type
	 * @param mixed  $default
	 */
	private static function add_field( string $option, string $label, string $type = 'text', $default = '' ): void {
		self::$fields[ $option ] = [
			'name'    => $option,
			'label'   => $label,
			'type'    => $type,
			'default' => $default,
		];
	}

	/**
	 * Save posted values.
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	public static function save_post( int $post_ID, WP_Post $post, bool $update ): void {
		foreach ( static::$fields as $field => $data ) {
			update_post_meta( $post_ID, 'wcca_' . $field, esc_html( $_REQUEST[ 'wcca_' . $field ] ?? null ) );
		}
	}

	/**
	 * Register the CPT.
	 */
	public static function register_cpt(): void {
		register_post_type( 'wcca', [
			'labels'        => [
				'name'               => _x( 'Automations', 'post type general name' ),
				'singular_name'      => _x( 'Automation', 'post type singular name' ),
				'add_new'            => _x( 'Add New', 'automation' ),
				'add_new_item'       => __( 'Add New Automation' ),
				'edit_item'          => __( 'Edit Automation' ),
				'new_item'           => __( 'New Automation' ),
				'view_item'          => __( 'View Automation' ),
				'view_items'         => __( 'View Automations' ),
				'search_items'       => __( 'Search Automations' ),
				'not_found'          => __( 'No automations found.' ),
				'not_found_in_trash' => __( 'No automations found in Trash.' ),
				'all_items'          => __( 'Automations' ),
			],
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => 'woocommerce-marketing',
			'menu_position' => 'wcca-20',
			'show_in_rest'  => false,
			'rewrite'       => false,
			'supports'      => [ 'title' ],
		] );
	}

	/**
	 * Register the CPT meta box
	 */
	public static function add_meta_boxes(): void {
		add_meta_box( 'wcca-fields', __( 'Configuration', WCCA_PLUGIN_NAME ), [ __CLASS__, 'render_meta_box_fields' ], 'wcca' );
	}

	/**
	 * @throws Exception
	 */
	public static function render_meta_box_fields() {
		foreach ( static::$fields as $field ) {
			WCCA_Admin::the_custom_field_admin( $field[ 'name' ], $field[ 'label' ], $field[ 'type' ], $field[ 'default' ] );
		}
	}

}
