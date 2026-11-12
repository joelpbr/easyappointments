<?php

namespace Tests\Unit\Library;

use Slot_rules;
use Tests\TestCase;

class SlotRulesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'libraries/Slot_rules.php';
    }

    private function appointment(array $overrides = []): array
    {
        return array_merge(
            [
                'id' => 7,
                'id_users_provider' => 4,
                'id_services' => 1,
                'start_datetime' => '2026-11-16 09:00:00',
                'end_datetime' => '2026-11-16 09:30:00',
                'status' => 'scheduled',
                'notes' => 'Primera revisión',
            ],
            $overrides,
        );
    }

    public function testReceptionCanConfirmOverlapsBlocksAndAfterHours(): void
    {
        $this->assertTrue(
            Slot_rules::is_forceable([
                Slot_rules::OVERLAP_APPOINTMENT,
                Slot_rules::OVERLAP_UNAVAILABILITY,
                Slot_rules::BLOCKED_PERIOD,
                Slot_rules::OUTSIDE_WORKING_PLAN,
            ]),
        );
    }

    public function testSomeRulesCanNeverBeConfirmed(): void
    {
        $this->assertFalse(Slot_rules::is_forceable([Slot_rules::OVERLAP_APPOINTMENT, Slot_rules::PROVIDER_INACTIVE]));
        $this->assertFalse(Slot_rules::is_forceable([Slot_rules::SERVICE_NOT_OFFERED]));
        $this->assertFalse(Slot_rules::is_forceable([Slot_rules::INVALID_PERIOD]));
    }

    public function testTheMessageExplainsEveryViolation(): void
    {
        $message = Slot_rules::message([Slot_rules::OVERLAP_APPOINTMENT, Slot_rules::OUTSIDE_WORKING_PLAN]);

        $this->assertSame('slot_rule_overlap_appointment slot_rule_outside_working_plan', $message);
    }

    public function testANewAppointmentIsAlwaysChecked(): void
    {
        $this->assertTrue(Slot_rules::slot_changed([], $this->appointment()));
    }

    public function testEditingOnlyTheNotesKeepsTheSlot(): void
    {
        $this->assertFalse(
            Slot_rules::slot_changed(
                $this->appointment(),
                $this->appointment(['notes' => 'Otra nota', 'status' => 'confirmed']),
            ),
        );
    }

    public function testMovingTheAppointmentChangesTheSlot(): void
    {
        $before = $this->appointment();

        $this->assertTrue(
            Slot_rules::slot_changed($before, $this->appointment(['start_datetime' => '2026-11-16 10:00:00'])),
        );
        $this->assertTrue(
            Slot_rules::slot_changed($before, $this->appointment(['end_datetime' => '2026-11-16 09:45:00'])),
        );
        $this->assertTrue(Slot_rules::slot_changed($before, $this->appointment(['id_users_provider' => 5])));
        $this->assertTrue(Slot_rules::slot_changed($before, $this->appointment(['id_services' => '3'])));
    }

    public function testRestoringACancelledAppointmentTakesTheSlotAgain(): void
    {
        $before = $this->appointment(['status' => 'cancelled']);

        $this->assertTrue(Slot_rules::slot_changed($before, $this->appointment(['status' => 'scheduled'])));
        $this->assertFalse(Slot_rules::slot_changed($before, $this->appointment(['status' => 'cancelled'])));
    }
}
