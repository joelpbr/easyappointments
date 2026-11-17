<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.4.0
 * ---------------------------------------------------------------------------- */

use GuzzleHttp\Client;

/**
 * Webhooks client library.
 *
 * Handles the webhook HTTP related functionality.
 *
 * @package Libraries
 */
class Webhooks_client
{
    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Webhook client constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('providers_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('secretaries_model');
        $this->CI->load->model('admins_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('settings_model');
        $this->CI->load->model('webhooks_model');
    }

    /**
     * Trigger the registered webhooks for the provided action.
     *
     * @param string $action Webhook action.
     * @param array $payload Payload data.
     *
     * @return void|null
     */
    public function trigger(string $action, array $payload)
    {
        $webhooks = $this->CI->webhooks_model->get(['is_active' => 1]);

        foreach ($webhooks as $webhook) {
            $actions = array_filter(array_map('trim', explode(',', (string) $webhook['actions'])));

            if (in_array($action, $actions, true)) {
                $this->call($webhook, $action, $payload);
            }
        }
    }

    /**
     * Call the provided webhook.
     *
     * @param array $webhook
     * @param string $action
     * @param array $payload
     */
    private function call(array $webhook, string $action, array $payload): void
    {
        try {
            $options = self::transport_options($webhook['url']);
            $client = new Client($options);

            $headers = [];

            if (!empty($webhook['secret_header']) && !empty($webhook['secret_token'])) {
                $headers[$webhook['secret_header']] = $webhook['secret_token'];
            }

            $response = $client->post($webhook['url'], [
                'headers' => $headers,
                'json' => [
                    'action' => $action,
                    'payload' => $payload,
                ],
            ]);

            // echo $response->getBody()->getContents(); // Use this for quick debugging
        } catch (Throwable $e) {
            log_message(
                'error',
                'Webhooks Client - The webhook (' .
                    ($webhook['id'] ?? null) .
                    ') request received an unexpected exception: ' .
                    $e->getMessage(),
            );
            log_message('error', $e->getTraceAsString());
        }
    }

    /** Resolve once and force cURL to connect to a checked public address. */
    public static function transport_options(string $url): array
    {
        validate_url($url, ['https']);
        $uri = new \GuzzleHttp\Psr7\Uri($url);
        $host = $uri->getHost();
        if ($uri->getUserInfo() !== '' || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || !preg_match('/\.[a-z]{2,63}$/iD', $host)) {
            throw new InvalidArgumentException('Webhook requires a public HTTPS hostname.');
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $addresses = array_merge(array_column($records, 'ip'), array_column($records, 'ipv6'));
        if (!$addresses) {
            throw new InvalidArgumentException('Webhook hostname has no address.');
        }
        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
                || (str_contains($address, ':') && (!preg_match('/^[23]/i', $address)
                    || str_starts_with(strtolower($address), '2002:') || str_starts_with(inet_pton($address), "\x20\x01\x00\x00")))) {
                throw new InvalidArgumentException('Webhook requires a public destination.');
            }
        }
        $port = $uri->getPort() ?? 443;
        $ip = str_contains($addresses[0], ':') ? '[' . $addresses[0] . ']' : $addresses[0];
        return [
            'handler' => new \GuzzleHttp\Handler\CurlHandler(),
            'verify' => true,
            'allow_redirects' => false,
            'proxy' => '',
            'connect_timeout' => 5,
            'timeout' => 10,
            'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"], CURLOPT_PROXY => ''],
        ];
    }
}
