<?php
/**
 * This file contains all helpers/public functions
 * that can be used both on the back-end or front-end
 */

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'RWMB_Helper' ) )
{
	/**
	 * Wrapper class for helper functions
	 */
	class RWMB_Helper
	{
		/**
		 * Find field by field ID
		 * This function finds field in meta boxes registered by 'rwmb_meta_boxes' filter
		 * Note: if users use old code to add meta boxes, this function might not work properly
		 *
		 * @param  string $field_id Field ID
		 *
		 * @return array|false Field params (array) if success. False otherwise.
		 */
		static function find_field( $field_id )
		{
			// Get all meta boxes registered with 'rwmb_meta_boxes' hook
			$meta_boxes = apply_filters( 'rwmb_meta_boxes', array() );

			// Find field
			foreach ( $meta_boxes as $meta_box )
			{
				foreach ( $meta_box['fields'] as $field )
				{
					if ( $field_id == $field['id'] )
					{
						return $field;
					}
				}
			}

			return false;
		}

		/**
		 * Get post meta
		 *
		 * @param string   $key     Meta key. Required.
		 * @param int|null $post_id Post ID. null for current post. Optional
		 * @param array    $args    Array of arguments. Optional.
		 *
		 * @return mixed
		 */
		static function meta( $key, $args = array(), $post_id = null )
		{
			$post_id = empty( $post_id ) ? get_the_ID() : $post_id;

			$args = wp_parse_args( $args, array(
				'type' => 'text',
			) );

			// Set 'multiple' for fields based on 'type'
			if ( ! isset( $args['multiple'] ) )
				$args['multiple'] = in_array( $args['type'], array( 'checkbox_list', 'file', 'file_advanced', 'image', 'image_advanced', 'plupload_image', 'thickbox_image' ) );

			$meta = get_post_meta( $post_id, $key, ! $args['multiple'] );

			// Get uploaded images info
			if ( in_array( $args['type'], array( 'image', 'plupload_image', 'thickbox_image', 'image_advanced' ) ) )
			{
				global $wpdb;

				$meta = $wpdb->get_col( $wpdb->prepare( "
					SELECT meta_value FROM $wpdb->postmeta
					WHERE post_id = %d AND meta_key = '%s'
					ORDER BY meta_id ASC
				", $post_id, $key ) );

				if ( is_array( $meta ) && ! empty( $meta ) )
				{
					$images = array();
					foreach ( $meta as $id )
					{
						// Get only info of existing attachments
						if ( get_attached_file( $id ) )
						{
							$images[$id] = self::image_info( $id, $args );
						}
					}
					$meta = $images;
				}
			}

			// Get terms
			elseif ( 'taxonomy_advanced' == $args['type'] )
			{
				if ( ! empty( $args['taxonomy'] ) )
				{
					$term_ids = array_map( 'intval', array_filter( explode( ',', $meta . ',' ) ) );

					// Allow to pass more arguments to "get_terms"
					$func_args = wp_parse_args( array(
						'include'    => $term_ids,
						'hide_empty' => false,
					), $args );
					unset( $func_args['type'], $func_args['taxonomy'], $func_args['multiple'] );
					$meta = get_terms( $args['taxonomy'], $func_args );
				}
				else
				{
					$meta = array();
				}
			}

			// Get post terms
			elseif ( 'taxonomy' == $args['type'] )
			{
				$meta = empty( $args['taxonomy'] ) ? array() : wp_get_post_terms( $post_id, $args['taxonomy'] );
			}

			// Get map
			elseif ( 'map' == $args['type'] )
			{
				$meta = self::map( $key, $args, $post_id );
			}

			return apply_filters( 'rwmb_meta', $meta, $key, $args, $post_id );
		}

		/**
		 * Get uploaded image information
		 *
		 * @param int   $id   Attachment image ID (post ID). Required.
		 * @param array $args Array of arguments (for size). Required.
		 *
		 * @return array|bool False if file not found. Array of (id, name, path, url) on success
		 */
		static function image_info( $id, $args = array() )
		{
			$args = wp_parse_args( $args, array(
				'size' => 'thumbnail',
			) );

			$img_src = wp_get_attachment_image_src( $id, $args['size'] );
			if ( empty( $img_src ) )
				return false;

			$attachment = get_post( $id );
			$path       = get_attached_file( $id );

			return array(
				'ID'          => $id,
				'name'        => basename( $path ),
				'path'        => $path,
				'url'         => $img_src[0],
				'width'       => $img_src[1],
				'height'      => $img_src[2],
				'full_url'    => wp_get_attachment_url( $id ),
				'title'       => $attachment->post_title,
				'caption'     => $attachment->post_excerpt,
				'description' => $attachment->post_content,
				'alt'         => get_post_meta( $id, '_wp_attachment_image_alt', true ),
			);
		}

		/**
		 * Display map using Google API
		 *
		 * @param  string   $key     Meta key
		 * @param  array    $args    Map parameter
		 * @param  int|null $post_id Post ID
		 *
		 * @return string
		 */
		static function map( $key, $args = array(), $post_id = null )
		{
			$post_id = empty( $post_id ) ? get_the_ID() : $post_id;
			$loc     = get_post_meta( $post_id, $key, true );
			if ( ! $loc )
				return '';

			$parts = array_map( 'trim', explode( ',', $loc ) );

			// No zoom entered, set it to 14 by default
			if ( count( $parts ) < 3 )
				$parts[2] = 14;

			// Map parameters
			$args               = wp_parse_args( $args, array(
				'width'        => '640px',
				'height'       => '480px',
				'marker'       => true, // Display marker?
				'marker_title' => '', // Marker title, when hover
				'info_window'  => '', // Content of info window (when click on marker). HTML allowed
				'js_options'   => array(),
			) );
			$args['js_options'] = wp_parse_args( $args['js_options'], array(
				'zoom'      => $parts[2], // Default to 'zoom' level set in admin, but can be overwritten
				'mapTypeId' => 'ROADMAP', // Map type, see https://developers.google.com/maps/documentation/javascript/reference#MapTypeId
			) );

			// Counter to display multiple maps on same page
			static $counter = 0;

			$html = sprintf(
				'<div id="rwmb-map-canvas-%d" style="width:%s;height:%s"></div>',
				$counter,
				$args['width'],
				$args['height']
			);

			// Load Google Maps script only when needed
			$html .= '<script>if ( typeof google !== "object" || typeof google.maps !== "object" )
						document.write(\'<script src="//maps.google.com/maps/api/js?sensor=false"><\/script>\')</script>';
			$html .= '<script>
				( function()
				{
			';

			$html .= sprintf( '
				var center = new google.maps.LatLng( %s, %s ),
					mapOptions = %s,
					map;

				switch ( mapOptions.mapTypeId )
				{
					case "ROADMAP":
						mapOptions.mapTypeId = google.maps.MapTypeId.ROADMAP;
						break;
					case "SATELLITE":
						mapOptions.mapTypeId = google.maps.MapTypeId.SATELLITE;
						break;
					case "HYBRID":
						mapOptions.mapTypeId = google.maps.MapTypeId.HYBRID;
						break;
					case "TERRAIN":
						mapOptions.mapTypeId = google.maps.MapTypeId.TERRAIN;
						break;
				}
				mapOptions.center = center;
				map = new google.maps.Map( document.getElementById( "rwmb-map-canvas-%d" ), mapOptions );
				',
				$parts[0], $parts[1],
				json_encode( $args['js_options'] ),
				$counter
			);

			if ( $args['marker'] )
			{
				if ( $args['marker_settings'] ){
					$settings = $args['marker_settings'];

					// checking if url is not empty
					if (empty($settings['url']))
						return '<p>Please give an image</p>';

					// checking if size is not empty
					if (empty($settings['size'])) {
						list($width, $height) = getimagesize($settings['url']);
						$settings['size'] = $width . ',' . $height;
					}

					// checking if url is an image
					if (!getimagesize($settings['url']))
						return '<p>This is not an image</p>';

					// check if size has a comma
					if (strpos(',', $settings['size']) !== false)
						return '<p>The given size is not correct. Example: 50,50</p>';

					// defining the marker for the map
					$html .= sprintf('
						var image = new google.maps.MarkerImage(
							"' . $settings['url'] . '",
							null, /* size is determined at runtime */
    						null, /* origin is 0,0 */
   							null, /* anchor is bottom center of the scaled image */
    						new google.maps.Size(' . $settings['size'] . ')
						);
						var marker = new google.maps.Marker( {
							position: center,
							map: map%s,
							icon: image
						} );',
						$args['marker_title'] ? ', title: "' . $args['marker_title'] . '"' : ''
					);
				} else {
					// if no marker is specified
					$html .= sprintf( '
						var marker = new google.maps.Marker( {
							position: center,
							map: map%s,
						} );',
						$args['marker_title'] ? ', title: "' . $args['marker_title'] . '"' : ''
					);
				}

				if ( $args['info_window'] )
				{
					$html .= sprintf( '
						var infoWindow = new google.maps.InfoWindow( {
							content: "%s"
						} );

						google.maps.event.addListener( marker, "click", function()
						{
							infoWindow.open( map, marker );
						} );',
						$args['info_window']
					);
				}
			}

			$html .= '} )();
				</script>';

			$counter ++;

			return $html;
		}
	}
}

if ( ! function_exists( 'rwmb_meta' ) )
{
	/**
	 * Get post meta
	 *
	 * @param string   $key     Meta key. Required.
	 * @param int|null $post_id Post ID. null for current post. Optional
	 * @param array    $args    Array of arguments. Optional.
	 *
	 * @deprecated Use rwmb_get_field instead
	 *
	 * @return mixed
	 */
	function rwmb_meta( $key, $args = array(), $post_id = null )
	{
		return rwmb_get_field( $key, $args, $post_id );
	}
}

if ( ! function_exists( 'rwmb_get_field' ) )
{
	/**
	 * Get value of custom field.
	 * This is used to replace old version of rwmb_meta key.
	 *
	 * @param  string   $field_id Field ID. Required.
	 * @param  array    $args     Additional arguments. Rarely used. See specific fields for details
	 * @param  int|null $post_id  Post ID. null for current post. Optional.
	 *
	 * @return mixed false if field doesn't exist. Field value otherwise.
	 */
	function rwmb_get_field( $field_id, $args = array(), $post_id = null )
	{
		$field = RWMB_Helper::find_field( $field_id );

		// Get field value
		$value = $field ? call_user_func( array( RW_Meta_Box::get_class_name( $field ), 'get_value' ), $field, $args, $post_id ) : false;

		/**
		 * Allow developers to change the returned value of field
		 *
		 * @param mixed    $value   Field value
		 * @param array    $field   Field parameter
		 * @param array    $args    Additional arguments. Rarely used. See specific fields for details
		 * @param int|null $post_id Post ID. null for current post. Optional.
		 */
		$value = apply_filters( 'rwmb_get_field', $value, $field, $args, $post_id );

		return $value;
	}
}

if ( ! function_exists( 'rwmb_the_field' ) )
{
	/**
	 * Display the value of a field
	 *
	 * @param  string   $field_id Field ID. Required.
	 * @param  array    $args     Additional arguments. Rarely used. See specific fields for details
	 * @param  int|null $post_id  Post ID. null for current post. Optional.
	 * @param  bool     $echo     Display field meta value? Default `true` which works in almost all cases. We use `false` for  the [rwmb_meta] shortcode
	 *
	 * @return string
	 */
	function rwmb_the_field( $field_id, $args = array(), $post_id = null, $echo = true )
	{
		// Find field
		$field = RWMB_Helper::find_field( $field_id );

		if ( ! $field )
			return '';

		$output = call_user_func( array( RW_Meta_Box::get_class_name( $field ), 'the_value' ), $field, $args, $post_id );

		/**
		 * Allow developers to change the returned value of field
		 *
		 * @param mixed    $value   Field HTML output
		 * @param array    $field   Field parameter
		 * @param array    $args    Additional arguments. Rarely used. See specific fields for details
		 * @param int|null $post_id Post ID. null for current post. Optional.
		 */
		$output = apply_filters( 'rwmb_the_field', $output, $field, $args, $post_id );

		if ( $echo )
			echo $output;

		return $output;
	}
}

if ( ! function_exists( 'rwmb_meta_shortcode' ) )
{
	/**
	 * Shortcode to display meta value
	 *
	 * @param $atts Array of shortcode attributes, same as meta() function, but has more "meta_key" parameter
	 *
	 * @see meta() function below
	 *
	 * @return string
	 */
	function rwmb_meta_shortcode( $atts )
	{
		$atts = wp_parse_args( $atts, array(
			'post_id' => get_the_ID(),
		) );
		if ( empty( $atts['meta_key'] ) )
			return '';

		$field_id = $atts['meta_key'];
		$post_id  = $atts['post_id'];
		unset( $atts['meta_key'], $atts['post_id'] );

		return rwmb_the_field( $field_id, $atts, $post_id, false );
	}

	add_shortcode( 'rwmb_meta', 'rwmb_meta_shortcode' );
}
