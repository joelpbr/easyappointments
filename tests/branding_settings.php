<?php
// Standalone checks: php tests/branding_settings.php (no database required).
define('BASEPATH', __DIR__ . '/../system/');
$settings = [];
function setting($key, $default = null)
{
    global $settings;
    return $settings[$key] ?? $default;
}
function base_url($path)
{
    return 'https://clinic.test/' . $path;
}
function site_url($path)
{
    return 'https://clinic.test/index.php/' . $path;
}
require __DIR__ . '/../application/helpers/branding_helper.php';
function check_branding($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
check_branding(branding_color() === '#063f7e', 'Default palette');
$settings['company_color'] = '#ffffff';
check_branding(branding_color() === '#063f7e', 'Legacy white marker');
$settings['company_accent_color'] = '#abc';
check_branding(branding_color() === '#ffffff', 'Explicit white palette');
check_branding(branding_color('company_accent_color') === '#abc', 'Short hex');
$settings['company_color'] = '</style><script>alert(1)</script>';
check_branding(branding_color() === '#063f7e', 'Malformed color fallback');
check_branding(branding_contrast('#fff') === '#000000', 'Light contrast');
check_branding(branding_contrast('#063f7e') === '#ffffff', 'Dark contrast');
$settings['company_name'] = '  Clínica de prova  ';
check_branding(branding_name() === 'Clínica de prova', 'Clinic name');
check_branding(branding_valid_logo(''), 'Logo can be cleared');
check_branding(
    !branding_valid_logo('data:image/png;base64,' . base64_encode('<script>bad</script>')),
    'Reject fake image',
);
check_branding(!branding_valid_logo('data:image/svg+xml;base64,' . base64_encode('<svg/>')), 'Reject SVG');
$settings['company_logo'] =
    'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../assets/img/logo-192.png'));
check_branding(branding_valid_logo($settings['company_logo']), 'Valid PNG');
check_branding(
    str_starts_with(branding_logo(), 'https://clinic.test/index.php/branding/logo?v='),
    'Public versioned logo',
);
$settings['company_logo'] = '';
check_branding(branding_logo() === 'https://clinic.test/assets/img/logo-192.png', 'Default logo after removal');
ob_start();
require __DIR__ . '/../application/views/components/company_color_style.php';
$css = ob_get_clean();
check_branding(!str_contains($css, '<script>'), 'Palette output escapes malformed settings');
check_branding(str_contains($css, '--ea-accent: #abc'), 'Accent reaches CSS');
echo "Branding settings checks passed.\n";
