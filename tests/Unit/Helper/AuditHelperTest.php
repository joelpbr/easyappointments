<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

class AuditHelperTest extends TestCase
{
    public function testItRecordsTheValuesOfTheFieldsThatChanged(): void
    {
        $changes = audit_changes(
            ['start_datetime' => '2026-11-16 09:00:00', 'status' => 'scheduled', 'id_services' => 1],
            ['start_datetime' => '2026-11-16 10:00:00', 'status' => 'scheduled', 'id_services' => '1'],
            ['start_datetime', 'status', 'id_services'],
        );

        $this->assertSame(['start_datetime' => ['2026-11-16 09:00:00', '2026-11-16 10:00:00']], $changes);
    }

    public function testItIgnoresFieldsThatAreNotListedOrNotSent(): void
    {
        $changes = audit_changes(['status' => 'scheduled', 'color' => '#000000'], ['color' => '#ffffff'], ['status']);

        $this->assertSame([], $changes);
    }

    public function testANewRecordReportsEveryListedField(): void
    {
        $changes = audit_changes([], ['status' => 'scheduled', 'is_first_visit' => 1], ['status', 'is_first_visit']);

        $this->assertSame(['status' => [null, 'scheduled'], 'is_first_visit' => [null, 1]], $changes);
    }

    public function testRedactedFieldsOnlyRecordThatTheyChanged(): void
    {
        $changes = audit_changes(['notes' => 'Dolor en el talón'], ['notes' => 'Diabetes'], ['notes'], ['notes']);

        $this->assertSame(['notes' => ['***', '***']], $changes);
        $this->assertStringNotContainsString('Diabetes', json_encode($changes));
    }

    public function testUnchangedRedactedFieldsAreNotRecorded(): void
    {
        $this->assertSame([], audit_changes(['notes' => 'Igual'], ['notes' => 'Igual'], ['notes'], ['notes']));
    }

    public function testBooleansAreComparedAsTheZeroOrOneStoredInTheDatabase(): void
    {
        $changes = audit_changes(
            ['is_private' => '0', 'is_active' => '1'],
            ['is_private' => false, 'is_active' => false],
            ['is_private', 'is_active'],
        );

        $this->assertSame(['is_active' => ['1', 0]], $changes);
    }

    public function testAWholeRecordIsComparedExceptItsTechnicalFields(): void
    {
        $changes = audit_diff(
            ['id' => 3, 'name' => 'Nadal', 'date' => '2026-12-25', 'update_datetime' => '2026-01-01 00:00:00'],
            ['id' => 3, 'name' => 'Nadal', 'date' => '2026-12-26', 'update_datetime' => '2026-11-16 10:00:00'],
        );

        $this->assertSame(['date' => ['2026-12-25', '2026-12-26']], $changes);
    }

    public function testANewRecordReportsAllItsFieldsAndADeletedOneWhatItWas(): void
    {
        $this->assertSame(
            ['name' => [null, 'Nadal'], 'date' => [null, '2026-12-25']],
            audit_diff([], ['name' => 'Nadal', 'date' => '2026-12-25', 'create_datetime' => '2026-11-16 10:00:00']),
        );

        $this->assertSame(
            ['name' => ['Nadal', null], 'date' => ['2026-12-25', null]],
            audit_diff(['id' => 3, 'name' => 'Nadal', 'date' => '2026-12-25'], []),
        );
    }

    public function testSecretsNeverReachTheLog(): void
    {
        $changes = audit_diff(
            ['password' => 'old-hash', 'salt' => 'a', 'password_fingerprint' => 'x', 'email' => 'a@b.c'],
            ['password' => 'new-hash', 'salt' => 'b', 'password_fingerprint' => 'y', 'email' => 'a@b.c'],
            [],
            ['password_fingerprint'],
        );

        $this->assertSame(['password_fingerprint' => ['***', '***']], $changes);
        $this->assertStringNotContainsString('hash', json_encode($changes));
    }

    public function testListsAreComparedAsJson(): void
    {
        $this->assertSame([], audit_diff(['providers' => [4, 5]], ['providers' => [4, 5]]));
        $this->assertSame(['providers' => ['[4,5]', '[5]']], audit_diff(['providers' => [4, 5]], ['providers' => [5]]));
    }
}
