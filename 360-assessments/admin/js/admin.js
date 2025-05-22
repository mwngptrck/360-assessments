jQuery(document).ready(function($) {
    // Initialize tooltips
    function initTooltips() {
        $('[data-tooltip]').tooltip({
            content: function() {
                return $(this).attr('data-tooltip');
            },
            position: {
                my: 'left center',
                at: 'right+10 center'
            }
        });
    }

    // Handle confirmation dialogs
    function handleConfirmations() {
        $('[data-confirm]').on('click', function(e) {
            if (!confirm($(this).data('confirm'))) {
                e.preventDefault();
            }
        });
    }

    // Handle status toggles
    function handleStatusToggles() {
        $('.status-toggle').on('click', function(e) {
            e.preventDefault();
            
            const $this = $(this);
            const itemId = $this.data('id');
            const action = $this.data('action');
            const nonce = $this.data('nonce');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'assessment_360_toggle_status',
                    id: itemId,
                    toggle_action: action,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error updating status');
                    }
                },
                error: function() {
                    alert('Error updating status. Please try again.');
                }
            });
        });
    }

    // Handle bulk actions
    function handleBulkActions() {
        $('.bulk-action-apply').on('click', function(e) {
            e.preventDefault();
            
            const action = $(this).prev('.bulk-action-select').val();
            if (!action) return;

            const selectedItems = $('.bulk-select:checked').map(function() {
                return $(this).val();
            }).get();

            if (!selectedItems.length) {
                alert('Please select items to process.');
                return;
            }

            if (!confirm('Are you sure you want to ' + action + ' the selected items?')) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'assessment_360_bulk_action',
                    bulk_action: action,
                    items: selectedItems,
                    nonce: assessment360Admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error processing bulk action');
                    }
                },
                error: function() {
                    alert('Error processing bulk action. Please try again.');
                }
            });
        });
    }

    // Handle file uploads
    function handleFileUploads() {
        $('.file-upload-field').on('change', function() {
            const $this = $(this);
            const fileName = $this.val().split('\\').pop();
            $this.next('.file-name').text(fileName || 'No file chosen');
        });
    }

    // Handle dynamic form fields
    function handleDynamicFields() {
        // Add new field
        $('.add-field').on('click', function() {
            const template = $(this).data('template');
            const container = $(this).data('container');
            const index = $(container).children().length;
            
            const newField = template.replace(/\{index\}/g, index);
            $(container).append(newField);
        });

        // Remove field
        $(document).on('click', '.remove-field', function() {
            $(this).closest('.dynamic-field').remove();
        });
    }

    // Handle date range inputs
    function handleDateRanges() {
        $('.date-range-start').on('change', function() {
            const endDate = $(this).closest('.date-range').find('.date-range-end');
            endDate.attr('min', $(this).val());
        });
    }

    // Handle search/filter functionality
    function handleSearch() {
        let searchTimer;
        
        $('.table-search').on('input', function() {
            clearTimeout(searchTimer);
            const $this = $(this);
            
            searchTimer = setTimeout(function() {
                const searchTerm = $this.val().toLowerCase();
                const table = $this.data('table');
                
                $(`${table} tbody tr`).each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(searchTerm));
                });
            }, 300);
        });
    }

    // Handle tabs
    function handleTabs() {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            const $this = $(this);
            const target = $this.data('tab');
            
            // Update tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $this.addClass('nav-tab-active');
            
            // Update content
            $('.tab-content').hide();
            $(target).show();
            
            // Update URL hash
            history.pushState(null, null, $this.attr('href'));
        });

        // Handle hash changes
        $(window).on('hashchange', function() {
            const hash = window.location.hash || $('.nav-tab').first().data('tab');
            $(`.nav-tab[data-tab="${hash}"]`).trigger('click');
        }).trigger('hashchange');
    }

    // Handle notifications
    function handleNotifications() {
        // Dismiss notices
        $('.notice-dismiss').on('click', function() {
            $(this).closest('.notice').slideUp();
        });

        // Auto-dismiss success messages
        setTimeout(function() {
            $('.notice-success').slideUp();
        }, 5000);
    }

    // Initialize all handlers
    function init() {
        initTooltips();
        handleConfirmations();
        handleStatusToggles();
        handleBulkActions();
        handleFileUploads();
        handleDynamicFields();
        handleDateRanges();
        handleSearch();
        handleTabs();
        handleNotifications();
    }

    // Run initialization
    init();
});
