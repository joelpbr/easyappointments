<?php defined('BASEPATH') or exit('No direct script access allowed');

function branding_name(): string
{
    return trim((string) setting('company_name')) ?: 'ProgramaConsulta';
}

function branding_color(string $name = 'company_color'): string
{
    $fallback = $name === 'company_accent_color' ? '#029ecb' : '#063f7e';
    $value = (string) setting($name, $fallback);
    // White was the old marker for “use the theme's default”.
    if ($name === 'company_color' && strtolower($value) === '#ffffff' && setting('company_accent_color') === null) {
        return $fallback;
    }
    return preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $value) ? $value : $fallback;
}

function branding_contrast(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $channels = array_map(function ($part) {
        $value = hexdec($part) / 255;
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, str_split($hex, 2));
    $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    return $luminance > 0.179 ? '#000000' : '#ffffff';
}

function branding_valid_logo(string $value): bool
{
    if ($value === '') {
        return true;
    }
    if (
        strlen($value) > 2800000 ||
        !preg_match('~^data:image/(png|jpeg|webp|gif);base64,([A-Za-z0-9+/=\r\n]+)$~', $value, $match)
    ) {
        return false;
    }
    $bytes = base64_decode($match[2], true);
    $image = $bytes !== false ? @getimagesizefromstring($bytes) : false;
    return $image && $image['mime'] === 'image/' . $match[1] && strlen($bytes) <= 2 * 1024 * 1024;
}

function branding_logo(): string
{
    $logo = (string) setting('company_logo', '');
    return $logo !== '' && branding_valid_logo($logo)
        ? site_url('branding/logo') . '?v=' . substr(hash('sha256', $logo), 0, 12)
        : base_url('assets/img/logo-192.png');
}
