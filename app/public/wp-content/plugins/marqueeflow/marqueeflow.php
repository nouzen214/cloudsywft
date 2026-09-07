<?php
/**
 * Plugin Name: MarqueeFlow
 * Plugin URI:  https://wordpress.org/plugins/marqueeflow/
 * Description: A lightweight marquee-style slider for continuously scrolling images.
 * Version:     1.0.0
 * Author:      tupacan
 * Author URI:  https://profiles.wordpress.org/tupacan/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: marqueeflow
 * Requires at least: 6.3
 * Tested up to:      6.9
 * Requires PHP:      8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MarqueeFlow_Plugin {

	private static ?MarqueeFlow_Plugin $instance = null;

	public const VERSION = '1.0.0';
	public const SLUG    = 'marqueeflow';

	public static function instance(): MarqueeFlow_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->define_constants();
		$this->includes();

		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	private function define_constants(): void {
		if ( ! defined( 'MARQUEEFLOW_PLUGIN_FILE' ) ) {
			define( 'MARQUEEFLOW_PLUGIN_FILE', __FILE__ );
		}
		if ( ! defined( 'MARQUEEFLOW_PLUGIN_DIR' ) ) {
			define( 'MARQUEEFLOW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}
		if ( ! defined( 'MARQUEEFLOW_PLUGIN_URL' ) ) {
			define( 'MARQUEEFLOW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}
	}

	private function includes(): void {
		require_once MARQUEEFLOW_PLUGIN_DIR . 'includes/class-mf-assets.php';
		require_once MARQUEEFLOW_PLUGIN_DIR . 'includes/class-mf-block-marqueeflow.php';
	}

	public function init(): void {
		MarqueeFlow_Assets::init();
		MarqueeFlow_Block_MarqueeFlow::init();
	}
}

MarqueeFlow_Plugin::instance();
