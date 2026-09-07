jQuery(document).ready(function ($) {
    var progressInterval;
    var previewDebounce;

    // Load statistics and filter options on page load
    loadMediaStats();
    loadFilterOptions();

    // -------------------------------------------------------------------------
    // Filter options — dropdown style
    // -------------------------------------------------------------------------

    function loadFilterOptions() {
        $('#filter-loading').show();
        $('#filter-content').hide();

        $.ajax({
            url: emazExportMediaZip.ajax_url,
            type: 'POST',
            data: {
                action: 'emaz_get_filter_options',
                nonce: emazExportMediaZip.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderYearFilters(response.data.years);
                    renderSizeFilters(response.data.sizes);
                    $('#filter-loading').hide();
                    $('#filter-content').show();
                    updatePreviewCount();
                } else {
                    $('#filter-loading').text('Could not load filter options.');
                }
            },
            error: function () {
                $('#filter-loading').text('Could not load filter options.');
            }
        });
    }

    function renderYearFilters(years) {
        var $container = $('#year-filters');
        $container.empty();

        if (!years || years.length === 0) {
            $container.html('<p class="emaz-dropdown-empty">No images found.</p>');
            return;
        }

        $.each(years, function (i, item) {
            var $label = $('<label>').addClass('emaz-dropdown-item');
            var $cb = $('<input>')
                .attr('type', 'checkbox')
                .val(item.year)
                .prop('checked', true)
                .addClass('emaz-year-cb');
            $label.append($cb).append(
                $('<span>').text(item.year + ' (' + item.count + ')')
            );
            $container.append($label);
        });

        updateYearDropdownLabel();
    }

    function renderSizeFilters(sizes) {
        var $container = $('#size-filters');
        $container.empty();

        $.each(sizes, function (sizeName, sizeData) {
            var $label = $('<label>').addClass('emaz-dropdown-item');
            var $cb = $('<input>')
                .attr('type', 'checkbox')
                .val(sizeName)
                .prop('checked', sizeName === 'full')
                .addClass('emaz-size-cb');

            var labelText = sizeData.label;
            if (sizeData.dimensions) {
                labelText += ' — ' + sizeData.dimensions;
            }
            $label.append($cb).append($('<span>').text(labelText));
            $container.append($label);
        });

        updateSizeDropdownLabel();
    }

    // Reflect current selection in the dropdown trigger label
    function updateYearDropdownLabel() {
        var $checked = $('.emaz-year-cb:checked');
        var $all     = $('.emaz-year-cb');
        var label;

        if ($checked.length === 0) {
            label = 'No years selected';
        } else if ($checked.length === $all.length) {
            label = 'All Years';
        } else {
            var years = [];
            $checked.each(function () { years.push($(this).val()); });
            label = years.length <= 3
                ? years.join(', ')
                : years.slice(0, 3).join(', ') + ' (+' + (years.length - 3) + ')';
        }

        $('#year-dropdown .emaz-dropdown-label').text(label);
    }

    function updateSizeDropdownLabel() {
        var $checked = $('.emaz-size-cb:checked');
        var $all     = $('.emaz-size-cb');
        var label;

        if ($checked.length === 0) {
            label = 'No sizes selected';
        } else if ($checked.length === $all.length) {
            label = 'All Sizes';
        } else if ($checked.length === 1) {
            label = $checked.first().closest('label').find('span').text().split(' — ')[0];
        } else {
            label = $checked.length + ' sizes selected';
        }

        $('#size-dropdown .emaz-dropdown-label').text(label);
    }

    // -------------------------------------------------------------------------
    // Dropdown open / close
    // -------------------------------------------------------------------------

    $(document).on('click', '.emaz-dropdown-trigger', function (e) {
        e.stopPropagation();
        var $dropdown = $(this).closest('.emaz-dropdown');
        var wasOpen   = $dropdown.hasClass('open');
        $('.emaz-dropdown').removeClass('open');
        if (!wasOpen) {
            $dropdown.addClass('open');
        }
    });

    // Close all dropdowns on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.emaz-dropdown').length) {
            $('.emaz-dropdown').removeClass('open');
        }
    });

    // Prevent panel clicks from closing the dropdown
    $(document).on('click', '.emaz-dropdown-panel', function (e) {
        e.stopPropagation();
    });

    // -------------------------------------------------------------------------
    // Select All / None buttons
    // -------------------------------------------------------------------------

    $(document).on('click', '.select-all-years', function () {
        $('.emaz-year-cb').prop('checked', true);
        updateYearDropdownLabel();
        triggerPreviewUpdate();
    });

    $(document).on('click', '.deselect-all-years', function () {
        $('.emaz-year-cb').prop('checked', false);
        updateYearDropdownLabel();
        triggerPreviewUpdate();
    });

    $(document).on('click', '.select-all-sizes', function () {
        $('.emaz-size-cb').prop('checked', true);
        updateSizeDropdownLabel();
        triggerPreviewUpdate();
    });

    $(document).on('click', '.deselect-all-sizes', function () {
        $('.emaz-size-cb').prop('checked', false);
        updateSizeDropdownLabel();
        triggerPreviewUpdate();
    });

    // Update label + preview whenever a checkbox changes
    $(document).on('change', '.emaz-year-cb', function () {
        updateYearDropdownLabel();
        triggerPreviewUpdate();
    });

    $(document).on('change', '.emaz-size-cb', function () {
        updateSizeDropdownLabel();
        triggerPreviewUpdate();
    });

    function triggerPreviewUpdate() {
        clearTimeout(previewDebounce);
        previewDebounce = setTimeout(updatePreviewCount, 400);
    }

    function updatePreviewCount() {
        var years = getSelectedYears();
        var sizes = getSelectedSizes();

        if (sizes.length === 0) {
            $('#filter-preview-text').text('No sizes selected. Please select at least one image size.');
            $('#export-media-zip-button').prop('disabled', true);
            return;
        }

        $('#filter-preview-text').text('Calculating...');

        $.ajax({
            url: emazExportMediaZip.ajax_url,
            type: 'POST',
            data: {
                action: 'emaz_preview_export',
                nonce: emazExportMediaZip.nonce,
                years: years,
                sizes: sizes
            },
            success: function (response) {
                if (response.success) {
                    var count     = response.data.attachment_count;
                    var sizeCount = response.data.size_count;

                    if (count === 0) {
                        $('#filter-preview-text').text('No images found for the selected filters.');
                        $('#export-media-zip-button').prop('disabled', true);
                    } else {
                        var imgLabel  = count.toLocaleString() + ' image' + (count !== 1 ? 's' : '');
                        var sizeLabel = sizeCount + ' size' + (sizeCount !== 1 ? 's' : '');
                        $('#filter-preview-text').html(
                            '<strong>' + imgLabel + '</strong> &times; <strong>' + sizeLabel + '</strong> will be exported.'
                        );
                        $('#export-media-zip-button').prop('disabled', false);
                    }
                }
            },
            error: function () {
                $('#filter-preview-text').text('Could not calculate preview count.');
            }
        });
    }

    function getSelectedYears() {
        var years = [];
        $('.emaz-year-cb:checked').each(function () {
            years.push($(this).val());
        });
        return years;
    }

    function getSelectedSizes() {
        var sizes = [];
        $('.emaz-size-cb:checked').each(function () {
            sizes.push($(this).val());
        });
        return sizes;
    }

    // -------------------------------------------------------------------------
    // Media statistics
    // -------------------------------------------------------------------------

    function loadMediaStats() {
        $('#media-stats-loading').show();
        $('#media-stats-content').hide();

        $.ajax({
            url: emazExportMediaZip.ajax_url,
            type: 'POST',
            data: {
                action: 'emaz_get_media_stats',
                nonce: emazExportMediaZip.nonce
            },
            success: function (response) {
                if (response.success) {
                    var data = response.data;

                    $('#total-images').text(data.total_images.toLocaleString());
                    $('#total-size').text(data.total_size);
                    $('#file-types').text(data.file_type_count);

                    var $breakdown = $('#file-type-breakdown');
                    $breakdown.empty();

                    if (data.file_types && Object.keys(data.file_types).length > 0) {
                        $breakdown.append($('<strong>').text('File Types: '));
                        for (var type in data.file_types) {
                            if (data.file_types.hasOwnProperty(type)) {
                                $breakdown.append(
                                    $('<span>').addClass('file-type-item').text(
                                        type.toUpperCase() + ': ' + data.file_types[type]
                                    )
                                );
                            }
                        }
                    }

                    $('#media-stats-loading').hide();
                    $('#media-stats-content').show();
                } else {
                    showError('Failed to load media statistics: ' + response.data.message);
                    $('#media-stats-loading').hide();
                }
            },
            error: function () {
                showError('Failed to load media statistics.');
                $('#media-stats-loading').hide();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Export
    // -------------------------------------------------------------------------

    function validateBeforeExport() {
        var sizes = getSelectedSizes();
        if (sizes.length === 0) {
            showError('Please select at least one image size to export.');
            return false;
        }
        if (!window.XMLHttpRequest) {
            showError('Your browser does not support the required features for this export.');
            return false;
        }
        return true;
    }

    function updateButtonState(isLoading) {
        var $button      = $('#export-media-zip-button');
        var $buttonText  = $('.button-text');
        var $spinner     = $('.button-spinner');

        if (isLoading) {
            $button.prop('disabled', true);
            $buttonText.text('Exporting...');
            $spinner.show();
        } else {
            $button.prop('disabled', false);
            $buttonText.text('Export Images');
            $spinner.hide();
        }
    }

    $('#export-media-zip-button').on('click', function () {
        hideError();

        if (!validateBeforeExport()) {
            return;
        }

        var years = getSelectedYears();
        var sizes = getSelectedSizes();

        updateButtonState(true);

        $('#progress-section').show();
        $('#download-section').hide();

        $('#progress-bar').css('width', '0%');
        $('#progress-text').text('0%');
        $('#progress-files').text('0 / 0 files');
        $('#current-file').empty().hide();

        $.ajax({
            url: emazExportMediaZip.ajax_url,
            type: 'POST',
            data: {
                action: 'emaz_export_media_zip',
                nonce: emazExportMediaZip.nonce,
                years: years,
                sizes: sizes
            },
            success: function (response) {
                if (response.success) {
                    var data = response.data;

                    $('#download-section').show();

                    var $downloadLink = $('#download-link');
                    $downloadLink.empty();

                    var $downloadBtn = $('<a>')
                        .attr('href', data.download_url)
                        .attr('download', '')
                        .addClass('download-btn')
                        .text('Download ZIP File');

                    var $info      = $('<div>').addClass('download-info');
                    var $summaryP  = $('<p>').append($('<strong>').text('Export Summary:'));
                    var $list      = $('<ul>');

                    $list.append(
                        $('<li>').text('Files processed: ' + (data.processed_files || 0) + ' / ' + (data.total_files || 0))
                    );
                    if (data.zip_size) {
                        $list.append($('<li>').text('ZIP file size: ' + data.zip_size));
                    }

                    $info.append($summaryP).append($list);
                    $downloadLink.append($downloadBtn).append($info);

                    if (data.warning) {
                        showWarning(data.warning);
                    }

                    $('#progress-bar').css('width', '100%');
                    $('#progress-text').text('100%');
                    $('#current-file').hide();

                    updateButtonState(false);

                    if (progressInterval) {
                        clearInterval(progressInterval);
                    }
                } else {
                    var errorMsg = 'Export failed';
                    if (response.data && response.data.message) {
                        errorMsg += ': ' + response.data.message;
                    }
                    showError(errorMsg);
                    $('#progress-section').hide();
                    updateButtonState(false);

                    if (progressInterval) {
                        clearInterval(progressInterval);
                    }
                }
            },
            error: function (xhr, status) {
                var errorMsg = 'Server error occurred during export';

                if (xhr.status === 403) {
                    errorMsg = 'Access denied. Please refresh the page and try again.';
                } else if (xhr.status === 404) {
                    errorMsg = 'Export service not found. Please contact administrator.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Internal server error. Please try again later.';
                } else if (xhr.status === 0) {
                    errorMsg = 'Network connection error. Please check your internet connection.';
                } else if (status === 'timeout') {
                    errorMsg = 'Export request timed out. Please try again.';
                } else if (status === 'parsererror') {
                    errorMsg = 'Server response error. Please try again.';
                }

                showError(errorMsg);
                $('#progress-section').hide();
                updateButtonState(false);

                if (progressInterval) {
                    clearInterval(progressInterval);
                }
            },
            timeout: 300000 // 5 minutes
        });

        // Poll for progress updates
        progressInterval = setInterval(function () {
            $.ajax({
                url: emazExportMediaZip.ajax_url,
                type: 'POST',
                data: {
                    action: 'emaz_get_export_progress',
                    nonce: emazExportMediaZip.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var data            = response.data;
                        var progress        = data.progress || 0;
                        var processedFiles  = data.processed_files || 0;
                        var totalFiles      = data.total_files || 0;
                        var currentFile     = data.current_file || '';

                        $('#progress-bar').css('width', progress + '%');
                        $('#progress-text').text(Math.round(progress) + '%');
                        $('#progress-files').text(processedFiles + ' / ' + totalFiles + ' files');

                        if (progress < 100 && currentFile) {
                            $('#current-file').text(currentFile).show();
                        } else if (progress >= 100) {
                            $('#current-file').hide();
                        }

                        if (progress >= 100) {
                            clearInterval(progressInterval);
                        }
                    }
                },
                error: function () {
                    clearInterval(progressInterval);
                }
            });
        }, 500);
    });

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function showError(message) {
        $('#error-message')
            .removeClass('warning-message')
            .addClass('error-message')
            .text(message)
            .show();
        $('html, body').animate({ scrollTop: $('#error-message').offset().top - 100 }, 500);
    }

    function showWarning(message) {
        $('#error-message')
            .removeClass('error-message')
            .addClass('warning-message')
            .text('Warning: ' + message)
            .show();
    }

    function hideError() {
        $('#error-message').hide().empty().removeClass('error-message warning-message');
    }

    // Refresh stats button (optional)
    $(document).on('click', '.refresh-stats', function () {
        loadMediaStats();
    });
});
