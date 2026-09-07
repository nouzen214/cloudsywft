<?php
/**
 * MarqueeFlow Assets Manager
 *
 * Handles enqueuing CSS and JavaScript assets for the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MarqueeFlow_Assets {

	private static ?MarqueeFlow_Assets $instance = null;

	public static function instance(): MarqueeFlow_Assets {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function init(): void {
		self::instance()->register_hooks();
	}

	private function __construct() {}

	private function register_hooks(): void {
		add_action( 'init', [ $this, 'register_styles' ] );
	}

	/**
	 * Register styles so block.json can reference them by handle.
	 * This ensures the CSS loads inside the editor iframe.
	 */
	public function register_styles(): void {
		wp_register_style(
			'marqueeflow-style',
			MARQUEEFLOW_PLUGIN_URL . 'assets/css/marqueeflow.css',
			[],
			MarqueeFlow_Plugin::VERSION
		);
	}

}
