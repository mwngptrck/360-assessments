jQuery(document).ready(function($) {
    // Handle position change for sections loading
    $('#position_id').on('change', function() {
        loadSectionsByPosition($(this).val());
    });

    function loadSectionsByPosition(positionId) {
        const sectionSelect = $('#section_id');
        sectionSelect.html('<option value="">Loading sections...</option>');
        
        if (!positionId) {
            sectionSelect.html('<option value="">Select Position First</option>');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_sections_by_position',
                position_id: positionId,
                nonce: assessment360Admin.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    let options = '<option value="">Select Section</option>';
                    response.data.forEach(function(section) {
                        options += `<option value="${section.id}">${section.name} (${section.topic_name})</option>`;
                    });
                    sectionSelect.html(options);
                } else {
                    sectionSelect.html('<option value="">No sections found</option>');
                }
            },
            error: function() {
                sectionSelect.html('<option value="">Error loading sections</option>');
            }
        });
    }

    // Handle question form validation
    $('#question-form').on('submit', function(e) {
        const questionText = $('#question_text').val().trim();
        const sectionId = $('#section_id').val();
        const positionId = $('#position_id').val();

        if (!questionText) {
            e.preventDefault();
            alert('Please enter the question text.');
            $('#question_text').focus();
            return false;
        }

        if (!positionId) {
            e.preventDefault();
            alert('Please select a position.');
            $('#position_id').focus();
            return false;
        }

        if (!sectionId) {
            e.preventDefault();
            alert('Please select a section.');
            $('#section_id').focus();
            return false;
        }
    });

    // Handle bulk question actions
    $('.bulk-action-apply').on('click', function() {
        const action = $('.bulk-action-select').val();
        const selectedQuestions = $('.question-select:checked').map(function() {
            return $(this).val();
        }).get();

        if (!action) {
            alert('Please select an action.');
            return;
        }

        if (!selectedQuestions.length) {
            alert('Please select questions to process.');
            return;
        }

        if (!confirm('Are you sure you want to ' + action + ' the selected questions?')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bulk_question_action',
                bulk_action: action,
                questions: selectedQuestions,
                nonce: assessment360Admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error processing questions');
                }
            },
            error: function() {
                alert('Error processing questions. Please try again.');
            }
        });
    });

    // Handle question preview
    function updateQuestionPreview() {
        const questionText = $('#question_text').val();
        const isMandatory = $('#is_mandatory').is(':checked');
        const hasComments = $('#has_comment_box').is(':checked');

        let previewHtml = `
            <div class="question-preview">
                <div class="question-text">
                    ${questionText}
                    ${isMandatory ? '<span class="required">*</span>' : ''}
                </div>
                <div class="rating-scale">
                    ${generateRatingScale()}
                </div>
                ${hasComments ? `
                    <div class="comment-box">
                        <label>Additional Comments:</label>
                        <textarea rows="3" disabled placeholder="Comment box will appear here"></textarea>
                    </div>
                ` : ''}
            </div>
        `;

        $('#question-preview').html(previewHtml);
    }

    function generateRatingScale() {
        let scale = '';
        for (let i = 1; i <= 5; i++) {
            scale += `
                <label class="rating-label">
                    <input type="radio" name="preview_rating" value="${i}" disabled>
                    <span class="rating-star">★</span>
                </label>
            `;
        }
        return scale;
    }

    // Update preview on input changes
    $('#question_text, #is_mandatory, #has_comment_box').on('input change', updateQuestionPreview);

    // Initial preview update
    updateQuestionPreview();

    // Initialize tooltips
    $('[data-tooltip]').tooltip({
        content: function() {
            return $(this).attr('data-tooltip');
        },
        position: {
            my: 'left center',
            at: 'right+10 center'
        }
    });
});
