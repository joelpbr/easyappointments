<?php
$report = vars('report'); $issued = vars('issued');
$duration = static fn($seconds) => sprintf('%d:%02d:%02d', intdiv((int)$seconds, 3600), intdiv((int)$seconds % 3600, 60), (int)$seconds % 60);
$local = static fn($time) => $time ? (new DateTimeImmutable($time, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($report['timezone'] ?? 'UTC'))->format('d/m/Y H:i:s P') : '—';
?>
<!doctype html><html lang="<?= e(config('language_code')) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(lang('att_title').' · '.$report['month']) ?></title>
<style>body{font:14px system-ui,sans-serif;color:#222;max-width:1100px;margin:30px auto;padding:0 20px}table{border-collapse:collapse;width:100%;margin:20px 0}td,th{border:1px solid #ccc;padding:8px;text-align:left}th{background:#eee}h1{font-size:24px}small{color:#555}tr{break-inside:avoid}@media print{button{display:none}body{margin:0}thead{display:table-header-group}}</style></head><body>
<h1><?= lang('att_title') ?> · <?= e($report['month']) ?></h1>
<p><strong><?= e($report['company']['name'] ?? branding_name()) ?></strong><br><?= e($report['company']['address'] ?? setting('company_address')) ?></p>
<p><?= lang('att_employee') ?>: <strong><?= e($report['employee']['employee_name']) ?></strong> · ID <?= (int)$report['employee']['employee_id'] ?><br>
<?= lang('att_timezone') ?>: <?= e($report['timezone'] ?? 'UTC') ?> · <?= lang('att_generated') ?>: <?= e($local($report['generated_at'])) ?></p>
<p><strong><?= $issued ? lang('att_issued').' #'.(int)$issued['id'] : lang('att_draft') ?></strong> · <?= lang('att_provisional') ?>: <?= $report['summary']['provisional'] ? lang('yes') : lang('no') ?></p>
<p><?= lang('att_counted') ?>: <strong><?= $duration($report['summary']['counted_seconds']) ?></strong> · <?= lang('att_unknown') ?>: <?= $duration($report['summary']['totals']['unknown']) ?></p>
<?php if (isset($report['classification'])): ?><p><?php foreach ($report['classification'] as $key => $value): ?><?= e(lang('att_'.$key)) ?>: <strong><?= $duration($value) ?></strong> · <?php endforeach; ?></p><?php endif; ?>
<?php
$employee = $report['employee'];
$rules = $report['rules'] ?? [];
$days_names = [1 => 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
$schedule = [];
foreach ($employee['schedule'] ?? [] as $day => $intervals) {
    if ($intervals) {
        $schedule[] = lang($days_names[$day]) . ' ' . implode(', ', array_map(fn($interval) => $interval[0] . '–' . $interval[1], $intervals));
    }
}
$weekly = $employee['weekly_minutes'] ?? null;
?>
<p><?= lang('att_category') ?>: <?= e(($employee['category'] ?? '') ?: '—') ?> · <?= lang('att_contract') ?>: <?= e(lang('att_'.$employee['contract_type'])) ?><?php if (!empty($employee['contract_duration']) && $employee['contract_duration'] !== 'unknown'): ?>, <?= e(lang('att_'.$employee['contract_duration'])) ?><?php endif; ?> · <?= lang('att_weekly') ?>: <?= $weekly === null ? '—' : e(sprintf('%d:%02d', intdiv((int) $weekly, 60), (int) $weekly % 60)) ?>
<?php if (!empty($employee['complementary_percent'])): ?> · <?= lang('att_complementary_pact') ?>: <?= (int) $employee['complementary_percent'] ?> %<?php endif; ?>
<br><?= lang('att_schedule') ?>: <?= e($schedule ? implode(' · ', $schedule) : '—') ?>
<br><?= lang('att_agreement') ?>: <?= e(($rules['agreement'] ?? '') ?: ($employee['agreement'] ?? '') ?: '—') ?></p>
<h2><?= lang('att_daily') ?></h2><table><thead><tr><th><?= lang('date') ?></th><th><?= lang('att_planned') ?></th><?php foreach (['work','paid','unpaid','unknown'] as $kind): ?><th><?= lang('att_'.$kind) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($report['summary']['days'] as $day): ?><tr><td><?= e($day['date']) ?></td><td><?= isset($day['planned']) ? $duration($day['planned']) : '—' ?></td><?php foreach (['work','paid','unpaid','unknown'] as $kind): ?><td><?= $duration($day[$kind]) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody><tfoot><tr><th><?= lang('att_total') ?></th><th><?= isset($report['summary']['planned_seconds']) ? $duration($report['summary']['planned_seconds']) : '—' ?></th><?php foreach (['work','paid','unpaid','unknown'] as $kind): ?><th><?= $duration($report['summary']['totals'][$kind]) ?></th><?php endforeach; ?></tr></tfoot></table>
<h2><?= lang('att_entries') ?></h2><table><thead><tr><th>ID / <?= lang('att_revision') ?></th><th><?= lang('att_start') ?></th><th><?= lang('att_end') ?></th><th><?= lang('att_kind') ?></th><th><?= lang('att_policy') ?></th><th><?= lang('att_status') ?></th></tr></thead><tbody>
<?php foreach ($report['entries'] as $entry): foreach ($entry['segments'] as $segment): ?><tr><td><?= (int)$entry['id'] ?> / <?= (int)$entry['revision'] ?></td><td><?= e($local($segment['start'])) ?></td><td><?= e($local($segment['end'])) ?></td><td><?= e(lang('att_'.$segment['kind'])) ?></td><td><?= e(lang('att_'.$segment['policy'])) ?></td><td><?= lang($entry['voided'] ? 'att_voided' : ($entry['end_at'] ? 'att_closed' : 'att_open')) ?></td></tr><?php endforeach; endforeach; ?>
</tbody></table><p><small><?= lang('att_report_note') ?></small></p>
<?php if ($issued): ?><p><?= lang('att_delivery') ?>: <?= e($local($issued['delivered_at'])) ?> · <?= e($issued['delivery_note'] ?? '') ?></p><?php endif; ?>
<p><small><?= sprintf(lang('att_legal'), (int) ($rules['retention_years'] ?? 4)) ?></small></p>
<button type="button" id="att-print-report"><?= lang('att_print') ?></button>
<script src="<?= asset_url('assets/js/pages/attendance_print.js') ?>"></script>
</body></html>
