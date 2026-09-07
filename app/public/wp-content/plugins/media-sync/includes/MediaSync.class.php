<?php

/**
 * Media Sync
 *
 * This class is used for generating main content and also to import files to database
 *
 * @package     MediaSync
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 * @author      Erol Živina
 */
if ( !class_exists( 'MediaSync' ) ) :

    class MediaSync
    {

        /**
         * Absolute path to main "uploads" directory.
         *
         * @var null
         */
        static $upload_dir_path = null;

        /**
         * Files already in Media Library.
         *
         * @var null|array
         */
        static $files_in_db = null;

        /**
         * For showing passed time when debugging.
         *
         * @since 1.2.0
         * @var null|string
         */
        static $start_time = null;

        /**
         * Render main plugin content
         *
         * @since 0.1.0
         * @return void
         */
        static public function media_sync_main_page()
        {
            // Suppress raw PHP errors on Media Sync page, so we can show cleaner custom message
            @ini_set('display_errors', '0');

            // Register shutdown function to catch fatal errors (e.g. memory exhausted)
            register_shutdown_function(function() {
                $message = self::media_sync_get_fatal_error_message();
                if ( ! empty( $message ) ) {
                    self::media_sync_render_error_message( $message );
                }
            });

            if(!MediaSync::media_sync_user_has_general_access()) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'media-sync'));
            }

            $scan_files = self::filter_input_boolean(INPUT_GET, 'scan_files');
            $associated_filter = self::sanitize_input_string(INPUT_GET, 'associated-filter');
            $missing_from_ml = $associated_filter && urldecode($associated_filter) === 'missing_from:media_library';

            $here = esc_url(get_admin_url(null, 'upload.php?page=media-sync-page'));

            $upload_dir_path = self::media_sync_get_uploads_basedir();
            $relative_path = self::media_sync_get_relative_path($upload_dir_path);
            $no_upload_dir_message = __('Scan directory not found. Please check settings.', 'media-sync');
            ?>

            <div class="wrap main-media-sync-page" xmlns="http://www.w3.org/1999/html" xmlns="http://www.w3.org/1999/html">
                <h1><?= __('Media Sync', 'media-sync') ?></h1>

                <?php do_action('media_sync_extended_initial_notice'); ?>

                <div class="notice notice-error hidden media-sync-global-errors js-media-sync-error-placeholder"></div>

                <?php if ($scan_files) : ?>
                    <div class="notice notice-error">
                        <p><?= __('Please backup your database! This plugin makes database changes.', 'media-sync') ?></p>
                    </div>
                    <div class="notice notice-success notice-files-imported">
                        <p><?= sprintf(__('Done! Highlighted files were successfully imported. %s to see changes.', 'media-sync'),
                                '<a href="'.add_query_arg('scan_files', 1, $here).'">'.__('Re-scan', 'media-sync').'</a>') ?></p>
                    </div>
                <?php endif; ?>

                <div class="media-sync-list-files">
                    <form action="<?= $here ?>" method="GET">
                        <input type="hidden" name="page" value="media-sync-page"/>
                        <input type="hidden" name="scan_files" value="<?= $scan_files ?>"/>
                        <div class="media-sync-buttons-holder">
                            <?php if (!$scan_files) : ?>
                                <div class="card">
                                    <h2 class="title"><?= __('Sync - uploads directory', 'media-sync') ?></h2>

                                    <?php if($upload_dir_path) : ?>
                                        <a class="button button-primary" href="<?= add_query_arg('scan_files', 1, $here) ?>">
                                            <?= __('Scan Files', 'media-sync') ?>
                                        </a>

                                        <p class="media-sync-scan-files-message">
                                            <?= sprintf(__('Use this to see content of upload dir: %s and import files to Media Library.', 'media-sync'),
                                                '<code title="'.$upload_dir_path.'">'.$relative_path.'</code>') ?>
                                        </p>
                                    <?php else: ?>
                                        <p><i><?= $no_upload_dir_message ?></i></p>
                                    <?php endif; ?>
                                </div>
                                <div class="card">
                                    <h2 class="title"><?= __('Sync - Media Library', 'media-sync') ?></h2>
                                    <a class="button button-primary" href="<?= add_query_arg(array('mode' => 'list', 'media_sync_missing_files' => 'yes'), get_admin_url(null, 'upload.php')) ?>">
                                        <?= __('Filter Media Library', 'media-sync') ?>
                                    </a>

                                    <p class="media-sync-scan-files-message">
                                        <?= __('Use this to see Media Library items that are missing actual files. This takes you to Media Library but with custom filter.', 'media-sync') ?>
                                    </p>
                                </div>
                                <?php MediaSync::media_sync_render_pro_card(); ?>
                            <?php endif; ?>

                            <?php if ($scan_files) : ?>
                                <div class="media-sync-button-holder">
                                    <a class="button button-primary" href="<?= add_query_arg('scan_files', 1, $here) ?>">
                                        <?= __('Re-scan', 'media-sync') ?>
                                    </a>
                                </div>

                                <div class="media-sync-button-holder">
                                    <button class="button button-primary js-import-selected"><?= __('Import Selected', 'media-sync') ?></button>
                                    <span class="spinner import-spinner"></span>
                                </div>

                                <div class="import-options">
                                    <?php if ( !!get_option( 'ms_sg_use_dry_run', 1 ) ) : ?>
                                        <div class="media-sync-dry-run-holder">
                                            <input type="checkbox" id="dry-run" name="dry_run" checked="checked" />
                                            <label for="dry-run"><?= __('Dry Run (test without making database changes)', 'media-sync') ?></label>
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="dry_run" value="" />
                                    <?php endif; ?>

                                    <div class="import-option-date-type">
                                        <label><?= __('Date/time to set for newly imported files', 'media-sync') ?>:</label>
                                        <?php self::media_sync_render_post_date_option( 'file_post_date' ) ?>
                                    </div>
                                    <div class="import-option-batch-size">
                                        <label for="batch-size">
                                            <?= __('Batch Size:', 'media-sync') ?>
                                            <input type="number" value="10" step="1" min="1" class="small-text" name="batch_size" id="batch-size" />
                                        </label>
                                        <p class="description">
                                            <?= __('The number of files to process per network request.', 'media-sync') ?>
                                            <?= __('A lower value might avoid some limits of your server (e.g. max_execution_time). ', 'media-sync') ?>
                                            <br />
                                            <?= __('If you still get errors, please try disabling other plugins related to files/images.', 'media-sync') ?>
                                            <?= __('To reduce additional actions related to file uploads (e.g. generating additional thumbnails).', 'media-sync') ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($scan_files) : ?>
                            <p class="media-sync-state-holder">
                                <span class="media-sync-progress-holder">
                                    <span class="media-sync-progress"></span>
                                </span>
                                <span class="media-sync-state">
                                    <span class="media-sync-state-text">
                                        <?= __('Imported', 'media-sync') ?>
                                    </span>
                                    <span class="media-sync-state-number media-sync-imported-count js-media-sync-imported-count">0</span>
                                    <span class="media-sync-state-text">
                                        <?= __('out of', 'media-sync') ?>
                                    </span>
                                    <span class="media-sync-state-number media-sync-selected-count js-media-sync-selected-count">0</span>
                                    <span class="media-sync-state-text">
                                        <?= __('selected items', 'media-sync') ?>
                                    </span>
                                </span>
                                <span class="media-sync-state media-sync-state-note">
                                    <?= __('Files already in Media Library will be skipped during import', 'media-sync') ?>
                                </span>
                            </p>

                            <div class="wp-filter">
                                <div class="filter-items">
                                    <label for="associated-filter" class="screen-reader-text"><?= __('Filter by type', 'media-sync') ?></label>
                                    <select class="associated-filters" name="associated-filter" id="associated-filter">
                                        <option value=""><?= __('All files', 'media-sync') ?></option>
                                        <option value="missing_from:media_library"<?= $missing_from_ml ? 'selected="selected"' : '' ?>>
                                            <?= __('Only files missing from Media Library', 'media-sync') ?>
                                        </option>
                                    </select>

                                    <div class="actions">
                                        <input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?= __('Filter', 'media-sync') ?>">
                                    </div>
                                </div>
                            </div>
                            <?php
                            $tree = $upload_dir_path ? self::media_sync_get_list_of_uploads() : array();
                            ?>
                            <?php if (!empty($tree)) : ?>
                                <div class="media-sync-table-holder">
                                    <table class="wp-list-table widefat fixed media">
                                        <?php self::media_sync_render_thead_tfoot_row('thead') ?>
                                        <tbody id="the-list">
                                        <?php foreach ($tree as $item) : ?>
                                            <?php self::media_sync_render_row($item) ?>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <?php self::media_sync_render_thead_tfoot_row('tfoot') ?>
                                    </table>
                                    <span class="spinner is-active table-spinner"></span>
                                </div>
                            <?php else : ?>
                                <p class="media-sync-no-results">
                                    <?php if(!$upload_dir_path) : ?>
                                        <?= $no_upload_dir_message ?>
                                    <?php else : ?>
                                        <?= __('No Results', 'media-sync') ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php
        }


        /**
         * Render error message when limits are reached.
         *
         * @param string $message
         * @return void
         */
        static private function media_sync_render_error_message($message)
        {
            ?>
            <div class="notice notice-error inline">
                <p>
                    <?= __('Server has reached limits that prevent scanning all files at once.', 'media-sync') ?>
                </p>
                <p>
                    <strong><?= __('Caught error:', 'media-sync') ?></strong>
                    <?= $message ?>
                </p>
            </div>
            <?php
            MediaSync::media_sync_render_pro_card();
        }


        /**
         * Get fatal error message if one occurred.
         *
         * @since 1.4.9
         * @return string
         */
        static private function media_sync_get_fatal_error_message() {
            try {
                $error = error_get_last();
                // Check if it's a fatal error and if it's related to memory or time
                if ( $error && ( $error['type'] === E_ERROR || $error['type'] === E_USER_ERROR ) ) {
                    if ( strpos( $error['message'], 'Allowed memory size' ) !== false ) {
                        return sprintf(
                            __( 'Memory limit reached (%s used).', 'media-sync' ),
                            size_format( memory_get_peak_usage( true ) )
                        );
                    }
                    if ( strpos( $error['message'], 'Maximum execution time' ) !== false ) {
                        return sprintf(
                            __( 'Execution time limit reached (%s seconds).', 'media-sync' ),
                            ini_get( 'max_execution_time' )
                        );
                    }
                }
            } catch ( Exception $e ) {
            }

            return "";
        }


        /**
         * @since 1.4.9
         * @return void
         */
        static private function media_sync_render_pro_card() {
            ?>
            <div class="card">
                <h2 class="title">Media Sync Pro</h2>
                <a id="purchase-media-sync-pro" class="button button-primary" href="https://checkout.freemius.com/mode/dialog/plugin/14503/plan/24225/?show_monthly_switch=1" target="_blank" rel="nofollow noopener">Upgrade Now</a>
                &nbsp;
                <a class="button button-secondary" href="https://mediasyncplugin.com/?utm_source=base_plugin_banner&amp;utm_medium=init_page&amp;utm_campaign=bip" target="_blank" rel="noopener">Find out more</a>

                <h4>Check out our newly revamped pro version with amazing new features.</h4>

                <ul style="list-style: circle; margin-left: 15px;">
                    <li><strong>Revised incremental scan</strong>: Allows scanning and importing unlimited number of files.</li>
                    <li><strong>Quick single directory rescan</strong>: Easily rescan one directory to find new files or apply a different filter without reloading the whole page.</li>
                    <li><strong>Advanced filters</strong>: Find any file by customizing all default filters, search for a specific file type (images, videos, etc.), skip by tailor-made rules, or enter any custom pattern.</li>
                    <li><strong>Schedule automatic imports</strong>: Select a desired interval and let the plugin automatically import any new files it finds.</li>
                    <li><strong>Import logs</strong>: View the history of manual or scheduled imports.</li>
                    <li><strong>Limit plugin access</strong>: Limit plugin access to a specific role.</li>
                </ul>
            </div>
            <?php
        }


        /**
         * Prepare plugin settings
         *
         * @since 1.1.0
         * @return void|boolean
         */
        static public function media_sync_options_setup()
        {
            if ( !current_user_can( 'manage_options' ) ) {
                return false;
            }

            // From this plugin
            add_option( 'ms_sg_use_dry_run', 1 );
            add_option( 'ms_sg_file_post_date', 'default' );
            register_setting( 'media-sync-settings-group', 'ms_sg_use_dry_run' );
            register_setting( 'media-sync-settings-group', 'ms_sg_file_post_date' );

            // From Add-On
            do_action('media_sync_extended_advanced_options_register');

            // Delete options that are no longer used
            // delete_option('ms_sg_scan_sub_dir');
            delete_option('ms_sg_use_debug');
        }


        /**
         * Render plugin settings
         *
         * @since 1.1.0
         * @return void|boolean
         */
        static public function media_sync_options_page()
        {
            if ( !current_user_can( 'manage_options' ) ) {
                return false;
            }
            ?>
            <div class="wrap media-sync-page-settings">
                <h1><?= __('Media Sync Settings', 'media-sync') ?></h1>

                <form method="post" action="options.php">
                    <?php
                    // From this plugin
                    settings_fields( 'media-sync-settings-group' );
                    do_settings_sections( 'media-sync-settings-group' );
                    ?>

                    <table class="form-table">
                        <tbody>
                            <?php
                            // From this plugin
                            ?>
                            <tr>
                                <th scope="row">
                                    <label><?= __('Dry Run', 'media-sync') ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <label for="ms-sg-use-dry-run">
                                            <?php $checked = checked( 1, get_option( 'ms_sg_use_dry_run' ), false ); ?>
                                            <input type="checkbox" name="ms_sg_use_dry_run" id="ms-sg-use-dry-run" value="1" <?= $checked ?>>
                                            <?= __('Show "Dry Run" option', 'media-sync') ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?= __('Preselected / default value for file date', 'media-sync') ?></label>
                                </th>
                                <td>
                                    <?php self::media_sync_render_post_date_option( 'ms_sg_file_post_date' ) ?>
                                </td>
                            </tr>
                            <?php
                            // From Add-On
                            do_action('media_sync_extended_advanced_options_render');
                            ?>
                        </tbody>
                    </table>

                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
        }


        /**
         * Render option for selecting post date
         *
         * @since 1.1.0
         * @param string $name Field name
         * @return void
         */
        static public function media_sync_render_post_date_option($name)
        {
            $selected = self::sanitize_input_string(INPUT_GET, 'file_post_date');
            if (empty($selected)) {
                $selected = get_option( 'ms_sg_file_post_date' );
            }
            if (empty($selected)) {
                $selected = 'default';
            }
            ?>
            <fieldset>
                <div class="import-option-date-type-item">
                    <label>
                        <input type="radio" name="<?= $name ?>" value="default" <?= checked( 'default', $selected ) ?>/>
                        <span><?= __('Default', 'media-sync') ?></span>
                    </label>
                    <p class="description"><?= __('Defaults to what WordPress uses - current time.', 'media-sync') ?></p>
                </div>
                <div class="import-option-date-type-item">
                    <label>
                        <input type="radio" name="<?= $name ?>" value="file_time" <?= checked( 'file_time', $selected ) ?>/>
                        <span><?= __('File time', 'media-sync') ?></span>
                    </label>
                    <p class="description"><?= __('File modification timestamp - when it was last modified.', 'media-sync') ?></p>
                </div>
                <div class="import-option-date-type-item">
                    <label>
                        <input type="radio" name="<?= $name ?>" value="smart_file_time" <?= checked( 'smart_file_time', $selected ) ?>/>
                        <span><?= __('Smart file time', 'media-sync') ?></span>
                    </label>
                    <p class="description"><?= sprintf(__('If file timestamp does not match the folder in which it is - it will construct the date/time based on the folder: %s.', 'media-sync'), '{year}-{month}-01 00:00:00') ?></p>
                </div>
            </fieldset>
            <?php
        }


        /**
         * Render table header and footer
         *
         * @since 0.1.0
         * @param string $tag [thead|tfoot]
         * @return void
         */
        static public function media_sync_render_thead_tfoot_row($tag)
        {
            $cb_id = 'cb-select-all-' . ($tag == 'thead' ? '1' : '2');
            ?>
            <<?= $tag ?>>
                <tr>
                    <td class="manage-column check-column check-column-all"<?= $tag == 'thead' ? ' id="cb"':''?>>
                        <label class="screen-reader-text" for="<?= $cb_id ?>"><?= __('Select All', 'media-sync') ?></label>
                        <input id="<?= $cb_id ?>" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-title column-primary"<?= $tag == 'thead' ? ' id="title"':''?>>
                        <span><?= __('File', 'media-sync') ?></span>
                    </th>
                </tr>
            </<?= $tag ?>>
            <?php
        }


        /**
         * Render table row for each file or directory
         *
         * @since 0.1.0
         * @param array $item
         * @return void
         */
        static public function media_sync_render_row($item)
        {
            $has_file_id = isset($item['file_id']) && $item['file_id'] !== false;
            $url = $has_file_id ? esc_url(add_query_arg(array('post' => $item['file_id'], 'action' => 'edit'), get_admin_url(null, 'post.php'))) : $item['url'];
            $url_attr = $item['is_dir'] !== true ? ' target="_blank"' : '';
            $count_children = $item['count_children'];

            $cls = 'media-sync-list-file';
            $cls .= ' is-' . ($item['is_dir'] === true ? 'dir' : 'file');
            $cls .= ' level-' . $item['level'];
            $cls .= ' is-first-level-' . ($item['level'] === 1 ? 'yes' : 'no');
            if ($item['is_dir'] !== true) {
                $cls .= ' is-in-db-' . ($has_file_id ? 'yes' : 'no');
            }

            // This can be made optional, for a bit different UI
            $toggle_arrows = true;
            if ($toggle_arrows) {
                $is_link = $item['is_dir'] !== true;
                $cls .= ' toggle-arrows-yes';
            } else {
                $is_link = $item['is_dir'] !== true || $count_children > 0;
                $url_attr .= ' class="js-toggle-row"';
            }

            $is_trash = isset($item['file_status']) && $item['file_status'] === 'trash';
            ?>

            <tr class="<?= $cls ?>" id="<?= $item['row_id'] ?>" data-parent-id="<?= $item['parent_id'] ?>">
                <th scope="row" class="check-column">
                    <label class="screen-reader-text" for="cb-select-<?= $item['row_id'] ?>"></label>
                    <input type="checkbox" class="js-checkbox" id="cb-select-<?= $item['row_id'] ?>"
                           value="<?= $item['absolute_path'] ?>" data-row-id="<?= $item['row_id'] ?>">
                </th>
                <td class="title column-title has-row-actions column-primary" data-colname="<?= __('File', 'media-sync') ?>">
                    <?php if (!empty($item['parents'])) : ?>
                        <span class="media-sync-parents">
                            <?php foreach ($item['parents'] as $parent_key => $parent) : ?>
                                <?php
                                $parent_cls = 'media-sync-parent';
                                $parent_cls .= ' is-first-' . ($parent_key == 0 ? 'yes' : 'no');
                                $parent_cls .= ' is-last-' . ($parent_key + 1 == count($item['parents']) ? 'yes' : 'no');
                                ?>
                                <span class="<?= $parent_cls ?>"><i></i></span>
                            <?php endforeach; ?>
                            <span class="clearfix"></span>
                        </span>
                    <?php endif; ?>

                    <?php if ($toggle_arrows && $item['is_dir'] === true) : ?>
                        <span class="js-toggle-row media-sync-toggle-row dashicons"></span>
                    <?php endif; ?>

                    <?= $is_link ? '<a href="' . $item['url'] . '"' . $url_attr . '>' : '' ?>
                    <?php if ($item['is_dir'] === true) : ?>
                        <span class="dashicons dashicons-category"></span>
                    <?php endif; ?>
                        <span class="media-sync-file-name">
                            <?= $item['display_name'] ?>
                        </span>
                    <?= $is_link ? '</a>' : '' ?>

                    <?php if ($item['is_dir'] === true && $count_children !== null) : ?>
                        <span class="media-sync-num-items"><?= sprintf('(%u %s)', $count_children, $count_children == 1 ? __('item', 'media-sync') : __('items', 'media-sync')) ?></span>
                    <?php endif; ?>

                    <?php if ($has_file_id) : ?>
                        <span class="media-sync-already-in-db"> - <?= __('Already in', 'media-sync') ?>
                            <a href="<?= $url ?>" class="dashicons dashicons-admin-media" target="_blank"></a>
                            <?= $is_trash ? ' (' . __('In Trash', 'media-sync') . ')' : '' ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>

            <?php
            if (!empty($item['children'])) :
                foreach ($item['children'] as $child_item) :
                    self::media_sync_render_row($child_item);
                endforeach;
            endif;
        }


        /**
         * Ajax action to import selected files
         *
         * @since 0.1.0
         * @return string
         */
        static public function media_sync_import_files()
        {
            // Register shutdown function to catch fatal errors (e.g. memory exhausted)
            register_shutdown_function(function() {
                $message = self::media_sync_get_fatal_error_message();
                if ( ! empty( $message ) ) {
                    wp_send_json_error(
                        __( 'Server has reached limits that prevented importing all selected files.', 'media-sync' ) . ' ' .
                        $message . ' ' .
                        sprintf(
                            __( '%s solves this by processing files in smaller batches.', 'media-sync' ),
                            '<a href="https://mediasyncplugin.com/?utm_source=base_plugin_error&utm_medium=scan_page&utm_campaign=esp" target="_blank">Media Sync Pro</a>'
                        )
                    );
                }
            });

            if(!MediaSync::media_sync_user_has_general_access()) {
                wp_send_json_error( __( 'You do not have sufficient permissions to access this page.', 'media-sync' ) );
            }

            check_ajax_referer( 'media_sync_import_files', 'security' );

            // Get database stuff
            global $wpdb;

            $results = array();

            // We can't apply sanitize_text_field here because file names might contain special characters (e.g. հօվիկ.jpg)
            if(isset($_POST['media_items']) && !empty($_POST['media_items'])) {

                // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
                require_once( ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'image.php' );


                $files_in_db = self::media_sync_get_files_in_db();

                $post_date_type = self::sanitize_input_string(INPUT_POST, 'file_post_date');
                if (empty($post_date_type)) {
                    $post_date_type = get_option( 'ms_sg_file_post_date', 'default' );
                }
                $dry_run = self::filter_input_boolean(INPUT_POST, 'dry_run');

                foreach ($_POST['media_items'] as $media_item) {

                    if(isset($media_item['file']) && !empty($media_item['file'])) {

                        // This comes from JS and it's taken from checkbox value, which is $item['absolute_path'] from media_sync_get_list_of_files()
                        $absolute_path = urldecode($media_item['file']);

                        $validated_path = self::media_sync_validate_path($absolute_path);
                        if ($validated_path === false) {
                            $results[] = array(
                                'row_id' => $media_item['row_id'],
                                'inserted' => false,
                                'errorMessage' => __('Invalid file path.', 'media-sync')
                            );
                            continue;
                        }
                        $absolute_path = $validated_path;

                        $relative_path = self::media_sync_url_encode(self::media_sync_get_relative_path($absolute_path));

                        // It's quicker to get all files already in db and check that array, than to do this query for each file
                        // $query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE guid LIKE '%{$relative_path}'";
                        // $is_in_db = intval($wpdb->get_var($query)) > 0;

                        $is_in_db = isset($files_in_db[$relative_path]) && !empty($files_in_db[$relative_path]);

                        // Check if file is already in database
                        if(!$is_in_db) {

                            // Prepare data to be saved to `wp_posts` and `wp_postmeta`
                            $attachment = self::media_sync_prepare_attachment_data($absolute_path, $relative_path, $post_date_type);

                            if (!$attachment || isset($attachment['error']) || isset($attachment['errorMessage'])) {
                                // It's probably better to just continue importing other files
                                // echo json_encode($attachment);
                                // wp_die();

                                // This will mark this file as failed and show error below file name
                                $results[] = array(
                                    'row_id' => $media_item['row_id'],
                                    'inserted' => false,
                                    'error' => isset($attachment['error']) ? $attachment['error'] : null,
                                    'errorMessage' => isset($attachment['errorMessage']) ? $attachment['errorMessage'] : null
                                );
                                continue;
                            }

                            // If we're actually importing (creating database records)
                            if(!$dry_run) {

                                // Import file to database (`wp_posts` and `wp_postmeta`)
                                $import_response = self::media_sync_update_import_to_database($attachment, $absolute_path);

                                // Response is an array if there are errors or true if success
                                $is_inserted = $import_response === true;

                                // It's probably better to just continue importing other files
                                //if (!$is_inserted) {
                                //    echo json_encode($import_response);
                                //    wp_die();
                                //}

                                $results[] = array(
                                    'row_id' => $media_item['row_id'],
                                    'inserted' => $is_inserted,
                                    'error' => isset($import_response['error']) ? $import_response['error'] : null,
                                    'errorMessage' => isset($import_response['errorMessage']) ? $import_response['errorMessage'] : null
                                );
                            } else {
                                $results[] = array(
                                    'row_id' => $media_item['row_id'],
                                    'inserted' => true
                                );
                            }
                        }
                    }
                }
            }

            echo json_encode(array(
                'results' => $results
            ));

            wp_die(); // Must have for Ajax calls
        }


        /**
         * Find mime type for file being imported
         *
         * @since 1.2.3
         *
         * @param string $absolute_path Absolute path to the file being imported
         * @return string|array
         */
        static public function media_sync_get_mime_type($absolute_path)
        {
            try {
                $mime_type = '';
                if (function_exists('mime_content_type')) {
                    $mime_type = mime_content_type($absolute_path);
                }

                if (!empty($mime_type)) {
                    return $mime_type;
                }

                $file_type = wp_check_filetype(basename($absolute_path), null);

                if(!$file_type['type']) {
                    return array(
                        'errorMessage' => sprintf(__('Invalid mime type for file: %s.', 'media-sync'), $absolute_path),
                    );
                }

                return $file_type['type'];

            } catch (Exception $e) {
                return array(
                    'errorMessage' => sprintf(__('Error getting mime type for file: %s.', 'media-sync'), $absolute_path),
                    'error' => $e->getMessage()
                );
            }
        }

        /**
         * Prepare data to be imported to `wp_posts` table
         *
         * @since 1.2.2
         *
         * @param string $absolute_path Absolute path to the file being imported
         * @param string $relative_path Path relative to the site URL
         * @param string $post_date_type How to generate post date
         * @return array
         */
        static public function media_sync_prepare_attachment_data($absolute_path, $relative_path, $post_date_type) {
            try {
                $mime_type = self::media_sync_get_mime_type( $absolute_path );

                if ( isset( $mime_type['error'] ) || isset( $mime_type['errorMessage'] ) ) {
                    return $mime_type;
                }

                $decoded_relative_path = urldecode( $relative_path );

                $title = self::media_sync_get_file_title( $absolute_path, $mime_type );

                // Prepare an array of post data for the attachment.
                $attachment = array(
                    'guid'           => get_site_url() . $decoded_relative_path,
                    'post_mime_type' => $mime_type,
                    'post_title'     => $title ?: preg_replace( '/\.[^.]+$/', '', basename( $decoded_relative_path ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );

                // Try to get post date based on settings - "Default" option will not set anything, so WP can use defaults
                $post_date = self::media_sync_post_date( $absolute_path, $post_date_type );

                if ( ! empty( $post_date ) ) {
                    $attachment['post_date']     = $post_date;
                    $attachment['post_date_gmt'] = $post_date;
                }

                return $attachment;
            } catch ( Exception $e ) {
                return array(
                    'errorMessage' => sprintf( __( 'Error preparing attachment data for file: %s.', 'media-sync' ), $absolute_path ),
                    'error'        => $e->getMessage()
                );
            }
        }


        /**
         * Try to extract an embedded title from a file's metadata.
         * Returns null if no title is found, so the caller can fall back to the filename.
         *
         * @since 1.5.2
         * @param string $absolute_path Absolute path to the file
         * @param string $mime_type MIME type of the file
         * @return string|null
         */
        static private function media_sync_get_file_title($absolute_path, $mime_type) {
            try {
                $is_image = strpos( $mime_type, 'image/' ) === 0;
                if ( $is_image ) {
                    return self::media_sync_get_image_title( $absolute_path );
                }
                if ( $mime_type === 'application/pdf' ) {
                    return self::media_sync_get_pdf_title( $absolute_path );
                }
            } catch ( Exception $e ) {
            }

            return null;
        }


        /**
         * Extract title from image EXIF metadata.
         * Checks XPTitle (Windows, UTF-16LE) then ImageDescription.
         *
         * @since 1.5.2
         * @param string $path Absolute path to the image file
         * @return string|null
         */
        static private function media_sync_get_image_title($path) {
            $exif_available = function_exists( 'exif_read_data' );
            if ( ! $exif_available ) {
                return null;
            }

            $exif          = @exif_read_data( $path );
            $exif_is_valid = is_array( $exif );
            if ( ! $exif_is_valid ) {
                return null;
            }

            $xp_title = ! empty( $exif['XPTitle'] ) ? $exif['XPTitle'] : null;
            if ( $xp_title !== null ) {
                $title = trim( mb_convert_encoding( $xp_title, 'UTF-8', 'UTF-16LE' ) );
                if ( ! empty( $title ) ) {
                    return $title;
                }
            }

            $image_description = ! empty( $exif['ImageDescription'] ) ? trim( $exif['ImageDescription'] ) : null;
            if ( ! empty( $image_description ) ) {
                return $image_description;
            }

            return null;
        }


        /**
         * Extract title from PDF XMP metadata or info dictionary.
         * Reads the first 128KB. XMP is tried first as it is more reliably near the start of the file.
         *
         * @since 1.5.2
         * @param string $path Absolute path to the PDF file
         * @return string|null
         */
        static private function media_sync_get_pdf_title($path) {
            $content          = @file_get_contents( $path, false, null, 0, 131072 );
            $content_is_empty = $content === false || $content === '';
            if ( $content_is_empty ) {
                return null;
            }

            // XMP metadata stream: <dc:title> block (checked first more reliably near start of file)
            $has_xmp = preg_match( '/<dc:title>.*?<rdf:li[^>]*>([^<]+)<\/rdf:li>.*?<\/dc:title>/s', $content, $xmp_matches );
            if ( $has_xmp ) {
                $title = trim( html_entity_decode( $xmp_matches[1], ENT_XML1 | ENT_QUOTES, 'UTF-8' ) );
                if ( ! empty( $title ) ) {
                    return $title;
                }
            }

            // Info dictionary literal string: /Title (value)
            $has_literal = preg_match( '/\/Title\s*\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', $content, $literal_matches );
            if ( $has_literal ) {
                $raw           = $literal_matches[1];
                $raw           = preg_replace_callback( '/\\\\([nrtbf()\\\\]|[0-7]{1,3})/', function ( $escape_match ) {
                    $char = $escape_match[1];
                    $map  = [
                        'n'  => "\n",
                        'r'  => "\r",
                        't'  => "\t",
                        'b'  => "\x08",
                        'f'  => "\x0C",
                        '('  => '(',
                        ')'  => ')',
                        '\\' => '\\'
                    ];

                    return isset( $map[ $char ] ) ? $map[ $char ] : chr( octdec( $char ) );
                }, $raw );
                $has_utf16_bom = strlen( $raw ) >= 2 && "\xFE\xFF" === substr( $raw, 0, 2 );
                if ( $has_utf16_bom ) {
                    $raw = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16BE' );
                }
                $title = trim( $raw );
                if ( ! empty( $title ) ) {
                    return $title;
                }
            }

            // Info dictionary hex string: /Title <hex>
            $has_hex = preg_match( '/\/Title\s*<([0-9a-fA-F\s]+)>/', $content, $hex_matches );
            if ( $has_hex ) {
                $hex = preg_replace( '/\s/', '', $hex_matches[1] );
                $raw = @hex2bin( $hex );
                if ( $raw ) {
                    $has_utf16_bom = strlen( $raw ) >= 2 && "\xFE\xFF" === substr( $raw, 0, 2 );
                    if ( $has_utf16_bom ) {
                        $raw = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16BE' );
                    }
                    $title = trim( $raw );
                    if ( ! empty( $title ) ) {
                        return $title;
                    }
                }
            }

            return null;
        }


        /**
         * Import file to database (`wp_posts` and `wp_postmeta`)
         *
         * @since 1.2.1
         *
         * @param array $attachment Data for `wp_posts` table
         * @param string $absolute_path Absolute path to the file being imported. Decoded.
         * @return array|true
         */
        static public function media_sync_update_import_to_database($attachment, $absolute_path)
        {
            if (!stream_resolve_include_path($absolute_path)) {
                return array(
                    'errorMessage' => sprintf(__('File to import not found at path: %s.', 'media-sync'), $absolute_path)
                );
            }

            // Insert the attachment (`wp_posts` table)
            try {
                $max_execution_time = ini_get('max_execution_time');
                if ($max_execution_time) {
                    // Reset execution time so that each file can be processed
                    set_time_limit($max_execution_time);
                }

                $attach_id = wp_insert_attachment($attachment, $absolute_path, 0, true);
            } catch (Exception $e) {
                return array(
                    'errorMessage' => sprintf(__('Error inserting attachment (`wp_posts` table) for file: %s.', 'media-sync'), $absolute_path),
                    'error' => $e->getMessage()
                );
            }

            if (!($attach_id > 0)) {
                return array(
                    'errorMessage' => sprintf(__('Attach ID not received for inserted attachment (`wp_posts` table) for file: %s.', 'media-sync'), $absolute_path)
                );
            }

            try {
                // Create custom meta tag (in `wp_postmeta` table) so that we later know this file was imported using this plugin
                $media_sync_metadata = 1;
                $media_sync_metadata = apply_filters('media_sync_filter_before_update_msc_metadata', $media_sync_metadata, $attach_id, $absolute_path);
                if ($media_sync_metadata) {
                    add_post_meta($attach_id, '_msc', $media_sync_metadata, true);
                }
            } catch (Exception $e) {}

            try {
                // Generate the metadata for the attachment
                $attach_data = wp_generate_attachment_metadata($attach_id, $absolute_path);
            } catch (Exception $e) {
                return array(
                    'errorMessage' => sprintf(__('Error generating attachment metadata (`wp_postmeta` table) for file: %s.', 'media-sync'), $absolute_path),
                    'error' => $e->getMessage()
                );
            }

            if (!$attach_data || empty($attach_data)) {
                return array(
                    'errorMessage' => sprintf(__('Attachment metadata could not be generated from %s.', 'media-sync'), $absolute_path),
                    'error' => json_encode($attachment)
                );
            }

            try {
                /**
                 * Apply this filter to collect additional metadata and/or to run some additional actions,
                 * e.g. to "auto-connect" items to pages, posts, and WooCommerce products.
                 * Returning empty data will skip updating metadata (wp_update_attachment_metadata),
                 * so this filter can also be used to totally overwrite updating attachment metadata.
                 *
                 * This filter can be used in a number of different ways:
                 * 1. to collect additional metadata,
                 * 2. to run some additional custom actions
                 *   (e.g. to "auto-connect" items to pages, posts, and WooCommerce products),
                 * 3. to skip or completely overwrite updating the metadata (`wp_update_attachment_metadata` function),
                 *   when this filter returns empty data (null).
                 *
                 * NOTE:
                 * Things could break if the filter doesn't return proper attach data
                 * and it hasn't created/updated the `wp_postmeta` table record (meta_key _wp_attachment_metadata).
                 *
                 * @since 1.2.5
                 *
                 * @param array $attach_data Data received from WP function: wp_generate_attachment_metadata
                 * @param int $attach_id
                 * @return array|null
                 */
                $attach_data = apply_filters('media_sync_filter_before_update_metadata', $attach_data, $attach_id);
            } catch (Exception $e) {
                return array(
                    'errorMessage' => sprintf(__('Error processing filter media_sync_filter_before_update_metadata for file: %s.', 'media-sync'), $absolute_path),
                    'error' => $e->getMessage()
                );
            }

            // There is this same validation (if) before "media_sync_filter_before_update_metadata" filter,
            // so if we end up here, it's because of that filter. Which either already updated metadata,
            // or doesn't want to update it (basically telling us not to run wp_update_attachment_metadata).
            if (!$attach_data || empty($attach_data)) {
                return true;
            }

            try {
                // Update `wp_postmeta` database record
                wp_update_attachment_metadata($attach_id, $attach_data);
            } catch (Exception $e) {
                return array(
                    'errorMessage' => sprintf(__('Error updating attachment metadata (`wp_postmeta` table) for file: %s.', 'media-sync'), $absolute_path),
                    'error' => $e->getMessage()
                );
            }

            return true;
        }


        /**
         * Returns post date for file being imported based on selected option
         * 
         * @since 1.1.0
         * @param string $file_path Absolute path to file being imported
         * @param string $type Selected option telling which date to find [default|file_time|smart_file_time]
         * @return string
         */
        static public function media_sync_post_date($file_path, $type)
        {
            // Default is empty string so that WordPress can set its default value (current date)
            $post_date = '';

            try {

                // Take "time modified" of file being imported
                $file_timestamp = filemtime( $file_path );
                if($file_timestamp) {

                    // Convert to datetime
                    $file_time = date( 'Y-m-d H:i:s', $file_timestamp );
                    if($file_time) {

                        // For "File time" option - return file time
                        if($type === 'file_time') {
                            $post_date = $file_time;
                        }

                        // For "Smart file time" option - determine post date by comparing file's modified date with folder in which it is
                        if($type === 'smart_file_time') {
                            // Use file time as a fallback
                            $post_date = $file_time;

                            // Compare the file date with the folder date structure
                            // Try to extract year and month from file path (e.g. "2020/02" or "2020\02")
                            // But this path is now always using forward slash (even on Windows Server), so we can again use just '/(\d{4})\/(\d{2})/'
                            preg_match('/(\d{4})[\/|\\\\](\d{2})/', $file_path, $matches);
                            if ( !empty($matches) ) {
                                $year_and_month = $matches[1] . '-' . $matches[2];
                                $folder_time = $year_and_month . '-' . '01' . ' 00:00:00';

                                // If what we found for folder date is a date (double check for regex above)
                                if ( date('Y-m-d H:i:s', strtotime( $folder_time )) == $folder_time ) {

                                    // If file's year and month values do not match with folder's year and month
                                    if ( date( 'Y-m', $file_timestamp ) != $year_and_month ) {
                                        // Use the folder date instead
                                        $post_date = $folder_time;
                                    }
                                }
                            }
                        }
                    }
                }


                // Receive external function for setting custom post date
                $external = apply_filters('media_sync_filter_post_date', $post_date, $file_path, $type );

                // Return received custom post date if it's empty string or a date
                if( is_string($external) && ($external === '' || ( date('Y-m-d H:i:s', strtotime( $external )) == $external) ) ) {
                    $post_date = $external;
                }

                return $post_date;
            } catch ( Exception $e ) {
                return $post_date;
            }
        }


        /**
         * Get path of directory used by this plugin as main import directory (uploads dir).
         *
         * Not normalizing dir path anymore (keeping backslashes on Windows Server),
         * to avoid issues with relative path when importing.
         *
         * @since 1.1.3
         * @return string|null
         */
        static private function media_sync_get_uploads_basedir()
        {
            $upload_dir = wp_get_upload_dir();

            if(!($upload_dir && isset($upload_dir['basedir']) && !empty($upload_dir['basedir']))) {
                return null;
            }

            return $upload_dir['basedir'];
        }


        /**
         * Validate that a path resolves within the uploads directory.
         *
         * @since 1.5.0
         * @param string $path Absolute path to validate
         * @return string|false Resolved canonical path, or false if invalid
         */
        static private function media_sync_validate_path($path)
        {
            // Reject stream wrappers e.g. ftp://, php://
            if (strpos($path, '://') !== false) {
                return false;
            }

            // Reject path traversal sequences
            if (strpos($path, '..') !== false) {
                return false;
            }

            // Resolve canonical uploads base path
            $uploads_basedir = realpath(self::media_sync_get_uploads_basedir());
            if ($uploads_basedir === false) {
                return false;
            }

            // Resolve canonical path (also confirms the path exists on disk)
            $resolved = realpath($path);
            if ($resolved === false) {
                return false;
            }

            // Confirm resolved path is within uploads
            if (strpos($resolved, $uploads_basedir) !== 0) {
                return false;
            }

            return $path;
        }


        /**
         * Get path absolute to WP root. Always using forward slashes.
         *
         * e.g. /var/www/WP/wp-content/uploads -> /wp-content/uploads
         * e.g. C:/www/WP/wp-content/uploads -> /wp-content/uploads
         *
         * @since 1.1.3
         * @param string $absolute_path Absolute file path. Will be converted to forward slashes.
         * @return string
         */
        static private function media_sync_get_relative_path($absolute_path)
        {
            // Since get_home_path() and WP in general always use forward slashes, we need to convert it as well
            $absolute_path = wp_normalize_path($absolute_path);

            // Always using forward slash
            return str_replace(get_home_path(), '/', $absolute_path);
        }


        /**
         * Scan "uploads" directory and return recursive list of files and directories
         *
         * @since 0.1.0
         * @return Generator
         */
        static private function media_sync_get_list_of_uploads()
        {
            self::$start_time = microtime(true);

            self::$upload_dir_path = self::media_sync_get_uploads_basedir();
            if(!self::$upload_dir_path) {
                return array();
            }

            // Limit scanning to specific sub folder or encoded path (e.g. &sub_dir=2020%2F01)
            $sub_dir = self::sanitize_input_string(INPUT_GET, 'sub_dir');
            if ($sub_dir) {
                $sub_dir_validated = self::media_sync_validate_path( self::$upload_dir_path . '/' . $sub_dir );
                if ( $sub_dir_validated === false ) {
                    return array();
                }
                self::$upload_dir_path = $sub_dir_validated;
            }

            if(empty(self::$files_in_db)) {
                self::$files_in_db = self::media_sync_get_files_in_db();
            }

            $associated_filter = self::sanitize_input_string(INPUT_GET, 'associated-filter');
            $is_missing_from_ml_filter = $associated_filter && urldecode($associated_filter) === 'missing_from:media_library';

            // Clear cached files (affecting file_exists, stream_resolve_include_path, etc.)
            clearstatcache();

            // Get all files - returning Generator (not Array)
            return self::media_sync_get_list_of_files(self::$upload_dir_path, self::$files_in_db, $is_missing_from_ml_filter);
        }


        /**
         * Scan directory (passed as first value) and return recursive list of files and directories
         *
         * @since 0.1.0
         * @param string $current_dir_path Changing recursively for each directory that gets iterated
         * @param array $files_in_db List of files that are already in database
         * @param bool $is_missing_from_ml_filter Filter by "association"
         * @return Generator
         */
        static private function media_sync_get_list_of_files($current_dir_path, $files_in_db, $is_missing_from_ml_filter)
        {
            if(!self::$upload_dir_path) {
                yield null;
            }

            // "stream_resolve_include_path" is like "file_exists" but should be quicker
            if( !stream_resolve_include_path($current_dir_path) ) {
                yield null;
            }

            foreach (scandir($current_dir_path) as $key => $file_name) {
                // Skip ".", "..", etc.
                if ($file_name == '.' || $file_name == '..' || $file_name == ".DS_Store" || $file_name == ".htaccess" || $file_name == "index.php") {
                    continue;
                }

                $full_path = $current_dir_path . '/' . $file_name;

                $isDir = is_dir($full_path);

                // Default file skipping rules:
                // 1. contains image size at the end (e.g. -100x100.jpg), or
                // 2. ends with "-scaled" (also WP generated), or
                // 3. retina thumbnail (e.g. -100x100@2x.jpg), or
                // 4. has .webp after another file type (e.g. .jpg.webp).
                $is_ignored = preg_match('/(-scaled|[_-]\d+x\d+)|@[2-6]x(?=\.[a-z]{3,4}$)|\.[a-z]{3,4}.webp/im', $file_name) == true;

                // Receive external rules for skipping files/folders
                $is_ignored_external = apply_filters('media_sync_filter_is_scan_object_ignored', $is_ignored, $full_path, $file_name);

                // Take custom rule for skipping files/folders from external hook/filter function
                if (is_bool($is_ignored_external)) {
                    $is_ignored = $is_ignored_external;
                }


                if ($is_ignored) {
                    continue;
                }


                $relative_path = self::media_sync_url_encode(self::media_sync_get_relative_path($full_path));
                $file_in_db = isset($files_in_db[$relative_path]) && !empty($files_in_db[$relative_path]) ?
                    $files_in_db[$relative_path] : false;
                $file_id = $file_in_db && !empty($file_in_db['id']) ? $file_in_db['id'] : false;
                $file_status = $file_in_db && !empty($file_in_db['status']) ? $file_in_db['status'] : false;

                if (!$isDir && $is_missing_from_ml_filter && $file_id !== false) {
                    continue;
                }


                // Ugly, but efficient
                $children = array();

                if ($isDir) {
                    $children = self::media_sync_get_list_of_files($full_path, $files_in_db, $is_missing_from_ml_filter);

                    if (empty($children)) {
                        continue;
                    }
                }

                // Get current parents, to get for example: "/2012/03"
                $parents_path = str_replace(self::$upload_dir_path, '', $current_dir_path);

                // Trim first slash, to get for example: "2012/03"
                $parents_path = ltrim($parents_path, '/');

                // Since this path is always using forward slashes, that's what we'll use here
                $parents = !empty($parents_path) ? explode('/', $parents_path) : array();

                $item = array(
                    'display_name' => $file_name,
                    'is_dir' => !!$isDir,
                    'level' => count($parents) + 1,
                    'row_id' => self::get_row_id( self::media_sync_url_encode( $parents_path . '/' . $file_name ) ),
                    'parent_id' => self::get_row_id( self::media_sync_url_encode( $parents_path ) ),
                    'parents' => $parents,
                    'absolute_path' => self::media_sync_url_encode($full_path)
                );

                if ($isDir) {
                    $item['url'] = 'javascript:;';
                    $item['children'] = $children;
                    // Not so easy when using generators (yield)
                    $item['count_children'] = null;
                } else {
                    $item['url'] = get_site_url() . $relative_path;
                    $item['file_id'] = $file_id;
                    $item['file_status'] = $file_status;
                    $item['children'] = [];
                    $item['count_children'] = 0;
                }

                yield $item;
            }
        }


        /**
         * Get row id from path.
         * Cleanup since this will be used as HTML attribute.
         *
         * @param string $encoded_path
         * @return string
         * @since 1.4.5
         */
        static public function get_row_id(string $encoded_path): string {
            if ( empty( $encoded_path ) ) {
                return "";
            }

            return 'msc-' . str_replace( '.', '-', sanitize_file_name( $encoded_path ) );
        }


        /**
         * Convert special characters to safe characters but keeping forward slash.
         *
         * @param $url
         * @return string
         * @since 1.2.4
         */
        static private function media_sync_url_encode($url)
        {
            return str_replace('%2F', '/', rawurlencode($url));
        }


        /**
         * Get list of files that are already in database
         *
         * Caching does not seem to work, disabled for now
         *
         * @since 0.1.0
         * @return array
         */
        static private function media_sync_get_files_in_db()
        {
            $media_query = new WP_Query( array(
                'post_type'      => 'attachment',
                'post_status'    => array( 'inherit', 'trash' ),
                'posts_per_page' => - 1
            ) );

            $upload_dir_path          = self::media_sync_get_uploads_basedir();
            $upload_dir_relative_path = self::media_sync_get_relative_path( $upload_dir_path );

            $files = array();
            foreach ( $media_query->posts as $post ) {

                $file_path = get_post_meta( $post->ID, '_wp_attached_file', true );
                if ( empty( $file_path ) ) {
                    continue;
                }

                // e.g. /2012/03/img space.jpg
                $short_relative_path = '/' . $file_path;

                // e.g. /wp-content/uploads/2012/03/img%20space.jpg
                $relative_path = self::media_sync_url_encode( $upload_dir_relative_path . $short_relative_path );

                $file = array(
                    'id'     => $post->ID,
                    'name'   => $post->post_title,
                    'status' => $post->post_status
                );

                $files[ $relative_path ] = $file;

                // Path to current file without file name
                $base_path = self::get_base_path( $file_path );

                // For large images - WordPress creates resized versions ("-scaled" at the end of file)
                // https://make.wordpress.org/core/2019/10/09/introducing-handling-of-big-images-in-wordpress-5-3/
                // So we also need to find and treat original file as "file in db"
                //
                // WordPress 7.1 client-side media processing also writes companion basenames into
                // metadata: the HEIC/HEIF original kept beside its JPEG derivative, and the MP4 and
                // poster JPEG generated from an animated GIF. All four keys resolve the same way.
                $meta = wp_get_attachment_metadata( $post->ID );
                foreach (
                    array(
                        'original_image',
                        'source_image',
                        'animated_video',
                        'animated_video_poster'
                    ) as $companion_key
                ) {
                    if ( ! empty( $meta[ $companion_key ] ) && is_string( $meta[ $companion_key ] ) ) {
                        $companion_path           = self::media_sync_url_encode( $upload_dir_relative_path . $base_path . $meta[ $companion_key ] );
                        $files[ $companion_path ] = $file;
                    }
                }

                // Check if this is an edited image (crop, rotate, etc. in Media Library) and treat all those edits as "files in db"
                $backup_sizes = get_post_meta( $post->ID, '_wp_attachment_backup_sizes', true );
                if ( ! empty( $backup_sizes ) ) {
                    foreach ( $backup_sizes as $backup_size ) {
                        $backup_size_path           = self::media_sync_url_encode( $upload_dir_relative_path . $base_path . $backup_size['file'] );
                        $files[ $backup_size_path ] = $file;
                    }
                }
            }

            return $files;
        }


        /**
         * Take base path from provided file path.
         *
         * @param string $file_path
         * @return string
         * @since 1.3.3
         */
        static private function get_base_path($file_path)
        {
            $base_path = pathinfo($file_path, PATHINFO_DIRNAME);
            if ($base_path === '.') {
                return '/';
            } else {
                return '/' . rtrim($base_path, '/') . '/';
            }
        }


        /**
         * Parse the memory_limit variable from the php.ini file.
         *
         * @since 1.2.0
         * @return int
         */
        static private function media_sync_get_memory_limit()
        {
            $limit_string = ini_get('memory_limit');
            $unit = strtolower(mb_substr($limit_string, -1 ));
            $bytes = intval(mb_substr($limit_string, 0, -1), 10);

            switch ($unit)
            {
                case 'k':
                    $bytes *= 1024;
                    break 1;

                case 'm':
                    $bytes *= 1024 * 1024;
                    break 1;

                case 'g':
                    $bytes *= 1024 * 1024 * 1024;
                    break 1;

                default:
                    break 1;
            }

            return $bytes;
        }

        
        /**
         * Check if logged in user has access
         *
         * @since 1.1.0
         * @return boolean
         */
        static public function media_sync_user_has_general_access() 
        {
            if ( !current_user_can( 'upload_files' ) ) {
                return false;
            }

            return true;
        }


        /**
         * Sanitize a string input from GET or POST request.
         * Be careful not to strip special characters from file or directory name.
         *
         * @param int $type The input type (INPUT_GET, INPUT_POST).
         * @param string $input_name The name of the input to retrieve and sanitize.
         * @return string The sanitized string.
         * @since 1.4.1
         */
        static public function sanitize_input_string($type, $input_name)
        {
            $value = filter_input( $type, $input_name, FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ?: '';

            return ! empty( $value ) ? sanitize_text_field( $value ) : '';
        }


        /**
         * Sanitize a boolean input from GET or POST request.
         *
         * @param int $type The input type (INPUT_GET, INPUT_POST).
         * @param string $input_name The name of the input to retrieve and sanitize.
         * @return bool The sanitized boolean.
         * @since 1.4.1
         */
        static public function filter_input_boolean($type, $input_name)
        {
            return (bool) filter_input($type, $input_name, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
    }
endif; // End if class_exists check.
?>