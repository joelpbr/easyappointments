<?php defined('BASEPATH') or exit('No direct script access allowed');

// Add custom values by settings them to the $config array.
// Example: $config['smtp_host'] = 'smtp.gmail.com';
// @link https://codeigniter.com/user_guide/libraries/email.html

$config['useragent'] = 'Easy!Appointments';
$config['protocol'] = 'mail'; // or 'smtp'
$config['mailtype'] = 'html'; // or 'text'
// $config['smtp_debug'] = '0'; // or '1'
// $config['smtp_auth'] = TRUE; //or FALSE for anonymous relay.
// $config['smtp_host'] = '';
// $config['smtp_user'] = '';
// $config['smtp_pass'] = '';
// $config['smtp_crypto'] = 'ssl'; // or 'tls'
// $config['smtp_port'] = 25;
// $config['from_name'] = '';
// $config['from_address'] = '';
// $config['reply_to'] = '';

// The SMTP server can also be set through environment variables, as the Docker setup does.
if (getenv('SMTP_HOST')) {
    $config['protocol'] = 'smtp';
    $config['smtp_host'] = getenv('SMTP_HOST');
    $config['smtp_port'] = (int) (getenv('SMTP_PORT') ?: 587);
    $config['smtp_auth'] = (bool) getenv('SMTP_USER');
    $config['smtp_user'] = (string) getenv('SMTP_USER');
    $config['smtp_pass'] = (string) getenv('SMTP_PASS');
    $config['smtp_crypto'] = (string) getenv('SMTP_CRYPTO');
}

$config['crlf'] = "\r\n";
$config['newline'] = "\r\n";
