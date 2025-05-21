(function($) {
    'use strict';

    class AssessmentDashboard {
        constructor() {
            this.init();
        }

        init() {
            this.initCharts();
            this.bindEvents();
            this.setupFilters();
        }

        initCharts() {
            // Progress chart
            const ctx = document.getElementById('progressChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', 'Pending'],
                        datasets: [{
                            data: [
                                parseInt(ctx.dataset.completed),
                                parseInt(ctx.dataset.pending)
                            ],
                            backgroundColor: ['#198754', '#ffc107']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }

        bindEvents() {
            // Assessment start confirmation
            $('.start-assessment').on('click', (e) => {
                if (!confirm('Are you ready to start this assessment?')) {
                    e.preventDefault();
                }
            });

            // Group collapse toggle
            $('.group-header').on('click', function() {
                $(this).next('.group-assessees').slideToggle();
                $(this).find('.toggle-icon').toggleClass('bi-chevron-down bi-chevron-up');
            });
        }

        setupFilters() {
            // Search filter
            $('#searchAssessees').on('input', (e) => {
                const searchTerm = $(e.target).val().toLowerCase();
                $('.assessee-item').each((i, el) => {
                    const $item = $(el);
                    const name = $item.find('.assessee-name').text().toLowerCase();
                    const position = $item.find('.assessee-position').text().toLowerCase();
                    
                    if (name.includes(searchTerm) || position.includes(searchTerm)) {
                        $item.show();
                    } else {
                        $item.hide();
                    }
                });
            });

            // Status filter
            $('#statusFilter').on('change', (e) => {
                const status = $(e.target).val();
                $('.assessee-item').each((i, el) => {
                    const $item = $(el);
                    if (status === 'all' || $item.data('status') === status) {
                        $item.show();
                    } else {
                        $item.hide();
                    }
                });
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new AssessmentDashboard();
    });

})(jQuery);
