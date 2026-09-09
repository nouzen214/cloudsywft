<?php
/**
 * Justify for Paragraph Block
 *
 * Adds justify alignment option to the Paragraph block in the Block Editor,
 * with per-block typographic options (hyphenation, word spacing, first-line
 * indent, drop cap and justify mode) available in the block sidebar.
 *
 * @package JustifyForParagraphBlock
 * @since 1.0.0
 *
 * Plugin Name: Justify for Paragraph Block
 * Plugin URI: https://www.nick-digital-projects.com/Justify-for-Paragraph-Block
 * Description: Adds justify alignment option to the Paragraph block in the Block Editor with per-block typographic enhancements.
 * Version: 1.2.1
 * Author: Nick Digital Projects
 * Author URI: http://www.nick-digital-projects.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: justify-for-paragraph-block
 */

// Prevent direct access to the plugin
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enqueue scripts for the Block Editor
 *
 * Loads the JavaScript file that adds justify alignment functionality
 * and the per-block inspector sidebar controls.
 *
 * @since 1.0.0
 * @since 1.2.0 Added wp-components dependency for InspectorControls.
 */
function jfpb_enqueue_editor_assets() {
    wp_enqueue_script(
        'jfpb-justify-alignment-script',
        plugins_url('justify-for-paragraph-block.js', __FILE__),
        array('wp-blocks', 'wp-dom-ready', 'wp-edit-post', 'wp-components', 'wp-i18n'),
        filemtime(plugin_dir_path(__FILE__) . 'justify-for-paragraph-block.js'),
        true
    );
}
add_action('enqueue_block_editor_assets', 'jfpb_enqueue_editor_assets');

/**
 * Enqueue block-specific styles for editor and frontend
 *
 * Loads the CSS file for text justification and per-block typographic
 * options. The same file is used for both editor and frontend so the
 * appearance is consistent.
 *
 * The wp-edit-blocks dependency is declared only inside the admin/editor
 * context. Declaring it unconditionally would force the whole block editor
 * stylesheet (and its wp-components dependencies) to load on the public
 * site, overriding the theme font sizes and colors.
 *
 * @since 1.0.0
 * @since 1.2.1 The wp-edit-blocks dependency is limited to the editor.
 */
function jfpb_enqueue_block_assets() {
    $deps = is_admin() ? array('wp-edit-blocks') : array();

    wp_enqueue_style(
        'jfpb-justify-editor-style',
        plugins_url('editor-style.css', __FILE__),
        $deps,
        filemtime(plugin_dir_path(__FILE__) . 'editor-style.css')
    );
}
add_action('enqueue_block_assets', 'jfpb_enqueue_block_assets');

/**
 * Add inline style for frontend text justification
 *
 * Outputs the base text-align: justify rule for the has-text-align-justify
 * class plus the per-block typographic option classes. The global settings
 * from the Settings page are applied as the base; per-block classes can
 * add additional typographic enhancements on top.
 *
 * @since 1.0.0
 * @since 1.2.0 Now outputs per-block CSS classes for the new typographic
 *              options (hyphens, word spacing, text indent, drop cap,
 *              justify mode). Global settings remain as defaults.
 */
function jfpb_add_frontend_style() {
    $css = '';

    // Base rule: text-align justify for all justified paragraphs.
    $css .= '.has-text-align-justify { text-align: justify; }';

    // Per-block typographic option classes.
    // These are additive: they apply on top of the base rule when the
    // corresponding per-block attribute is set in the editor sidebar.

    // Automatic hyphenation.
    $css .= '.has-text-align-justify.jfpb-hyphens { hyphens: auto; -webkit-hyphens: auto; }';

    // Word spacing presets.
    $css .= '.has-text-align-justify.jfpb-word-spacing-lg { word-spacing: 0.02em; }';
    $css .= '.has-text-align-justify.jfpb-word-spacing-sm { word-spacing: -0.01em; }';

    // Custom word spacing marker class. The actual word-spacing value is
    // injected as an inline style on the block wrapper by the JS, so this
    // class is only a marker for potential theme overrides.
    $css .= '.has-text-align-justify.jfpb-word-spacing-custom { }';

    // First-line indent (editorial / book style).
    $css .= '.has-text-align-justify.jfpb-text-indent { text-indent: 1.5em; }';

    // Drop cap (magazine style).
    $css .= '.has-text-align-justify.jfpb-drop-cap::first-letter { '
        . 'float: left; font-size: 3.5em; line-height: 0.8; font-weight: bold; '
        . 'margin-right: 0.1em; margin-top: 0.05em; }';

    // Justify mode.
    $css .= '.has-text-align-justify.jfpb-justify-inter-word { text-justify: inter-word; }';
    $css .= '.has-text-align-justify.jfpb-justify-inter-character { text-justify: inter-character; }';

    // Stretch last line: force the last line of a justified paragraph to be
    // full-width instead of short (text-align-last: justify).
    $css .= '.has-text-align-justify.jfpb-justify-last { text-align-last: justify; }';

    // Global settings: apply the global "advanced" defaults to all justified
    // paragraphs that do NOT have a per-block override. Per-block classes have
    // higher specificity (two classes) so they win over the global rule.
    $settings = jfpb_get_typography_settings();
    if ($settings['mode'] === 'advanced') {
        $global_rules = array();

        if ($settings['enable_hyphens']) {
            $global_rules[] = 'hyphens: auto;';
            $global_rules[] = '-webkit-hyphens: auto;';
        }

        if ($settings['word_spacing_value'] !== '0') {
            $global_rules[] = 'word-spacing: ' . esc_attr($settings['word_spacing_value']) . ';';
        }

        if (!empty($global_rules)) {
            $css .= '.has-text-align-justify { ' . implode(' ', $global_rules) . ' }';
        }
    }

    wp_add_inline_style('wp-block-library', $css);
}
add_action('wp_enqueue_scripts', 'jfpb_add_frontend_style');

/**
 * Add inline style for editor text justification
 *
 * Mirrors the frontend typographic behavior inside the block editor.
 * The per-block classes are already styled by editor-style.css, so this
 * function only outputs the global "advanced" defaults.
 *
 * @since 1.1.0
 * @since 1.2.0 Per-block classes moved to editor-style.css; this function
 *              now only outputs the global advanced defaults.
 */
function jfpb_add_editor_style() {
    $settings = jfpb_get_typography_settings();

    if ($settings['mode'] !== 'advanced') {
        return;
    }

    $rules = array();

    if ($settings['enable_hyphens']) {
        $rules[] = 'hyphens: auto;';
        $rules[] = '-webkit-hyphens: auto;';
    }

    if ($settings['word_spacing_value'] !== '0') {
        $rules[] = 'word-spacing: ' . esc_attr($settings['word_spacing_value']) . ';';
    }

    if (!empty($rules)) {
        $style = '.has-text-align-justify { ' . implode(' ', $rules) . ' }';
        wp_add_inline_style('wp-edit-blocks', $style);
    }
}
add_action('enqueue_block_editor_assets', 'jfpb_add_editor_style');

/**
 * Retrieve global typography settings for justified paragraphs
 *
 * These settings act as defaults for all justified paragraphs. Per-block
 * settings set via the editor sidebar override these defaults.
 *
 * @since 1.1.0
 * @since 1.2.0 Settings now act as defaults; per-block overrides added.
 *
 * @return array
 */
function jfpb_get_typography_settings() {
    $mode            = get_option('jfpb_justification_mode', 'standard');
    $enable_hyphens  = (bool) get_option('jfpb_enable_hyphens', false);
    $word_spacing    = get_option('jfpb_word_spacing', '0');
    $word_spacing_custom_value = get_option('jfpb_word_spacing_custom_value', '');
    $word_spacing_custom_unit  = get_option('jfpb_word_spacing_custom_unit', 'em');

    if ($mode !== 'advanced') {
        $mode = 'standard';
    }

    $allowed_presets = array('0', '0.02em', '-0.01em', 'custom');

    if (!in_array($word_spacing, $allowed_presets, true)) {
        $word_spacing = '0';
    }

    // Determine the effective CSS value for word-spacing
    $word_spacing_value = '0';
    if ($word_spacing === 'custom') {
        // Use custom numeric value with unit if provided, otherwise fall back to no change
        $value = trim((string) $word_spacing_custom_value);
        $unit  = in_array($word_spacing_custom_unit, array('em', 'px', 'rem'), true) ? $word_spacing_custom_unit : 'em';

        if ($value !== '' && is_numeric($value)) {
            $word_spacing_value = $value . $unit;
        }
    } elseif ($word_spacing !== '0') {
        $word_spacing_value = $word_spacing;
    }

    return array(
        'mode'                     => $mode,
        'enable_hyphens'           => $enable_hyphens,
        'word_spacing'             => $word_spacing,
        'word_spacing_value'       => $word_spacing_value,
        'word_spacing_custom_value'=> $word_spacing_custom_value,
        'word_spacing_custom_unit' => $word_spacing_custom_unit,
    );
}

/**
 * Add settings page for Justify for Paragraph Block plugin
 *
 * @since 1.1.0
 */
function jfpb_add_settings_page() {
    add_options_page(
        'Justify for Paragraph Block',
        'Justify Paragraph',
        'manage_options',
        'justify-for-paragraph-block',
        'jfpb_render_settings_page'
    );
}
add_action('admin_menu', 'jfpb_add_settings_page');

/**
 * Render settings page
 *
 * @since 1.1.0
 */
function jfpb_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['jfpb_settings_submitted']) && check_admin_referer('jfpb_save_settings')) {
        $mode = isset($_POST['jfpb_justification_mode'])
            ? sanitize_text_field(wp_unslash($_POST['jfpb_justification_mode']))
            : 'standard';

        $enable_hyphens = isset($_POST['jfpb_enable_hyphens']) ? '1' : '';

        $word_spacing = isset($_POST['jfpb_word_spacing'])
            ? sanitize_text_field(wp_unslash($_POST['jfpb_word_spacing']))
            : '0';

        $word_spacing_custom_value = '';
        $word_spacing_custom_unit  = 'em';
        if ($word_spacing === 'custom') {
            if (isset($_POST['jfpb_word_spacing_custom_value'])) {
                $word_spacing_custom_value = sanitize_text_field(wp_unslash($_POST['jfpb_word_spacing_custom_value']));
            }
            if (isset($_POST['jfpb_word_spacing_custom_unit'])) {
                $word_spacing_custom_unit = sanitize_text_field(wp_unslash($_POST['jfpb_word_spacing_custom_unit']));
            }
        }

        update_option('jfpb_justification_mode', $mode);
        update_option('jfpb_enable_hyphens', $enable_hyphens);
        update_option('jfpb_word_spacing', $word_spacing);
        update_option('jfpb_word_spacing_custom_value', $word_spacing_custom_value);
        update_option('jfpb_word_spacing_custom_unit', $word_spacing_custom_unit);

        echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'justify-for-paragraph-block') . '</p></div>';
    }

    $settings = jfpb_get_typography_settings();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Justify for Paragraph Block â€“ Typography Settings', 'justify-for-paragraph-block'); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('jfpb_save_settings'); ?>
            <input type="hidden" name="jfpb_settings_submitted" value="1" />

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="jfpb_justification_mode"><?php esc_html_e('Justification mode', 'justify-for-paragraph-block'); ?></label>
                    </th>
                    <td>
                        <select name="jfpb_justification_mode" id="jfpb_justification_mode">
                            <option value="standard" <?php selected($settings['mode'], 'standard'); ?>>
                                <?php esc_html_e('Standard (only text-align: justify)', 'justify-for-paragraph-block'); ?>
                            </option>
                            <option value="advanced" <?php selected($settings['mode'], 'advanced'); ?>>
                                <?php esc_html_e('Advanced (typographic enhancements)', 'justify-for-paragraph-block'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Standard keeps the default browser behavior. Advanced enables optional typographic improvements for justified blocks.', 'justify-for-paragraph-block'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="jfpb_enable_hyphens"><?php esc_html_e('Enable automatic hyphenation (hyphens: auto)', 'justify-for-paragraph-block'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="jfpb_enable_hyphens" id="jfpb_enable_hyphens" value="1" <?php checked($settings['enable_hyphens']); ?> />
                            <?php esc_html_e('Try to break words at line endings to reduce large gaps in justified text.', 'justify-for-paragraph-block'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, compatible browsers may break long words at the end of a line (adding a hyphen) to create a more even justified text. The effect depends on the browser and the site language.', 'justify-for-paragraph-block'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="jfpb_word_spacing"><?php esc_html_e('Adjust space between words', 'justify-for-paragraph-block'); ?></label>
                    </th>
                    <td>

                        <select name="jfpb_word_spacing" id="jfpb_word_spacing">
                            <option value="0" <?php selected($settings['word_spacing'], '0'); ?>>
                                <?php esc_html_e('No change', 'justify-for-paragraph-block'); ?>
                            </option>
                            <option value="0.02em" <?php selected($settings['word_spacing'], '0.02em'); ?> >
                                <?php esc_html_e('Slightly larger', 'justify-for-paragraph-block'); ?>
                            </option>
                            <option value="-0.01em" <?php selected($settings['word_spacing'], '-0.01em'); ?> >
                                <?php esc_html_e('Slightly smaller', 'justify-for-paragraph-block'); ?>
                            </option>
                            <option value="custom" <?php selected($settings['word_spacing'], 'custom'); ?> >
                                <?php esc_html_e('Custom value', 'justify-for-paragraph-block'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Fine-tune the spacing between words in justified blocks. Small adjustments can help improve readability, while large changes are avoided to prevent uncomfortable text.', 'justify-for-paragraph-block'); ?>
                        </p>
                        <div id="jfpb_word_spacing_custom_wrapper">
                            <p>
                                <label for="jfpb_word_spacing_custom_value">
                                    <?php esc_html_e('Custom word-spacing value', 'justify-for-paragraph-block'); ?>
                                </label><br />
                                <input type="number" step="0.01" name="jfpb_word_spacing_custom_value" id="jfpb_word_spacing_custom_value" value="<?php echo esc_attr($settings['word_spacing_custom_value']); ?>" class="small-text" />
                                <select name="jfpb_word_spacing_custom_unit" id="jfpb_word_spacing_custom_unit">
                                    <option value="em" <?php selected($settings['word_spacing_custom_unit'], 'em'); ?>>em</option>
                                    <option value="px" <?php selected($settings['word_spacing_custom_unit'], 'px'); ?>>px</option>
                                    <option value="rem" <?php selected($settings['word_spacing_custom_unit'], 'rem'); ?>>rem</option>
                                </select>
                            </p>
                            <p class="description">
                                <?php esc_html_e('Used only when "Custom value" is selected above. The number controls the amount of spacing and the unit defines how it is measured.', 'justify-for-paragraph-block'); ?>
                            </p>
                        </div>
                    </td>
                </tr>

            </table>

            <script>
            (function() {
                var select = document.getElementById('jfpb_word_spacing');
                var wrapper = document.getElementById('jfpb_word_spacing_custom_wrapper');
                if (!select || !wrapper) {
                    return;
                }

                function toggleCustomControls() {
                    if (select.value === 'custom') {
                        wrapper.style.display = '';
                    } else {
                        wrapper.style.display = 'none';
                    }
                }

                select.addEventListener('change', toggleCustomControls);
                toggleCustomControls();
            })();
            </script>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
