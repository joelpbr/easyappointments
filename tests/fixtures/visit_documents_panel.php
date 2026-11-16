<?php
/** Render the real panel with fictional data for a browser check, without a database or authenticated session. */
define('BASEPATH', true);
define('FISCAL_ID_TYPES', ['dni', 'nie', 'passport', 'other']);
require __DIR__ . '/../../application/language/catalan/translations_lang.php';
require __DIR__ . '/../../application/libraries/Clinical_record_templates.php';
function lang($key)
{
    return $GLOBALS['lang'][$key] ?? $key;
}
function e($text)
{
    return htmlspecialchars($text, ENT_QUOTES);
}
function asset_url($path)
{
    return $path;
}
$standalone = true;
require __DIR__ . '/../../application/views/components/clinical_record_modal.php';
