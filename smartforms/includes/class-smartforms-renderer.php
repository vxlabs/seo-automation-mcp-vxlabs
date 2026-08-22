<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Renderer {

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'show_title' => '' ), $atts, 'smartforms' );
		return self::render( absint( $atts['id'] ), array( 'show_title' => $atts['show_title'] ) );
	}

	public static function render( $id, $overrides = array() ) {
		$form = SmartForms_Form_Repository::get( $id );
		if ( is_wp_error( $form ) || 'publish' !== $form['status'] ) {
			return current_user_can( 'smartforms_manage_forms' ) ? '<p class="smartforms-unavailable">This SmartForm is not published.</p>' : '';
		}

		wp_enqueue_style( 'smartforms-frontend' );
		wp_enqueue_script( 'smartforms-frontend' );
		wp_localize_script(
			'smartforms-frontend',
			'smartFormsConfig',
			array( 'restUrl' => esc_url_raw( rest_url( 'smartforms/v1/forms/' ) ), 'submitting' => 'Submitting…' )
		);

		$settings   = $form['settings'];
		$show_title = isset( $overrides['show_title'] ) && '' !== $overrides['show_title'] ? filter_var( $overrides['show_title'], FILTER_VALIDATE_BOOLEAN ) : $settings['show_title'];
		$global     = SmartForms_Recaptcha::settings();
		$captcha    = ! empty( $settings['enable_recaptcha'] ) && SmartForms_Recaptcha::is_configured();
		if ( $captcha ) {
			$src = 'v3' === $global['recaptcha_mode'] ? 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $global['recaptcha_site'] ) : 'https://www.google.com/recaptcha/api.js?render=explicit';
			wp_enqueue_script( 'google-recaptcha', $src, array(), null, true );
		}

		ob_start();
		?>
		<div class="smartforms-wrap" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
			<?php if ( $show_title ) : ?><h2 class="smartforms-title"><?php echo esc_html( $form['title'] ); ?></h2><?php endif; ?>
			<form class="smartforms-form" method="post" novalidate data-captcha-mode="<?php echo esc_attr( $captcha ? $global['recaptcha_mode'] : 'none' ); ?>" data-site-key="<?php echo esc_attr( $captcha ? $global['recaptcha_site'] : '' ); ?>">
				<input type="hidden" name="form_token" value="<?php echo esc_attr( self::token( $form ) ); ?>">
				<input type="hidden" name="form_started" value="<?php echo esc_attr( time() ); ?>">
				<input type="hidden" name="source_url" value="<?php echo esc_url( self::current_url() ); ?>">
				<input type="hidden" name="recaptcha_token" value="">
				<div class="smartforms-hp" aria-hidden="true"><label>Leave this empty<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label></div>
				<div class="smartforms-grid">
					<?php foreach ( $form['schema']['fields'] as $field ) : self::render_field( $field ); endforeach; ?>
				</div>
				<?php if ( $captcha && in_array( $global['recaptcha_mode'], array( 'v2_checkbox', 'v2_invisible' ), true ) ) : ?>
					<div class="smartforms-recaptcha" data-size="<?php echo 'v2_invisible' === $global['recaptcha_mode'] ? 'invisible' : 'normal'; ?>"></div>
				<?php endif; ?>
				<div class="smartforms-response" role="status" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_field( $field ) {
		$type  = $field['type'];
		$style = '--smartforms-field-width:' . absint( $field['width'] ) . '%';
		$class = 'smartforms-field smartforms-field-' . sanitize_html_class( $type ) . ( $field['css_class'] ? ' ' . sanitize_html_class( $field['css_class'] ) : '' );
		if ( 'separator' === $type ) {
			echo '<div class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '"><hr></div>';
			return;
		}
		if ( 'heading' === $type ) {
			echo '<div class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '"><h3>' . wp_kses_post( $field['content'] ?: $field['label'] ) . '</h3></div>';
			return;
		}
		if ( 'image' === $type ) {
			echo '<div class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">' . ( $field['image_url'] ? '<img src="' . esc_url( $field['image_url'] ) . '" alt="' . esc_attr( $field['label'] ) . '">' : '' ) . '</div>';
			return;
		}
		if ( 'icon' === $type ) {
			echo '<div class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '" aria-hidden="true">★</div>';
			return;
		}
		if ( 'button' === $type ) {
			echo '<div class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '"><button type="submit" class="smartforms-submit">' . esc_html( $field['label'] ?: 'Submit' ) . '</button></div>';
			return;
		}

		$id       = 'smartforms-' . $field['id'];
		$required = ! empty( $field['required'] );
		echo '<div class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '" data-field-id="' . esc_attr( $field['id'] ) . '">';
		if ( 'gdpr' !== $type ) {
			echo '<label class="smartforms-label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' ) . '</label>';
		}

		$name = 'fields[' . $field['id'] . ']';
		$common = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '"' . ( $required ? ' required' : '' );
		if ( in_array( $type, array( 'textarea', 'address' ), true ) ) {
			echo '<textarea' . $common . ( $field['max_length'] ? ' maxlength="' . absint( $field['max_length'] ) . '"' : '' ) . '>' . esc_textarea( $field['default'] ) . '</textarea>';
		} elseif ( in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
			echo '<div class="smartforms-options">';
			foreach ( $field['options'] as $index => $option ) {
				$option_id = $id . '-' . $index;
				$option_name = 'checkbox' === $type ? $name . '[]' : $name;
				echo '<label for="' . esc_attr( $option_id ) . '"><input type="' . esc_attr( $type ) . '" id="' . esc_attr( $option_id ) . '" name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $option ) . '"> ' . esc_html( $option ) . '</label>';
			}
			echo '</div>';
		} elseif ( 'select' === $type ) {
			echo '<select' . $common . '><option value="">' . esc_html( $field['placeholder'] ?: 'Choose an option' ) . '</option>';
			foreach ( $field['options'] as $option ) {
				echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'gdpr' === $type ) {
			echo '<label class="smartforms-agreement"><input type="checkbox"' . $common . ' value="1"> ' . esc_html( $field['label'] ) . '</label>';
		} else {
			$html_type = in_array( $type, array( 'email', 'url', 'number', 'phone' ), true ) ? ( 'phone' === $type ? 'tel' : $type ) : 'text';
			echo '<input type="' . esc_attr( $html_type ) . '"' . $common . ' value="' . esc_attr( $field['default'] ) . '"' . ( '' !== $field['min'] ? ' min="' . esc_attr( $field['min'] ) . '"' : '' ) . ( '' !== $field['max'] ? ' max="' . esc_attr( $field['max'] ) . '"' : '' ) . ( $field['max_length'] ? ' maxlength="' . absint( $field['max_length'] ) . '"' : '' ) . '>';
		}
		if ( $field['help'] ) {
			echo '<small class="smartforms-help">' . esc_html( $field['help'] ) . '</small>';
		}
		echo '<span class="smartforms-error" aria-live="polite"></span></div>';
	}

	public static function token( $form ) {
		$version = isset( $form['schema']['version'] ) ? absint( $form['schema']['version'] ) : 1;
		return hash_hmac( 'sha256', $form['id'] . '|' . $form['modified_gmt'] . '|' . $version, wp_salt( 'nonce' ) );
	}

	private static function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return $host ? $scheme . $host . $uri : home_url( '/' );
	}
}
