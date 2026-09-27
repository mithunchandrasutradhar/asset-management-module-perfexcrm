<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$l       = $license;
$cur     = get_base_currency();
$free    = max(0, (int) $l->seats - (int) $l->seats_used);
$expired = $l->expiry_date && $l->expiry_date < date('Y-m-d');
$row     = fn ($label, $value) => '<tr><td class="tw-font-medium tw-text-neutral-500" style="width:40%">' . _l($label) . '</td><td>' . ($value === '' || $value === null ? '<span class="text-muted">-</span>' : $value) . '</td></tr>';
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
                    <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                        <?= e($l->name); ?>
                        <?php if ($expired) { ?><span class="label label-danger"><?= _l('ams_lic_expired'); ?></span><?php } ?>
                        <?php if (! $l->active) { ?><span class="label label-default"><?= _l('ams_inactive'); ?></span><?php } ?>
                    </h4>
                    <div class="tw-flex tw-gap-1">
                        <?php if (staff_can('edit', 'ams_licenses') && $l->active) { ?>
                        <a href="#" class="btn btn-success<?= $free ? '' : ' disabled'; ?>" data-toggle="modal" data-target="#ams_seat_modal"><i class="fa-solid fa-user-plus tw-mr-1"></i><?= _l('ams_lic_assign_seat'); ?></a>
                        <?php } ?>
                        <?php if (staff_can('edit', 'ams_licenses')) { ?>
                        <a href="<?= admin_url('asset_management/licenses/license/' . $l->id); ?>" class="btn btn-default"><i class="fa-regular fa-pen-to-square tw-mr-1"></i><?= _l('edit'); ?></a>
                        <?php } ?>
                        <?php if (staff_can('delete', 'ams_licenses')) { ?>
                        <a href="<?= admin_url('asset_management/licenses/delete/' . $l->id); ?>" class="btn btn-danger _delete"><i class="fa-regular fa-trash-can"></i></a>
                        <?php } ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5">
                        <div class="panel_s">
                            <div class="panel-body">
                                <table class="table table-bordered tw-mb-0">
                                    <tbody>
                                        <?= $row('ams_lic_seats_used', '<span class="tw-font-semibold">' . (int) $l->seats_used . ' / ' . (int) $l->seats . '</span> <span class="text-muted">(' . _l('ams_lic_n_free', $free) . ')</span>'); ?>
                                        <?= $row('ams_lic_type', e(_l('ams_lic_type_' . $l->license_type))); ?>
                                        <?= $row('ams_lic_manufacturer', e($l->brand_name)); ?>
                                        <?= $row('ams_lic_key', ! $l->has_key ? '' : (staff_can('view_keys', 'ams_licenses')
                                            ? '<code class="ams-lic-key">••••••••••••</code> <a href="#" onclick="ams_reveal_key(); return false;">' . _l('ams_lic_reveal') . '</a>'
                                            : '<span class="text-muted">' . _l('ams_lic_key_hidden') . '</span>')); ?>
                                        <?= $row('ams_lic_licensed_to', e($l->licensed_to)); ?>
                                        <?= $row('ams_supplier', e($l->supplier_name)); ?>
                                        <?= $row('ams_order_no', e($l->order_no)); ?>
                                        <?= $row('ams_purchase_date', e(_d($l->purchase_date))); ?>
                                        <?= $row('ams_purchase_cost', $l->purchase_cost !== null ? e(app_format_money($l->purchase_cost, $cur)) : ''); ?>
                                        <?= $row('ams_lic_expiry', $l->expiry_date ? '<span class="' . ($expired ? 'text-danger tw-font-semibold' : '') . '">' . e(_d($l->expiry_date)) . '</span>' : ''); ?>
                                        <?= $row('ams_lic_renewal_cost', $l->renewal_cost !== null ? e(app_format_money($l->renewal_cost, $cur)) : ''); ?>
                                        <?= $row('ams_lic_auto_renew', $l->auto_renew ? _l('settings_yes') : _l('settings_no')); ?>
                                        <?= $row('ams_notes', nl2br(e($l->notes))); ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg"><?= _l('ams_lic_seats'); ?></h4>
                                <?php render_datatable([_l('ams_license'), _l('ams_assigned_to'), _l('ams_lic_assigned_at'), _l('ams_note'), _l('ams_lic_released'), _l('ams_done_by')], 'ams-license-seats'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (staff_can('edit', 'ams_licenses')) { ?>
<div class="modal fade" id="ams_seat_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/licenses/assign/' . $l->id), ['id' => 'ams-seat-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_lic_assign_seat'); ?> - <?= e($l->name); ?></h4>
            </div>
            <div class="modal-body">
                <?= render_select('assign_type', [['id' => 'staff', 'name' => _l('ams_assign_type_staff')], ['id' => 'asset', 'name' => _l('ams_asset')]], ['id', 'name'], 'ams_lic_assign_to', 'staff', [], [], '', '', false); ?>
                <div class="ams-seat ams-seat-staff"><?= render_select('assign_id_staff', $staff, ['id', 'name'], 'ams_assign_type_staff'); ?></div>
                <div class="ams-seat ams-seat-asset hide"><?= render_select('assign_id_asset', $assets, ['id', 'name'], 'ams_asset'); ?></div>
                <?= render_input('note', 'ams_note'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-success"><?= _l('ams_lic_assign_seat'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/licenses/_seat_js'); ?>
<script>
    $(function() {
        initDataTable('.table-ams-license-seats', admin_url + 'asset_management/licenses/seats_table/<?= (int) $l->id; ?>', [], [], {}, [2, 'desc']).column(0).visible(false, false);
        $('#ams-seat-form select[name="assign_type"]').on('change', function() {
            $('.ams-seat').addClass('hide');
            $('.ams-seat-' + $(this).val()).removeClass('hide');
        });
        $('#ams-seat-form').on('submit', function(e) {
            e.preventDefault();
            $.post(this.action, $(this).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : alert_float('danger', r.message);
            });
        });
    });

    function ams_reveal_key() {
        $.post(admin_url + 'asset_management/licenses/reveal_key/<?= (int) $l->id; ?>').done(function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            r.success ? $('.ams-lic-key').text(r.key) : alert_float('danger', r.message);
        });
    }
</script>
</body>
</html>
