<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$currencies = [];
foreach (ams_currency_options() as $cu) {
    $currencies[$cu['id']] = $cu['name'];
}
$fmt    = fn ($v, $cur) => e(app_format_money((float) $v, $currencies[$cur] ?? get_base_currency()));
$months = $forecast['months'];
$colors = ['#3b82f6', '#f59e0b', '#22c55e', '#ef4444', '#8b5cf6'];
$chart  = ['labels' => array_map(fn ($m) => date('M Y', strtotime($m . '-01')), $months), 'datasets' => []];
$i      = 0;
foreach ($forecast['by_month'] as $cur => $values) {
    $chart['datasets'][] = [
        'label'           => $currencies[$cur] ?? '',
        'backgroundColor' => $colors[$i++ % count($colors)],
        'data'            => array_map(fn ($m) => round($values[$m] ?? 0, 2), $months),
    ];
}
?>
<div id="wrapper">
    <div class="content">
        <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
            <h4 class="tw-my-0 tw-font-bold tw-text-xl"><a href="<?= admin_url('asset_management/reports'); ?>" class="tw-text-neutral-500"><?= _l('ams_reports'); ?></a> › <?= _l('ams_report_depreciation'); ?></h4>
            <form method="get" class="tw-flex tw-gap-2 tw-items-center">
                <label class="tw-mb-0"><?= _l('ams_report_next_months'); ?></label>
                <select name="months" class="form-control" style="width:auto" onchange="this.form.submit()">
                    <?php foreach ([6, 12, 24, 36] as $m) { ?>
                    <option value="<?= $m; ?>" <?= $months && count($months) === $m ? 'selected' : ''; ?>><?= $m; ?></option>
                    <?php } ?>
                </select>
            </form>
        </div>

        <?php if (! $forecast['by_month']) { ?>
        <div class="alert alert-info"><?= _l('ams_report_depreciation_empty'); ?></div>
        <?php } else { ?>
        <div class="panel_s">
            <div class="panel-body">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_report_dep_by_month'); ?></h4>
                <div style="height:280px"><canvas id="ams-dep-chart"></canvas></div>
                <div class="table-responsive tw-mt-3">
                    <table class="table table-condensed tw-mb-0">
                        <thead><tr><th></th><?php foreach ($months as $m) { ?><th class="text-right"><?= e(date('M y', strtotime($m . '-01'))); ?></th><?php } ?><th class="text-right"><?= _l('ams_po_total'); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($forecast['by_month'] as $cur => $values) { ?>
                            <tr>
                                <td class="tw-font-medium"><?= e($currencies[$cur] ?? ''); ?></td>
                                <?php foreach ($months as $m) { ?><td class="text-right"><?= $fmt($values[$m] ?? 0, $cur); ?></td><?php } ?>
                                <td class="text-right tw-font-semibold"><?= $fmt(array_sum($values), $cur); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel_s">
            <div class="panel-body">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-base"><?= _l('ams_report_dep_by_category'); ?></h4>
                <table class="table table-striped tw-mb-0">
                    <thead><tr><th><?= _l('ams_category'); ?></th><th class="text-right"><?= _l('ams_assets'); ?></th><th class="text-right"><?= _l('ams_depreciation'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($forecast['by_category'] as $cur => $cats) {
                            foreach ($cats as $name => $c) { ?>
                        <tr>
                            <td><?= e($name); ?></td>
                            <td class="text-right"><?= (int) ($c['assets'] ?? 0); ?></td>
                            <td class="text-right"><?= $fmt($c['amount'], $cur); ?></td>
                        </tr>
                        <?php }
                            } ?>
                    </tbody>
                </table>
                <p class="text-muted tw-text-sm tw-mt-3 tw-mb-0"><?= _l('ams_report_depreciation_help'); ?></p>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
<?php init_tail(); ?>
<?php if ($forecast['by_month']) { ?>
<script>
    $(function() {
        new Chart($('#ams-dep-chart'), {
            type: 'bar',
            data: <?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_APOS); ?>,
            options: { responsive: true, maintainAspectRatio: false, scales: { xAxes: [{ stacked: false }], yAxes: [{ ticks: { beginAtZero: true } }] } }
        });
    });
</script>
<?php } ?>
</body>
</html>
