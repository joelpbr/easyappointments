<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

class ProviderHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'helpers/provider_helper.php';
    }

    public function testShortNameIsTrimmedAndFullNameIsPreserved(): void
    {
        $provider = ['first_name' => 'Maria', 'last_name' => 'Garcia Lopez', 'short_name' => '  Mari  '];
        $this->assertSame('Mari', provider_display_name($provider));
        $this->assertSame('Maria', $provider['first_name']);
        $this->assertSame('Garcia Lopez', $provider['last_name']);
    }

    public function testMissingAndBlankShortNamesFallBackToFullName(): void
    {
        foreach ([null, '', '   '] as $short_name) {
            $this->assertSame(
                'Maria Garcia',
                provider_display_name([
                    'first_name' => 'Maria',
                    'last_name' => 'Garcia',
                    'short_name' => $short_name,
                ]),
            );
        }
        $this->assertSame('Maria', provider_display_name(['first_name' => 'Maria', 'last_name' => null]));
    }
}
