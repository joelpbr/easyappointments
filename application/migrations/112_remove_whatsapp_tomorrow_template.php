<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * The WhatsApp template no longer says "demà" in its text ({{2}} says "avui", "demà" or the date), so the switch for
 * the former template goes away. The reminders it left for review ("the appointment is not tomorrow") go back to the
 * queue when their appointment is still to come: the next run sends them, or leaves them for review if too little
 * notice is left.
 */
class Migration_Remove_whatsapp_tomorrow_template extends EA_Migration
{
    /**
     * Error the switch left on those reminders, as it was saved in each language.
     */
    private const ERRORS = [
        'La cita no és demà i el missatge diu «demà»: només es pot enviar el dia abans.',
        'La cita no es mañana y el mensaje dice «demà» (mañana): solo se puede enviar el día antes.',
    ];

    public function up(): void
    {
        $this->db->delete('settings', ['name' => 'whatsapp_template_says_tomorrow']);

        if (!$this->db->table_exists('whatsapp_reminders')) {
            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
        $ids = array_column(
            $this->db
                ->select('whatsapp_reminders.id')
                ->from('whatsapp_reminders')
                ->join('appointments', 'appointments.id = whatsapp_reminders.id_appointments', 'inner')
                ->where('whatsapp_reminders.status', 'review')
                ->where('whatsapp_reminders.is_deleted', 0)
                ->where_in('whatsapp_reminders.error', self::ERRORS)
                ->where('appointments.start_datetime >', $now->format('Y-m-d H:i:s'))
                ->get()
                ->result_array(),
            'id',
        );

        if ($ids) {
            $this->db
                ->set(['status' => 'pending', 'error' => null, 'update_datetime' => date('Y-m-d H:i:s')])
                ->where_in('id', $ids)
                ->update('whatsapp_reminders');
        }
    }

    public function down(): void
    {
        if (!$this->db->get_where('settings', ['name' => 'whatsapp_template_says_tomorrow'])->num_rows()) {
            $this->db->insert('settings', ['name' => 'whatsapp_template_says_tomorrow', 'value' => '0']);
        }
    }
}
