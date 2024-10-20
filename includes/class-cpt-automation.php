<?php

namespace WCCA;

use Exception;
use WC_Coupon;
use WP_Post;
use WP_Query;

/**
 * This class handles the CPT management (admin registration, fields, and every related calculation).
 */
class Cpt_Automation {
  private static array $fields = [];

  public function __construct() {

    $this->configure_fields();

    // WCCA Custom Post Type
    add_action( 'init', [ __CLASS__, 'register_cpt' ] );
    add_action( 'add_meta_boxes_wcca', [ __CLASS__, 'add_meta_boxes' ] );
    add_action( 'edit_form_before_permalink', [ __CLASS__, 'edit_form_before_permalink' ] );
    add_action( 'save_post_wcca', [ __CLASS__, 'save_post' ] );

    // Fill in the cart with some nice stuff
    add_action( 'wp_loaded', [ __CLASS__, 'woocommerce_init' ] );

    // Finalize the process
    add_action( 'woocommerce_pre_payment_complete', [ __CLASS__, 'woocommerce_pre_payment_complete' ] );

    // Store some logs
    add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'woocommerce_checkout_order_created' ] );
  }

  /**
   * Configure the fields used in the admin panels.
   */
  private function configure_fields() {
    static::add_field( 'token', __( 'Unique token', WCCA_PLUGIN_NAME ), 'text', [
      'required' => true,
    ] );

    static::add_field( 'add_to_current_cart', __( 'Merge with the user\'s cart', WCCA_PLUGIN_NAME ), 'radio', [
      'choices' => [
        0 => __( 'Erase current cart' ),
        1 => __( 'Merge both carts' ),
      ],
    ] );

    static::add_field( 'products', __( 'Products to add', WCCA_PLUGIN_NAME ), 'repeater', [
      'sub_fields' => [
        [
          'option' => 'quantity',
          'label'  => __( 'Quantity', WCCA_PLUGIN_NAME ),
          'type'   => 'number',
        ],
        [
          'option' => 'product',
          'label'  => __( 'Product', WCCA_PLUGIN_NAME ),
          'type'   => 'select2',
          'args'   => [
            'post_type' => 'product',
            'single'    => true,
          ],
        ],
      ],
    ] );

    static::add_field( 'redirect', __( 'Redirect URL', WCCA_PLUGIN_NAME ), 'url', [
      'required' => false,
    ] );

    static::add_field( 'coupons', __( 'Coupons to add', WCCA_PLUGIN_NAME ), 'select2', [
      'post_type' => 'shop_coupon',
      'single'    => false,
    ] );
  }

  /**
   * @param string $option
   * @param string $label
   * @param string $type
   * @param array  $args
   */
  private static function add_field( string $option, string $label, string $type = 'text', array $args = [] ): void {
    self::$fields[ $option ] = [
      'name'  => $option,
      'label' => $label,
      'type'  => $type,
      'args'  => $args,
    ];
  }

  public static function woocommerce_checkout_order_created( $order ): void {
    // Handle the stats
    wcca()->add_order_to_wcca( $order );
  }

  /**
   * Once the order is complete, restore the old cart.
   */
  public static function woocommerce_pre_payment_complete(): void {
    // This action is added only if the order is successful.
    add_action( 'woocommerce_cart_emptied', [ __CLASS__, 'woocommerce_cart_emptied' ] );
  }

  /**
   * If there is a stored cart for the current customer, import it.
   *
   * @throws Exception
   */
  public static function woocommerce_cart_emptied(): void {
    $cart = get_transient( 'wcca_saved_cart_' . WC()->customer->get_id() );
    if ( $cart ) {
      // ensure that the cart is loaded
      WC()->cart->get_cart();
      foreach ( $cart[ 'content' ] as $product ) {
        WC()->cart->add_to_cart( $product );
      }

      foreach ( $cart[ 'coupons' ] as $coupon ) {
        if ( $the_coupon = new WC_Coupon( $coupon ) ) {
          WC()->cart->apply_coupon( $the_coupon->get_code() );
        }
      }
    }
  }

  /**
   * Check if there is a WCCA code in the URL
   * and add everything to the cart.
   * @throws Exception
   */
  public static function woocommerce_init(): void {
    if ( ! ( $wcca = $_REQUEST[ 'wcca' ] ?? null ) ) {
      // bail early if there is no wcca code in the URL
      return;
    }

    if ( is_admin() ) {
      // no need to do anything on the admin screens
      return;
    }

    $query = new WP_Query( [
      'fields'       => 'ids',
      'post_type'    => 'wcca',
      'meta_key'     => 'wcca_token',
      'meta_value'   => $wcca,
      'meta_compare' => '=',
    ] );

    if ( ! WC()->session->has_session() ) {
      // manually set the session so notices are persisted
      WC()->session->set_customer_session_cookie( true );
    }

    if ( ! $query->found_posts ) {
      // the wcca has maybe expired
      wc_add_notice( __( 'The link you followed was not valid. Please try to copy/paste it in your browser.', WCCA_PLUGIN_NAME ), 'error' );

      // redirect to the cart so the notice is displayed
      wp_safe_redirect( get_permalink( wc_get_page_id( 'shop' ) ) );
      // wp_safe_redirect( wc_get_cart_url() );
      exit;
    }

    static::create_cart_from_wcca( current( $query->posts ) );
  }

  /**
   * Create the WCCA cart and proceed to checkout.
   *
   * @param      $wcca_ID
   *
   * @throws Exception
   */
  private static function create_cart_from_wcca( $wcca_ID ): void {
    if ( empty( $wcca_ID ) ) {
      // something might have gone wrong earlier
      return;
    }

    if ( $wcca_ID instanceof WP_Post ) {
      $wcca_ID = $wcca_ID->ID;
    }

    // ensure that the cart is loaded
    WC()->cart->get_cart();

    // should the WCCA be added to the current cart?
    if ( wcca()->wcca_should_add_to_current_cart( $wcca_ID ) ) {
      // should we save the current cart for later?
      if ( wcca()->should_restore_cart_after_checkout() ) {
        $cart_content = WC()->cart->get_cart();
        $cart_coupons = WC()->cart->get_applied_coupons();
        set_transient( 'wcca_saved_cart_' . WC()->customer->get_id(), [
          'content' => $cart_content,
          'coupons' => $cart_coupons,
        ], wcca()->keep_old_cart_for_hours() );
      }
    } else {
      // initialize an empty cart
      WC()->cart->empty_cart();
    }

    wcca()->add_customer_wcca_opening( $wcca_ID );

    $has_product = $has_coupon = false;

    foreach ( get_post_meta( $wcca_ID, 'wcca_products', true ) as $product ) {
      $has_product = true;

      if ( is_scalar( $product ) ) {
        // store only the product with quantity 1 (early version of the plugin)
        WC()->cart->add_to_cart( $product );
      } else {
        if ( $wc_product = wc_get_product( $product[ 'product' ] ) ) {
          if ( $wc_product->is_type( 'simple' ) ) {
            WC()->cart->add_to_cart( $wc_product->get_id(), $product[ 'quantity' ] ?? 1 );
          } else if ( $wc_product->is_type( 'variation' ) ) {
            WC()->cart->add_to_cart( $wc_product->get_parent_id(), $product[ 'quantity' ] ?? 1, $wc_product->get_id() );
          }
        }
      }
    }

    foreach ( get_post_meta( $wcca_ID, 'wcca_coupons' ) as $coupon ) {
      $has_coupon = true;

      if ( $the_coupon = new WC_Coupon( $coupon ) ) {
        WC()->cart->apply_coupon( $the_coupon->get_code() );
      }
    }

    // Add some nice notice but previously disable other so the output is not polluted
    wc_clear_notices();
    if ( $has_product ) {
      wc_add_notice( __( 'Your cart has been successfully updated.', WCCA_PLUGIN_NAME ) );
    } else if ( $has_coupon ) {
      wc_add_notice( __( 'Your coupon has been applied to your cart.', WCCA_PLUGIN_NAME ) );
    }

    // Redirect to the checkout URL for fast checkout
    if ( $redirect = get_post_meta( $wcca_ID, 'wcca_redirect', true ) ) {
      wp_safe_redirect( $redirect );
    } else {
      wp_safe_redirect( wc_get_checkout_url() );
    }
    exit;
  }

  /**
   * Save posted values.
   *
   * @param int $wcca_ID Post ID.
   */
  public static function save_post( int $wcca_ID ): void {
    foreach ( static::$fields as $field => $data ) {
      if ( ! empty( $data[ 'args' ][ 'sub_fields' ] ?? [] ) ) {
        /*$sub_fields = [];
        foreach ( $_REQUEST[ 'wcca_' . $field ] ?? [] as $row_index => $row_values ) {
          foreach ( $data['args']['sub_fields'] as $key => $sub_field ) {
            $a = 0;
          }
        }*/
        // store all the repeater values in the same repeater meta
        $sanitized_values = [];
        foreach ( $_REQUEST[ 'wcca_' . $field ] ?? [] as $row_id => $row ) {
          if ( $row_id < 0 ) {
            continue;
          }
          foreach ( $row as $key => $value ) {
            if ( is_array( $value ) ) {
              $row[ $key ] = array_map( 'sanitize_text_field', $value );
            } else {
              $row[ $key ] = sanitize_text_field( $value );
            }
          }
          $sanitized_values[] = $row;
        }

        update_post_meta( $wcca_ID, 'wcca_' . $field, $sanitized_values );
      } else if ( $data[ 'args' ][ 'single' ] ?? true ) {
        update_post_meta( $wcca_ID, 'wcca_' . $field, sanitize_text_field( $_REQUEST[ 'wcca_' . $field ] ?? null ) );
      } else {
        delete_post_meta( $wcca_ID, 'wcca_' . $field );
        foreach ( $_REQUEST[ 'wcca_' . $field ] ?? [] as $value ) {
          add_post_meta( $wcca_ID, 'wcca_' . $field, sanitize_text_field( $value ) );
        }
      }
    }
  }

  /**
   * Register the CPT.
   */
  public static function register_cpt(): void {
    register_post_type( 'wcca', [
      'labels'        => [
        'name'               => _x( 'Automations', 'post type general name', WCCA_PLUGIN_NAME ),
        'singular_name'      => _x( 'Automation', 'post type singular name', WCCA_PLUGIN_NAME ),
        'add_new'            => _x( 'Add New', 'automation', WCCA_PLUGIN_NAME ),
        'add_new_item'       => __( 'Add New Automation', WCCA_PLUGIN_NAME ),
        'edit_item'          => __( 'Edit Automation', WCCA_PLUGIN_NAME ),
        'new_item'           => __( 'New Automation', WCCA_PLUGIN_NAME ),
        'view_item'          => __( 'View Automation', WCCA_PLUGIN_NAME ),
        'view_items'         => __( 'View Automations', WCCA_PLUGIN_NAME ),
        'search_items'       => __( 'Search Automations', WCCA_PLUGIN_NAME ),
        'not_found'          => __( 'No automations found.', WCCA_PLUGIN_NAME ),
        'not_found_in_trash' => __( 'No automations found in Trash.', WCCA_PLUGIN_NAME ),
        'all_items'          => __( 'Automations', WCCA_PLUGIN_NAME ),
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
    // Remove the slug metabox
    remove_meta_box( 'slugdiv', 'wcca', 'normal' );

    // Add our custom fields
    add_meta_box( 'wcca-fields', __( 'Configuration', WCCA_PLUGIN_NAME ), [ __CLASS__, 'render_meta_box_fields' ], 'wcca', 'advanced', 'high' );
  }

  /**
   * @throws Exception
   */
  public static function render_meta_box_fields() {
    foreach ( static::$fields as $field ) {
      WCCA_Admin::the_custom_field_admin( $field[ 'name' ], $field[ 'label' ], $field[ 'type' ], $field[ 'args' ] );
    }
  }

  /**
   * Display the WCCA link.
   *
   * @param WP_Post $post
   */
  public static function edit_form_before_permalink( WP_Post $post ): void {
    if ( 'wcca' !== $post->post_type ) {
      // Only check for WCCA post type
      return;
    }

    if ( in_array( $post->post_status, [ 'publish', 'future' ] ) ) {
      if ( $token = get_post_meta( $post->ID, 'wcca_token', true ) ) {
        $link = get_home_url() . '?' . http_build_query( [ 'wcca' => $token ] );
        echo '<p>';
        _e( 'Anyone clicking on this link will activate the cart automation.', WCCA_PLUGIN_NAME );
        printf( '<br><a href="%s" target="_blank">%s</a>', $link, $link );
        echo '</p>';
      }

      if ( 'future' === $post->post_status ) {
        echo '<p class="warning">';
        printf( __( 'Be careful, your link will only work at : %s', WCCA_PLUGIN_NAME ), $post->post_date );
        echo '</p>';
      }
    } else {
      printf( __( 'You must first publish this page to enable the link. Clicking on a disable link will not work.', WCCA_PLUGIN_NAME ) );
    }
  }
}
