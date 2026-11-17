<?php

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

final class WebhookSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPPATH . 'helpers/security_helper.php';
        require_once APPPATH . 'libraries/Webhooks_client.php';
    }

    public function testPrivateAndInsecureDestinationsAreRejectedBeforeSending(): void
    {
        foreach (['http://example.org', 'https://localhost', 'https://127.0.0.1', 'https://[::1]', 'https://user@example.org'] as $url) {
            try {
                \Webhooks_client::transport_options($url);
                self::fail('Destination should have been rejected');
            } catch (\InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testIntegrationCredentialsAreOmittedFromSettings(): void
    {
        $names = ['company_name', 'whatsapp_access_token', 'whatsapp_app_secret', 'whatsapp_webhook_verify_token',
            'altcha_hmac_key', 'ldap_password'];
        $settings = array_map(static fn($name) => ['name' => $name, 'value' => 'example'], $names);
        self::assertSame([$settings[0]], filter_sensitive_settings($settings));
    }
}
