<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $o = $order; ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
                    <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                        <?= _l('ams_hb_order'); ?> #<?= e($o['order_number'] ?: $o['hb_order_id']); ?>
                        <?= ams_hb_status_badge($o['status']); ?> <?= ams_hb_status_badge($o['invoice_status']); ?>
                    </h4>
                    <div class="tw-flex tw-gap-1">
                        <a href="<?= admin_url('asset_management/hostbill/orders'); ?>" class="btn btn-default"><i class="fa-solid fa-arrow-left tw-mr-1"></i><?= _l('ams_hb_orders'); ?></a>
                        <?php if (staff_can('sync', 'ams_hostbill')) { ?>
                        <button type="button" class="btn btn-default ams-hb-post" data-url="<?= admin_url('asset_management/hostbill/resync/' . $o['id']); ?>">
                            <i class="fa-solid fa-rotate tw-mr-1"></i><?= _l('ams_hb_resync_order'); ?>
                        </button>
                        <?php } ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_hb_order_lines'); ?></h4>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th><?= _l('ams_hb_product'); ?></th>
                                            <th class="text-right"><?= _l('ams_hb_hb_qty'); ?></th>
                                            <th><?= _l('ams_item'); ?></th>
                                            <th class="text-right"><?= _l('ams_hb_units'); ?></th>
                                            <th><?= _l('ams_location'); ?></th>
                                            <th><?= _l('ams_hb_stock_state'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lines as $l) { ?>
                                        <tr>
                                            <td>#<?= (int) $l['hb_product_id']; ?> <?= e($l['product_name']); ?></td>
                                            <td class="text-right"><?= ams_qty($l['hb_qty']); ?></td>
                                            <td><?= $l['item_id'] ? '<a href="' . admin_url('asset_management/inventory/view/' . $l['item_id']) . '">' . e($l['sku'] . ' - ' . $l['item_name']) . '</a>' : '<span class="text-muted">-</span>'; ?></td>
                                            <td class="text-right"><?= $l['units'] !== null ? ams_qty($l['units']) . ' ' . e($l['unit']) : ''; ?></td>
                                            <td><?= e($l['location_name']); ?></td>
                                            <td>
                                                <?= ams_hb_line_state_badge($l['state']); ?>
                                                <?php if ($l['message']) { ?><div class="tw-text-xs text-danger tw-mt-1"><?= e($l['message']); ?></div><?php } ?>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                        <?php if (! $lines) { ?>
                                        <tr><td colspan="6" class="text-muted"><?= _l('ams_hb_no_lines'); ?></td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                <p class="text-muted tw-text-sm tw-mb-0"><?= _l('ams_hb_state_help'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_hb_client'); ?></h4>
                                <p class="tw-mb-1 tw-font-medium">
                                    <?= $o['perfex_client_id'] ? '<a href="' . admin_url('clients/client/' . $o['perfex_client_id']) . '">' . e($o['client_name']) . '</a>' : e($o['client_name']); ?>
                                </p>
                                <p class="text-muted tw-mb-3"><?= e($o['client_email']); ?></p>
                                <p class="tw-mb-1"><?= _l('ams_date'); ?>: <?= e(_dt($o['order_date'])); ?></p>
                                <p class="tw-mb-1"><?= _l('ams_hb_total'); ?>: <?= $o['total'] !== null ? e(number_format((float) $o['total'], 2)) : '-'; ?></p>
                                <p class="tw-mb-0 text-muted"><?= _l('ams_hb_synced'); ?>: <?= e(_dt($o['last_synced_at'])); ?></p>
                            </div>
                        </div>

                        <?php if ($o['has_mapped']) { ?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_hb_fulfilment'); ?></h4>
                                <p><?= ams_hb_fulfilment_badge($o['fulfilment']); ?>
                                    <?php if ($o['fulfilled_by']) { ?><span class="text-muted tw-text-sm"><?= e(get_staff_full_name($o['fulfilled_by'])); ?>, <?= e(_dt($o['fulfilled_at'])); ?></span><?php } ?>
                                </p>
                                <?php if (staff_can('edit', 'ams_hostbill')) { ?>
                                <?= form_open(admin_url('asset_management/hostbill/fulfilment/' . $o['id']), ['id' => 'ams-hb-fulfil']); ?>
                                <?= render_select('fulfilment', [
                                    ['id' => 'pending', 'name' => _l('ams_hb_fulfilment_pending')],
                                    ['id' => 'picked', 'name' => _l('ams_hb_fulfilment_picked')],
                                    ['id' => 'delivered', 'name' => _l('ams_hb_fulfilment_delivered')],
                                ], ['id', 'name'], 'ams_hb_fulfilment', $o['fulfilment'], [], [], '', '', false); ?>
                                <?= render_textarea('fulfilment_note', 'ams_note', $o['fulfilment_note']); ?>
                                <button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button>
                                <?= form_close(); ?>
                                <?php } elseif ($o['fulfilment_note']) { ?>
                                <p><?= nl2br(e($o['fulfilment_note'])); ?></p>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/hostbill/_post_js'); ?>
<script>
    $(function() {
        $('#ams-hb-fulfil').on('submit', function(e) {
            e.preventDefault();
            $.post(this.action, $(this).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : alert_float('danger', r.message);
            });
        });
    });
</script>
</body>
</html>
