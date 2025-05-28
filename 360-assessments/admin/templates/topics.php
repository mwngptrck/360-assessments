<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline">Topics</h1>
    <?php if (!isset($_GET['action']) || $_GET['action'] !== 'edit'): ?>
        <a href="?page=assessment-360-topics&action=new" class="page-title-action">Add New</a>
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
    $topic_manager = Assessment_360_Topic::get_instance();
    $section_manager = Assessment_360_Section::get_instance();
    $question_manager = Assessment_360_Question::get_instance();
    
    if (isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit')):
        $topic = null;
        if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
            $topic = $topic_manager->get_topic(intval($_GET['id']));
            if (!$topic) {
                echo '<div class="notice notice-error"><p>Topic not found.</p></div>';
                return;
            }
        }
    ?>
        <div class="topic-form-container">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('assessment_360_topic_nonce'); ?>
                <input type="hidden" name="action" value="assessment_360_save_topic">
                <?php if ($topic): ?>
                    <input type="hidden" name="topic_id" value="<?php echo esc_attr($topic->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="topic_name">Topic Name *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="topic_name" 
                                   name="topic_name" 
                                   class="regular-text" 
                                   value="<?php echo $topic ? esc_attr($topic->name) : ''; ?>" 
                                   required>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Topic</button>
                    <a href="?page=assessment-360-topics" class="button">Cancel</a>
                </p>
            </form>
        </div>

    <?php else: 
        $topics = $topic_manager->get_all_topics();
    ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Sections</th>
                    <th>Questions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($topics)): ?>
                    <?php foreach ($topics as $topic): 
                        // Get section count for this topic
                        $sections = $section_manager->get_sections_by_topic($topic->id);
                        $section_count = count($sections);
                        
                        // Get question count for this topic
                        $question_count = $question_manager->get_question_count_by_topic($topic->id);
                    ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="?page=assessment-360-topics&action=edit&id=<?php echo $topic->id; ?>">
                                        <?php echo esc_html($topic->name); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html($section_count); ?></td>
                            <td><?php echo esc_html($question_count); ?></td>
                            <td>
                                <a href="?page=assessment-360-topics&action=edit&id=<?php echo $topic->id; ?>" 
                                   class="button button-small">Edit</a>
                                
                                <?php if ($section_count == 0): ?>
                                    <a href="<?php echo wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'page' => 'assessment-360-topics',
                                                'action' => 'delete',
                                                'id' => $topic->id
                                            ),
                                            admin_url('admin.php')
                                        ),
                                        'delete_topic_' . $topic->id
                                    ); ?>" 
                                       class="button button-small"
                                       onclick="return confirm('Are you sure you want to delete this topic?');">
                                        Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No topics found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.topic-form-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}

.button-small {
    margin: 0 5px;
}

.button-small:first-child {
    margin-left: 0;
}

.wp-list-table .column-name {
    width: 40%;
}

.wp-list-table .column-sections,
.wp-list-table .column-questions {
    width: 20%;
}

.wp-list-table .column-actions {
    width: 20%;
}
</style>
