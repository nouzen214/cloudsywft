/**
 * Justify for Paragraph Block
 *
 * Adds justify alignment functionality to the Paragraph block in the Block
 * Editor, with per-block typographic options (hyphenation, word spacing,
 * first-line indent, drop cap and justify mode) available in the block
 * sidebar when a paragraph is justified.
 *
 * @package JustifyForParagraphBlock
 * @since 1.0.0
 */
(function (wp) {
    // Verify WordPress dependencies are loaded
    if (typeof wp === 'undefined' || typeof wp.element === 'undefined' || typeof wp.blockEditor === 'undefined') {
        console.error('WordPress dependencies not loaded correctly!');
        return;
    }

    const { addFilter } = wp.hooks;
    const { createHigherOrderComponent } = wp.compose;
    const { Fragment } = wp.element;
    const { BlockControls, InspectorControls } = wp.blockEditor;
    const { ToolbarButton, PanelBody, ToggleControl, SelectControl, TextControl } = wp.components;
    const { __ } = wp.i18n;

    /**
     * Custom attributes registered on the core/paragraph block to store
     * per-block typographic options for justified paragraphs.
     *
     * These attributes are only meaningful when the paragraph is justified
     * (align === 'justify'). They generate CSS classes that are styled in
     * editor-style.css (editor) and via wp_add_inline_style (frontend).
     *
     * @since 1.2.0
     */
    var JFPB_CUSTOM_ATTRIBUTES = {
        jfpbHyphens: {
            type: 'boolean',
            default: false
        },
        jfpbWordSpacing: {
            type: 'string',
            default: ''
        },
        jfpbWordSpacingCustomValue: {
            type: 'string',
            default: ''
        },
        jfpbWordSpacingCustomUnit: {
            type: 'string',
            default: 'em'
        },
        jfpbTextIndent: {
            type: 'boolean',
            default: false
        },
        jfpbDropCap: {
            type: 'boolean',
            default: false
        },
        jfpbTextJustify: {
            type: 'string',
            default: ''
        },
        jfpbJustifyLast: {
            type: 'boolean',
            default: false
        }
    };

    /**
     * Allowed units for the custom word-spacing value.
     *
     * @since 1.2.0
     */
    var JFPB_ALLOWED_UNITS = ['em', 'px', 'rem'];

    /**
     * Mapping from jfpbWordSpacing attribute values to CSS classes.
     *
     * @since 1.2.0
     */
    var JFPB_WORD_SPACING_CLASSES = {
        '0.02em': 'jfpb-word-spacing-lg',
        '-0.01em': 'jfpb-word-spacing-sm'
    };

    /**
     * Mapping from jfpbTextJustify attribute values to CSS classes.
     *
     * @since 1.2.0
     */
    var JFPB_TEXT_JUSTIFY_CLASSES = {
        'inter-word': 'jfpb-justify-inter-word',
        'inter-character': 'jfpb-justify-inter-character'
    };

    /**
     * Build the list of plugin CSS classes for a given set of attributes.
     *
     * @since 1.2.0
     * @param {Object} attributes - Block attributes.
     * @return {string[]} Array of CSS class names.
     */
    function jfpb_getClasses(attributes) {
        var classes = [];

        if (attributes.align === 'justify') {
            classes.push('has-text-align-justify');
        }

        if (attributes.jfpbHyphens) {
            classes.push('jfpb-hyphens');
        }

        if (attributes.jfpbWordSpacing === 'custom') {
            classes.push('jfpb-word-spacing-custom');
        } else if (attributes.jfpbWordSpacing && JFPB_WORD_SPACING_CLASSES[attributes.jfpbWordSpacing]) {
            classes.push(JFPB_WORD_SPACING_CLASSES[attributes.jfpbWordSpacing]);
        }

        if (attributes.jfpbTextIndent) {
            classes.push('jfpb-text-indent');
        }

        if (attributes.jfpbDropCap) {
            classes.push('jfpb-drop-cap');
        }

        if (attributes.jfpbTextJustify && JFPB_TEXT_JUSTIFY_CLASSES[attributes.jfpbTextJustify]) {
            classes.push(JFPB_TEXT_JUSTIFY_CLASSES[attributes.jfpbTextJustify]);
        }

        if (attributes.jfpbJustifyLast) {
            classes.push('jfpb-justify-last');
        }

        return classes;
    }

    /**
     * Build the inline style object for the custom word-spacing value.
     *
     * When the user selects "custom" as the word spacing option, the value
     * and unit are stored as separate attributes. The CSS rule cannot be
     * expressed via a static class, so we return a React style object that
     * is merged into the block wrapper's style prop.
     *
     * Returns null when no custom value is set or when the value is not
     * numeric.
     *
     * @since 1.2.0
     * @param {Object} attributes - Block attributes.
     * @return {Object|null} React style object (e.g. { wordSpacing: '0.05em' }) or null.
     */
    function jfpb_getCustomWordSpacingStyle(attributes) {
        if (attributes.jfpbWordSpacing !== 'custom') {
            return null;
        }

        var value = (attributes.jfpbWordSpacingCustomValue || '').toString().trim();
        var unit = attributes.jfpbWordSpacingCustomUnit;

        if (value === '' || !isNumeric(value)) {
            return null;
        }

        if (JFPB_ALLOWED_UNITS.indexOf(unit) === -1) {
            unit = 'em';
        }

        return { wordSpacing: value + unit };
    }

    /**
     * Check whether a value is a numeric string or number.
     *
     * @since 1.2.0
     * @param {*} value - Value to test.
     * @return {boolean} True when the value is numeric.
     */
    function isNumeric(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    }

    /**
     * Merge a list of classes into an existing className string, avoiding
     * duplicates.
     *
     * @since 1.2.0
     * @param {string} existingClass - Current className value.
     * @param {string[]} newClasses - Classes to add.
     * @return {string} Merged className.
     */
    function jfpb_mergeClasses(existingClass, newClasses) {
        var current = existingClass ? existingClass.split(/\s+/) : [];
        var merged = current.slice();

        for (var i = 0; i < newClasses.length; i++) {
            if (merged.indexOf(newClasses[i]) === -1) {
                merged.push(newClasses[i]);
            }
        }

        return merged.join(' ').trim();
    }

    /**
     * Add justify alignment support and custom attributes to the paragraph
     * block settings.
     *
     * @since 1.0.0
     * @since 1.2.0 Added per-block typographic attributes.
     *
     * @param {Object} settings - Block settings.
     * @param {string} name - Block name.
     * @return {Object} Modified block settings.
     */
    function jfpb_addJustifyToSettings(settings, name) {
        if (name !== 'core/paragraph') {
            return settings;
        }

        // Add justify to supported alignments
        if (settings.supports && settings.supports.align) {
            settings.supports.align.push('justify');
        }

        // Register custom attributes for per-block typographic options
        if (!settings.attributes) {
            settings.attributes = {};
        }
        for (var attr in JFPB_CUSTOM_ATTRIBUTES) {
            if (!settings.attributes[attr]) {
                settings.attributes[attr] = JFPB_CUSTOM_ATTRIBUTES[attr];
            }
        }

        return settings;
    }

    addFilter(
        'blocks.registerBlockType',
        'jfpb-justify-for-paragraph-block/add-justify-alignment',
        jfpb_addJustifyToSettings
    );

    /**
     * Add plugin CSS classes and inline style on block save (frontend markup).
     *
     * @since 1.0.0
     * @since 1.2.0 Now injects all per-block typographic classes and the
     *              custom word-spacing inline style.
     *
     * @param {Object} extraProps - Additional block properties.
     * @param {Object} blockType - Block type.
     * @param {Object} attributes - Block attributes.
     * @return {Object} Modified extra properties.
     */
    function jfpb_addJustifyClass(extraProps, blockType, attributes) {
        if (blockType.name !== 'core/paragraph') {
            return extraProps;
        }

        var classes = jfpb_getClasses(attributes);
        if (classes.length > 0) {
            extraProps.className = jfpb_mergeClasses(extraProps.className || '', classes);
        }

        // Inject inline style for custom word-spacing value.
        // React style props are objects, so we merge rather than concatenate.
        var customStyle = jfpb_getCustomWordSpacingStyle(attributes);
        if (customStyle) {
            extraProps.style = Object.assign({}, extraProps.style || {}, customStyle);
        }

        return extraProps;
    }

    addFilter(
        'blocks.getSaveContent.extraProps',
        'jfpb-justify-for-paragraph-block/add-justify-class',
        jfpb_addJustifyClass
    );

    /**
     * Add the plugin CSS classes and inline style to the block wrapper in
     * the editor.
     *
     * The save filter only affects frontend markup. In the editor the block
     * wrapper class is controlled by BlockListBlock, so we need a separate
     * filter to make the classes appear while editing.
     *
     * @since 1.2.0
     */
    const jfpb_withJustifyEditorClass = createHigherOrderComponent((BlockListBlock) => {
        return (props) => {
            if (props.name !== 'core/paragraph') {
                return wp.element.createElement(BlockListBlock, props);
            }

            var classes = jfpb_getClasses(props.attributes);
            if (classes.length > 0) {
                props.className = jfpb_mergeClasses(props.className || '', classes);
            }

            // Inject inline style for custom word-spacing value.
            // React style props are objects, so we merge rather than concatenate.
            var customStyle = jfpb_getCustomWordSpacingStyle(props.attributes);
            if (customStyle) {
                props.style = Object.assign({}, props.style || {}, customStyle);
            }

            return wp.element.createElement(BlockListBlock, props);
        };
    }, 'jfpb_withJustifyEditorClass');

    addFilter(
        'editor.BlockListBlock',
        'jfpb-justify-for-paragraph-block/with-justify-editor-class',
        jfpb_withJustifyEditorClass
    );

    /**
     * Add the justify toolbar button and the per-block inspector sidebar
     * controls to the paragraph block.
     *
     * The sidebar panel is only rendered when the paragraph is justified,
     * so the controls do not clutter the UI for non-justified paragraphs.
     *
     * @since 1.0.0
     * @since 1.2.0 Added InspectorControls sidebar with per-block options.
     *
     * @return {Function} Modified block edit component.
     */
    const jfpb_withJustifyButton = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            if (props.name !== 'core/paragraph') {
                return wp.element.createElement(BlockEdit, props);
            }

            var isJustified = props.attributes.align === 'justify';

            // The core paragraph BlockEdit calls useBlockProps() without
            // arguments, so it does not pick up props.style from the wrapper.
            // To apply the custom word-spacing in the editor we wrap the
            // BlockEdit in a <div> that carries the inline style. The
            // word-spacing property is inherited, so the inner <p> will use
            // it unless it has its own explicit word-spacing (which the core
            // paragraph only sets when the user picks a typography preset,
            // not by default).
            var customStyle = jfpb_getCustomWordSpacingStyle(props.attributes);
            var wrapperStyle = customStyle || null;

            var sidebarPanel = null;
            if (isJustified) {
                sidebarPanel = wp.element.createElement(
                    InspectorControls,
                    null,
                    wp.element.createElement(
                        PanelBody,
                        {
                            title: __('Justify Options', 'justify-for-paragraph-block'),
                            initialOpen: true
                        },
                        wp.element.createElement(ToggleControl, {
                            label: __('Automatic hyphenation', 'justify-for-paragraph-block'),
                            help: __('Break long words at line endings to reduce gaps.', 'justify-for-paragraph-block'),
                            checked: !!props.attributes.jfpbHyphens,
                            onChange: function (val) {
                                props.setAttributes({ jfpbHyphens: val });
                            }
                        }),
                        wp.element.createElement(SelectControl, {
                            label: __('Word spacing', 'justify-for-paragraph-block'),
                            value: props.attributes.jfpbWordSpacing || '',
                            options: [
                                { label: __('Default', 'justify-for-paragraph-block'), value: '' },
                                { label: __('Slightly larger', 'justify-for-paragraph-block'), value: '0.02em' },
                                { label: __('Slightly smaller', 'justify-for-paragraph-block'), value: '-0.01em' },
                                { label: __('Custom value', 'justify-for-paragraph-block'), value: 'custom' }
                            ],
                            onChange: function (val) {
                                props.setAttributes({ jfpbWordSpacing: val });
                            }
                        }),
                        // Custom word-spacing controls: only visible when the
                        // user selects "Custom value" in the select above.
                        props.attributes.jfpbWordSpacing === 'custom' ? wp.element.createElement(
                            Fragment,
                            null,
                            wp.element.createElement(TextControl, {
                                type: 'number',
                                step: '0.01',
                                label: __('Custom word-spacing value', 'justify-for-paragraph-block'),
                                help: __('Numeric value for the spacing between words.', 'justify-for-paragraph-block'),
                                value: props.attributes.jfpbWordSpacingCustomValue || '',
                                onChange: function (val) {
                                    props.setAttributes({ jfpbWordSpacingCustomValue: val });
                                }
                            }),
                            wp.element.createElement(SelectControl, {
                                label: __('Unit', 'justify-for-paragraph-block'),
                                value: props.attributes.jfpbWordSpacingCustomUnit || 'em',
                                options: [
                                    { label: 'em', value: 'em' },
                                    { label: 'px', value: 'px' },
                                    { label: 'rem', value: 'rem' }
                                ],
                                onChange: function (val) {
                                    props.setAttributes({ jfpbWordSpacingCustomUnit: val });
                                }
                            })
                        ) : null,
                        wp.element.createElement(ToggleControl, {
                            label: __('First-line indent', 'justify-for-paragraph-block'),
                            help: __('Indent the first line of each justified paragraph (editorial style).', 'justify-for-paragraph-block'),
                            checked: !!props.attributes.jfpbTextIndent,
                            onChange: function (val) {
                                props.setAttributes({ jfpbTextIndent: val });
                            }
                        }),
                        wp.element.createElement(ToggleControl, {
                            label: __('Drop cap', 'justify-for-paragraph-block'),
                            help: __('Enlarge the first letter of the paragraph (magazine style).', 'justify-for-paragraph-block'),
                            checked: !!props.attributes.jfpbDropCap,
                            onChange: function (val) {
                                props.setAttributes({ jfpbDropCap: val });
                            }
                        }),
                        wp.element.createElement(SelectControl, {
                            label: __('Justify mode', 'justify-for-paragraph-block'),
                            value: props.attributes.jfpbTextJustify || '',
                            options: [
                                { label: __('Browser default', 'justify-for-paragraph-block'), value: '' },
                                { label: __('Inter-word', 'justify-for-paragraph-block'), value: 'inter-word' },
                                { label: __('Inter-character', 'justify-for-paragraph-block'), value: 'inter-character' }
                            ],
                            onChange: function (val) {
                                props.setAttributes({ jfpbTextJustify: val });
                            }
                        }),
                        wp.element.createElement(ToggleControl, {
                            label: __('Stretch last line', 'justify-for-paragraph-block'),
                            help: __('Force the last line of the justified paragraph to be full-width instead of short (text-align-last: justify).', 'justify-for-paragraph-block'),
                            checked: !!props.attributes.jfpbJustifyLast,
                            onChange: function (val) {
                                props.setAttributes({ jfpbJustifyLast: val });
                            }
                        })
                    )
                );
            }

            // When a custom word-spacing style is set, wrap the BlockEdit in a
            // <div> that carries the inline style so the inner <p> inherits it.
            // When no custom style is set, render the BlockEdit directly to
            // avoid changing the DOM structure unnecessarily.
            var blockEditElement = wp.element.createElement(BlockEdit, props);
            if (wrapperStyle) {
                blockEditElement = wp.element.createElement(
                    'div',
                    { style: wrapperStyle },
                    blockEditElement
                );
            }

            return wp.element.createElement(
                Fragment,
                null,
                blockEditElement,
                sidebarPanel,
                wp.element.createElement(
                    BlockControls,
                    null,
                    wp.element.createElement(ToolbarButton, {
                        icon: "editor-justify",
                        title: __("Justify", 'justify-for-paragraph-block'),
                        isActive: isJustified,
                        onClick: function () {
                            props.setAttributes({
                                align: isJustified ? undefined : 'justify'
                            });
                        }
                    })
                )
            );
        };
    }, 'jfpb_withJustifyButton');

    addFilter(
        'editor.BlockEdit',
        'jfpb-justify-for-paragraph-block/with-justify-button',
        jfpb_withJustifyButton
    );
})(window.wp);
