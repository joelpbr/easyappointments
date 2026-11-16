<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

class DeviceHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'helpers/device_helper.php';
    }

    public function testPhonesAreDetected(): void
    {
        $this->assertTrue(
            is_phone_user_agent(
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            ),
        );
        $this->assertTrue(
            is_phone_user_agent(
                'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36',
            ),
        );
        $this->assertTrue(
            is_phone_user_agent('Mozilla/5.0 (Android 14; Mobile; rv:128.0) Gecko/128.0 Firefox/128.0'),
        );
    }

    public function testTabletsAndComputersAreNotPhones(): void
    {
        $this->assertFalse(
            is_phone_user_agent(
                'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            ),
        );
        $this->assertFalse(
            is_phone_user_agent(
                'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            ),
        );
        $this->assertFalse(
            is_phone_user_agent(
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 Edg/126.0',
            ),
        );
        $this->assertFalse(
            is_phone_user_agent(
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
            ),
        );
    }

    public function testAnEmptyUserAgentIsNotAPhone(): void
    {
        $this->assertFalse(is_phone_user_agent(null));
        $this->assertFalse(is_phone_user_agent(''));
    }
}
