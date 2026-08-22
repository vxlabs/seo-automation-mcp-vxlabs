<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SmartForms_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'smartforms'; }
	public function get_title() { return 'SmartForms'; }
	public function get_icon() { return 'eicon-form-horizontal'; }
	public function get_categories() { return array( 'general' ); }
	public function get_keywords() { return array( 'form', 'smartforms', 'contact', 'lead' ); }
	public function get_style_depends() { return array( 'smartforms-frontend' ); }
	public function get_script_depends() { return array( 'smartforms-frontend' ); }

	protected function register_controls() {
		$forms   = SmartForms_Form_Repository::list_forms( array( 'status' => 'publish' ) );
		$options = array( '' => 'Choose a form' );
		foreach ( $forms as $form ) {
			$options[ $form['id'] ] = $form['title'];
		}

		$this->start_controls_section( 'content', array( 'label' => 'Form' ) );
		$this->add_control( 'form_id', array( 'label' => 'SmartForm', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $options ) );
		$this->add_control( 'show_title', array( 'label' => 'Show title', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'accent', array( 'label' => 'Button color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .smartforms-wrap' => '--sf-accent: {{VALUE}}' ) ) );
		$this->add_control( 'label_color', array( 'label' => 'Label color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .smartforms-label' => 'color: {{VALUE}}' ) ) );
		$this->add_responsive_control( 'field_gap', array( 'label' => 'Field spacing', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .smartforms-field' => 'padding: {{SIZE}}{{UNIT}}' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( empty( $settings['form_id'] ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>Choose a SmartForm in the widget settings.</p>';
			}
			return;
		}
		echo SmartForms_Renderer::render( absint( $settings['form_id'] ), array( 'show_title' => 'yes' === $settings['show_title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

