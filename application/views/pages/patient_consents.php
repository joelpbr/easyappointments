<?php extend('layouts/backend_layout'); ?>
<?php
// The controller provides these through html_vars(), as on the other settings pages.
$entries = vars('entries');
$total = vars('total');
$page = vars('page');
$pages = vars('pages');
$query = vars('query');
?>
<?php section('content'); ?>

<div class="container backend-page py-3" id="patient-consents-page">
    <div class="row">
        <div class="col-sm-3">
            <?php component('settings_nav'); ?>
        </div>
        <div class="col-sm-9">
            <div class="d-flex justify-content-between align-items-center border-bottom mb-3 py-2 gap-3">
                <h4 class="mb-0 fw-light"><?= lang('consents_admin') ?></h4>
                <span class="text-muted small"><?= e(sprintf(lang('consents_admin_count'), $total)) ?></span>
            </div>
            <p class="text-muted small"><?= lang('consents_admin_hint') ?></p>
            <form method="get" action="<?= site_url('patient_consents') ?>" class="mb-3">
                <label for="consent-search" class="form-label"><?= lang('consents_admin_search') ?></label>
                <div class="input-group">
                    <input type="search" id="consent-search" name="q" value="<?= e($query) ?>"
                           maxlength="100" class="form-control">
                    <button type="submit" class="btn btn-primary"><?= lang('search') ?></button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('consents_admin_patient') ?></th>
                            <th scope="col"><?= lang('date') ?></th>
                            <th scope="col"><?= lang('consents_admin_signer') ?></th>
                            <th scope="col"><?= lang('consents_admin_collector') ?></th>
                            <th scope="col"><?= lang('language') ?></th>
                            <th scope="col">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td>
                                    <a href="<?= site_url(
                                        'customers?customer_id=' . (int) $entry['id_users_customer'],
                                    ) ?>">
                                        <?= e(trim($entry['first_name'] . ' ' . $entry['last_name'])) ?>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($entry['signed_at']))) ?></td>
                                <td><?= e($entry['signer_name']) ?></td>
                                <td><?= e($entry['collected_by_name']) ?></td>
                                <td><?= e(strtoupper($entry['language'])) ?></td>
                                <td>
                                    <?php if ($entry['id_patient_documents']): ?>
                                        <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener"
                                           href="<?= site_url(
                                               'patient_consents/pdf?document_id=' .
                                                   (int) $entry['id_patient_documents'],
                                           ) ?>">
                                            <?= lang('consent_view_signed') ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$entries): ?>
                            <tr><td colspan="6" class="text-muted text-center py-4"><?= lang(
                                'no_records_found',
                            ) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <?php if ($page > 1): ?>
                            <a class="btn btn-outline-secondary" href="<?= e(
                                site_url('patient_consents') .
                                    '?' .
                                    http_build_query(['q' => $query, 'page' => $page - 1]),
                            ) ?>"><?= lang('previous') ?></a>
                        <?php endif; ?>
                    </div>
                    <span class="text-muted"><?= (int) $page ?> / <?= (int) $pages ?></span>
                    <div>
                        <?php if ($page < $pages): ?>
                            <a class="btn btn-outline-secondary" href="<?= e(
                                site_url('patient_consents') .
                                    '?' .
                                    http_build_query(['q' => $query, 'page' => $page + 1]),
                            ) ?>"><?= lang('next') ?></a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php end_section('content'); ?>
