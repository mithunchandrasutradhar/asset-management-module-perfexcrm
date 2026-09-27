<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-flex-wrap tw-justify-between tw-items-center tw-mb-3 tw-gap-2">
                    <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                        <?= staff_profile_image($staff->staffid, ['staff-profile-image-small', 'tw-mr-1']); ?>
                        <?= e(get_staff_full_name($staff->staffid)); ?>
                        <?php if (! $staff->active) { ?><span class="label label-default"><?= _l('ams_inactive'); ?></span><?php } ?>
                        <span class="tw-font-normal tw-text-neutral-500 tw-text-base"><?= e(implode(', ', $depts)); ?></span>
                    </h4>
                    <div class="tw-flex tw-gap-1">
                        <a href="<?= admin_url('asset_management/people'); ?>" class="btn btn-default"><i class="fa-solid fa-arrow-left tw-mr-1"></i><?= _l('ams_assets_by_staff'); ?></a>
                        <a href="<?= admin_url('staff/member/' . $staff->staffid); ?>" class="btn btn-default"><?= _l('ams_staff_profile'); ?></a>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                            <li class="active"><a href="#tab_assets" data-toggle="tab"><?= _l('ams_assets'); ?> <span class="badge"><?= (int) $holdings['assets']; ?></span></a></li>
                            <?php if (ams_item_can('view', 'accessory')) { ?>
                            <li><a href="#tab_accessories" data-toggle="tab"><?= _l('ams_accessories'); ?> <span class="badge"><?= ams_qty($holdings['accessories']); ?></span></a></li>
                            <?php } ?>
                            <li><a href="#tab_acceptances" data-toggle="tab"><?= _l('ams_acceptances'); ?> <?php if ($holdings['acceptances']) { ?><span class="badge"><?= (int) $holdings['acceptances']; ?></span><?php } ?></a></li>
                        </ul>
                        <div class="tab-content tw-mt-4">
                            <div class="tab-pane active" id="tab_assets">
                                <?php render_datatable([
                                    '', _l('ams_asset_tag'), _l('ams_asset_name'), _l('ams_category'), _l('ams_brand_model'), _l('ams_serial_no'), _l('ams_status'),
                                    _l('ams_assigned_to'), _l('ams_department'), _l('ams_location'), _l('ams_warranty'), _l('ams_purchase_cost'), _l('ams_purchase_date'),
                                ], 'ams-staff-assets'); ?>
                            </div>
                            <?php if (ams_item_can('view', 'accessory')) { ?>
                            <div class="tab-pane" id="tab_accessories">
                                <?php render_datatable([
                                    _l('ams_date'), _l('ams_item'), _l('ams_quantity'), _l('ams_returned'), _l('ams_outstanding'),
                                    _l('ams_assigned_to'), _l('ams_department'), _l('ams_expected_return'), _l('ams_status'), _l('ams_done_by'),
                                ], 'ams-staff-checkouts'); ?>
                            </div>
                            <?php } ?>
                            <div class="tab-pane" id="tab_acceptances">
                                <?php render_datatable([
                                    _l('ams_date'), _l('ams_item'), _l('ams_item_kind'), _l('ams_status'), _l('ams_responded'), _l('ams_signed_name'), _l('ams_assigned_by'),
                                ], 'ams-staff-acceptances'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Read-only acceptance view (signature image) -->
<div class="modal fade" id="ams_acceptance_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_acceptance'); ?>: <span class="ams-acc-label"></span></h4>
            </div>
            <div class="modal-body">
                <div class="ams-acc-terms tw-p-3 tw-mb-4 tw-rounded-md tw-bg-neutral-50" style="white-space:pre-wrap;max-height:220px;overflow:auto"></div>
                <p><span class="ams-acc-status tw-font-semibold"></span> <span class="text-muted ams-acc-when"></span></p>
                <p class="ams-acc-signed"></p>
                <img class="ams-acc-sigimg img-thumbnail hide" alt="" style="max-height:160px">
                <p class="ams-acc-note text-danger"></p>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function() {
        var id = <?= (int) $staff->staffid; ?>;
        initDataTable('.table-ams-staff-assets', admin_url + 'asset_management/people/assets_table/' + id, [0], [0], {}, [1, 'asc'])
            .columns([0, 7, 11, 12]).visible(false, false).columns.adjust();
        <?php if (ams_item_can('view', 'accessory')) { ?>
        initDataTable('.table-ams-staff-checkouts', admin_url + 'asset_management/people/checkouts_table/' + id, [], [], {}, [0, 'desc']);
        <?php } ?>
        initDataTable('.table-ams-staff-acceptances', admin_url + 'asset_management/people/acceptances_table/' + id, [], [], {}, [0, 'desc']);
    });

    function ams_open_acceptance(id) {
        $.get(admin_url + 'asset_management/my_assets/acceptance/' + id, function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            var m = $('#ams_acceptance_modal');
            m.find('.ams-acc-label').text(r.label);
            m.find('.ams-acc-terms').text(r.terms || '');
            m.find('.ams-acc-status').text(r.status);
            m.find('.ams-acc-when').text(r.date_responded);
            m.find('.ams-acc-signed').text(r.signed_name ? '<?= e(_l('ams_signed_name')); ?>: ' + r.signed_name : '');
            m.find('.ams-acc-sigimg').toggleClass('hide', !r.signature_url).attr('src', r.signature_url || '');
            m.find('.ams-acc-note').text(r.note || '');
            m.modal('show');
        });
    }
</script>
</body>
</html>
