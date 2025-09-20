<?php
/**
 * Plugin Name:       اتصال پیامک المنتور به IPPanel
 * Description:       اطلاعات فرم المنتور را از طریق Webhook دریافت کرده و با IPPanel پیامک ارسال می‌کند.
 * Version:           1.1.0
 * Author:            Sajjad Ehsanfar
 * Author URI:        https://mr-ehsanfar.ir
 */

// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

// =========================================================================
//  بخش اول: ساخت صفحه تنظیمات در داشبورد وردپرس
// =========================================================================

// (این بخش هیچ تغییری نکرده است)
add_action('admin_menu', 'sms_plugin_add_admin_menu');
function sms_plugin_add_admin_menu() {
    add_menu_page('تنظیمات پیامک IPPanel', 'پیامک المنتور', 'manage_options', 'elementor_sms_settings', 'sms_plugin_options_page_html', 'dashicons-email-alt2');
}

add_action('admin_init', 'sms_plugin_settings_init');
function sms_plugin_settings_init() {
    register_setting('sms_plugin_options_group', 'sms_plugin_settings');
    add_settings_section('sms_plugin_main_section', 'اطلاعات سرویس IPPanel', null, 'elementor_sms_settings');
    add_settings_field('api_key', 'کلید API', 'sms_plugin_api_key_field_html', 'elementor_sms_settings', 'sms_plugin_main_section');
    add_settings_field('sender_number', 'شماره فرستنده', 'sms_plugin_sender_number_field_html', 'elementor_sms_settings', 'sms_plugin_main_section');
    add_settings_field('pattern_code', 'کد الگو (Pattern Code)', 'sms_plugin_pattern_code_field_html', 'elementor_sms_settings', 'sms_plugin_main_section');
}

function sms_plugin_api_key_field_html() {
    $options = get_option('sms_plugin_settings', []);
    $api_key = isset($options['api_key']) ? esc_attr($options['api_key']) : '';
    echo '<input type="text" name="sms_plugin_settings[api_key]" value="' . $api_key . '" class="regular-text" dir="ltr">';
}

function sms_plugin_sender_number_field_html() {
    $options = get_option('sms_plugin_settings', []);
    $sender_number = isset($options['sender_number']) ? esc_attr($options['sender_number']) : '';
    echo '<input type="text" name="sms_plugin_settings[sender_number]" value="' . $sender_number . '" class="regular-text" dir="ltr">';
}

function sms_plugin_pattern_code_field_html() {
    $options = get_option('sms_plugin_settings', []);
    $pattern_code = isset($options['pattern_code']) ? esc_attr($options['pattern_code']) : '';
    echo '<input type="text" name="sms_plugin_settings[pattern_code]" value="' . $pattern_code . '" class="regular-text" dir="ltr">';
}

function sms_plugin_options_page_html() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('sms_plugin_options_group');
            do_settings_sections('elementor_sms_settings');
            submit_button('ذخیره تنظیمات');
            ?>
        </form>
        <hr>
        <h2>راهنمای اتصال به المنتور</h2>
        <p>برای ارسال اطلاعات فرم به این افزونه، از اکشن <strong>Webhook</strong> در تنظیمات فرم المنتور استفاده کنید.</p>
        <p>آدرس زیر را در فیلد "Webhook URL" کپی و جای‌گذاری کنید:</p>
        <input type="text" readonly="readonly" value="<?php echo esc_url(get_rest_url(null, 'sms-integration/v1/send')); ?>" class="large-text" dir="ltr">
    </div>
    <?php
}

// =========================================================================
// بخش دوم: ساخت API Endpoint برای دریافت اطلاعات از المنتور
// =========================================================================

// (این بخش هیچ تغییری نکرده است)
add_action('rest_api_init', 'sms_plugin_register_rest_route');
function sms_plugin_register_rest_route() {
    register_rest_route('sms-integration/v1', '/send', [
        'methods' => 'POST',
        'callback' => 'handle_sms_webhook',
        'permission_callback' => '__return_true'
    ]);
}

function handle_sms_webhook(WP_REST_Request $request) {
    $autoload_path = plugin_dir_path(__FILE__) . 'autoload.php';
    if (!file_exists($autoload_path)) {
        return new WP_Error('library_missing', 'فایل autoload.php در ریشه افزونه پیدا نشد.', ['status' => 500]);
    }
    require_once $autoload_path;

    $params = $request->get_json_params();
    $phone_number = isset($params['شماره_تلفن_همراه_پیمانکار']) ? sanitize_text_field($params['شماره_تلفن_همراه_پیمانکار']) : null;
    $post_title = isset($params['نام_مناقصه']) ? sanitize_text_field($params['نام_مناقصه']) : null;

    if (!$phone_number || !$post_title) {
        return new WP_Error('missing_params', 'پارامترهای شماره تلفن و نام مناقصه الزامی است.', ['status' => 400]);
    }

    $options = get_option('sms_plugin_settings', []);
    $api_key = $options['api_key'] ?? '';
    $sender_number = $options['sender_number'] ?? '';
    $pattern_code = $options['pattern_code'] ?? '';

    if (empty($api_key) || empty($sender_number) || empty($pattern_code)) {
        return new WP_Error('config_error', 'تنظیمات افزونه پیامک کامل نیست.', ['status' => 500]);
    }

    $pattern_values = ["tend-name" => $post_title];
    $client = new \IPPanel\Client($api_key);

    try {
        $messageId = $client->sendPattern($pattern_code, $sender_number, $phone_number, $pattern_values);
        return new WP_REST_Response(['success' => true, 'messageId' => $messageId], 200);

    } catch (\Exception $e) {
        error_log('IPPanel SMS Plugin Error: ' . $e->getMessage());
        return new WP_Error('sms_api_error', 'خطا در ارتباط با سرویس پیامک.', ['status' => 502]);
    }
}

// =========================================================================
// بخش سوم: تابع برای پاک کردن اطلاعات هنگام حذف افزونه
// =========================================================================

/**
 * این تابع هنگام حذف افزونه اجرا شده و تنظیمات را از دیتابیس پاک می‌کند.
 */
function sms_plugin_cleanup_on_uninstall() {
    // حذف گروه تنظیماتی که در دیتابیس ذخیره شده است
    delete_option('sms_plugin_settings');
}
// ثبت تابع بالا برای اجرا شدن در رویداد "حذف" افزونه
register_uninstall_hook(__FILE__, 'sms_plugin_cleanup_on_uninstall');