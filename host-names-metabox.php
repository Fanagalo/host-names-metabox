<?php

/*
Plugin Name: Host Names Metabox
Description: User interface for vernacular names, synonyms and notes
Version: 1.12
Author: Jaap Wiering
Author URI: https://fanagalo.nl
Text Domain: bladmineerders-fngl
License: GPLv2
*/

// Enqueue CSS for admin
function enqueue_admin_styles()
{
    wp_enqueue_style('host-names-metabox', plugin_dir_url(__FILE__) . 'admin/css/host-names-metabox.css');
}
add_action('admin_enqueue_scripts', 'enqueue_admin_styles');

// Get names for the keys, used in functions as a sort of global variable
function get_names_keys()
{
    return ['en_vernacular', 'nl_vernacular', 'synonym', 'name_note'];
}

// Substitutes key names with nice names
function nice_name($subject)
{
    $search  = array('en_vernacular', 'nl_vernacular', 'synonym', 'name_note');
    $replace = array('English vernacular name', 'Dutch vernacular name', 'Synonym', 'Note');
    return str_replace($search, $replace, $subject);
}

    // Add custom meta box
    function add_custom_meta_box() {

        // Conditional for certain templates
        global $post;

        if (!empty($post)) {
            $allowed_templates = array('host-genus-determination.php', 'host-species-determination.php');
            $template = get_post_meta($post->ID, '_wp_page_template', true);

            if (in_array( $template, $allowed_templates)) {

                // Creation of meta box
                add_meta_box(
                    'custom_meta_box', // $id
                    'Name alternatives', // $title
                    'show_custom_meta_box', // $callback
                    'page', // $screen
                    'normal', // $context
                    'high' // $priority
                );
            }
        }
    }
    add_action('add_meta_boxes', 'add_custom_meta_box');

// Show custom meta box
function show_custom_meta_box()
{
    global $post;
    $meta_keys = get_names_keys();

?>

    <input type="hidden" name="custom_meta_box_nonce" value="<?php echo wp_create_nonce(basename(__FILE__)); ?>">

    <?php foreach ($meta_keys as $meta_key) : ?>
        <?php $meta_values = get_post_meta($post->ID, $meta_key, false); ?>
        <div id="<?php echo esc_attr($meta_key); ?>-fields">
            <h3><?php echo esc_html(nice_name($meta_key)); ?></h3>
            <?php if (!empty($meta_values)) :
                foreach ($meta_values as $value) { ?>
                    <p>
                        <label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($meta_key)?></label>
                        <input type="text" name="<?php echo esc_attr($meta_key); ?>[]" value="<?php echo esc_attr($value); ?>" size="60">
                        <button class="remove_field button button-small">Remove</button>
                    </p>
                <?php }
            endif; ?>
        </div>
        <p>
            <button class="add_field_button button button-small button-primary" data-field="<?php echo esc_attr($meta_key); ?>">Add Row</button>
        </p>

    <?php endforeach; ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var max_fields = 999; //maximum input boxes allowed

            $('.add_field_button').click(function(e) {
                e.preventDefault();
                var field = $(this).data('field');
                var wrapper = $('#' + field + '-fields');
                var x = wrapper.find('input[type="text"]').length;

                if (x < max_fields) { //max input box allowed
                    x++; //text box increment
                    $(wrapper).append('<p><label for="' + field + '">' + field.replace(/_/g, ' ') + '</label><input type="text" name="' + field + '[]" value="" size="60"><button class="remove_field button button-small">Remove</button></p>'); //add input box
                }
            });

            $(document).on("click", ".remove_field", function(e) { //user click on remove text
                e.preventDefault();
                $(this).parent('p').remove();
            });
        });
    </script>
<?php }

// Save custom meta data
function save_custom_meta($post_id)
{
    // Verify nonce
    if (!isset($_POST['custom_meta_box_nonce']) || !wp_verify_nonce($_POST['custom_meta_box_nonce'], basename(__FILE__))) {
        return $post_id;
    }
    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }
    // Check permissions
    if (isset($_POST['post_type']) && 'page' === $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return $post_id;
        }
    } elseif (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    $meta_keys = get_names_keys();

    foreach ($meta_keys as $meta_key) {
        // Get existing meta values
        $old_meta_values = get_post_meta($post_id, $meta_key, false);
        $new_meta_values = isset($_POST[$meta_key]) ? array_map('sanitize_text_field', $_POST[$meta_key]) : array();

        // Compare old and new meta values
        $meta_to_delete = array_diff($old_meta_values, $new_meta_values);
        $meta_to_add = array_diff($new_meta_values, $old_meta_values);

        echo "old_meta_values"; var_dump($old_meta_values);
        echo "new_meta_values"; var_dump($new_meta_values);
        echo "meta_to_deletes"; var_dump($meta_to_delete);
        echo "meta_to_add"; var_dump($meta_to_add);
        

        // Delete removed meta values
        foreach ($meta_to_delete as $meta_value) {
            delete_post_meta($post_id, $meta_key, $meta_value);
        }

        // Add new unique meta values
        foreach ($meta_to_add as $meta_value) {
            if (!empty($meta_value)) {
                add_post_meta($post_id, $meta_key, $meta_value, false);
            }
        }
    }  
}
add_action('save_post', 'save_custom_meta');

// Function to display Name meta fields
function display_name_meta($post_id)
{
    // Check if post type is 'page'
    if (get_post_type($post_id) !== 'page') {
        return '';
    }

    // Show the names of the host
    $output = '';
    $output .= "<h2>" . get_the_title() . "</h2>";

    $meta_keys = get_names_keys();

    // Build HTML output for each meta key
    foreach ($meta_keys as $meta_key) {
        $meta_values = get_post_meta($post_id, $meta_key, false);
        if (!empty($meta_values)) {
            $output .= '<div class="name-' . esc_attr($meta_key) . '">';
            $output .= '<h3>' . esc_html(nice_name($meta_key)) . ':</h3>';
            $output .= '<ul>';
            foreach ($meta_values as $meta_value) {
                $output .= '<li>' . esc_html($meta_value) . '</li>';
            }
            $output .= '</ul>';
            $output .= '</div>';
        }
    }

    return $output;
}

function display_all_names_meta()
{
    $content = '';
    $args = array(
        'post_type'      => 'page',
        'posts_per_page' => -1,
    );
    $names_query = new WP_Query($args);

    if ($names_query->have_posts()) {
        while ($names_query->have_posts()) {
            $names_query->the_post();
            $names_id = get_the_ID();
            $content .= display_names_meta($names_id);
        }
        wp_reset_postdata();
    } else {
        $content .= 'No names found.';
    }
    return $content;
}
