=== MarqueeFlow ===
Contributors: tupacan
Tags: marquee, slider, carousel, image, scrolling
Requires at least: 6.3
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight marquee-style slider for continuously scrolling images. Perfect for image carousels, partner showcases, and brand strips.

== Description ==

MarqueeFlow adds a smooth, CSS-only marquee slider to your WordPress site via the block editor. No JavaScript animations, no jQuery, no external dependencies — just fast, GPU-accelerated scrolling.

= Features =

* Continuous marquee animation with seamless looping
* Three speed presets: Slow, Normal, Fast
* Three image height presets: Small, Medium, Large
* Two gap presets: Normal and Spacious
* Max image width control to prevent wide logos from dominating
* Direction control: Left to Right or Right to Left
* Pause on hover
* Drag-and-drop image reordering in the editor
* Supports JPG, PNG, WebP, GIF, and AVIF
* SVG support via third-party plugins (e.g. Safe SVG)
* Accessible: respects `prefers-reduced-motion` and includes ARIA labels
* Lightweight: CSS-only animations, no external dependencies

= How It Works =

1. Add the MarqueeFlow block in the editor
2. Select your images
3. Adjust speed, height, and style in the sidebar
4. Publish

== Installation ==

1. Upload the `marqueeflow` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Add the "MarqueeFlow" block in the block editor

== Frequently Asked Questions ==

= Does this work with the classic editor? =

Not yet. MarqueeFlow currently requires the WordPress block editor (Gutenberg). Shortcode support is planned for a future release.

= Can I use SVG images? =

WordPress does not allow SVG uploads by default. If you install an SVG plugin like Safe SVG, MarqueeFlow will display SVGs just like any other image format.

= Will it slow down my site? =

No. MarqueeFlow uses CSS-only animations with GPU-accelerated transforms. There is no JavaScript running on the frontend.

= What happens when a visitor has motion sensitivity? =

MarqueeFlow respects the `prefers-reduced-motion` media query. When enabled, the animation stops and images become horizontally scrollable instead.

== Screenshots ==

1. Frontend marquee scrolling colorful images, embedded naturally in a page layout
2. Block editor view showing the live preview, image management, and full settings sidebar

== Changelog ==

= 1.0.0 =
* Initial release
* Marquee animation with three speed presets
* Image height presets (Small, Medium, Large)
* Gap presets (Normal, Spacious)
* Max image width control
* Direction control (left-to-right, right-to-left)
* Pause on hover
* Drag-and-drop image reordering
* Accessible: ARIA labels, keyboard navigation, reduced motion support
