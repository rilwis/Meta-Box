<?php
defined( 'ABSPATH' ) || die;

/**
 * The Switch field.
 */
class RWMB_Switch_Field extends RWMB_Input_Field {
	public static function admin_enqueue_scripts() {
		wp_enqueue_style( 'rwmb-switch', RWMB_CSS_URL . 'switch.css', [], RWMB_VER );
		wp_style_add_data( 'rwmb-switch', 'path', RWMB_CSS_DIR . 'switch.css' );
	}

	/**
	 * Get field HTML.
	 *
	 * @param mixed $meta  Meta value.
	 * @param array $field Field parameters.
	 *
	 * @return string
	 */
	public static function html( $meta, $field ) {
		$attributes = self::get_attributes( $field, 1 );
		$output     = sprintf(
			'<label class="rwmb-switch-label rwmb-switch-label--' . esc_attr( $field['style'] ) . '">
				<input %s %s>
				<div class="rwmb-switch-status">
					<span class="rwmb-switch-slider"></span>
					<span class="rwmb-switch-on">' . $field['on_label'] . '</span>
					<span class="rwmb-switch-off">' . $field['off_label'] . '</span>
				</div>
				</label>
			',
			self::render_attributes( $attributes ),
			checked( ! empty( $meta ), 1, false )
		);

		return $output;
	}

	/**
	 * Normalize parameters for field.
	 *
	 * @param array $field Field parameters.
	 *
	 * @return array
	 */
	public static function normalize( $field ) {
		$field = parent::normalize( $field );
		$field = wp_parse_args( $field, [
			'style'     => 'rounded',
			'on_label'  => '',
			'off_label' => '',
		] );

		return $field;
	}

	/**
	 * Get the attributes for a field.
	 *
	 * @param array $field The field parameters.
	 * @param mixed $value The attribute value.
	 *
	 * @return array
	 */
	public static function get_attributes( $field, $value = null ) {
		$attributes         = parent::get_attributes( $field, $value );
		$attributes['type'] = 'checkbox';

		return $attributes;
	}

	/**
	 * Format a single value for the helper functions. Sub-fields should overwrite this method if necessary.
	 *
	 * @param array    $field   Field parameters.
	 * @param string   $value   The value.
	 * @param array    $args    Additional arguments. Rarely used. See specific fields for details.
	 * @param int|null $post_id Post ID. null for current post. Optional.
	 *
	 * @return string
	 */
	public static function format_single_value( $field, $value, $args, $post_id ) {
		$on  = $field['on_label'] ?: __( 'On', 'meta-box' );
		$off = $field['off_label'] ?: __( 'Off', 'meta-box' );
		return $value ? $on : $off;
	}

	/**
	 * Get the type of a single item.
	 *
	 * @param array $field Field parameters.
	 *
	 * @return array
	 */
	protected static function get_schema( $field ) {
		return [ 'type' => 'integer' ];
	}
}
