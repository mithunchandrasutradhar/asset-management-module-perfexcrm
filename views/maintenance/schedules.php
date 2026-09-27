<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $units = array_map(fn ($u) => ['id' => $u, 'name' => _l('ams_unit_' . $u)], ['day', 'week', 'month', 'year']); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_mt_schedules'); ?></h4>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'ams_maintenance')) { ?>
                        <a href="#" class="btn btn-primary" onclick="ams_sch_new(); return false;"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_mt_new_schedule'); ?></a>
                        <?php } ?>
                        <a href="<?= admin_url('asset_management/maintenance'); ?>" class="btn btn-default"><?= _l('ams_maintenance'); ?></a>
                        <div id="vueApp" class="tw-inline pull-right">
                            <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                                :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                            </app-filters>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info"><?= _l('ams_mt_schedules_help', (int) get_option('ams_maintenance_lead_days')); ?></div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            _l('ams_asset'), _l('ams_mt_title'), _l('ams_mt_type'), _l('ams_mt_interval'), _l('ams_mt_next_due'), _l('ams_mt_last_done'), _l('ams_mt_vendor'), _l('ams_active'),
                        ], 'ams-schedules'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ams_sch_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/maintenance/save_schedule'), ['id' => 'ams-sch-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_mt_schedule'); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" value="">
                <div class="ams-sch-assets">
                    <?= render_select('asset_ids[]', $assets, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_mt_for_assets'), '', ['multiple' => true, 'data-actions-box' => true], [], '', '', false); ?>
                </div>
                <?= render_input('title', '<small class="req text-danger">* </small>' . _l('ams_mt_title'), '', 'text', ['placeholder' => _l('ams_mt_title_placeholder')]); ?>
                <div class="row">
                    <div class="col-md-4"><?= render_input('interval_value', 'ams_mt_every_label', '3', 'number', ['min' => 1]); ?></div>
                    <div class="col-md-4"><?= render_select('interval_unit', $units, ['id', 'name'], '&nbsp;', 'month', [], [], '', '', false); ?></div>
                    <div class="col-md-4"><?= render_date_input('next_due', 'ams_mt_next_due'); ?></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><?= render_select('type', $types, ['id', 'name'], 'ams_mt_type', 'preventive', [], [], '', '', false); ?></div>
                    <div class="col-md-6"><?= render_select('supplier_id', $suppliers, ['id', 'name'], 'ams_mt_vendor'); ?></div>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="active" id="sch_active" value="1" checked>
                    <label for="sch_active"><?= _l('ams_active'); ?></label>
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

<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-ams-schedules', admin_url + 'asset_management/maintenance/schedules_table', [], [], {}, [4, 'asc']);
        $('#ams-sch-form').on('submit', function(e) {
            e.preventDefault();
            $.post(this.action, $(this).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : alert_float('danger', r.message);
            });
        });
    });

    function ams_sch_new() {
        var f = $('#ams-sch-form');
        f.find('[name="id"]').val('');
        f.find('.ams-sch-assets').removeClass('hide');
        $('#ams_sch_modal').modal('show');
    }

    function ams_sch_edit(id) {
        $.get(admin_url + 'asset_management/maintenance/get_schedule/' + id, function(s) {
            s = typeof s === 'string' ? JSON.parse(s) : s;
            var f = $('#ams-sch-form');
            f.find('[name="id"]').val(s.id);
            f.find('.ams-sch-assets').addClass('hide');
            f.find('[name="title"]').val(s.title);
            f.find('[name="interval_value"]').val(s.interval_value);
            f.find('[name="interval_unit"]').selectpicker('val', s.interval_unit);
            f.find('[name="next_due"]').val(s.next_due);
            f.find('[name="type"]').selectpicker('val', s.type);
            f.find('[name="supplier_id"]').selectpicker('val', s.supplier_id || '');
            f.find('[name="active"]').prop('checked', s.active == 1);
            $('#ams_sch_modal').modal('show');
        });
    }
</script>
</body>
</html>
