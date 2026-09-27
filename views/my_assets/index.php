<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$canAssets   = staff_can('view', 'ams_assets') || staff_can('view_own', 'ams_assets');
$canAcc      = staff_can('view', 'ams_accessories') || staff_can('view_own', 'ams_accessories');
$canRequests = staff_can('create', 'ams_requests') || staff_can('view_own', 'ams_requests') || staff_can('view', 'ams_requests');
$canCreate   = staff_can('create', 'ams_requests');
$canLic      = staff_can('view', 'ams_licenses') || staff_can('view_own', 'ams_licenses');
$firstTab    = $holdings['acceptances'] ? 'acceptances' : ($canAssets ? 'assets' : ($canAcc ? 'accessories' : 'requests'));
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
                    <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= _l('ams_my_assets'); ?></h4>
                    <?php if ($canCreate) { ?>
                    <div class="tw-flex tw-gap-1">
                        <a href="#" class="btn btn-primary" onclick="ams_new_request('asset'); return false;"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_req_new'); ?></a>
                        <?php if ($my_assets) { ?>
                        <a href="#" class="btn btn-default" onclick="ams_new_request('issue'); return false;"><i class="fa-solid fa-screwdriver-wrench tw-mr-1"></i><?= _l('ams_req_report_issue'); ?></a>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>

                <?php if ($holdings['acceptances']) { ?>
                <div class="alert alert-warning"><i class="fa-solid fa-file-signature tw-mr-1"></i><?= _l('ams_acceptances_waiting', $holdings['acceptances']); ?></div>
                <?php } ?>

                <div class="panel_s">
                    <div class="panel-body">
                        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                            <?php if ($canAssets) { ?>
                            <li class="<?= $firstTab === 'assets' ? 'active' : ''; ?>"><a href="#tab_assets" data-toggle="tab"><?= _l('ams_assets'); ?> <span class="badge"><?= (int) $holdings['assets']; ?></span></a></li>
                            <?php } ?>
                            <?php if ($canAcc) { ?>
                            <li class="<?= $firstTab === 'accessories' ? 'active' : ''; ?>"><a href="#tab_accessories" data-toggle="tab"><?= _l('ams_accessories'); ?> <span class="badge"><?= ams_qty($holdings['accessories']); ?></span></a></li>
                            <?php } ?>
                            <?php if ($canLic) { ?>
                            <li><a href="#tab_licenses" data-toggle="tab"><?= _l('ams_licenses'); ?></a></li>
                            <?php } ?>
                            <li class="<?= $firstTab === 'acceptances' ? 'active' : ''; ?>"><a href="#tab_acceptances" data-toggle="tab"><?= _l('ams_acceptances'); ?> <?php if ($holdings['acceptances']) { ?><span class="badge bg-warning"><?= (int) $holdings['acceptances']; ?></span><?php } ?></a></li>
                            <?php if ($canRequests) { ?>
                            <li class="<?= $firstTab === 'requests' ? 'active' : ''; ?>"><a href="#tab_requests" data-toggle="tab"><?= _l('ams_my_requests'); ?></a></li>
                            <?php } ?>
                        </ul>
                        <div class="tab-content tw-mt-4">
                            <?php if ($canAssets) { ?>
                            <div class="tab-pane <?= $firstTab === 'assets' ? 'active' : ''; ?>" id="tab_assets">
                                <?php render_datatable([
                                    '', _l('ams_asset_tag'), _l('ams_asset_name'), _l('ams_category'), _l('ams_brand_model'), _l('ams_serial_no'), _l('ams_status'),
                                    _l('ams_assigned_to'), _l('ams_department'), _l('ams_location'), _l('ams_warranty'), _l('ams_purchase_cost'), _l('ams_purchase_date'),
                                ], 'ams-my-assets'); ?>
                            </div>
                            <?php } ?>
                            <?php if ($canAcc) { ?>
                            <div class="tab-pane <?= $firstTab === 'accessories' ? 'active' : ''; ?>" id="tab_accessories">
                                <?php render_datatable([
                                    _l('ams_date'), _l('ams_item'), _l('ams_quantity'), _l('ams_returned'), _l('ams_outstanding'),
                                    _l('ams_assigned_to'), _l('ams_department'), _l('ams_expected_return'), _l('ams_status'), _l('ams_done_by'),
                                ], 'ams-my-checkouts'); ?>
                            </div>
                            <?php } ?>
                            <?php if ($canLic) { ?>
                            <div class="tab-pane" id="tab_licenses">
                                <?php render_datatable([_l('ams_license'), _l('ams_assigned_to'), _l('ams_lic_assigned_at'), _l('ams_note'), _l('ams_lic_released'), _l('ams_done_by')], 'ams-my-licenses'); ?>
                            </div>
                            <?php } ?>
                            <div class="tab-pane <?= $firstTab === 'acceptances' ? 'active' : ''; ?>" id="tab_acceptances">
                                <?php render_datatable([
                                    _l('ams_date'), _l('ams_item'), _l('ams_item_kind'), _l('ams_status'), _l('ams_responded'), _l('ams_signed_name'), _l('ams_assigned_by'),
                                ], 'ams-my-acceptances'); ?>
                            </div>
                            <?php if ($canRequests) { ?>
                            <div class="tab-pane <?= $firstTab === 'requests' ? 'active' : ''; ?>" id="tab_requests">
                                <?php render_datatable([
                                    _l('ams_req_no'), _l('ams_date'), _l('ams_requester'), _l('ams_department'), _l('ams_req_type'),
                                    _l('ams_req_subject'), _l('ams_quantity'), _l('ams_priority'), _l('ams_needed_by'), _l('ams_status'),
                                ], 'ams-my-requests'); ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Acceptance: review terms and sign (or decline) -->
<div class="modal fade" id="ams_acceptance_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_acceptance'); ?>: <span class="ams-acc-label"></span></h4>
            </div>
            <div class="modal-body">
                <h5 class="tw-font-semibold"><?= _l('ams_acceptance_terms'); ?></h5>
                <div class="ams-acc-terms tw-p-3 tw-mb-4 tw-rounded-md tw-bg-neutral-50 tw-border tw-border-solid tw-border-neutral-200" style="white-space:pre-wrap;max-height:220px;overflow:auto"></div>

                <div class="ams-acc-pending">
                    <?= render_input('signed_name', 'ams_signed_name', get_staff_full_name()); ?>
                    <p class="tw-font-medium tw-mb-1"><?= _l('ams_signature'); ?></p>
                    <div class="tw-border tw-border-solid tw-border-neutral-300 tw-rounded-md" style="background:#fff">
                        <canvas id="ams-signature" height="160" style="width:100%;touch-action:none"></canvas>
                    </div>
                    <button type="button" class="btn btn-default btn-xs tw-mt-1" onclick="ams_sig && ams_sig.clear();"><?= _l('clear'); ?></button>
                    <div class="ams-acc-decline hide tw-mt-4">
                        <?= render_textarea('decline_note', 'ams_decline_reason'); ?>
                    </div>
                </div>

                <div class="ams-acc-done hide">
                    <p><?= _l('ams_status'); ?>: <span class="ams-acc-status"></span> <span class="text-muted ams-acc-when"></span></p>
                    <p class="ams-acc-signed"></p>
                    <img class="ams-acc-sigimg img-thumbnail hide" alt="" style="max-height:160px">
                    <p class="ams-acc-note text-danger"></p>
                </div>
            </div>
            <div class="modal-footer ams-acc-pending">
                <button type="button" class="btn btn-default pull-left" onclick="ams_toggle_decline();"><?= _l('ams_decline'); ?></button>
                <button type="button" class="btn btn-danger hide ams-acc-decline-btn" onclick="ams_submit_acceptance('decline');"><?= _l('ams_confirm_decline'); ?></button>
                <button type="button" class="btn btn-success ams-acc-accept-btn" onclick="ams_submit_acceptance('accept');"><i class="fa-solid fa-file-signature tw-mr-1"></i><?= _l('ams_accept_and_sign'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php if ($canCreate) { ?>
<!-- New request / report issue -->
<div class="modal fade" id="ams_request_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/requests/create'), ['id' => 'ams-request-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_req_new'); ?></h4>
            </div>
            <div class="modal-body">
                <?= render_select('type', [
                    ['id' => 'asset', 'name' => _l('ams_req_type_asset')],
                    ['id' => 'accessory', 'name' => _l('ams_req_type_accessory')],
                    ['id' => 'consumable', 'name' => _l('ams_req_type_consumable')],
                    ['id' => 'issue', 'name' => _l('ams_req_type_issue')],
                ], ['id', 'name'], 'ams_req_type', 'asset', [], [], '', '', false); ?>
                <div class="ams-req-f ams-req-f-asset">
                    <?= render_select('category_id', $categories, ['id', 'name'], 'ams_category'); ?>
                </div>
                <div class="ams-req-f ams-req-f-accessory ams-req-f-consumable hide">
                    <?= render_select('item_id', $items, ['id', 'name'], 'ams_item'); ?>
                </div>
                <div class="ams-req-f ams-req-f-issue hide">
                    <?= render_select('asset_id', array_map(fn ($a) => ['id' => $a['id'], 'name' => $a['asset_tag'] . ' - ' . $a['name']], $my_assets), ['id', 'name'], 'ams_asset'); ?>
                </div>
                <?= render_input('subject', '<small class="req text-danger">* </small>' . _l('ams_req_subject')); ?>
                <?= render_textarea('description', 'ams_req_description'); ?>
                <div class="row">
                    <div class="col-md-4 ams-req-f ams-req-f-asset ams-req-f-accessory ams-req-f-consumable">
                        <?= render_input('qty', 'ams_quantity', '1', 'number', ['min' => '1', 'step' => '1']); ?>
                    </div>
                    <div class="col-md-4">
                        <?= render_select('priority', [
                            ['id' => 'low', 'name' => _l('ams_priority_low')],
                            ['id' => 'normal', 'name' => _l('ams_priority_normal')],
                            ['id' => 'high', 'name' => _l('ams_priority_high')],
                            ['id' => 'urgent', 'name' => _l('ams_priority_urgent')],
                        ], ['id', 'name'], 'ams_priority', 'normal', [], [], '', '', false); ?>
                    </div>
                    <div class="col-md-4">
                        <?= render_date_input('needed_by', 'ams_needed_by'); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php init_tail(); ?>
<script src="<?= base_url('assets/plugins/signature-pad/signature_pad.min.js'); ?>"></script>
<script>
    var ams_sig = null, ams_acc_id = null;

    $(function() {
        <?php if ($canAssets) { ?>
        // Checkbox, "assigned to" (me), department, cost and purchase date are not needed here.
        initDataTable('.table-ams-my-assets', admin_url + 'asset_management/my_assets/assets_table', [0], [0], {}, [1, 'asc'])
            .columns([0, 7, 8, 11, 12]).visible(false, false).columns.adjust();
        <?php } ?>
        <?php if ($canAcc) { ?>
        initDataTable('.table-ams-my-checkouts', admin_url + 'asset_management/my_assets/checkouts_table', [], [], {}, [0, 'desc']);
        <?php } ?>
        initDataTable('.table-ams-my-acceptances', admin_url + 'asset_management/my_assets/acceptances_table', [], [], {}, [0, 'desc']);
        <?php if ($canLic) { ?>
        initDataTable('.table-ams-my-licenses', admin_url + 'asset_management/licenses/my_seats_table', [], [], {}, [2, 'desc']).column(1).visible(false, false);
        <?php } ?>
        <?php if ($canRequests) { ?>
        initDataTable('.table-ams-my-requests', admin_url + 'asset_management/my_assets/requests_table', [], [], {}, [1, 'desc']);
        <?php } ?>

        $('#ams_acceptance_modal').on('shown.bs.modal', function() {
            var canvas = document.getElementById('ams-signature');
            canvas.width = canvas.offsetWidth;
            ams_sig = new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)' });
        });

        <?php if ($canCreate) { ?>
        $('#ams-request-form select[name="type"]').on('change', function() {
            $('.ams-req-f').addClass('hide');
            $('.ams-req-f-' + $(this).val()).removeClass('hide');
        });
        appValidateForm($('#ams-request-form'), { subject: 'required' }, function(form) {
            $.post(form.action, $(form).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : alert_float('danger', r.message);
            });
            return false;
        });
        <?php } ?>
    });

    function ams_new_request(type) {
        var form = $('#ams-request-form');
        form.find('select[name="type"]').selectpicker('val', type).trigger('change');
        $('#ams_request_modal').modal('show');
    }

    function ams_open_acceptance(id) {
        $.get(admin_url + 'asset_management/my_assets/acceptance/' + id, function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            var m = $('#ams_acceptance_modal');
            ams_acc_id = r.id;
            m.find('.ams-acc-label').text(r.label);
            m.find('.ams-acc-terms').text(r.terms || '');
            var pending = r.status === 'pending' && r.mine;
            m.find('.ams-acc-pending').toggleClass('hide', !pending);
            m.find('.ams-acc-done').toggleClass('hide', pending);
            m.find('.ams-acc-decline, .ams-acc-decline-btn').addClass('hide');
            m.find('.ams-acc-accept-btn').removeClass('hide');
            if (!pending) {
                m.find('.ams-acc-status').text(r.status);
                m.find('.ams-acc-when').text(r.date_responded);
                m.find('.ams-acc-signed').text(r.signed_name ? '<?= e(_l('ams_signed_name')); ?>: ' + r.signed_name : '');
                m.find('.ams-acc-sigimg').toggleClass('hide', !r.signature_url).attr('src', r.signature_url || '');
                m.find('.ams-acc-note').text(r.note || '');
            }
            m.modal('show');
        });
    }

    function ams_toggle_decline() {
        var m = $('#ams_acceptance_modal');
        m.find('.ams-acc-decline, .ams-acc-decline-btn').toggleClass('hide');
        m.find('.ams-acc-accept-btn').toggleClass('hide');
    }

    function ams_submit_acceptance(decision) {
        var data = { decision: decision };
        if (decision === 'accept') {
            if (!ams_sig || ams_sig.isEmpty()) {
                alert_float('warning', '<?= e(_l('ams_signature_required')); ?>');
                return;
            }
            data.signature = ams_sig.toDataURL('image/png');
            data.signed_name = $('#signed_name').val();
        } else {
            data.note = $('#decline_note').val();
        }
        $.post(admin_url + 'asset_management/my_assets/respond/' + ams_acc_id, data).done(function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            r.success ? window.location.reload() : alert_float('danger', r.message);
        });
    }
</script>
</body>
</html>
