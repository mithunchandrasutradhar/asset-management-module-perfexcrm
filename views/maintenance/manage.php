<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$canEdit = staff_can('edit', 'ams_maintenance');
$viewAll = staff_can('view', 'ams_maintenance');
$mine    = ! $viewAll || $this->input->get('mine');
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_maintenance') . ($mine ? ' <span class="text-muted tw-font-normal tw-text-base">- ' . _l('ams_mt_my_jobs') . '</span>' : ''); ?></h4>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'ams_maintenance')) { ?>
                        <a href="#" class="btn btn-primary" onclick="ams_mt_new(); return false;"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_mt_new'); ?></a>
                        <?php } ?>
                        <?php if ($viewAll) { ?>
                        <a href="<?= admin_url('asset_management/maintenance' . ($mine ? '' : '?mine=1')); ?>" class="btn btn-default">
                            <i class="fa-regular fa-<?= $mine ? 'rectangle-list' : 'user'; ?> tw-mr-1"></i><?= $mine ? _l('ams_mt_all_jobs') : _l('ams_mt_my_jobs'); ?>
                        </a>
                        <?php } ?>
                        <a href="<?= admin_url('asset_management/maintenance/schedules' . ($mine ? '?mine=1' : '')); ?>" class="btn btn-default"><i class="fa-regular fa-calendar-check tw-mr-1"></i><?= $mine ? _l('ams_mt_my_schedules') : _l('ams_mt_schedules'); ?></a>
                        <?php if ($canEdit) { ?>
                        <a href="#" data-toggle="modal" data-target="#ams_mt_bulk_actions" class="hide bulk-actions-btn table-btn" data-table=".table-ams-maintenance"><?= _l('bulk_actions'); ?></a>
                        <?php } ?>
                        <div id="vueApp" class="tw-inline pull-right">
                            <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                                :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                            </app-filters>
                        </div>
                    </div>
                </div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            [
                                'name'     => '<span class="hide"> - </span><div class="checkbox mass_select_all_wrap"><input type="checkbox" id="mass_select_all" data-to-table="ams-maintenance"><label></label></div>',
                                'th_attrs' => ['class' => $canEdit ? '' : 'not_visible'],
                            ],
                            '#', _l('ams_asset'), _l('ams_mt_title'), _l('ams_mt_type'), _l('ams_status'), _l('ams_mt_responsible'),
                            _l('ams_mt_due_date'), _l('ams_mt_start_date'), _l('ams_mt_end_date'), _l('ams_supplier'), _l('ams_mt_cost'),
                        ], 'ams-maintenance', [], [
                            'data-last-order-identifier' => 'ams-maintenance',
                            'data-default-order'         => get_table_last_order('ams-maintenance'),
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canEdit) { ?>
<div class="modal fade bulk_actions" id="ams_mt_bulk_actions" tabindex="-1" role="dialog" data-table=".table-ams-maintenance">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_mt_bulk_reassign'); ?></h4>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?= _l('ams_mt_bulk_help'); ?></p>
                <?php $this->load->view(AMS_MODULE_NAME . '/maintenance/_responsible_select', ['options' => $responsible, 'select_id' => 'ams_mt_bulk_responsible']); ?>
                <div class="radio radio-primary"><input type="radio" name="ams_mt_bulk_mode" id="ams_mt_bulk_add" value="add" checked><label for="ams_mt_bulk_add"><?= _l('ams_mt_bulk_add'); ?></label></div>
                <div class="radio radio-primary"><input type="radio" name="ams_mt_bulk_mode" id="ams_mt_bulk_replace" value="replace"><label for="ams_mt_bulk_replace"><?= _l('ams_mt_bulk_replace'); ?></label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <a href="#" class="btn btn-primary" onclick="ams_mt_bulk(this); return false;"><?= _l('confirm'); ?></a>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php $this->load->view(AMS_MODULE_NAME . '/maintenance/_modals', ['assets' => $assets, 'types' => $types, 'suppliers' => $suppliers, 'request' => $request, 'fixed_asset' => null, 'responsible' => $responsible]); ?>
<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/maintenance/_js'); ?>
<script>
    $(function() {
        var dt = initDataTable('.table-ams-maintenance', admin_url + 'asset_management/maintenance/table<?= $mine && $viewAll ? '?mine=1' : ''; ?>', [0, 6], [0, 6], {}, [1, 'desc']);
        <?php if (! $canEdit) { ?>
        dt.column(0).visible(false, false);
        <?php } ?>
        <?php if ($request && staff_can('create', 'ams_maintenance')) { ?>
        ams_mt_new();
        <?php } ?>
    });

    function ams_mt_bulk(el) {
        var ids = [];
        $('.table-ams-maintenance tbody tr').find('td:first input[type="checkbox"]:checked').each(function() {
            ids.push($(this).val());
        });
        if (!ids.length) {
            return;
        }
        $(el).addClass('disabled');
        $.post(admin_url + 'asset_management/maintenance/bulk_responsible', {
            ids: ids,
            responsible_departments: $('#ams_mt_bulk_responsible_dept').selectpicker('val') || [],
            responsible_staff: $('#ams_mt_bulk_responsible_staff').selectpicker('val') || [],
            mode: $('input[name="ams_mt_bulk_mode"]:checked').val()
        }).done(function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            r.success ? window.location.reload() : alert_float('danger', r.message);
        }).always(function() {
            $(el).removeClass('disabled');
        });
    }
</script>
</body>
</html>
