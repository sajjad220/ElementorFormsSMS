<?php
/**
 * Plugin Name:       اتصال پیامک المنتور به IPPanel
 * Description:       اطلاعات فرم المنتور را از طریق Webhook دریافت کرده و با IPPanel پیامک ارسال می‌کند.
 * Version:           2.0.1
 * Author:            Sajjad Ehsanfar
 * Author URI:        https://mr-ehsanfar.ir
 */

if (!defined('ABSPATH')) exit;

// =========================================================================
//  بخش اول: ساخت صفحه تنظیمات
// =========================================================================

// ثبت اکشن‌ها با توابع نام‌گذاری شده برای سازگاری کامل
add_action('admin_menu', 'sms_plugin_add_admin_menu');
add_action('admin_init', 'sms_plugin_settings_init');

function sms_plugin_add_admin_menu() {
    add_menu_page('تنظیمات پیامک', 'پیامک المنتور', 'manage_options', 'elementor_sms_settings', 'sms_plugin_options_page_html', 'dashicons-email-alt2');
}

function sms_plugin_settings_init() {
    register_setting('sms_plugin_options_group', 'sms_plugin_settings', ['sanitize_callback' => 'sms_plugin_sanitize_settings']);

    // بخش ۱: تنظیمات اصلی
    add_settings_section('sms_plugin_main_section', '۱. اطلاعات سرویس IPPanel', null, 'elementor_sms_settings');
    add_settings_field('api_key', 'کلید API', 'sms_plugin_render_field', 'elementor_sms_settings', 'sms_plugin_main_section', ['name' => 'api_key', 'type' => 'text', 'placeholder' => 'مثال: Dg5...YhY=']);
    add_settings_field('sender_number', 'شماره فرستنده', 'sms_plugin_render_field', 'elementor_sms_settings', 'sms_plugin_main_section', ['name' => 'sender_number', 'type' => 'text', 'placeholder' => 'مثال: 3000500600700']);
    add_settings_field('pattern_code', 'کد الگو', 'sms_plugin_render_field', 'elementor_sms_settings', 'sms_plugin_main_section', ['name' => 'pattern_code', 'type' => 'text', 'placeholder' => 'مثال: 98oDG4fhk7ijz']);
    
    // بخش ۲: تنظیمات فرم
    add_settings_section('sms_plugin_form_section', '۲. تنظیمات اتصال به فرم المنتور', null, 'elementor_sms_settings');
    add_settings_field('recipient_phone_label', 'عنوان فیلد شماره گیرنده', 'sms_plugin_render_field', 'elementor_sms_settings', 'sms_plugin_form_section', ['name' => 'recipient_phone_label', 'type' => 'text', 'placeholder' => 'مثال: شماره موبایل', 'desc' => 'عنوان (Label) فیلدی که شماره موبایل گیرنده در آن وارد می‌شود.']);

    // بخش ۳: اتصال متغیرهای الگو
    add_settings_section('sms_plugin_mapping_section', '۳. اتصال متغیرهای الگو به فیلدهای فرم', 'sms_plugin_mapping_section_callback', 'elementor_sms_settings');
}

function sms_plugin_render_field($args) {
    $options = get_option('sms_plugin_settings', []);
    $name = $args['name'];
    $value = isset($options[$name]) ? esc_attr($options[$name]) : '';
    $placeholder = $args['placeholder'] ?? '';
    echo "<input type='text' name='sms_plugin_settings[{$name}]' value='{$value}' class='regular-text' dir='ltr' placeholder='{$placeholder}'>";
    if (!empty($args['desc'])) {
        echo "<p class='description'>" . esc_html($args['desc']) . "</p>";
    }
}

function sms_plugin_mapping_section_callback() {
    echo '<p>در این بخش، مشخص کنید که مقدار هر فیلد از فرم شما، باید جایگزین کدام متغیر در الگوی پیامک‌تان شود.</p>';
    sms_plugin_render_mappings_table();
}

function sms_plugin_render_mappings_table() {
    $options = get_option('sms_plugin_settings', []);
    $mappings = !empty($options['mappings']) ? $options['mappings'] : [['label' => '', 'variable' => '']];
    ?>
    <table id="sms-mappings-table" class="wp-list-table widefat striped">
        <thead><tr><th style="width: 45%;">عنوان فیلد در فرم المنتور</th><th style="width: 45%;">نام متغیر در الگوی IPPanel</th><th style="width: 10%;">حذف</th></tr></thead>
        <tbody>
            <?php foreach ($mappings as $index => $mapping): ?>
            <tr class="mapping-row">
                <td><input type="text" class="large-text" name="sms_plugin_settings[mappings][<?php echo $index; ?>][label]" value="<?php echo esc_attr($mapping['label']); ?>" placeholder="مثال: نام و نام خانوادگی"></td>
                <td><input type="text" class="large-text" name="sms_plugin_settings[mappings][<?php echo $index; ?>][variable]" value="<?php echo esc_attr($mapping['variable']); ?>" placeholder="مثال: user-name"></td>
                <td><button type="button" class="button button-secondary remove-mapping-row">حذف</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="3"><button type="button" id="add-mapping-row" class="button button-primary">افزودن ردیف جدید</button></td></tr></tfoot>
    </table>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let tableBody = document.querySelector('#sms-mappings-table tbody');
            document.getElementById('add-mapping-row').addEventListener('click', function() {
                let newIndex = tableBody.rows.length;
                let newRow = document.createElement('tr');
                newRow.className = 'mapping-row';
                newRow.innerHTML = `<td><input type="text" class="large-text" name="sms_plugin_settings[mappings][${newIndex}][label]" placeholder="مثال: کد سفارش"></td><td><input type="text" class="large-text" name="sms_plugin_settings[mappings][${newIndex}][variable]" placeholder="مثال: order-id"></td><td><button type="button" class="button button-secondary remove-mapping-row">حذف</button></td>`;
                tableBody.appendChild(newRow);
            });
            tableBody.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-mapping-row')) {
                    if (tableBody.rows.length > 1) {
                        e.target.closest('tr').remove();
                    } else {
                        alert('حداقل یک ردیف برای اتصال متغیرها باید وجود داشته باشد.');
                    }
                }
            });
        });
    </script>
    <?php
}

function sms_plugin_sanitize_settings($input) {
    $sanitized_input = [];
    foreach ($input as $key => $value) {
        if ($key === 'mappings') {
            $sanitized_input['mappings'] = array_values(array_filter(array_map(function($row) {
                $row['label'] = sanitize_text_field($row['label']);
                $row['variable'] = sanitize_text_field($row['variable']);
                return (empty($row['label']) || empty($row['variable'])) ? null : $row;
            }, $value)));
        } else {
            $sanitized_input[$key] = sanitize_text_field($value);
        }
    }
    return $sanitized_input;
}

function sms_plugin_options_page_html() { ?> <div class="wrap"> <h1><?php echo esc_html(get_admin_page_title()); ?></h1> <form action="options.php" method="post"> <?php settings_fields('sms_plugin_options_group'); do_settings_sections('elementor_sms_settings'); submit_button('ذخیره تنظیمات'); ?> </form> <hr> <h2>راهنمای اتصال</h2> <p>آدرس زیر را در فیلد "Webhook URL" در تنظیمات فرم المنتور کپی کنید:</p> <input type="text" readonly value="<?php echo esc_url(get_rest_url(null, 'sms-integration/v1/send')); ?>" class="large-text" dir="ltr"> </div> <?php }

// =========================================================================
//  بخش دوم: دریافت اطلاعات از المنتور
// =========================================================================

add_action('rest_api_init', 'sms_plugin_register_rest_route');
function sms_plugin_register_rest_route() {
    register_rest_route('sms-integration/v1', '/send', ['methods' => 'POST', 'callback' => 'handle_sms_webhook', 'permission_callback' => '__return_true']);
}

function handle_sms_webhook(WP_REST_Request $request) {
    require_once plugin_dir_path(__FILE__) . 'autoload.php';

    $options = get_option('sms_plugin_settings', []);
    $api_key = $options['api_key'] ?? '';
    $sender_number = $options['sender_number'] ?? '';
    $pattern_code = $options['pattern_code'] ?? '';
    $recipient_phone_label = $options['recipient_phone_label'] ?? '';
    $mappings = $options['mappings'] ?? [];
    
    if (empty($api_key) || empty($recipient_phone_label) || empty($pattern_code)) {
        return new WP_Error('config_error', 'تنظیمات اصلی افزونه (API، شماره فرستنده، کد الگو و فیلد گیرنده) کامل نیست.', ['status' => 500]);
    }

    $params = $request->get_json_params();
    $phone_number = isset($params[$recipient_phone_label]) ? sanitize_text_field($params[$recipient_phone_label]) : null;

    if (!$phone_number) {
        return new WP_Error('missing_phone', 'شماره تلفن گیرنده در اطلاعات ارسالی از فرم یافت نشد. عنوان فیلد گیرنده را در تنظیمات بررسی کنید.', ['status' => 400]);
    }
    
    $pattern_values = [];
    foreach ($mappings as $mapping) {
        if (isset($params[$mapping['label']])) {
            $pattern_values[$mapping['variable']] = sanitize_text_field($params[$mapping['label']]);
        }
    }
    
    $client = new \IPPanel\Client($api_key);

    try {
        $messageId = $client->sendPattern($pattern_code, $sender_number, $phone_number, $pattern_values);
        return new WP_REST_Response(['success' => true, 'messageId' => $messageId], 200);
    } catch (\Exception $e) {
        error_log('IPPanel SMS Plugin Error: ' . $e->getMessage());
        return new WP_Error('sms_api_error', 'خطا در ارتباط با سرویس پیامک: ' . $e->getMessage(), ['status' => 502]);
    }
}

// =========================================================================
//  بخش سوم: پاک کردن اطلاعات هنگام حذف افزونه
// =========================================================================

// تعریف تابع پاک‌سازی
function sms_plugin_cleanup_on_uninstall() {
    delete_option('sms_plugin_settings');
}
// ثبت هوک حذف افزونه با استفاده از تابع نام‌گذاری شده
register_uninstall_hook(__FILE__, 'sms_plugin_cleanup_on_uninstall');