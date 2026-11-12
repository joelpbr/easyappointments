<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Distinct treatment backgrounds with strong contrast for black text.
 * The most common treatments receive the most distinct hues first.
 */
class Migration_Improve_treatment_color_contrast extends EA_Migration
{
    private const COLORS = [
        'Quiropodia' => '#7db9f0',
        'Exploración' => '#8ed6a0',
        'Entrega SP' => '#f3bb72',
        'Cura' => '#f0a6c4',
        'Cura PV' => '#7dd8d2',
        'Wallet' => '#f3df7d',
        'Infiltración' => '#b7a1e8',
        'Silicona' => '#c8d993',
        'Ortonixia' => '#e9c8a3',
        'Moldes' => '#a5bfd0',
        'Cirugía' => '#f29a8f',
        'Otro' => '#d0d0d0',
    ];

    public function up(): void
    {
        $this->db->trans_start();

        foreach (self::COLORS as $name => $color) {
            $services = $this->db->get_where('services', ['name' => $name])->result_array();

            foreach ($services as $service) {
                // Keep appointments using the previous treatment color in sync.
                // Individually chosen appointment colors remain unchanged.
                $this->db->update(
                    'appointments',
                    ['color' => $color],
                    [
                        'id_services' => $service['id'],
                        'is_unavailability' => 0,
                        'color' => $service['color'],
                    ],
                );
                $this->db->update('services', ['color' => $color], ['id' => $service['id']]);
            }
        }

        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            throw new RuntimeException('Could not update the treatment colors.');
        }
    }

    public function down(): void
    {
        // Preserve the current palette and any individually chosen colors.
    }
}
