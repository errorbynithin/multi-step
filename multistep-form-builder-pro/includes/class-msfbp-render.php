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

		$pages = $this->build_pages( $form['fields'] );

		ob_start();
		wp_nonce_field( 'msfbp_submit_' . $form_id, '_msfbp_nonce' );
		?>
		<div class="msfbp-modern" data-form-id="<?php echo esc_attr( $form_id ); ?>">
			<div class="msfbp-progress-bar"><div class="msfbp-progress-fill"></div></div>
			<div class="msfbp-container">
				<div class="msfbp-sidebar">
					<?php foreach ( $pages as $index => $page ) : ?>
						<div class="msfbp-step<?php echo 0 === $index ? ' active' : ''; ?>" data-step="<?php echo esc_attr( $index ); ?>">
							<div class="step-number"><?php echo esc_html( $index + 1 ); ?></div>
							<div class="step-label"><?php echo esc_html( $page['title'] ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="msfbp-content">
					<form class="msfbp-form-el" method="post" enctype="multipart/form-data" novalidate>
						<input type="hidden" name="msfbp_form_id" value="<?php echo esc_attr( $form_id ); ?>" />
						<input type="text" name="msfbp_hp" class="msfbp-hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
						<?php foreach ( $pages as $index => $page ) : ?>
							<div class="msfbp-step-content<?php echo 0 === $index ? '' : ' hidden'; ?>" data-step="<?php echo esc_attr( $index ); ?>">
								<div class="step-header">
									<div class="step-indicator"><?php printf( esc_html__( 'Step %1$d of %2$d', 'msfbp' ), $index + 1, count( $pages ) ); ?></div>
									<h2 class="step-title"><?php echo esc_html( $page['title'] ); ?></h2>
								</div>
								<div class="msfbp-fields">
									<?php foreach ( $page['fields'] as $field ) : ?>
										<?php echo $this->render_field( $field ); ?>
									<?php endforeach; ?>
								</div>
								<div class="msfbp-actions">
									<button type="button" class="btn msfbp-prev"<?php echo 0 === $index ? ' disabled' : ''; ?>><?php esc_html_e( 'Previous', 'msfbp' ); ?></button>
									<?php if ( ( $index + 1 ) === count( $pages ) ) : ?>
										<button type="submit" class="btn primary msfbp-submit"><?php esc_html_e( 'Submit', 'msfbp' ); ?></button>
									<?php else : ?>
										<button type="button" class="btn primary msfbp-next"><?php esc_html_e( 'Next', 'msfbp' ); ?></button>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</form>
				</div>
			</div>
			<div class="msfbp-messages" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Split fields into pages.
	 */
	private function build_pages( $fields ) {
		$pages    = array();
		$page     = array(
			'title'  => __( 'Step 1', 'msfbp' ),
			'index'  => 0,
			'fields' => array(),
		);
		$counter  = 1;
		foreach ( $fields as $field ) {
			if ( 'page_break' === $field['type'] ) {
				$pages[] = $page;
				$counter++;
				$page = array(
					'title'  => ! empty( $field['label'] ) ? $field['label'] : sprintf( __( 'Step %d', 'msfbp' ), $counter ),
					'index'  => $counter - 1,
					'fields' => array(),
				);
				continue;
			}
			$page['fields'][] = $field;
		}

		$pages[] = $page;
		return $pages;
	}

	/**
	 * Render a single field block.
	 */
	private function render_field( $field ) {
		$slug        = esc_attr( $field['slug'] );
		$type        = $field['type'];
		$conditional = isset( $field['settings']['conditional'] ) ? ' data-conditional="' . esc_attr( wp_json_encode( $field['settings']['conditional'] ) ) . '"' : '';
		$html        = '<div class="msfbp-field" data-type="' . esc_attr( $type ) . '"' . $conditional . '>';

		if ( ! in_array( $type, array( 'html', 'hidden', 'calculation' ), true ) ) {
			$html .= '<label for="msfbp_' . $slug . '">' . esc_html( $field['label'] );
			if ( ! empty( $field['required'] ) ) {
				$html .= '<span class="msfbp-required">*</span>';
			}
			$html .= '</label>';
		}

		$html .= $this->render_input( $field );
		$html .= '</div>';

		return $html;
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
