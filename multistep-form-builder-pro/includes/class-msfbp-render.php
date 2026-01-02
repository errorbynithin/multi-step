<?php
/**
 * Rendering engine.
 *
 * @package MSFBP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSFBP_Render {

	/**
	 * Register shortcode and block.
	 */
	public function hooks() {
		add_shortcode( 'msfbp_form', array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Shortcode handler.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts
		);

		$form_id = absint( $atts['id'] );
		if ( ! $form_id ) {
			return '';
		}

		$db   = new MSFBP_DB();
		$form = $db->get_form( $form_id );

		if ( ! $form ) {
			return '';
		}

		ob_start();
		wp_nonce_field( 'msfbp_submit_' . $form_id, '_msfbp_nonce' );
		?>
		<div class="msfbp-form" data-form="<?php echo esc_attr( wp_json_encode( $form ) ); ?>" data-form-id="<?php echo esc_attr( $form_id ); ?>">
			<div class="msfbp-progress" aria-live="polite"></div>
			<form class="msfbp-form-el" method="post" enctype="multipart/form-data">
				<input type="hidden" name="msfbp_form_id" value="<?php echo esc_attr( $form_id ); ?>" />
				<input type="text" name="msfbp_hp" class="msfbp-hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
				<?php echo $this->render_fields( $form['fields'] ); ?>
				<div class="msfbp-actions">
					<button type="button" class="msfbp-prev"><?php esc_html_e( 'Previous', 'msfbp' ); ?></button>
					<button type="button" class="msfbp-next"><?php esc_html_e( 'Next', 'msfbp' ); ?></button>
					<button type="submit" class="msfbp-submit"><?php esc_html_e( 'Submit', 'msfbp' ); ?></button>
				</div>
			</form>
			<div class="msfbp-messages" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render fields recursively.
	 */
	private function render_fields( $fields ) {
		$output     = '';
		$page_index = 0;
		foreach ( $fields as $field ) {
			if ( 'page_break' === $field['type'] ) {
				$page_index++;
				$output .= '<div class="msfbp-page-break" data-page="' . esc_attr( $page_index ) . '"></div>';
				continue;
			}

			$conditional = isset( $field['settings']['conditional'] ) ? ' data-conditional="' . esc_attr( wp_json_encode( $field['settings']['conditional'] ) ) . '"' : '';
			$output     .= '<div class="msfbp-field" data-type="' . esc_attr( $field['type'] ) . '" data-page="' . esc_attr( $page_index ) . '"' . $conditional . '>';
			$output     .= '<label for="msfbp_' . esc_attr( $field['slug'] ) . '">' . esc_html( $field['label'] );
			if ( ! empty( $field['required'] ) ) {
				$output .= '<span class="msfbp-required">*</span>';
			}
			$output .= '</label>';

			$output .= $this->render_input( $field );
			$output .= '</div>';
		}

		return $output;
	}

	/**
	 * Render individual field types.
	 */
	private function render_input( $field ) {
		$slug  = esc_attr( $field['slug'] );
		$type  = $field['type'];
		$html  = '';
		$attrs = ! empty( $field['required'] ) ? ' required' : '';

		switch ( $type ) {
			case 'textarea':
				$html = '<textarea id="msfbp_' . $slug . '" name="' . $slug . '"' . $attrs . '></textarea>';
				break;
			case 'select':
			case 'multi-select':
			case 'radio':
			case 'checkbox':
				$options = msfbp_array_get( $field['settings'], 'choices', array() );
				if ( 'select' === $type || 'multi-select' === $type ) {
					$html .= '<select id="msfbp_' . $slug . '" name="' . $slug . ( 'multi-select' === $type ? '[]' : '' ) . '"' . ( 'multi-select' === $type ? ' multiple' : '' ) . $attrs . '>';
					foreach ( $options as $choice ) {
						$html .= '<option value="' . esc_attr( $choice['value'] ) . '">' . esc_html( $choice['label'] ) . '</option>';
					}
					$html .= '</select>';
				} else {
					foreach ( $options as $choice ) {
						$html .= '<label><input type="' . ( 'checkbox' === $type ? 'checkbox' : 'radio' ) . '" name="' . $slug . ( 'checkbox' === $type ? '[]' : '' ) . '" value="' . esc_attr( $choice['value'] ) . '"' . $attrs . '> ' . esc_html( $choice['label'] ) . '</label>';
					}
				}
				break;
			case 'html':
				$html = wp_kses_post( msfbp_array_get( $field['settings'], 'content', '' ) );
				break;
			case 'page_break':
				$html = '';
				break;
			case 'repeater':
				$html  = '<div class="msfbp-repeater" data-slug="' . $slug . '">';
				$html .= '<div class="msfbp-repeat-row"><input type="text" name="' . $slug . '[]" /></div>';
				$html .= '<button type="button" class="msfbp-add-row" data-target="' . $slug . '">' . esc_html__( 'Add Row', 'msfbp' ) . '</button>';
				$html .= '</div>';
				break;
			case 'calculation':
				$formula = msfbp_array_get( $field['settings'], 'formula', '' );
				$html    = '<input type="text" readonly id="msfbp_' . $slug . '" name="' . $slug . '" data-formula="' . esc_attr( $formula ) . '" />';
				break;
			default:
				$html = '<input type="' . esc_attr( $this->map_input_type( $type ) ) . '" id="msfbp_' . $slug . '" name="' . $slug . '"' . $attrs . ' />';
				break;
		}

		return $html;
	}

	/**
	 * Map builder field type to input type.
	 */
	private function map_input_type( $type ) {
		$map = array(
			'text'     => 'text',
			'email'    => 'email',
			'number'   => 'number',
			'url'      => 'url',
			'phone'    => 'tel',
			'hidden'   => 'hidden',
			'date'     => 'date',
			'time'     => 'time',
			'file'     => 'file',
			'consent'  => 'checkbox',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : 'text';
	}

	/**
	 * Register simple block wrapper.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'msfbp-block',
			MSFBP_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components' ),
			MSFBP_VERSION,
			true
		);

		register_block_type(
			'msfbp/form',
			array(
				'editor_script'   => 'msfbp-block',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'id' => array(
						'type' => 'number',
					),
				),
			)
		);
	}

	/**
	 * Render block using shortcode output.
	 */
	public function render_block( $attributes ) {
		if ( empty( $attributes['id'] ) ) {
			return '';
		}

		return $this->render_shortcode( array( 'id' => $attributes['id'] ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'msfbp-frontend', MSFBP_PLUGIN_URL . 'assets/css/frontend.css', array(), MSFBP_VERSION );
		wp_enqueue_script( 'msfbp-frontend', MSFBP_PLUGIN_URL . 'assets/js/frontend.js', array( 'wp-i18n' ), MSFBP_VERSION, true );
	}
}
