<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Portone_Gateway_Blocks_Support extends AbstractPaymentMethodType {

//	private $gateway;

	protected $name = 'chaiport'; // payment gateway id

	public function initialize() {
		// get payment gateway settings
		$this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
		// $this->gateway  = new WC_Chaiport_Gateway();
	}

	public function is_active() {
		return $this->get_setting( 'enabled' ) === 'yes';
	}

	public function get_payment_method_script_handles() {

		wp_register_script(
			'wc-portone-blocks-integration',
			plugin_dir_url( __DIR__ ) . 'includes/portoneBlocks.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			false, // or time() or filemtime( ... ) to skip caching
			true
		);

		return array( 'wc-portone-blocks-integration' );

	}

	public function get_payment_method_data() {
		$data = array(
			'title' => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'icon'        => plugin_dir_url( __DIR__ ) . 'images/woocommerce_icon.png',
		);

		return $data;
	}

}