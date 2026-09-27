<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$base   = get_base_currency();
$total  = array_sum(array_column($summary, 'cost'));
$jobs   = array_sum(array_column($summary, 'jobs'));
$label  = fn ($s) => $group === 'type' ? _l('ams_mt_type_' . $s['label']) : ($s['label'] ?? _l('ams_none'));
$chart  = [
    'labels'   => array_map(fn ($m) => date('M Y', strtotime($m['ym'] . '-01')), $monthly),
    'datasets' => [[
        'label'           => _l('ams_mt_cost'),
        'backgroundColor' => 'rgba(59,130,246,0.6)',
        'borderColor'     => '#3b82f6',
        'data'            => array_map(fn ($m) => round($m['cost'], 2), $monthly),
    ]],
];
?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl"><a href="<?= admin_url('asset_management/reports'); ?>" class="tw-text-neutral-500"><?= _l('ams_reports'); ?></a> › <?= _l('ams_report_maintenance_cost'); ?></h4>
            <form method="get" class="tw-flex tw-flex-wrap tw-gap-2 tw-items-end">
                <div><?= render_date_input('from', 'ams_date_from', _d($from)); ?></div>
                <div><?= render_date_input('to', 'ams_date_to', _d($to)); ?></div>
                <div class="form-group">
                    <label class="control-label"><?= _l('ams_group_by'); ?></label>
                    <select name="group" class="form-control">
                        <?php foreach (['category' => 'ams_category', 'supplier' => 'ams_supplier', 'type' => 'ams_mt_type', 'asset' => 'ams_asset'] as $g => $l) { ?>
                        <option value="<?= $g; ?>" <?= $group === $g ? 'selected' : ''; ?>><?= _l($l); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group"><button type="submit" class="btn btn-primary"><?= _l('ams_apply'); ?></button></div>
            </form>
        </div>

        <div class="row">
            <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body">
                <p class="tw-text-neutral-500 tw-mb-0 tw-text-sm"><?= _l('ams_mt_total_cost'); ?></p>
                <p class="tw-font-semibold tw-text-2xl tw-mb-0"><?= e(app_format_money($total, $base)); ?></p>
            </div></div></div>
            <div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body">
                <p class="tw-text-neutral-500 tw-mb-0 tw-text-sm"><?= _l('ams_report_jobs_completed'); ?></p>
                <p class="tw-font-semibold tw-text-2xl tw-mb-0"><?= (int) $jobs; ?></p>
            </div></div></div>
        </div>

        <div class="panel_s">
            <div class="panel-body">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_report_cost_by_month'); ?></h4>
                <div style="height:280px"><canvas id="ams-mt-cost-chart"></canvas></div>
            </div>
        </div>

        <div class="panel_s">
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-striped tw-mb-0">
                        <thead><tr>
                            <th><?= _l(['category' => 'ams_category', 'supplier' => 'ams_supplier', 'type' => 'ams_mt_type', 'asset' => 'ams_asset'][$group]); ?></th>
                            <th class="text-right"><?= _l('ams_report_jobs'); ?></th>
                            <th class="text-right"><?= _l('ams_mt_downtime'); ?></th>
                            <th class="text-right"><?= _l('ams_mt_cost'); ?></th>
                            <th class="text-right">%</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($summary as $s) { ?>
                            <tr>
                                <td><?= e($label($s)); ?></td>
                                <td class="text-right"><?= (int) $s['jobs']; ?></td>
                                <td class="text-right"><?= e(ams_qty($s['downtime'])); ?></td>
                                <td class="text-right tw-font-medium"><?= e(app_format_money($s['cost'], $base)); ?></td>
                                <td class="text-right text-muted"><?= $total > 0 ? round($s['cost'] * 100 / $total, 1) : 0; ?></td>
                            </tr>
                            <?php } ?>
                            <?php if (! $summary) { ?>
                            <tr><td colspan="5" class="text-muted"><?= _l('ams_report_no_data'); ?></td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted tw-text-sm tw-mt-3 tw-mb-0"><?= _l('ams_report_mt_cost_help'); ?> <a href="<?= admin_url('asset_management/maintenance'); ?>"><?= _l('ams_maintenance'); ?> →</a></p>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        new Chart($('#ams-mt-cost-chart'), {
            type: 'bar',
            data: <?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_APOS); ?>,
            options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: { yAxes: [{ ticks: { beginAtZero: true } }] } }
        });
    });
</script>
</body>
</html>
