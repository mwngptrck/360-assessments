jQuery(document).ready(function($) {
    // Update progress information periodically
    function updateProgress() {
        const assessmentId = $('#active-assessment-id').val();
        if (!assessmentId) return;

        $.ajax({
            url: assessment360Ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_assessment_progress',
                nonce: assessment360Ajax.nonce,
                assessment_id: assessmentId
            },
            success: function(response) {
                if (response.success) {
                    updateProgressDisplay(response.data);
                }
            }
        });
    }

    function updateProgressDisplay(data) {
        // Update progress bar
        $('.progress').css('width', data.percentage + '%');
        $('.progress-text').text(data.percentage + '% Complete');

        // Update statistics
        $('.stat-value.completed').text(data.completed);
        $('.stat-value.pending').text(data.pending);
        $('.stat-value.total').text(data.total);

        // Update cards if status has changed
        if (data.updated_statuses) {
            Object.keys(data.updated_statuses).forEach(function(instanceId) {
                const status = data.updated_statuses[instanceId];
                const card = $(`.assessment-card[data-instance-id="${instanceId}"]`);
                
                if (card.length) {
                    updateCardStatus(card, status);
                }
            });
        }
    }

    function updateCardStatus(card, status) {
        // Remove existing status classes
        card.removeClass('pending completed');
        card.addClass(status);

        // Update status badge
        const badge = card.find('.status-badge');
        badge.removeClass('pending completed').addClass(status);
        badge.text(status.charAt(0).toUpperCase() + status.slice(1));

        // Update action button
        const actionArea = card.find('.assessment-meta');
        if (status === 'completed') {
            actionArea.html(`
                <span class="status-badge completed">Completed</span>
                <span class="completion-date">Completed: ${new Date().toLocaleDateString()}</span>
            `);
        }
    }

    // Initialize tooltips
    $('[data-tooltip]').each(function() {
        $(this).tooltip({
            content: $(this).attr('data-tooltip'),
            position: { my: 'left center', at: 'right+10 center' }
        });
    });

    // Handle card hover effects
    $('.assessment-card').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );

    // Confirm before starting assessment
    $('.start-assessment').on('click', function(e) {
        if (!confirm('Are you ready to start this assessment?')) {
            e.preventDefault();
        }
    });

    // Update progress every 60 seconds
    setInterval(updateProgress, 60000);

    // Initial progress update
    updateProgress();
});
