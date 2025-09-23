<?php
/**
 * Plugin Name:       Elementor Forms SMS Integration
 * Plugin URI:        https://github.com/sajjad220/ElementorFormsSMS
 * Description:       A smart and flexible WordPress plugin for connecting Elementor forms to the IPPanel SMS service.
 * Version:           2.1.2
 * Author:            Sajjad Ehsanfar
 * Author URI:        https://mr-ehsanfar.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elementor-sms-integration
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) exit;

// Load plugin textdomain for translation
add_action('plugins_loaded', 'sms_plugin_load_textdomain');
function sms_plugin_load_textdomain() {
    load_plugin_textdomain('elementor-sms-integration', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// =========================================================================
//  Admin Settings Page
// =========================================================================

add_action('admin_menu', 'sms_plugin_add_admin_menu');
function sms_plugin_add_admin_menu() {
    add_menu_page(
        __('SMS Settings', 'elementor-sms-integration'),
        __('Elementor SMS', 'elementor-sms-integration'),
        'manage_options',
        'elementor_sms_settings', // Page Slug
        'sms_plugin_options_page_html',
        'dashicons-email-alt2'
    );
}

add_action('admin_init', 'sms_plugin_settings_init');
function sms_plugin_settings_init() {
    register_setting('sms_plugin_options_group', 'sms_plugin_settings', ['sanitize_callback' => 'sms_plugin_sanitize_settings']);

    // The page slug was corrected here from 'elementor-sms_settings' to 'elementor_sms_settings'
    $page_slug = 'elementor_sms_settings';

    add_settings_section('sms_plugin_main_section', __('1. IPPanel Service Information', 'elementor-sms-integration'), null, $page_slug);
    add_settings_field('api_key', __('API Key', 'elementor-sms-integration'), 'sms_plugin_render_field', $page_slug, 'sms_plugin_main_section', ['name' => 'api_key', 'placeholder' => __('Example: Dg5...YhY=', 'elementor-sms-integration')]);
    add_settings_field('sender_number', __('Sender Number', 'elementor-sms-integration'), 'sms_plugin_render_field', $page_slug, 'sms_plugin_main_section', ['name' => 'sender_number', 'placeholder' => __('Example: 3000500600700', 'elementor-sms-integration')]);
    add_settings_field('pattern_code', __('Pattern Code', 'elementor-sms-integration'), 'sms_plugin_render_field', $page_slug, 'sms_plugin_main_section', ['name' => 'pattern_code', 'placeholder' => __('Example: 98oDG4fhk7ijz', 'elementor-sms-integration')]);
    
    add_settings_section('sms_plugin_form_section', __('2. Elementor Form Settings', 'elementor-sms-integration'), null, $page_slug);
    add_settings_field('recipient_phone_label', __('Recipient Phone Field Label', 'elementor-sms-integration'), 'sms_plugin_render_field', $page_slug, 'sms_plugin_form_section', [
        'name' => 'recipient_phone_label',
        'placeholder' => __('Example: phone_number', 'elementor-sms-integration'),
        'desc' => __('The Label of the field where the recipient\'s mobile number is entered.', 'elementor-sms-integration')
    ]);

    add_settings_section('sms_plugin_mapping_section', __('3. Map Pattern Variables to Form Fields', 'elementor-sms-integration'), 'sms_plugin_mapping_section_callback', $page_slug);
}

// All other functions remain the same
function sms_plugin_mapping_section_callback() { echo '<p>' . __('In this section, specify which form field value should replace each variable in your SMS pattern.', 'elementor-sms-integration') . '</p>'; echo '<p><strong>' . __('Important Note:', 'elementor-sms-integration') . '</strong> ' . __('Elementor automatically converts spaces in field labels to underscores (_). Please use underscores instead of spaces in the table below.', 'elementor-sms-integration') . '</p>'; echo '<p><strong>' . __('Example:', 'elementor-sms-integration') . '</strong> ' . __('If your field label in Elementor is "Full Name", you should enter "Full_Name" in the table below.', 'elementor-sms-integration') . '</p>'; sms_plugin_render_mappings_table(); }
function sms_plugin_render_field($args) { $options = get_option('sms_plugin_settings', []); $name = $args['name']; $value = isset($options[$name]) ? esc_attr($options[$name]) : ''; $placeholder = isset($args['placeholder']) ? esc_attr($args['placeholder']) : ''; echo "<input type='text' name='sms_plugin_settings[{$name}]' value='{$value}' class='regular-text' dir='ltr' placeholder='{$placeholder}'>"; if (!empty($args['desc'])) { echo "<p class='description'>" . wp_kses_post($args['desc']) . "</p>"; } }
function sms_plugin_render_mappings_table() { $options = get_option('sms_plugin_settings', []); $mappings = !empty($options['mappings']) ? $options['mappings'] : [['label' => '', 'variable' => '']]; ?> <table id="sms-mappings-table" class="wp-list-table widefat striped"> <thead><tr><th style="width: 45%;"><?php _e('Field Label in Form (with underscores)', 'elementor-sms-integration'); ?></th><th style="width: 45%;"><?php _e('Variable Name in IPPanel Pattern', 'elementor-sms-integration'); ?></th><th style="width: 10%;"><?php _e('Remove', 'elementor-sms-integration'); ?></th></tr></thead> <tbody> <?php foreach ($mappings as $index => $mapping): ?> <tr class="mapping-row"> <td><input type="text" class="large-text" name="sms_plugin_settings[mappings][<?php echo $index; ?>][label]" value="<?php echo esc_attr($mapping['label']); ?>" placeholder="<?php esc_attr_e('Example: full_name', 'elementor-sms-integration'); ?>"></td> <td><input type="text" class="large-text" name="sms_plugin_settings[mappings][<?php echo $index; ?>][variable]" value="<?php echo esc_attr($mapping['variable']); ?>" placeholder="<?php esc_attr_e('Example: user-name', 'elementor-sms-integration'); ?>"></td> <td><button type="button" class="button button-secondary remove-mapping-row"><?php _e('Remove', 'elementor-sms-integration'); ?></button></td> </tr> <?php endforeach; ?> </tbody> <tfoot><tr><td colspan="3"><button type="button" id="add-mapping-row" class="button button-primary"><?php _e('Add New Row', 'elementor-sms-integration'); ?></button></td></tr></tfoot> </table> <script> document.addEventListener('DOMContentLoaded', function() { const tableBody = document.querySelector('#sms-mappings-table tbody'); const addRowButton = document.getElementById('add-mapping-row'); function createElement(tag, attributes) { const el = document.createElement(tag); for (const key in attributes) { el.setAttribute(key, attributes[key]); } return el; } addRowButton.addEventListener('click', function() { const newIndex = tableBody.rows.length; const newRow = document.createElement('tr'); newRow.className = 'mapping-row'; const cell1 = document.createElement('td'); const labelInput = createElement('input', { type: 'text', class: 'large-text', name: `sms_plugin_settings[mappings][${newIndex}][label]`, placeholder: '<?php echo esc_js(__('Example: order_id', 'elementor-sms-integration')); ?>' }); cell1.appendChild(labelInput); newRow.appendChild(cell1); const cell2 = document.createElement('td'); const variableInput = createElement('input', { type: 'text', class: 'large-text', name: `sms_plugin_settings[mappings][${newIndex}][variable]`, placeholder: '<?php echo esc_js(__('Example: order-id', 'elementor-sms-integration')); ?>' }); cell2.appendChild(variableInput); newRow.appendChild(cell2); const cell3 = document.createElement('td'); const removeButton = createElement('button', { type: 'button', class: 'button button-secondary remove-mapping-row' }); removeButton.textContent = '<?php echo esc_js(__('Remove', 'elementor-sms-integration')); ?>'; cell3.appendChild(removeButton); newRow.appendChild(cell3); tableBody.appendChild(newRow); }); tableBody.addEventListener('click', function(e) { if (e.target && e.target.classList.contains('remove-mapping-row')) { if (tableBody.rows.length > 1) { e.target.closest('tr').remove(); } else { alert('<?php echo esc_js(__('At least one mapping row must exist.', 'elementor-sms-integration')); ?>'); } } }); }); </script> <?php }
function sms_plugin_options_page_html() { ?> <div class="wrap"> <h1><?php echo esc_html(get_admin_page_title()); ?></h1> <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) { add_settings_error('sms_plugin_messages', 'sms_plugin_message', __('Settings saved successfully.', 'elementor-sms-integration'), 'updated'); } settings_errors('sms_plugin_messages'); ?> <form action="options.php" method="post"> <?php settings_fields('sms_plugin_options_group'); do_settings_sections('elementor_sms_settings'); submit_button(__('Save Settings', 'elementor-sms-integration')); ?> </form> <hr> <h2><?php _e('Connection Guide', 'elementor-sms-integration'); ?></h2> <p><?php _e('Copy the following URL into the "Webhook URL" field in your Elementor form settings:', 'elementor-sms-integration'); ?></p> <input type="text" readonly value="<?php echo esc_url(get_rest_url(null, 'sms-integration/v1/send')); ?>" class="large-text" dir="ltr"> </div> <?php }
function sms_plugin_sanitize_settings($input) { $sanitized_input = []; if (!empty($input) && is_array($input)) { foreach ($input as $key => $value) { if ($key === 'mappings') { $sanitized_input['mappings'] = array_values(array_filter(array_map(function($row) { $row['label'] = isset($row['label']) ? sanitize_text_field($row['label']) : ''; $row['variable'] = isset($row['variable']) ? sanitize_text_field($row['variable']) : ''; return (empty($row['label']) || empty($row['variable'])) ? null : $row; }, $value))); } else { $sanitized_input[$key] = sanitize_text_field($value); } } } return $sanitized_input; }
add_action('rest_api_init', 'sms_plugin_register_rest_route');
function sms_plugin_register_rest_route() { register_rest_route('sms-integration/v1', '/send', ['methods' => 'POST', 'callback' => 'handle_sms_webhook', 'permission_callback' => '__return_true']); }
function handle_sms_webhook(WP_REST_Request $request) { require_once plugin_dir_path(__FILE__) . 'autoload.php'; $options = get_option('sms_plugin_settings', []); $api_key = $options['api_key'] ?? ''; $sender_number = $options['sender_number'] ?? ''; $pattern_code = $options['pattern_code'] ?? ''; $recipient_phone_label = $options['recipient_phone_label'] ?? ''; $mappings = $options['mappings'] ?? []; if (empty($api_key) || empty($sender_number) || empty($recipient_phone_label) || empty($pattern_code)) { $error_message = __('The main plugin settings (API, Sender Number, Pattern Code, and Recipient Field) are not complete.', 'elementor-sms-integration'); error_log('SMS Plugin Error: ' . $error_message); return new WP_Error('config_error', $error_message, ['status' => 500]); } $params = $request->get_params(); $phone_number = isset($params[$recipient_phone_label]) ? sanitize_text_field($params[$recipient_phone_label]) : null; if (!$phone_number) { $error_message = __('Recipient phone number not found in the data sent from the form.', 'elementor-sms-integration'); error_log('SMS Plugin Error: ' . $error_message . ' | Expected Label: "' . $recipient_phone_label . '" | Received form data: ' . wp_json_encode($params)); return new WP_Error('missing_phone', $error_message, ['status' => 400]); } $pattern_values = []; foreach ($mappings as $mapping) { if (isset($params[$mapping['label']])) { $pattern_values[$mapping['variable']] = sanitize_text_field($params[$mapping['label']]); } } $client = new \IPPanel\Client($api_key); try { $messageId = $client->sendPattern($pattern_code, $sender_number, $phone_number, $pattern_values); return new WP_REST_Response(['success' => true, 'messageId' => $messageId], 200); } catch (\Exception $e) { $error_message = __('Error connecting to the SMS service: ', 'elementor-sms-integration') . $e->getMessage(); error_log('SMS Plugin Error: ' . $error_message); return new WP_Error('sms_api_error', $error_message, ['status' => 502]); } }
register_uninstall_hook(__FILE__, 'sms_plugin_cleanup_on_uninstall');
function sms_plugin_cleanup_on_uninstall() { delete_option('sms_plugin_settings'); }