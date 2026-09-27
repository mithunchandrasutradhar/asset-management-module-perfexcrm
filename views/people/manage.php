<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_assets_by_staff'); ?></h4>
                <div class="tw-mb-2 tw-flex tw-justify-between">
                    <div>
                        <?php if (staff_can('checkin', 'ams_assets')) { ?>
                        <button type="button" class="btn btn-default" id="ams-send-reminders"><i class="fa-regular fa-bell tw-mr-1"></i><?= _l('ams_send_overdue_reminders'); ?></button>
                        <?php } ?>
                    </div>
                    <div id="vueApp">
                        <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                            :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                        </app-filters>
                    </div>
                </div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            _l('ams_assign_type_staff'), _l('ams_department'), _l('ams_assets'), _l('ams_accessories'),
                            _l('ams_overdue'), _l('ams_acc_pending_short'), _l('ams_open_requests'), _l('ams_active'),
                        ], 'ams-people', [], [
                            'data-last-order-identifier' => 'ams-people',
                            'data-default-order'         => get_table_last_order('ams-people'),
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-ams-people', admin_url + 'asset_management/people/table', [], [], {}, [2, 'desc']);
        $('#ams-send-reminders').on('click', function() {
            var btn = $(this).prop('disabled', true);
            $.post(admin_url + 'asset_management/people/send_reminders').done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : (alert_float('danger', r.message), btn.prop('disabled', false));
            });
        });
    });
</script>
</body>
</html>
