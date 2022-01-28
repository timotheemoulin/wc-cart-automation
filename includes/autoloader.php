<?php

/**
 * Autoload the classes of our plugin.
 */
spl_autoload_register( function ( $class ) {
	if ( false === strpos( $class, 'WCCA' ) ) {
		return;
	}

	$parts     = explode( '\\', $class );
	$namespace = $file_name = '';
	while ( $current = array_pop( $parts ) ) {
		$current = strtolower( $current );
		$current = str_ireplace( '_', '-', $current );

		if ( empty( $file_name ) ) {
			if ( strpos( strtolower( $parts[ count( $parts ) - 1 ] ), 'interface' ) ) {
				// Grab the name of the interface from its qualified name.
				$interface_name = explode( '_', $parts[ count( $parts ) - 1 ] );
				$interface_name = $interface_name[ 0 ];

				$file_name = "interface-$interface_name.php";

			} else {
				$file_name = "class-$current.php";
			}
		} else if ( ! empty( $parts ) ) {
			$namespace = '/' . $current . $namespace;
		}
	}

	// Now build a path to the file using mapping to the file location.
	$filepath = trailingslashit( dirname( __FILE__ ) . $namespace );
	$filepath .= $file_name;

	// If the file exists in the specified path, then include it.
	if ( file_exists( $filepath ) ) {
		include_once( $filepath );
	} else {
		wp_die( esc_html( "The file attempting to be loaded at $filepath does not exist." ) );
	}
} );
