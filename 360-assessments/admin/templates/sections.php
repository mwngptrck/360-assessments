<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline">Sections</h1>
    <?php if (!isset($_GET['action']) || $_GET['action'] !== 'edit'): ?>
        <a href="?page=assessment-360-sections&action=new" class="page-title-action">Add New</a>
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
    $section_manager = Assessment_360_Section::get_instance();
    $topic_manager = Assessment_360_Topic::get_instance();
    $position_manager = Assessment_360_Position::get_instance();
    $question_manager = Assessment_360_Question::get_instance();
    
    if (isset($_GET['action']) && ($_GET['action'] == 'new' || $_GET['action'] == 'edit')):
        $section = null;
        if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
            $section = $section_manager->get_section(intval($_GET['id']));
            if (!$section) {
                echo '<div class="notice notice-error"><p>Section not found.</p></div>';
                return;
            }
        }

        // Get all topics
        $topics = $topic_manager->get_all_topics();
        
        // Get all positions
        $positions = $position_manager->get_all_positions();
    ?>
        <div class="section-form-container">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('assessment_360_section_nonce'); ?>
                <input type="hidden" name="action" value="assessment_360_save_section">
                <?php if ($section): ?>
                    <input type="hidden" name="section_id" value="<?php echo esc_attr($section->id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="section_name">Section Name *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="section_name" 
                                   name="section_name" 
                                   class="regular-text" 
                                   value="<?php echo $section ? esc_attr($section->name) : ''; ?>" 
                                   required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="topic_id">Topic *</label>
                        </th>
                        <td>
                            <select id="topic_id" name="topic_id" class="regular-text" required>
                                <option value="">Select Topic</option>
                                <?php foreach ($topics as $topic): ?>
                                    <option value="<?php echo esc_attr($topic->id); ?>" 
                                            <?php selected($section && $section->topic_id == $topic->id); ?>>
                                        <?php echo esc_html($topic->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="position_id">Position *</label>
                        </th>
                        <td>
                            <select id="position_id" name="position_id" class="regular-text" required>
                                <option value="">Select Position</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo esc_attr($position->id); ?>" 
                                            <?php selected($section && $section->position_id == $position->id); ?>>
                                        <?php echo esc_html($position->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Section</button>
                    <a href="?page=assessment-360-sections" class="button">Cancel</a>
                </p>
            </form>
        </div>

    <?php else: 
        $sections = $section_manager->get_all_sections();
    ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="topic_filter" id="topic-filter">
                    <option value="">All Topics</option>
                    <?php foreach ($topic_manager->get_all_topics() as $topic): ?>
                        <option value="<?php echo esc_attr($topic->id); ?>">
                            <?php echo esc_html($topic->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="position_filter" id="position-filter">
                    <option value="">All Positions</option>
                    <?php foreach ($position_manager->get_all_positions() as $position): ?>
                        <option value="<?php echo esc_attr($position->id); ?>">
                            <?php echo esc_html($position->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button" id="filter-button">Filter</button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Topic</th>
                    <th>Position</th>
                    <th>Questions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sections)): ?>
                    <?php foreach ($sections as $section): 
                        $question_count = $question_manager->get_question_count_by_section($section->id);
                    ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="?page=assessment-360-sections&action=edit&id=<?php echo $section->id; ?>">
                                        <?php echo esc_html($section->name); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html($section->topic_name); ?></td>
                            <td><?php echo esc_html($section->position_name); ?></td>
                            <td><?php echo esc_html($question_count); ?></td>
                            <td>
                                <a href="?page=assessment-360-sections&action=edit&id=<?php echo $section->id; ?>" 
                                   class="button button-small">Edit</a>
                                
                                <?php if ($question_count == 0): ?>
                                    <a href="<?php echo wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'page' => 'assessment-360-sections',
                                                'action' => 'delete',
                                                'id' => $section->id
                                            ),
                                            admin_url('admin.php')
                                        ),
                                        'delete_section_' . $section->id
                                    ); ?>" 
                                       class="button button-small"
                                       onclick="return confirm('Are you sure you want to delete this section?');">
                                        Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No sections found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.section-form-container {
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

.tablenav select {
    margin-right: 8px;
}

.wp-list-table .column-name {
    width: 25%;
}

.wp-list-table .column-topic,
.wp-list-table .column-position {
    width: 20%;
}

.wp-list-table .column-questions {
    width: 15%;
}

.wp-list-table .column-actions {
    width: 20%;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Filter functionality
    $('#filter-button').on('click', function() {
        var topic = $('#topic-filter').val();
        var position = $('#position-filter').val();
        var url = new URL(window.location.href);
        
        if (topic) {
            url.searchParams.set('topic', topic);
        } else {
            url.searchParams.delete('topic');
        }
        
        if (position) {
            url.searchParams.set('position', position);
        } else {
            url.searchParams.delete('position');
        }
        
        window.location.href = url.toString();
    });

    // Set filter values from URL
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('topic')) {
        $('#topic-filter').val(urlParams.get('topic'));
    }
    if (urlParams.has('position')) {
        $('#position-filter').val(urlParams.get('position'));
    }
});
</script>
