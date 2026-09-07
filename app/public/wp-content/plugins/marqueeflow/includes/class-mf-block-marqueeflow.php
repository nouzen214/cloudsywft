<?php
/**
 * MarqueeFlow Gutenberg Block
 *
 * Handles block registration and rendering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MarqueeFlow_Block_MarqueeFlow {

	private const ALLOWED_SPEEDS        = [ 'slow', 'normal', 'fast' ];
	private const ALLOWED_IMAGE_HEIGHTS = [ 'small', 'medium', 'large' ];
	private const ALLOWED_GAPS          = [ 'normal', 'spacious' ];
	private const ALLOWED_DIRECTIONS    = [ 'ltr', 'rtl' ];
	private const ALLOWED_ALIGNS        = [ 'wide', 'full' ];
	private const ALLOWED_MAX_WIDTHS    = [ 'narrow', 'normal', 'medium', 'wide', 'none' ];

	private static ?MarqueeFlow_Block_MarqueeFlow $instance = null;

	public static function instance(): MarqueeFlow_Block_MarqueeFlow {
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
		add_action( 'init', [ $this, 'register_block_script' ] );
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Register the block editor script
	 */
	public function register_block_script(): void {
		wp_register_script(
			'marqueeflow-block-editor',
			MARQUEEFLOW_PLUGIN_URL . 'build/index.js',
			[
				'wp-blocks',
				'wp-element',
				'wp-i18n',
				'wp-components',
				'wp-block-editor',
				'wp-editor',
			],
			MarqueeFlow_Plugin::VERSION,
			true
		);

		wp_set_script_translations( 'marqueeflow-block-editor', 'marqueeflow' );
	}

	/**
	 * Register the MarqueeFlow block
	 */
	public function register_block(): void {
		register_block_type(
			MARQUEEFLOW_PLUGIN_DIR . 'blocks/marqueeflow/block.json',
			[
				'editor_script'   => 'marqueeflow-block-editor',
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Render the block on the frontend
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content Block content.
	 * @return string Rendered HTML.
	 */
	public function render_block( array $attributes, string $content ): string {
		// Treat saved block attributes as untrusted input. Post content can be migrated,
		// edited in code view, or filtered by other plugins before render.
		$images = isset( $attributes['images'] ) && is_array( $attributes['images'] ) ? $attributes['images'] : [];

		// Keep enum-like values on known CSS/UI contracts to avoid broken classes.
		$speed        = $this->sanitize_enum( $attributes['speed'] ?? '', self::ALLOWED_SPEEDS, 'normal' );
		$image_height = $this->sanitize_enum( $attributes['imageHeight'] ?? '', self::ALLOWED_IMAGE_HEIGHTS, 'medium' );
		$gap          = $this->sanitize_enum( $attributes['gap'] ?? '', self::ALLOWED_GAPS, 'normal' );
		$direction    = $this->sanitize_enum( $attributes['direction'] ?? '', self::ALLOWED_DIRECTIONS, 'ltr' );
		$align        = $this->sanitize_enum( $attributes['align'] ?? '', self::ALLOWED_ALIGNS, '' );
		$max_width    = $this->sanitize_enum( $attributes['maxWidth'] ?? '', self::ALLOWED_MAX_WIDTHS, 'normal' );

		$pause_hover = $this->sanitize_bool( $attributes['pauseOnHover'] ?? false );

		if ( empty( $images ) ) {
			return '';
		}

		// Speed classes and durations
		$speed_classes = [
			'slow'   => 'marqueeflow--slow',
			'normal' => 'marqueeflow--normal',
			'fast'   => 'marqueeflow--fast',
		];

		$speed_class = $speed_classes[ $speed ] ?? $speed_classes['normal'];

		// Base attributes applied to every image regardless of how it is rendered.
		$img_attrs = [ 'decoding' => 'async', 'loading' => 'eager' ];

		$image_html = '';
		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}

			$image_id  = (int) ( $image['id'] ?? 0 );
			$image_url = $image['url'] ?? '';
			$image_alt = $image['alt'] ?? '';

			if ( $image_id ) {
				// Use wp_get_attachment_image() for responsive srcset/sizes markup.
				$img_tag = wp_get_attachment_image( $image_id, 'full', false, array_merge( $img_attrs, [ 'alt' => $image_alt ] ) );
			} elseif ( $image_url ) {
				// Fallback: manual img tag when only a URL is stored (no attachment ID).
				$img_tag = sprintf(
					'<img src="%s" alt="%s" decoding="async" loading="eager" />',
					esc_url( $image_url ),
					esc_attr( $image_alt )
				);
			} else {
				continue;
			}

			if ( ! $img_tag ) {
				continue; // Attachment was deleted; skip silently.
			}

			$image_html .= sprintf( '<li class="marqueeflow__item">%s</li>', $img_tag );
		}

		// Build wrapper classes (including alignment, speed, height, gap, direction, pause)
		$wrapper_class = "marqueeflow $speed_class";

		// Image height class
		$wrapper_class .= ' marqueeflow--height-' . esc_attr( $image_height );

		// Gap class
		$wrapper_class .= ' marqueeflow--gap-' . esc_attr( $gap );

		// Direction class
		if ( 'rtl' === $direction ) {
			$wrapper_class .= ' marqueeflow--rtl';
		}

		// Pause on hover class
		if ( $pause_hover ) {
			$wrapper_class .= ' marqueeflow--pause-hover';
		}

		// Max width class
		$wrapper_class .= ' marqueeflow--max-width-' . esc_attr( $max_width );

		// Alignment class
		if ( '' !== $align ) {
			$wrapper_class .= ' align' . esc_attr( $align );
		}

		// Render: Three animated sets for seamless looping (covers wide screens with few images)
		$image_count     = count( $images );
		$safe_image_html = wp_kses( $image_html, [
			'ul'  => [ 'class' => true ],
			'li'  => [ 'class' => true ],
			'img' => [
				'src'      => true,
				'alt'      => true,
				'decoding' => true,
				'loading'  => true,
				'class'    => true,
				'srcset'   => true,
				'sizes'    => true,
				'width'    => true,
				'height'   => true,
			],
		] );
		$height_map = [ 'small' => 40, 'medium' => 60, 'large' => 80 ];
		$height_px  = $height_map[ $image_height ] ?? 60;
		$wrapper_styles = [
			sprintf( '--marqueeflow-total-images: %d', $image_count ),
			sprintf( '--marqueeflow-height: %dpx', $height_px ),
		];
		$wrapper_style_attr = esc_attr( implode( '; ', $wrapper_styles ) );

		return sprintf(
			'<div class="%s" style="%s" role="region" aria-label="%s">
				<ul class="marqueeflow__set">%s</ul>
				<ul class="marqueeflow__set" aria-hidden="true">%s</ul>
				<ul class="marqueeflow__set" aria-hidden="true">%s</ul>
			</div>',
			esc_attr( $wrapper_class ),
			$wrapper_style_attr,
			esc_attr__( 'Image carousel', 'marqueeflow' ),
			$safe_image_html,
			$safe_image_html,
			$safe_image_html
		);
	}

	/**
	 * Sanitize string enums against a strict allowlist.
	 */
	private function sanitize_enum( mixed $value, array $allowed, string $default ): string {
		if ( ! is_string( $value ) ) {
			return $default;
		}

		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Normalize booleans from mixed block attribute inputs.
	 */
	private function sanitize_bool( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value !== 0;
		}
		if ( is_string( $value ) ) {
			$normalized = strtolower( trim( $value ) );
			if ( in_array( $normalized, [ '1', 'true', 'yes', 'on' ], true ) ) {
				return true;
			}
			if ( in_array( $normalized, [ '0', 'false', 'no', 'off', '' ], true ) ) {
				return false;
			}
		}
		return ! empty( $value );
	}

}
