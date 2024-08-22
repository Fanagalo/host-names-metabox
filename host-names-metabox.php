<?php

/*
Plugin Name: Host Names Metabox
Description: User interface for vernacular names, synonyms and notes
Version: 1.20
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
function get_multi_value_name_keys()
{
    return ['en_vernacular', 'nl_vernacular', 'synonym'];
}

function get_single_value_name_keys()
{
    return ['name_note'];
}


// Substitutes key names with nice names
function nice_name($subject)
{
    $search  = array('en_vernacular', 'nl_vernacular', 'synonym', 'name_note');
    $replace = array('English vernacular name', 'Dutch vernacular name', 'Synonym', 'Note');
    return str_replace($search, $replace, $subject);
}

// Add custom meta box
function add_custom_meta_box()
{

    // Conditional for certain templates
    global $post;

    if (!empty($post)) {
        $allowed_templates = array('host-genus-determination.php', 'host-species-determination.php');
        $template = get_post_meta($post->ID, '_wp_page_template', true);

        if (in_array($template, $allowed_templates)) {

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
    $multi_value_meta_keys = get_multi_value_name_keys();
    $single_value_meta_keys = get_single_value_name_keys();

?>

    <input type="hidden" name="custom_meta_box_nonce" value="<?php echo wp_create_nonce(basename(__FILE__)); ?>">

    <?php
    // keys with multiple values in a text input field

    foreach ($multi_value_meta_keys as $multi_value_meta_key) : ?>
        <?php $multi_meta_values = get_post_meta($post->ID, $multi_value_meta_key, false); ?>
        <div id="<?php echo esc_attr($multi_value_meta_key); ?>-fields">
            <h3><?php echo esc_html(nice_name($multi_value_meta_key)); ?></h3>
            <?php if (!empty($multi_meta_values)) :
                foreach ($multi_meta_values as $multi_value) { ?>
                    <p>
                        <label for="<?php echo esc_attr($multi_value_meta_key); ?>"><?php echo esc_html($multi_value_meta_key) ?></label>
                        <input type="text" name="<?php echo esc_attr($multi_value_meta_key); ?>[]" value="<?php echo esc_attr($multi_value); ?>" size="60">
                        <button class="remove_field button button-small">Remove</button>
                    </p>
            <?php }
            endif; ?>
        </div>
        <p>
            <button class="add_field_button button button-small button-primary" data-field="<?php echo esc_attr($multi_value_meta_key); ?>">Add Row</button>
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

    <?php
    // keys with single values in a textarea field

    foreach ($single_value_meta_keys as $single_value_meta_key) : ?>
        <?php $single_meta_values = get_post_meta($post->ID, $single_value_meta_key, false); ?>
        <div id="<?php echo esc_attr($single_value_meta_key); ?>-textarea">
            <h3><?php echo esc_html(nice_name($single_value_meta_key)); ?></h3>
            <?php 
            if (!empty($single_meta_values)) :
                foreach ($single_meta_values as $single_value) { ?>
                    <p>
                        <label for="<?php echo esc_attr($single_value_meta_key); ?>"><?php echo esc_html($single_value_meta_key) ?></label>
                        <textarea name="<?php echo esc_attr($single_value_meta_key); ?>[]" rows="5" cols="60"><?php echo esc_attr($single_value); ?></textarea>
                        <button class="remove_field button button-small">Remove</button>
                    </p>
                <?php }
            else: ?>
                </div>
                    <p>
                        <button class="add_field_button button button-small button-primary" data-field="<?php echo esc_attr($single_value_meta_key); ?>">Add Field</button>
                    </p>
                <div>
            <?php
            endif; ?>
        </div>

    <?php endforeach; ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var max_fields = 1; //maximum input boxes allowed

            $('.add_field_button').click(function(e) {
                e.preventDefault();
                var field = $(this).data('field');
                var wrapper = $('#' + field + '-textarea');
                var x = wrapper.find('textarea').length;

                if (x < max_fields) { //max input box allowed
                    x++; //text box increment
                    $(wrapper).append('<p><label for="' + field + '">' + field.replace(/_/g, ' ') + '</label><textarea name="' + field + '[]" value=""  rows="5" cols="60"></textarea><button class="remove_field button button-small">Remove</button></p>'); //add input box
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

    // Check if it is a revision.
    if (wp_is_post_revision($post_id)) {
        return $post_id;
    }

    // Check permissions
    if (!current_user_can('edit_page', $post_id)) {
        return $post_id;
    }


    $multi_value_meta_keys = get_multi_value_name_keys();
    $single_value_meta_keys = get_single_value_name_keys();
    $meta_keys = array_merge($multi_value_meta_keys, $single_value_meta_keys);

    // $meta_keys = get_multi_value_name_keys();

    foreach ($meta_keys as $meta_key) {
        // Get existing meta values
        $old_meta_values = get_post_meta($post_id, $meta_key, false);
        // $new_meta_values = isset($_POST[$meta_key]) ? array_map('sanitize_text_field', $_POST[$meta_key]) : array(); // origineel van Gerard
        $new_meta_values = isset($_POST[$meta_key]) ? $_POST[$meta_key] : array();
        // First make sure the values are unique, so there is only one value if a user fills in the same value twice.
        $unique_new_meta_values = array_unique($new_meta_values);

        // Compare old and new meta values
        $meta_to_delete = array_diff($old_meta_values, $unique_new_meta_values);
        $meta_to_add = array_diff($unique_new_meta_values, $old_meta_values);

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
add_action('save_post_page', 'save_custom_meta');
