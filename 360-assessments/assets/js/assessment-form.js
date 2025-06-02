(function($) {
    'use strict';

    // Assessment Form Handler
    class AssessmentForm {
        constructor() {
            this.form = $('#assessmentForm');
            this.submitButton = this.form.find('button[type="submit"]');
            this.ratingInputs = this.form.find('input[type="radio"]');
            this.commentBoxes = this.form.find('textarea');
            this.progressBar = $('.assessment-progress');
            this.questionSections = $('.question-section');
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.updateProgress();
            this.initAutoSave();
        }

        bindEvents() {
            // Form submission
            this.form.on('submit', (e) => this.handleSubmit(e));

            // Rating selection
            this.ratingInputs.on('change', (e) => this.handleRatingChange(e));

            // Comment box input
            this.commentBoxes.on('input', (e) => this.handleCommentInput(e));

            // Navigation warning
            $(window).on('beforeunload', (e) => this.handleBeforeUnload(e));
        }

        handleSubmit(e) {
            e.preventDefault();
            
            if (!this.validateForm()) {
                return;
            }

            // Show confirmation dialog
            if (!confirm('Are you sure you want to submit this assessment? You won\'t be able to modify it later.')) {
                return;
            }

            // Disable submit button and show loading state
            this.submitButton.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...');

            // Remove navigation warning
            $(window).off('beforeunload');

            // Submit form
            this.form[0].submit();
        }

        validateForm() {
            let isValid = true;
            const requiredQuestions = this.form.find('.question-section[data-required="1"]');

            // Reset error states
            $('.error-message').hide();
            $('.question-section').removeClass('has-error');

            requiredQuestions.each((i, section) => {
                const $section = $(section);
                const questionId = $section.data('question-id');
                const hasRating = $section.find('input[type="radio"]:checked').length > 0;

                if (!hasRating) {
                    isValid = false;
                    $section.addClass('has-error')
                        .find('.error-message').show();
                }
            });

            if (!isValid) {
                // Scroll to first error
                const firstError = $('.has-error').first();
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            }

            return isValid;
        }

        handleRatingChange(e) {
            const $input = $(e.target);
            const $section = $input.closest('.question-section');
            
            // Remove error state
            $section.removeClass('has-error')
                .find('.error-message').hide();

            // Update progress
            this.updateProgress();

            // Auto-save
            this.saveProgress();
        }

        handleCommentInput(e) {
            // Debounced auto-save
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => this.saveProgress(), 1000);
        }

        handleBeforeUnload(e) {
            if (this.hasUnsavedChanges()) {
                e.preventDefault();
                return e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        }

        updateProgress() {
            const total = this.questionSections.length;
            const completed = this.form.find('input[type="radio"]:checked').length;
            const percentage = Math.round((completed / total) * 100);

            this.progressBar.find('.progress-bar')
                .css('width', percentage + '%')
                .attr('aria-valuenow', percentage);

            this.progressBar.find('.progress-text')
                .text(`${completed} of ${total} questions completed (${percentage}%)`);
        }

        hasUnsavedChanges() {
            return this.form.find('input[type="radio"]:checked').length > 0 ||
                   this.form.find('textarea').filter((i, el) => $(el).val().length > 0).length > 0;
        }

        saveProgress() {
            const formData = this.form.serializeArray();
            const assessmentId = this.form.find('input[name="assessment_id"]').val();
            const assesseeId = this.form.find('input[name="assessee_id"]').val();

            // Save to localStorage
            localStorage.setItem(`assessment_progress_${assessmentId}_${assesseeId}`, 
                JSON.stringify({
                    timestamp: new Date().getTime(),
                    data: formData
                })
            );
        }

        initAutoSave() {
            // Load saved progress
            const assessmentId = this.form.find('input[name="assessment_id"]').val();
            const assesseeId = this.form.find('input[name="assessee_id"]').val();
            const saved = localStorage.getItem(`assessment_progress_${assessmentId}_${assesseeId}`);

            if (saved) {
                const { timestamp, data } = JSON.parse(saved);
                const hoursSinceLastSave = (new Date().getTime() - timestamp) / (1000 * 60 * 60);

                // Only restore if less than 24 hours old
                if (hoursSinceLastSave < 24) {
                    data.forEach(item => {
                        const $input = this.form.find(`[name="${item.name}"]`);
                        if ($input.is(':radio')) {
                            $input.filter(`[value="${item.value}"]`).prop('checked', true);
                        } else {
                            $input.val(item.value);
                        }
                    });

                    this.updateProgress();
                }
            }
        }
    }

    // Rating Scale Guide
    class RatingGuide {
        constructor() {
            this.init();
        }

        init() {
            this.createTooltips();
            this.bindEvents();
        }

        createTooltips() {
            $('.rating-option').each((i, el) => {
                const $option = $(el);
                const rating = $option.data('rating');
                const description = $option.data('description');

                $option.tooltip({
                    title: description,
                    placement: 'top',
                    trigger: 'hover'
                });
            });
        }

        bindEvents() {
            // Show rating guide modal
            $('.show-rating-guide').on('click', (e) => {
                e.preventDefault();
                $('#ratingGuideModal').modal('show');
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new AssessmentForm();
        new RatingGuide();

        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();

        // Smooth scroll for anchor links
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.hash);
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });
    });

})(jQuery);
