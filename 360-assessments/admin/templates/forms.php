<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <?php
    // Initialize managers
    $topic_manager = Assessment_360_Topic::get_instance();
    $section_manager = Assessment_360_Section::get_instance();
    $question_manager = Assessment_360_Question::get_instance();
    $position_manager = Assessment_360_Position::get_instance();

    // Get common data
    $topics = $topic_manager->get_all_topics();
    $positions = $position_manager->get_all_positions();
    ?>

    <!-- Bootstrap Container -->
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <h1 class="wp-heading-inline mb-4"><i class="bi bi-clipboard-check"></i> Assessment Forms</h1>

                <?php if (isset($_GET['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo esc_html($_GET['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo esc_html($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Bootstrap Tabs -->
                <ul class="nav nav-tabs mb-3" id="formTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="topics-tab" data-bs-toggle="tab" 
                                data-bs-target="#topics" type="button" role="tab"><i class="bi bi-card-checklist"></i> Topics</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sections-tab" data-bs-toggle="tab" 
                                data-bs-target="#sections" type="button" role="tab"><i class="bi bi-view-list"></i> Sections</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="questions-tab" data-bs-toggle="tab" 
                                data-bs-target="#questions" type="button" role="tab"><i class="bi bi-question-square"></i> Questions</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="preview-tab" data-bs-toggle="tab" 
                                data-bs-target="#preview" type="button" role="tab"><i class="bi bi-eye"></i> Form Preview</button>
                    </li>
                </ul>

                <div class="tab-content" id="formTabsContent">
                    <div class="tab-pane fade show active" id="topics" role="tabpanel">
                        <div class="row">
                            
                            <!-- Topics Tab -->
                            <div class="col-sm-4">
                                <?php
                                // Check for edit action
                                $edit_topic = null;
                                if (isset($_GET['action']) && $_GET['action'] === 'edit_topic' && isset($_GET['id'])) {
                                    $edit_topic = $topic_manager->get_topic(intval($_GET['id']));
                                }
                                ?>

                                <!-- Topic Form -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h4 class="card-title"><?php echo $edit_topic ? 'Edit Topic' : 'Add New Topic'; ?></h4>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                            <?php wp_nonce_field('save_topic_nonce'); ?>
                                            <input type="hidden" name="action" value="save_topic">
                                            <?php if ($edit_topic): ?>
                                                <input type="hidden" name="id" value="<?php echo esc_attr($edit_topic->id); ?>">
                                            <?php endif; ?>

                                            <div class="mb-3">
                                                <label for="topic_name" class="form-label">Topic Name *</label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="topic_name" 
                                                       name="topic_name" 
                                                       value="<?php echo $edit_topic ? esc_attr($edit_topic->name) : ''; ?>" 
                                                       required>
                                            </div>

                                            <div class="mb-3">
                                                <button type="submit" class="btn btn-primary">
                                                    <?php echo $edit_topic ? 'Update Topic' : 'Add Topic'; ?>
                                                </button>
                                                <?php if ($edit_topic): ?>
                                                    <a href="?page=assessment-360-forms#topics" class="btn btn-secondary">Cancel</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div><!-- col4 -->
                            
                            <!-- Topics List -->
                            <div class="col-sm-8">
                                <div class="card w-100">
                                    <div class="card-body">
                                        <h4 class="card-title">List Topics</h4>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Sections</th>
                                                        <th>Questions</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    if (!empty($topics)): 
                                                        foreach ($topics as $topic): 
                                                            $sections = $section_manager->get_sections_by_topic($topic->id);
                                                            $section_count = is_array($sections) ? count($sections) : 0;
                                                            $question_count = $question_manager->get_question_count_by_topic($topic->id);
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <a href="?page=assessment-360-forms&action=edit_topic&id=<?php echo esc_attr($topic->id); ?>#topics">
                                                                    <?php echo esc_html($topic->name); ?>
                                                                </a>
                                                            </td>
                                                            <td><?php echo esc_html($section_count); ?></td>
                                                            <td><?php echo esc_html($question_count); ?></td>
                                                            <td>
                                                                <div class="btn-group">
                                                                    <a href="?page=assessment-360-forms&action=edit_topic&id=<?php echo esc_attr($topic->id); ?>#topics" 
                                                                       class="btn btn-sm btn-primary">Edit</a>
                                                                    <?php if ($section_count == 0): ?>
                                                                        <a href="?page=assessment-360-forms&action=delete_topic&id=<?php echo esc_attr($topic->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_topic_' . $topic->id); ?>#topics" 
                                                                           class="btn btn-sm btn-danger delete-topic"
                                                                           onclick="return confirm('Are you sure you want to delete this topic?');">
                                                                            Delete
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php 
                                                        endforeach;
                                                    else: 
                                                    ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center">No topics found.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- col-8 -->
                        </div> <!-- row -->
                        
                    </div>

                    <!-- Sections Tab -->
                    <div class="tab-pane fade" id="sections" role="tabpanel">
                        <div class="row">
                            <?php
                            // Check for edit action
                            $edit_section = null;
                            if (isset($_GET['action']) && $_GET['action'] === 'edit_section' && isset($_GET['id'])) {
                                $edit_section = $section_manager->get_section(intval($_GET['id']));
                            }
                            ?>

                            <!-- Section Form -->
                            <div class="col-sm-4">
                                <div class="card w-100">
                                    <div class="card-body">
                                        <h4 class="card-title"><?php echo $edit_section ? 'Edit Section' : 'Add New Section'; ?></h4>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                            <?php wp_nonce_field('save_section_nonce'); ?>
                                            <input type="hidden" name="action" value="save_section">
                                            <?php if ($edit_section): ?>
                                                <input type="hidden" name="id" value="<?php echo esc_attr($edit_section->id); ?>">
                                            <?php endif; ?>

                                            <div class="mb-3">
                                                <label for="section_name" class="form-label">Section Name *</label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="section_name" 
                                                       name="section_name" 
                                                       value="<?php echo $edit_section ? esc_attr($edit_section->name) : ''; ?>" 
                                                       required>
                                            </div>

                                            <div class="mb-3">
                                                <label for="topic_id" class="form-label">Topic *</label>
                                                <select class="form-select" id="topic_id" name="topic_id" required>
                                                    <option value="">Select Topic</option>
                                                    <?php foreach ($topics as $topic): ?>
                                                        <option value="<?php echo esc_attr($topic->id); ?>"
                                                                <?php selected($edit_section && $edit_section->topic_id == $topic->id); ?>>
                                                            <?php echo esc_html($topic->name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label for="position_ids" class="form-label">Positions *</label>
                                                <select class="form-select" 
                                                        id="position_ids" 
                                                        name="position_ids[]" 
                                                        multiple 
                                                        required
                                                        size="5">
                                                    <?php 
                                                    // Get selected positions if editing
                                                    $selected_positions = array();
                                                    if ($edit_section) {
                                                        $selected_positions = $section_manager->get_section_positions($edit_section->id);
                                                    }

                                                    foreach ($positions as $position): 
                                                        $is_selected = in_array($position->id, array_column($selected_positions ?? [], 'position_id'));
                                                    ?>
                                                        <option value="<?php echo esc_attr($position->id); ?>"
                                                                <?php selected($is_selected); ?>>
                                                            <?php echo esc_html($position->name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="form-text">
                                                    Hold Ctrl (Windows) or Command (Mac) to select multiple positions
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <button type="submit" class="btn btn-primary">
                                                    <?php echo $edit_section ? 'Update Section' : 'Add Section'; ?>
                                                </button>
                                                <?php if ($edit_section): ?>
                                                    <a href="?page=assessment-360-forms#sections" class="btn btn-secondary">Cancel</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Sections List -->
                            <?php 
                            // Get all sections grouped by position
                            $sections = $section_manager->get_all_sections();
                            $sections_by_position = array();

                            // Group sections by position
                            foreach ($sections as $section) {
                                if (!isset($sections_by_position[$section->position_id])) {
                                    $sections_by_position[$section->position_id] = array(
                                        'position_name' => $section->position_name,
                                        'sections' => array()
                                    );
                                }
                                $sections_by_position[$section->position_id]['sections'][] = $section;
                            }
                            ?>

                            <div class="col-sm-8">
                                <div class="card w-100">
                                    <div class="card-body">
                                    <h4 class="card-title">List Sections</h4>
                                    <div class="accordion" id="sectionsAccordion">
                                        <?php if (!empty($sections_by_position)): ?>
                                            <?php foreach ($sections_by_position as $position_id => $position_data): ?>
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button collapsed" type="button" 
                                                                data-bs-toggle="collapse" 
                                                                data-bs-target="#position<?php echo esc_attr($position_id); ?>">
                                                            <?php echo esc_html($position_data['position_name']); ?>
                                                        </button>
                                                    </h2>
                                                    <div id="position<?php echo esc_attr($position_id); ?>" 
                                                         class="accordion-collapse collapse" 
                                                         data-bs-parent="#sectionsAccordion">
                                                        <div class="accordion-body">
                                                            <div class="table-responsive">
                                                                <table class="table table-striped table-hover">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Name</th>
                                                                            <th>Topic</th>
                                                                            <th>Questions</th>
                                                                            <th>Actions</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($position_data['sections'] as $section): 
                                                                            $question_count = $question_manager->get_question_count_by_section($section->id);
                                                                        ?>
                                                                            <tr>
                                                                                <td>
                                                                                    <a href="?page=assessment-360-forms&action=edit_section&id=<?php echo esc_attr($section->id); ?>#sections">
                                                                                        <?php echo esc_html($section->name); ?>
                                                                                    </a>
                                                                                </td>
                                                                                <td><?php echo esc_html($section->topic_name); ?></td>
                                                                                <td><?php echo esc_html($question_count); ?></td>
                                                                                <td>
                                                                                    <div class="btn-group">
                                                                                        <a href="?page=assessment-360-forms&action=edit_section&id=<?php echo esc_attr($section->id); ?>#sections" 
                                                                                           class="btn btn-sm btn-primary">Edit</a>
                                                                                        <?php if ($question_count == 0): ?>
                                                                                            <a href="?page=assessment-360-forms&action=delete_section&id=<?php echo esc_attr($section->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_section_' . $section->id); ?>#sections" 
                                                                                               class="btn btn-sm btn-danger delete-section"
                                                                                               onclick="return confirm('Are you sure you want to delete this section?');">
                                                                                                Delete
                                                                                            </a>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <p>No sections found.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Questions Tab -->
                    <div class="tab-pane fade" id="questions" role="tabpanel">
                        <div class="row">
                            <?php
                            // Check for edit action
                            $edit_question = null;
                            if (isset($_GET['action']) && $_GET['action'] === 'edit_question' && isset($_GET['id'])) {
                                $edit_question = $question_manager->get_question(intval($_GET['id']));
                            }

                            // Preload all sections grouped by position
                            $all_sections = $section_manager->get_sections_with_topics();
                            $sections_by_position = array();

                            if (!empty($all_sections)) {
                                foreach ($all_sections as $section) {
                                    if (!isset($sections_by_position[$section->position_id])) {
                                        $sections_by_position[$section->position_id] = array();
                                    }
                                    $sections_by_position[$section->position_id][] = array(
                                        'id' => $section->id,
                                        'name' => $section->name,
                                        'topic_name' => $section->topic_name
                                    );
                                }
                            }
                            ?>

                            <!-- Question Form -->
                            <div class="col-sm-4">
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h4 class="card-title"><?php echo $edit_question ? 'Edit Question' : 'Add New Question'; ?></h4>
                                        <!-- Sections data for JavaScript -->
                                        <script type="text/javascript">
                                            var sectionsData = <?php echo json_encode($sections_by_position); ?>;
                                            console.log('Sections Data loaded:', sectionsData);
                                        </script>

                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                            <?php wp_nonce_field('save_question_nonce'); ?>
                                            <input type="hidden" name="action" value="save_question">
                                            <?php if ($edit_question): ?>
                                                <input type="hidden" name="id" value="<?php echo esc_attr($edit_question->id); ?>">
                                            <?php endif; ?>

                                            <div class="mb-3">
                                                <label for="position_id" class="form-label">Position *</label>
                                                <select class="form-select" id="position_id" name="position_id" required>
                                                    <option value="">Select Position</option>
                                                    <?php foreach ($positions as $position): ?>
                                                        <option value="<?php echo esc_attr($position->id); ?>"
                                                                <?php selected($edit_question && $edit_question->position_id == $position->id); ?>>
                                                            <?php echo esc_html($position->name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label for="section_id" class="form-label">Section *</label>
                                                <select class="form-select" id="section_id" name="section_id" required>
                                                    <option value="">Select Position First</option>
                                                    <?php 
                                                    if ($edit_question && isset($sections_by_position[$edit_question->position_id])):
                                                        foreach ($sections_by_position[$edit_question->position_id] as $section):
                                                    ?>
                                                        <option value="<?php echo esc_attr($section['id']); ?>"
                                                                <?php selected($edit_question->section_id == $section['id']); ?>>
                                                            <?php echo esc_html($section['name']); ?> 
                                                            (<?php echo esc_html($section['topic_name']); ?>)
                                                        </option>
                                                    <?php 
                                                        endforeach;
                                                    endif;
                                                    ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label for="question_text" class="form-label">Question Text *</label>
                                                <textarea class="form-control" 
                                                          id="question_text" 
                                                          name="question_text" 
                                                          rows="3" 
                                                          required><?php echo $edit_question ? esc_textarea($edit_question->question_text) : ''; ?></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input type="checkbox" 
                                                           class="form-check-input"
                                                           id="is_mandatory" 
                                                           name="is_mandatory" 
                                                           value="1" 
                                                           <?php checked($edit_question && $edit_question->is_mandatory == 1); ?>>
                                                    <label class="form-check-label" for="is_mandatory">
                                                        Mandatory question
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input type="checkbox" 
                                                           class="form-check-input"
                                                           id="has_comment_box" 
                                                           name="has_comment_box" 
                                                           value="1" 
                                                           <?php checked($edit_question && $edit_question->has_comment_box == 1); ?>>
                                                    <label class="form-check-label" for="has_comment_box">
                                                        Include comment box
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <button type="submit" class="btn btn-primary">
                                                    <?php echo $edit_question ? 'Update Question' : 'Add Question'; ?>
                                                </button>
                                                <?php if ($edit_question): ?>
                                                    <a href="?page=assessment-360-forms#questions" class="btn btn-secondary">Cancel</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div><!--col4-->
                            
                            

                            <!-- Questions List -->
                            <div class="col-sm-8">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title">List questions</h4>

                                        <?php 
                                        // Get all questions and group them by position
                                        $questions = $question_manager->get_all_questions();
                                        $questions_by_position = array();

                                        // Group questions by position
                                        if (!empty($questions)) {
                                            foreach ($questions as $question) {
                                                if (!isset($questions_by_position[$question->position_id])) {
                                                    $questions_by_position[$question->position_id] = array(
                                                        'position_name' => $question->position_name,
                                                        'questions' => array()
                                                    );
                                                }
                                                $questions_by_position[$question->position_id]['questions'][] = $question;
                                            }
                                        }
                                        ?>

                                        <?php if (!empty($questions_by_position)): ?>
                                            <div class="accordion" id="questionsAccordion">
                                                <?php foreach ($questions_by_position as $position_id => $position_data): ?>
                                                    <div class="accordion-item">
                                                        <h2 class="accordion-header">
                                                            <button class="accordion-button collapsed" type="button" 
                                                                    data-bs-toggle="collapse" 
                                                                    data-bs-target="#position<?php echo esc_attr($position_id); ?>">
                                                                <?php echo esc_html($position_data['position_name']); ?> 
                                                                <span class="badge bg-secondary ms-2">
                                                                    <?php echo count($position_data['questions']); ?> questions
                                                                </span>
                                                            </button>
                                                        </h2>
                                                        <div id="position<?php echo esc_attr($position_id); ?>" 
                                                             class="accordion-collapse collapse" 
                                                             data-bs-parent="#questionsAccordion">
                                                            <div class="accordion-body p-0">
                                                                <div class="table-responsive">
                                                                    <table class="table table-striped table-hover mb-0">
                                                                        <thead>
                                                                            <tr>
                                                                                <th>Question</th>
                                                                                <th>Topic</th>
                                                                                <th>Section</th>
                                                                                <th>Options</th>
                                                                                <th>Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($position_data['questions'] as $question): ?>
                                                                                <tr>
                                                                                    <td>
                                                                                        <a href="?page=assessment-360-forms&action=edit_question&id=<?php echo esc_attr($question->id); ?>#questions">
                                                                                            <?php echo esc_html($question->question_text); ?>
                                                                                        </a>
                                                                                    </td>
                                                                                    <td><?php echo esc_html($question->topic_name); ?></td>
                                                                                    <td><?php echo esc_html($question->section_name); ?></td>
                                                                                    <td>
                                                                                        <?php if ($question->is_mandatory): ?>
                                                                                            <span class="badge bg-danger">Mandatory</span>
                                                                                        <?php endif; ?>
                                                                                        <?php if ($question->has_comment_box): ?>
                                                                                            <span class="badge bg-success">Comments</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <td>
                                                                                        <div class="btn-group">
                                                                                            <a href="?page=assessment-360-forms&action=edit_question&id=<?php echo esc_attr($question->id); ?>#questions" 
                                                                                               class="btn btn-sm btn-primary">Edit</a>
                                                                                            <a href="?page=assessment-360-forms&action=delete_question&id=<?php echo esc_attr($question->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_question_' . $question->id); ?>#questions" 
                                                                                               class="btn btn-sm btn-danger delete-question"
                                                                                               onclick="return confirm('Are you sure you want to delete this question?');">
                                                                                                Delete
                                                                                            </a>
                                                                                        </div>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                No questions found.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div><!--col8-->
                        </div> <!-- row -->
                    </div>

                    <!-- Preview Tab -->
                    <div class="tab-pane fade" id="preview" role="tabpanel">
                        
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                <?php if (!empty($positions)): ?>
                                    <div class="col-sm-4">
                                        <div class="mb-4">
                                            <form method="get">
                                                <input type="hidden" name="page" value="assessment-360-forms">
                                                <input type="hidden" name="tab" value="preview">
                                                <select class="form-select" name="preview_position" style="max-width: 300px;" onchange="this.form.submit()">
                                                    <option value="">Select Position to Preview</option>
                                                    <?php foreach ($positions as $position): ?>
                                                        <option value="<?php echo esc_attr($position->id); ?>"
                                                                <?php selected(isset($_GET['preview_position']) && $_GET['preview_position'] == $position->id); ?>>
                                                            <?php echo esc_html($position->name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </div>
                                    </div>

                                    <?php
                                    // Show preview if position is selected
                                    if (isset($_GET['preview_position']) && !empty($_GET['preview_position'])):
                                        $position_id = intval($_GET['preview_position']);
                                        $position = $position_manager->get_position($position_id);

                                        if ($position):
                                            // Get sections with topics
                                            $sections = $section_manager->get_sections_by_position($position_id);

                                            if (empty($sections)):
                                                ?>
                                                <div class="alert alert-warning">
                                                    <p>No sections found for this position. Please add sections and questions first.</p>
                                                </div>
                                                <?php
                                            else:
                                                // Group sections by topic
                                                $grouped_sections = array();
                                                foreach ($sections as $section) {
                                                    if (!isset($grouped_sections[$section->topic_name])) {
                                                        $grouped_sections[$section->topic_name] = array();
                                                    }
                                                    $grouped_sections[$section->topic_name][] = $section;
                                                }
                                                ?>
                                                <div class="col-sm-8">
                                                    <div class="form-preview-wrapper">
                                                    <!-- Form Header -->
                                                    <div class="card mb-4">
                                                        <div class="card-body">
                                                            
                                                            <div class="card-title">
                                                                <h4 class="mb-0"><?php echo esc_html($position->name); ?> Assessment Form</h4>
                                                            </div>
                                                            
                                                            <!-- Topics and Sections -->
                                                            <div class="accordion" id="previewAccordion">
                                                                <?php foreach ($grouped_sections as $topic_name => $topic_sections): 
                                                                    $topic_id = sanitize_title($topic_name);
                                                                ?>
                                                                    <div class="accordion-item">
                                                                        <h2 class="accordion-header">
                                                                            <button class="accordion-button collapsed" type="button" 
                                                                                    data-bs-toggle="collapse" 
                                                                                    data-bs-target="#topic_<?php echo esc_attr($topic_id); ?>">
                                                                                <?php echo esc_html($topic_name); ?>
                                                                            </button>
                                                                        </h2>
                                                                        <div id="topic_<?php echo esc_attr($topic_id); ?>" 
                                                                             class="accordion-collapse collapse" 
                                                                             data-bs-parent="#previewAccordion">
                                                                            <div class="accordion-body">
                                                                                <?php foreach ($topic_sections as $section):
                                                                                    $questions = $question_manager->get_questions_by_section($section->id);
                                                                                    if (!empty($questions)):
                                                                                ?>
                                                                                    <div class="section-preview mb-4">
                                                                                        <h5 class="section-title"><?php echo esc_html($section->name); ?></h5>
                                                                                        <?php foreach ($questions as $question): ?>
                                                                                            <div class="question-preview card mb-3">
                                                                                                <div class="card-body">
                                                                                                    <div class="question-text mb-3">
                                                                                                        <?php echo esc_html($question->question_text); ?>
                                                                                                        <?php if ($question->is_mandatory): ?>
                                                                                                            <span class="text-danger">*</span>
                                                                                                        <?php endif; ?>
                                                                                                    </div>

                                                                                                    <div class="rating-options d-flex gap-3 mb-3">
                                                                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                                            <div class="form-check">
                                                                                                                <input class="form-check-input" type="radio" 
                                                                                                                       name="rating_<?php echo $question->id; ?>" 
                                                                                                                       value="<?php echo $i; ?>" 
                                                                                                                       <?php echo $question->is_mandatory ? 'required' : ''; ?> 
                                                                                                                       disabled>
                                                                                                                <label class="form-check-label"><?php echo $i; ?></label>
                                                                                                            </div>
                                                                                                        <?php endfor; ?>
                                                                                                    </div>

                                                                                                    <?php if ($question->has_comment_box): ?>
                                                                                                        <div class="comment-box">
                                                                                                            <label class="form-label">Comments:</label>
                                                                                                            <textarea class="form-control" rows="2" disabled 
                                                                                                                      placeholder="Space for comments"></textarea>
                                                                                                        </div>
                                                                                                    <?php endif; ?>
                                                                                                </div>
                                                                                            </div>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            <?php endforeach; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                </div>
                                            <?php
                                            endif;
                                        else:
                                            ?>
                                            <div class="alert alert-danger">
                                                <p>Position not found.</p>
                                            </div>
                                            <?php
                                        endif;
                                    else:
                                        ?>
                                    <div class="col-sm-8">
                                        <div class="card mb-4">
                                            <div class="alert alert-info">
                                                Select a position to preview its assessment form
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    endif;
                                    ?>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <p>No positions found. Create positions first to preview assessment forms.</p>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    </div>

                </div> <!-- End tab-content -->
            </div> <!-- End col-12 -->
        </div> <!-- End row -->
    </div> <!-- End container-fluid -->
</div> <!-- End wrap -->

<style>
/* WordPress Admin Adjustments */
.wrap h1 {padding-top: 0}
.wrap {
    margin: 10px 20px 0 2px;
}
.card {
    max-width: 100%;
    padding: .3em 1em 1em;} 

.wp-heading-inline {
    margin-top: 1rem !important;
}

/* Bootstrap Adjustments */
.card {
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
}

.nav-tabs .nav-link {
    color: #2271b1;
}

.nav-tabs .nav-link.active {
    color: #1d2327;
    border-color: #c3c4c7 #c3c4c7 #fff;
}

/* Form Elements */
form {max-width: 100%;}    
.form-control, .form-select {
    max-width: 100%;
}

.form-control:focus, .form-select:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
}

/* Table Adjustments */
.table {
    margin-top: 0.5rem;
}

.table > :not(caption) > * > * {
    padding: 0.75rem;
}

/* Badge Styling */
.badge {
    font-weight: normal;
    font-size: 0.85em;
}

/* Button Adjustments */
.btn-group {
    gap: 0.25rem;
    font-size: 12px
}

.btn-sm {
    padding: 0.25rem 0.5rem;
}

/* Accordion Customization */
.accordion{
    margin-top: 2rem ;
    padding: 2rem;
    background-color: #ffffff;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}
.accordion-button:not(.collapsed) {
    background-color: #f0f0f1;
    color: #1d2327;
}

.accordion-button:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 0.25rem rgba(34, 113, 177, 0.25);
}

/* Loading States */
.loading {
    position: relative;
    pointer-events: none;
    opacity: 0.6;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #2271b1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Adjustments */
@media (max-width: 782px) {
    .form-control, .form-select {
        max-width: 100%;
    }
    
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-group .btn {
        width: 100%;
    }
}

/* Preview Section */
#form-preview-content {
    min-height: 200px;
}

.preview-rating-scale {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.rating-item {
    flex: 1;
    min-width: 200px;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 4px;
}

.rating-number {
    font-size: 1.25rem;
    font-weight: bold;
    color: #2271b1;
}

.rating-label {
    display: block;
    font-weight: 500;
    margin: 0.5rem 0;
}

.rating-desc {
    font-size: 0.9em;
    color: #666;
}
    /* Add this to your existing styles */
.form-select:disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}

.form-select option {
    padding: 8px;
}

/* Loading state */
.form-select.loading {
    background-image: url('data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path fill="%236c757d" d="M8 0a8 8 0 0 0 0 16A8 8 0 0 0 8 0zm0 14A6 6 0 1 1 8 2a6 6 0 0 1 0 12z"/></svg>');
    background-position: right .75rem center;
    background-repeat: no-repeat;
    background-size: 16px;
}
.form-select:disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}

.debug-info {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.debug-info pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}
    /* Add this to your existing styles */
select[multiple] {
    height: auto !important;
    min-height: 120px;
}

select[multiple] option {
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
}

select[multiple] option:last-child {
    border-bottom: none;
}

select[multiple]:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Handle URL hash for tabs
    let hash = window.location.hash;
    if (hash) {
        const tab = new bootstrap.Tab(document.querySelector(`button[data-bs-target="${hash}"]`));
        tab.show();
    }

    // Update URL hash when tab changes
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        history.pushState(null, null, $(e.target).data('bs-target'));
    });

    // Position change handler for questions
    jQuery(document).ready(function($) {
        // Position change handler for questions
        $('#position_id').on('change', function() {
            const positionId = $(this).val();
            const sectionSelect = $('#section_id');

            console.log('Position changed to:', positionId);
            console.log('Available sections:', sectionsData);

            // Reset and disable section select if no position selected
            if (!positionId) {
                sectionSelect.html('<option value="">Select Position First</option>');
                return;
            }

            // Get sections for the selected position
            const sections = sectionsData[positionId] || [];
            console.log('Sections for position', positionId, ':', sections);

            if (sections && sections.length > 0) {
                // Build options HTML
                let options = '<option value="">Select Section</option>';
                sections.forEach(function(section) {
                    options += `<option value="${section.id}">
                        ${section.name} (${section.topic_name})
                    </option>`;
                });
                sectionSelect.html(options).prop('disabled', false);
                var selectedSection = <?php echo json_encode($edit_question ? $edit_question->section_id : ''); ?>;
                if (sections.length > 0 && selectedSection) {
                    sectionSelect.val(selectedSection);
                }
            } else {
                sectionSelect.html('<option value="">No sections found for this position</option>').prop('disabled', true);
            }
        });

        // Initialize sections if editing
        if ($('#position_id').val()) {
            $('#position_id').trigger('change');
        }
    });
    jQuery(document).ready(function($) {
        // Keep the accordion open based on URL hash
        if (window.location.hash) {
            const questionId = window.location.hash.split('question-')[1];
            if (questionId) {
                const accordion = $(`tr[data-question-id="${questionId}"]`)
                    .closest('.accordion-collapse');
                if (accordion.length) {
                    accordion.addClass('show');
                    accordion.prev().find('.accordion-button').removeClass('collapsed');

                    // Scroll to the question
                    $('html, body').animate({
                        scrollTop: $(`tr[data-question-id="${questionId}"]`).offset().top - 100
                    }, 500);
                }
            }
        }

        // Optional: Open first accordion by default if no specific accordion is targeted
        if (!window.location.hash) {
            $('.accordion-item:first-child .accordion-button').removeClass('collapsed');
            $('.accordion-item:first-child .accordion-collapse').addClass('show');
        }
    });

    // Form Preview handler
    $('#preview-position-select').on('change', function() {
        const positionId = $(this).val();
        const previewContent = $('#form-preview-content');
        
        if (!positionId) {
            previewContent.html(
                '<div class="alert alert-info">Select a position to preview its assessment form</div>'
            );
            return;
        }
        
        previewContent.addClass('loading').html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_form_preview',
                position_id: positionId,
                nonce: assessment360Ajax.nonces.form_preview
            },
            success: function(response) {
                if (response.success && response.data) {
                    previewContent.html(response.data);
                    initializePreviewAccordions();
                } else {
                    previewContent.html(
                        '<div class="alert alert-danger">' +
                        (response.data?.message || 'Error loading preview') +
                        '</div>'
                    );
                }
            },
            error: function() {
                previewContent.html(
                    '<div class="alert alert-danger">' +
                    'Server error occurred. Please try again.' +
                    '</div>'
                );
            },
            complete: function() {
                previewContent.removeClass('loading');
            }
        });
    });

    // Initialize preview accordions
    function initializePreviewAccordions() {
        $('.preview-accordion-header').on('click', function() {
            const accordion = $(this).closest('.preview-accordion');
            const content = accordion.find('.preview-accordion-content');
            
            $('.preview-accordion').not(accordion).removeClass('active')
                .find('.preview-accordion-content').slideUp();
            
            accordion.toggleClass('active');
            content.slideToggle();
        });
    }
        
    

    // Delete confirmations
    $('.delete-topic, .delete-section, .delete-question').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // Form validation
    $('form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true);
    });
});
</script>
