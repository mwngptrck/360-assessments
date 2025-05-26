<h2 class="h4 mb-4">Setup Complete!</h2>

<div class="setup-complete text-center py-4">
    <div class="complete-icon mb-4">
        <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
    </div>
    
    <h3 class="mb-3">Congratulations!</h3>
    <p class="text-muted mb-4">
        Your 360° Assessment system is now set up and ready to use.<br>
        You can start adding users and creating assessments.
    </p>

    <div class="next-steps mb-4">
        <h5 class="mb-3">Next Steps</h5>
        <div class="row justify-content-center">
            <div class="col-md-4">
                <a href="<?php echo admin_url('admin.php?page=assessment-360-user-management'); ?>" 
                   class="card text-decoration-none mb-2">
                    <div class="card-body">
                        <i class="bi bi-people mb-2" style="font-size: 1.5rem;"></i>
                        <h6 class="mb-0">Manage Users</h6>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?php echo admin_url('admin.php?page=assessment-360-assessments'); ?>" 
                   class="card text-decoration-none mb-2">
                    <div class="card-body">
                        <i class="bi bi-clipboard-check mb-2" style="font-size: 1.5rem;"></i>
                        <h6 class="mb-0">Create Assessment</h6>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div class="setup-actions">
        <div></div>
        <div>
            <a href="<?php echo admin_url('admin.php?page=assessment-360-dashboard'); ?>" 
               class="btn btn-primary">
                Go to Dashboard <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</div>
