<?php

/**
 * The plugin bootstrap file
 *
 * @category Backup_Plugin
 * @package  Export_Media_Zip
 * @author   Huzoor Bux <huzoorbakhsh@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 * @link     https://huzoorbux.com
 * @since    1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Export Media as ZIP
 * Description:       Adds an admin page under Media to export images as a ZIP file with year and size filters.
 * Version:           1.9
 * Author:            Huzoor Bux
 * Author URI:        https://huzoorbakhsh.com
 * License:           GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Main plugin class
class EMAZ_Export_Media_Zip {
	private $zip_filename = 'media-images.zip';
	private $zip_expiry   = 300; // 5 minutes in seconds

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'emaz_add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'emaz_enqueue_scripts' ) );
		add_action( 'wp_ajax_emaz_export_media_zip', array( $this, 'emaz_handle_export' ) );
		add_action( 'wp_ajax_emaz_get_export_progress', array( $this, 'emaz_get_export_progress' ) );
		add_action( 'wp_ajax_emaz_get_media_stats', array( $this, 'emaz_get_media_stats' ) );
		add_action( 'wp_ajax_emaz_get_filter_options', array( $this, 'emaz_get_filter_options' ) );
		add_action( 'wp_ajax_emaz_preview_export', array( $this, 'emaz_preview_export' ) );
		add_action( 'init', array( $this, 'emaz_schedule_zip_cleanup' ) );
		add_action( 'emaz_cleanup_expired_zips', array( $this, 'emaz_cleanup_expired_zips' ) );
	}

	// Add admin page under Media
	public function emaz_add_admin_page() {
		add_submenu_page(
			'upload.php',
			'Export Media as ZIP',
			'Export Media as ZIP',
			'manage_options',
			'export-media-zip',
			array( $this, 'emaz_render_admin_page' )
		);
	}

	// Render the admin page
	public function emaz_render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized access.' );
		}
		?>
		<div class="wrap export-media-wrap">
			<h1>Export Media as ZIP</h1>
			<p>Export images (.jpg, .jpeg, .png, .gif, .webp) from the media library as a ZIP file.</p>

			<!-- Media Statistics -->
			<div id="media-stats-container" class="stats-container">
				<h3>Media Library Statistics</h3>
				<div id="media-stats-loading" class="stats-loading">
					<span class="spinner"></span> Loading statistics...
				</div>
				<div id="media-stats-content" style="display: none;">
					<div class="stats-grid">
						<div class="stat-item">
							<span class="stat-number" id="total-images">0</span>
							<span class="stat-label">Total Images</span>
						</div>
						<div class="stat-item">
							<span class="stat-number" id="total-size">0 MB</span>
							<span class="stat-label">Total Size</span>
						</div>
						<div class="stat-item">
							<span class="stat-number" id="file-types">0</span>
							<span class="stat-label">File Types</span>
						</div>
					</div>
					<div id="file-type-breakdown" class="file-types"></div>
				</div>
			</div>

			<!-- Export Filters -->
			<div id="filter-section" class="filter-section">
				<h3>Export Filters</h3>
				<div id="filter-loading" class="stats-loading">
					<span class="spinner"></span> Loading filter options...
				</div>
				<div id="filter-content" style="display: none;">
					<div class="filter-row">

						<!-- Year Dropdown -->
						<div class="filter-field">
							<label class="filter-label">Year</label>
							<div class="emaz-dropdown" id="year-dropdown">
								<button type="button" class="emaz-dropdown-trigger">
									<span class="emaz-dropdown-label">All Years</span>
									<span class="emaz-dropdown-arrow">&#9662;</span>
								</button>
								<div class="emaz-dropdown-panel">
									<div class="emaz-dropdown-actions">
										<button type="button" class="emaz-action-link select-all-years">Select All</button>
										<button type="button" class="emaz-action-link deselect-all-years">None</button>
									</div>
									<div id="year-filters" class="emaz-dropdown-items"></div>
								</div>
							</div>
						</div>

						<!-- Image Size Dropdown -->
						<div class="filter-field">
							<label class="filter-label">Image Size</label>
							<div class="emaz-dropdown" id="size-dropdown">
								<button type="button" class="emaz-dropdown-trigger">
									<span class="emaz-dropdown-label">Full Size (Original)</span>
									<span class="emaz-dropdown-arrow">&#9662;</span>
								</button>
								<div class="emaz-dropdown-panel">
									<div class="emaz-dropdown-actions">
										<button type="button" class="emaz-action-link select-all-sizes">Select All</button>
										<button type="button" class="emaz-action-link deselect-all-sizes">None</button>
									</div>
									<p class="emaz-dropdown-hint">Sizes registered by WordPress core, theme &amp; plugins.</p>
									<div id="size-filters" class="emaz-dropdown-items"></div>
								</div>
							</div>
						</div>

					</div>

					<!-- Preview Count -->
					<div id="filter-preview" class="filter-preview">
						<span id="filter-preview-text">Select filters above to see export preview.</span>
					</div>
				</div>
			</div>

			<!-- Export Button -->
			<div class="export-section">
				<button id="export-media-zip-button" class="button button-primary button-hero" disabled>
					<span class="button-text">Export Images</span>
					<span class="button-spinner" style="display: none;"></span>
				</button>
			</div>

			<!-- Progress Section -->
			<div id="progress-section" class="progress-section" style="display: none;">
				<h3>Export Progress</h3>
				<div id="progress-bar-container" class="progress-container">
					<div id="progress-bar" class="progress-bar"></div>
					<div class="progress-info">
						<span id="progress-text">0%</span>
						<span id="progress-files">0 / 0 files</span>
					</div>
				</div>
				<div id="current-file" class="current-file"></div>
			</div>

			<!-- Download Section -->
			<div id="download-section" class="download-section" style="display: none;">
				<h3>Download Ready</h3>
				<div id="download-link"></div>
			</div>

			<!-- Error Messages -->
			<div id="error-message" class="error-message"></div>
		</div>
		<?php
	}

	// Enqueue scripts and styles only on the plugin's page
	public function emaz_enqueue_scripts( $hook ) {
		if ( 'media_page_export-media-zip' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'export-media-zip-js',
			plugin_dir_url( __FILE__ ) . 'scripts/export-media-zip.js',
			array( 'jquery' ),
			'1.7',
			true
		);

		wp_localize_script(
			'export-media-zip-js',
			'emazExportMediaZip',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'emaz_export_media_zip' ),
			)
		);

		wp_enqueue_style(
			'export-media-zip-css',
			plugin_dir_url( __FILE__ ) . 'styles/export-media-zip.css',
			array(),
			'1.7'
		);
	}

	// Return available years and registered image sizes for the filter UI
	public function emaz_get_filter_options() {
		check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		global $wpdb;

		// Years that have at least one image attachment
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$years = $wpdb->get_results(
			"SELECT YEAR(post_date) AS year, COUNT(*) AS count
			 FROM {$wpdb->posts}
			 WHERE post_type    = 'attachment'
			   AND post_mime_type LIKE 'image/%'
			   AND post_status  = 'inherit'
			 GROUP BY YEAR(post_date)
			 ORDER BY year DESC"
		);

		// All registered image sizes with their dimensions
		$sizes = array();

		// 'full' is the original uploaded file
		$sizes['full'] = array(
			'label'      => 'Full Size (Original)',
			'dimensions' => '',
		);

		// wp_get_registered_image_subsizes() was added in WP 5.3
		if ( function_exists( 'wp_get_registered_image_subsizes' ) ) {
			$registered = wp_get_registered_image_subsizes();
		} else {
			$registered = array();
			foreach ( get_intermediate_image_sizes() as $size_name ) {
				$registered[ $size_name ] = array(
					'width'  => (int) get_option( "{$size_name}_size_w" ),
					'height' => (int) get_option( "{$size_name}_size_h" ),
					'crop'   => (bool) get_option( "{$size_name}_crop" ),
				);
			}
		}

		foreach ( $registered as $size_name => $size_data ) {
			$label = ucwords( str_replace( array( '-', '_' ), ' ', $size_name ) );

			$dimensions = '';
			if ( ! empty( $size_data['width'] ) || ! empty( $size_data['height'] ) ) {
				$w          = ! empty( $size_data['width'] ) ? $size_data['width'] : '?';
				$h          = ! empty( $size_data['height'] ) ? $size_data['height'] : '?';
				$crop       = ! empty( $size_data['crop'] ) ? ', cropped' : '';
				$dimensions = "{$w}×{$h}{$crop}";
			}

			$sizes[ $size_name ] = array(
				'label'      => $label,
				'dimensions' => $dimensions,
			);
		}

		wp_send_json_success(
			array(
				'years' => $years,
				'sizes' => $sizes,
			)
		);
	}

	// Return a lightweight preview count for the current filter selection
	public function emaz_preview_export() {
		check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selected_years = isset( $_POST['years'] ) ? array_map( 'intval', (array) $_POST['years'] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selected_sizes = isset( $_POST['sizes'] ) ? array_map( 'sanitize_key', (array) $_POST['sizes'] ) : array( 'full' );

		if ( empty( $selected_sizes ) ) {
			wp_send_json_success( array( 'attachment_count' => 0, 'size_count' => 0 ) );
			return;
		}

		$query_args                  = $this->emaz_build_attachment_query( $selected_years );
		$query_args['fields']        = 'ids';
		$query_args['no_found_rows'] = true;

		$attachment_ids = get_posts( $query_args ); // phpcs:ignore WordPress.VIP.RestrictedFunctions

		wp_send_json_success(
			array(
				'attachment_count' => count( $attachment_ids ),
				'size_count'       => count( $selected_sizes ),
			)
		);
	}

	// Build a get_posts() args array for image attachments, with optional year filter
	private function emaz_build_attachment_query( $selected_years = array() ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
		);

		if ( ! empty( $selected_years ) ) {
			$args['date_query'] = array( 'relation' => 'OR' );
			foreach ( $selected_years as $year ) {
				$args['date_query'][] = array( 'year' => (int) $year );
			}
		}

		return $args;
	}

	// Handle export AJAX request
	public function emaz_handle_export() {
		if ( ! check_ajax_referer( 'emaz_export_media_zip', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_send_json_error( array( 'message' => 'ZipArchive extension is not available on this server. Please contact your hosting provider.' ) );
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		if ( ! WP_Filesystem() ) {
			wp_send_json_error( array( 'message' => 'Failed to initialize WordPress filesystem. Please check file permissions.' ) );
		}

		$upload_dir = wp_upload_dir();
		if ( $upload_dir['error'] ) {
			wp_send_json_error( array( 'message' => 'Upload directory error: ' . $upload_dir['error'] ) );
		}

		$base_dir = $upload_dir['basedir'];
		$zip_path = $base_dir . '/' . $this->zip_filename;

		if ( ! $wp_filesystem->is_dir( $base_dir ) || ! $wp_filesystem->is_readable( $base_dir ) ) {
			wp_send_json_error( array( 'message' => 'Uploads directory is not accessible. Please check file permissions.' ) );
		}

		if ( ! $wp_filesystem->is_writable( $base_dir ) ) {
			wp_send_json_error( array( 'message' => 'Cannot write to uploads directory. Please check file permissions.' ) );
		}

		// Sanitize filter inputs
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selected_years = isset( $_POST['years'] ) ? array_map( 'intval', (array) $_POST['years'] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selected_sizes = isset( $_POST['sizes'] ) ? array_map( 'sanitize_key', (array) $_POST['sizes'] ) : array( 'full' );

		if ( empty( $selected_sizes ) ) {
			wp_send_json_error( array( 'message' => 'Please select at least one image size to export.' ) );
		}

		// Query WordPress database for matching image attachments
		$query_args                  = $this->emaz_build_attachment_query( $selected_years );
		$query_args['fields']        = 'ids';
		$query_args['no_found_rows'] = true;
		$attachment_ids              = get_posts( $query_args ); // phpcs:ignore WordPress.VIP.RestrictedFunctions

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => 'No images found matching the selected filters.' ) );
		}

		$include_full = in_array( 'full', $selected_sizes, true );
		$other_sizes  = array_diff( $selected_sizes, array( 'full' ) );

		// Collect absolute paths -> archive names (keyed by path to avoid duplicates)
		$files_to_zip  = array();
		$image_exts    = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );

		foreach ( $attachment_ids as $attachment_id ) {
			$full_path = get_attached_file( $attachment_id );

			if ( ! $full_path || ! $wp_filesystem->exists( $full_path ) ) {
				continue;
			}

			$ext = strtolower( pathinfo( $full_path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $image_exts, true ) ) {
				continue;
			}

			// Full / original size
			if ( $include_full && $wp_filesystem->is_readable( $full_path ) ) {
				$relative                     = ltrim( str_replace( $base_dir, '', $full_path ), '/\\' );
				$files_to_zip[ $full_path ]   = $relative;
			}

			// Intermediate sizes stored in attachment metadata
			if ( ! empty( $other_sizes ) ) {
				$meta = wp_get_attachment_metadata( $attachment_id );
				if ( ! empty( $meta['sizes'] ) ) {
					$original_dir = dirname( $full_path );
					$relative_dir = ltrim( str_replace( $base_dir, '', $original_dir ), '/\\' );

					foreach ( $other_sizes as $size_name ) {
						if ( empty( $meta['sizes'][ $size_name ]['file'] ) ) {
							continue;
						}
						$size_file = $original_dir . '/' . $meta['sizes'][ $size_name ]['file'];
						if ( $wp_filesystem->exists( $size_file ) && $wp_filesystem->is_readable( $size_file ) ) {
							$archive_name                   = $relative_dir . '/' . $meta['sizes'][ $size_name ]['file'];
							$files_to_zip[ $size_file ]     = $archive_name;
						}
					}
				}
			}
		}

		if ( empty( $files_to_zip ) ) {
			wp_send_json_error( array( 'message' => 'No image files found for the selected filters and sizes.' ) );
		}

		// Create ZIP archive
		$zip        = new ZipArchive();
		$zip_result = $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		if ( true !== $zip_result ) {
			wp_send_json_error( array( 'message' => 'Failed to create ZIP file (Code: ' . $zip_result . ')' ) );
		}

		$total_files     = count( $files_to_zip );
		$processed_files = 0;
		$failed_files    = array();

		update_option( 'emaz_total_files', $total_files );
		update_option( 'emaz_processed_files', 0 );

		foreach ( $files_to_zip as $abs_path => $archive_name ) {
			if ( ! $zip->addFile( $abs_path, $archive_name ) ) {
				$failed_files[] = basename( $abs_path );
			}

			++$processed_files;
			update_option( 'emaz_progress', ( $processed_files / $total_files ) * 100 );
			update_option( 'emaz_processed_files', $processed_files );
			update_option( 'emaz_current_file', basename( $abs_path ) );
		}

		if ( ! $zip->close() ) {
			wp_send_json_error( array( 'message' => 'Failed to finalize ZIP file. The archive may be corrupted.' ) );
		}

		if ( ! $wp_filesystem->exists( $zip_path ) || 0 === $wp_filesystem->size( $zip_path ) ) {
			wp_send_json_error( array( 'message' => 'ZIP file was not created properly or is empty.' ) );
		}

		update_option( 'emaz_zip_time', time() );

		$download_url  = $upload_dir['baseurl'] . '/' . $this->zip_filename;
		$response_data = array(
			'download_url'    => $download_url,
			'total_files'     => $total_files,
			'processed_files' => $processed_files,
			'zip_size'        => $this->emaz_format_bytes( $wp_filesystem->size( $zip_path ) ),
		);

		if ( ! empty( $failed_files ) ) {
			$response_data['warning'] = 'Some files could not be added: ' . implode( ', ', array_slice( $failed_files, 0, 5 ) );
			if ( count( $failed_files ) > 5 ) {
				$response_data['warning'] .= ' and ' . ( count( $failed_files ) - 5 ) . ' more.';
			}
			$response_data['failed_files_count'] = count( $failed_files );
		}

		wp_send_json_success( $response_data );
	}

	// Get export progress AJAX
	public function emaz_get_export_progress() {
		check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
		wp_send_json_success(
			array(
				'progress'        => get_option( 'emaz_progress', 0 ),
				'current_file'    => get_option( 'emaz_current_file', '' ),
				'processed_files' => get_option( 'emaz_processed_files', 0 ),
				'total_files'     => get_option( 'emaz_total_files', 0 ),
			)
		);
	}

	// Get media statistics AJAX
	public function emaz_get_media_stats() {
		check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$upload_dir       = wp_upload_dir();
		$base_dir         = $upload_dir['basedir'];
		$image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
		$image_files      = array();
		$file_types       = array();
		$total_size       = 0;

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					$extension = strtolower( $file->getExtension() );
					if ( in_array( $extension, $image_extensions, true ) ) {
						$image_files[]              = $file->getPathname();
						$total_size                += $file->getSize();
						$file_types[ $extension ]   = ( isset( $file_types[ $extension ] ) ? $file_types[ $extension ] : 0 ) + 1;
					}
				}
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Error scanning media directory: ' . $e->getMessage() ) );
		}

		wp_send_json_success(
			array(
				'total_images'     => count( $image_files ),
				'total_size'       => $this->emaz_format_bytes( $total_size ),
				'total_size_bytes' => $total_size,
				'file_types'       => $file_types,
				'file_type_count'  => count( $file_types ),
			)
		);
	}

	// Helper: format bytes
	private function emaz_format_bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}
		return round( $bytes, $precision ) . ' ' . $units[ $i ];
	}

	// Schedule ZIP cleanup
	public function emaz_schedule_zip_cleanup() {
		if ( ! wp_next_scheduled( 'emaz_cleanup_expired_zips' ) ) {
			wp_schedule_event( time(), 'hourly', 'emaz_cleanup_expired_zips' );
		}
	}

	// Cleanup expired ZIPs
	public function emaz_cleanup_expired_zips() {
		global $wp_filesystem;
		if ( ! WP_Filesystem() ) {
			return;
		}

		$zip_path = wp_upload_dir()['basedir'] . '/' . $this->zip_filename;
		$zip_time = get_option( 'emaz_zip_time', 0 );
		if ( $wp_filesystem->is_file( $zip_path ) && ( time() - $zip_time > $this->zip_expiry ) ) {
			$wp_filesystem->delete( $zip_path );
			delete_option( 'emaz_zip_time' );
			delete_option( 'emaz_progress' );
			delete_option( 'emaz_current_file' );
			delete_option( 'emaz_processed_files' );
			delete_option( 'emaz_total_files' );
		}
	}
}

new EMAZ_Export_Media_Zip();
