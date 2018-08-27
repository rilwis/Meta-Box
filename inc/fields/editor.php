<?php
/**
 * The Editor test  field.
 *
 * @package Meta Box
 */

/**
 * Editor test field class.
 */

 class RWMB_Editor_Field extends RWMB_Textarea_Field {

	 /**
 	 * Enqueue scripts and styles.
 	 */
 	public static function admin_enqueue_scripts() {
		wp_enqueue_editor();
 		wp_enqueue_style( 'rwmb-editor', RWMB_CSS_URL . 'editor.css', array(), RWMB_VER );
 		wp_enqueue_script( 'rwmb-editor', RWMB_JS_URL . 'editor.js', array(), RWMB_VER, true );
 	}

	 /**
 	 * Get field HTML.
 	 *
 	 * @param mixed $meta Meta value.
 	 * @param array $field Field parameters.
 	 *
 	 * @return string
 	 */
 	public static function html( $meta, $field ) {
 		$attributes = self::get_attributes( $field, $meta );
 		return sprintf(
 			'<textarea %s data-tinymce="%s" data-quicktags="%s" data-media="%s">%s</textarea>',
 			self::render_attributes( $attributes ),
			esc_attr( wp_json_encode( $field['tinymce'] ) ),
			esc_attr( wp_json_encode( $field['quicktags'] ) ),
			esc_attr( wp_json_encode( $field['media'] ) ),
 			$meta
 		);
 	}

	 /**
 	 * Normalize parameters for field.
 	 *
 	 * @param array $field Field parameters.
 	 * @return array
 	 */
 	public static function normalize( $field ) {
 		$field = parent::normalize( $field );
		$field = wp_parse_args( $field, array(
			'raw'       => false,
			'tinymce'   => array(),
			'quicktags' => array(),
			'media'     => true,
			'dfw'       => true,
		) );

		$field['tinymce'] = wp_parse_args( $field['tinymce'], array(
			'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
			'toolbar2' => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help'
		));

		$field['quicktags'] = wp_parse_args( $field['quicktags'], array(
			'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close',
		));

 		return $field;
 	}
}
