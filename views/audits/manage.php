<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_audits'); ?></h4>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'ams_audits')) { ?>
                        <a href="#" class="btn btn-primary" onclick="ams_audit_edit(); return false;"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_audit_new'); ?></a>
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
                            _l('ams_audit_no'), _l('ams_audit_title'), _l('ams_audit_scope'), _l('ams_status'), _l('ams_audit_due_date'),
                            _l('ams_audit_progress'), _l('ams_audit_result_missing'), _l('ams_audit_completed_at'), _l('ams_audit_next_date'),
                        ], 'ams-audits', [], [
                            'data-last-order-identifier' => 'ams-audits',
                            'data-default-order'         => get_table_last_order('ams-audits'),
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view(AMS_MODULE_NAME . '/audits/_modal', ['locations' => $locations, 'departments' => $departments, 'categories' => $categories]); ?>
<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/audits/_modal_js'); ?>
<script>
    $(function() {
        initDataTable('.table-ams-audits', admin_url + 'asset_management/audits/table', [5], [5], {}, [0, 'desc']);
    });
</script>
</body>
</html>
