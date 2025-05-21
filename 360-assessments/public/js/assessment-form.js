(function($) {
    'use strict';

    // Configuration and globals
    const config = window.assessment360Form || {};
    let isSubmitting = false;

    // Debug logging function
    function debug(message, data) {
        if (config.isDebug && window.console) {
            console.log('Assessment Form:', message, data || '');
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        const form = $('.assessment-form');
        const submitButton = form.find('button[type="submit"]');

        debug('Initializing assessment form');

        // Form submission handling
        form.on('submit', function(e) {
            // Prevent multiple submissions
            if (isSubmitting) {
                e.preventDefault();
                debug('Preventing duplicate submission');
                return false;
            }

            const unratedQuestions = [];
            const invalidComments = [];
            
            // Check all questions are rated
            $('.question-container').each(function() {
                const container = $(this);
                const questionId = container.data('question-id');
                const questionText = container.find('.question-text').text().trim();
                const hasRating = container.find('input[type="radio"]:checked').length > 0;
                
                // Check required ratings
                if (!hasRating) {
                    unratedQuestions.push(questionText);
                }

                // Validate comments if required
                const commentBox = container.find('textarea');
                if (commentBox.length && commentBox.prop('required') && !commentBox.val().trim()) {
                    invalidComments.push(questionText);
                }
            });
            
            // Show errors if validation fails
            if (unratedQuestions.length > 0 || invalidComments.length > 0) {
                e.preventDefault();
                
                let errorMessage = '';
                
                if (unratedQuestions.length > 0) {
                    errorMessage += 'Please rate all questions:\n- ' + unratedQuestions.join('\n- ') + '\n\n';
                }
                
                if (invalidComments.length > 0) {
                    errorMessage += 'Please provide comments for:\n- ' + invalidComments.join('\n- ');
                }
                
                alert(errorMessage);
                debug('Validation failed', { unratedQuestions, invalidComments });
                return false;
            }

            // Confirm submission
            if (!confirm(config.messages.confirmSubmit)) {
                e.preventDefault();
                debug('Submission cancelled by user');
                return false;
            }
            
            // Disable form to prevent double submission
            isSubmitting = true;
            $(this).addClass('form-loading');
            submitButton.prop('disabled', true)
                .html('<span class="spinner"></span> Submitting...');
            
            debug('Form submission started');
            return true;
        });
        
        // Star rating system
        initializeStarRating();
        
        // Comment handling
        initializeComments();
        
        // Draft functionality
        if (form.data('enable-drafts')) {
            initializeDrafts(form);
        }

        // Page unload handling
        $(window).on('beforeunload', handlePageUnload);

        // Accessibility
        initializeAccessibility();

        debug('Form initialization complete');
    });

    // Star rating initialization
    function initializeStarRating() {
        $('.star-rating').each(function() {
            const container = $(this);
            const labels = container.find('label');
            const inputs = container.find('input');
            
            // Hover effects
            labels.hover(
                function() {
                    $(this).prevAll('label').addBack().addClass('hover');
                },
                function() {
                    labels.removeClass('hover');
                }
            );
            
            // Click effects
            inputs.on('change', function() {
                const rating = $(this).val();
                labels.removeClass('selected');
                $(this).next('label').prevAll('label').addBack().addClass('selected');
                
                container.trigger('rating:changed', [rating]);
                debug('Rating changed', { questionId: container.data('question-id'), rating });
            });
            
            // Clear hover effects when leaving container
            container.on('mouseleave', function() {
                labels.removeClass('hover');
            });
        });
    }

    // Comment handling initialization
    function initializeComments() {
        $('.comment-container textarea').each(function() {
            const textarea = $(this);
            const maxLength = textarea.attr('maxlength') || 500;
            const counter = $('<div class="character-counter">')
                .insertAfter(textarea);
            
            function updateCounter() {
                const remaining = maxLength - textarea.val().length;
                counter.text(`${remaining} characters remaining`);
                
                if (remaining < 50) {
                    counter.addClass('warning');
                } else {
                    counter.removeClass('warning');
                }

                debug('Comment updated', { 
                    questionId: textarea.data('question-id'), 
                    remaining 
                });
            }
            
            textarea.on('input', updateCounter);
            updateCounter(); // Initial count
        });
    }

    // Draft functionality
    function initializeDrafts(form) {
        let autosaveTimer;
        const AUTOSAVE_INTERVAL = 60000; // 1 minute
        const formId = form.data('assessment-id');
        const draftKey = `assessment_draft_${formId}`;

        function saveDraft() {
            const formData = form.serialize();
            
            try {
                localStorage.setItem(draftKey, formData);
                debug('Draft saved', { formId });
            } catch (e) {
                debug('Failed to save draft', e);
            }
        }

        function startAutosave() {
            autosaveTimer = setInterval(saveDraft, AUTOSAVE_INTERVAL);
            debug('Autosave started');
        }

        function stopAutosave() {
            clearInterval(autosaveTimer);
            debug('Autosave stopped');
        }

        function loadDraft() {
            const savedData = localStorage.getItem(draftKey);
            
            if (savedData) {
                if (confirm(config.messages.loadDraft)) {
                    const formData = new URLSearchParams(savedData);
                    
                    for (const [key, value] of formData) {
                        const element = form.find(`[name="${key}"]`);
                        
                        if (element.is(':radio')) {
                            element.filter(`[value="${value}"]`)
                                .prop('checked', true)
                                .trigger('change');
                        } else {
                            element.val(value).trigger('input');
                        }
                    }
                    
                    debug('Draft loaded', { formId });
                } else {
                    localStorage.removeItem(draftKey);
                    debug('Draft cleared', { formId });
                }
            }
        }

        // Initialize draft handling
        loadDraft();
        startAutosave();
        
        // Stop autosave and clear draft on submission
        form.on('submit', function() {
            stopAutosave();
            localStorage.removeItem(draftKey);
        });
    }

    // Page unload handling
    function handlePageUnload(e) {
        if (isSubmitting) return undefined;
        
        const hasChanges = $('.assessment-form').find('input:checked, textarea').length > 0;
        
        if (hasChanges) {
            const message = config.messages.unsavedChanges;
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    }

    // Accessibility improvements
    function initializeAccessibility() {
        // Keyboard navigation for star ratings
        $('.star-rating input').on('keydown', function(e) {
            const current = parseInt($(this).val());
            
            switch(e.key) {
                case 'ArrowRight':
                case 'ArrowUp':
                    e.preventDefault();
                    if (current < 5) {
                        $(this).closest('.star-rating')
                            .find(`input[value="${current + 1}"]`)
                            .prop('checked', true)
                            .trigger('change')
                            .focus();
                    }
                    break;
                    
                case 'ArrowLeft':
                case 'ArrowDown':
                    e.preventDefault();
                    if (current > 1) {
                        $(this).closest('.star-rating')
                            .find(`input[value="${current - 1}"]`)
                            .prop('checked', true)
                            .trigger('change')
                            .focus();
                    }
                    break;
            }
        });

        // Add ARIA labels
        $('.star-rating').each(function() {
            const container = $(this);
            const questionText = container.closest('.question-container')
                .find('.question-text').text().trim();
            
            container.attr('role', 'radiogroup')
                .attr('aria-label', `Rating for: ${questionText}`);
            
            container.find('label').each(function(index) {
                const rating = 5 - index;
                $(this).attr('aria-label', `${rating} star${rating !== 1 ? 's' : ''}`);
            });
        });

        debug('Accessibility features initialized');
    }

})(jQuery);
