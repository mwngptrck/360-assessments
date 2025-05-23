<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline">Questions</h1>
    <?php if (!isset($_GET['action']) || $_GET['action'] !== 'edit'): ?>
        <a href="?page=assessment-360-questions&action=new" class="page-title-action">Add New</a>
    <?php endif; ?>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($_GET['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <?php 
    if (isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit')):
        $question = null;
        if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
            $question = Assessment_360_Question::get_instance()->get_question($_GET['id']);
        }

        // Get all positions
        $positions = Assessment_360_Position::get_instance()->get_all_positions();
        
        // Get sections if editing
        $sections = array();
        if ($question) {
            $sections = Assessment_360_Section::get_instance()->get_sections_by_position($question->position_id);
        }
    ?>
        <div class="question-form-container">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('assessment_360_question_nonce'); ?>
                <input type="hidden" name="action" value="assessment_360_save_question">
                <?php if ($question): ?>
                    <input type="hidden" name="question_id" value="<?php echo esc_attr($question->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="position_id">Position *</label>
                        </th>
                        <td>
                            <select id="position_id" name="position_id" class="regular-text" required>
                                <option value="">Select Position</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo esc_attr($position->id); ?>" 
                                            <?php selected($question && $question->position_id == $position->id); ?>>
                                        <?php echo esc_html($position->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="section_id">Section *</label>
                        </th>
                        <td>
                            <select id="section_id" name="section_id" class="regular-text" required>
                                <option value="">Select Position First</option>
                                <?php if ($question && !empty($sections)): ?>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo esc_attr($section->id); ?>" 
                                                <?php selected($question->section_id == $section->id); ?>>
                                            <?php echo esc_html($section->name); ?> 
                                            (<?php echo esc_html($section->topic_name); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="question_text">Question Text *</label>
                        </th>
                        <td>
                            <textarea id="question_text" 
                                      name="question_text" 
                                      class="large-text" 
                                      rows="3" 
                                      required><?php echo $question ? esc_textarea($question->question_text) : ''; ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Options</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="is_mandatory" 
                                       value="1" 
                                       <?php checked($question ? $question->is_mandatory : true); ?>>
                                Mandatory question
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" 
                                       name="has_comment_box" 
                                       value="1" 
                                       <?php checked($question ? $question->has_comment_box : false); ?>>
                                Include comment box
                            </label>
                        </td>
                    </tr>
                </table>

                <div class="rating-scale-preview">
                    <h3>Rating Scale Preview</h3>
                    <div class="rating-scale-grid">
                        <div class="rating-item">
                            <span class="rating-number">1</span>
                            <span class="rating-label">Unsatisfactory</span>
                            <span class="rating-desc">Performance is consistently below expectations</span>
                        </div>
                        <div class="rating-item">
                            <span class="rating-number">2</span>
                            <span class="rating-label">Needs Improvement</span>
                            <span class="rating-desc">Performance occasionally meets expectations but needs improvement</span>
                        </div>
                        <div class="rating-item">
                            <span class="rating-number">3</span>
                            <span class="rating-label">Meets Expectations</span>
                            <span class="rating-desc">Performance consistently meets expectations</span>
                        </div>
                        <div class="rating-item">
                            <span class="rating-number">4</span>
                            <span class="rating-label">Exceeds Expectations</span>
                            <span class="rating-desc">Performance frequently exceeds expectations</span>
                        </div>
                        <div class="rating-item">
                            <span class="rating-number">5</span>
                            <span class="rating-label">Outstanding</span>
                            <span class="rating-desc">Performance consistently exceeds expectations</span>
                        </div>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Question</button>
                    <a href="?page=assessment-360-questions" class="button">Cancel</a>
                </p>
            </form>
        </div>

    <?php else: 
        $questions = Assessment_360_Question::get_instance()->get_all_questions();
    ?>
        <div class="question-list-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Position</th>
                        <th>Section</th>
                        <th>Topic</th>
                        <th>Options</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($questions)): ?>
                        <?php foreach ($questions as $question): ?>
                            <tr>
                                <td><?php echo esc_html($question->question_text); ?></td>
                                <td><?php echo esc_html($question->position_name); ?></td>
                                <td><?php echo esc_html($question->section_name); ?></td>
                                <td><?php echo esc_html($question->topic_name); ?></td>
                                <td>
                                    <?php if ($question->is_mandatory): ?>
                                        <span class="badge mandatory">Mandatory</span>
                                    <?php endif; ?>
                                    <?php if ($question->has_comment_box): ?>
                                        <span class="badge comment">Has Comments</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=assessment-360-questions&action=edit&id=<?php echo $question->id; ?>" 
                                       class="button button-small">Edit</a>
                                    
                                    <a href="<?php echo wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'page' => 'assessment-360-questions',
                                                'action' => 'delete',
                                                'id' => $question->id
                                            ),
                                            admin_url('admin.php')
                                        ),
                                        'delete_question_' . $question->id
                                    ); ?>" 
                                       class="button button-small"
                                       onclick="return confirm('Are you sure you want to delete this question?');">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No questions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.question-form-container,
.question-list-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}

.rating-scale-preview {
    margin-top: 20px;
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.rating-scale-grid {
    display: grid;
    gap: 15px;
    margin-top: 15px;
}

.rating-item {
    display: flex;
    align-items: center;
    gap: 15px;
}

.rating-number {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0073aa;
    color: #fff;
    border-radius: 50%;
    font-weight: bold;
}

.rating-label {
    font-weight: 500;
    width: 150px;
}

.rating-desc {
    color: #666;
    flex: 1;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    margin-right: 5px;
}

.badge.mandatory {
    background: #dc3545;
    color: #fff;
}

.badge.comment {
    background: #28a745;
    color: #fff;
}

.button-small {
    margin: 0 5px;
}

.button-small:first-child {
    margin-left: 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#position_id').on('change', function() {
        const positionId = $(this).val();
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
    });
});
</script>
