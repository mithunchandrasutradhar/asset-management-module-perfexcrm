<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php $this->load->view(AMS_MODULE_NAME . '/setup/_tabs', ['active' => 'changelog', 'help' => _l('ams_setup_changelog_help')]); ?>

                <div class="tw-mb-2 tw-flex tw-justify-end">
                    <div id="vueApp">
                        <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                            :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>">
                        </app-filters>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                            _l('ams_date'), _l('ams_setup_record_type'), _l('ams_name'), _l('ams_action'), _l('ams_changes'), _l('ams_by'),
                        ], 'ams-setup-log', [], [
                            'id' => $table->id(),
                            'data-last-order-identifier' => 'ams-setup-log',
                            'data-default-order'         => get_table_last_order('ams-setup-log'),
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
        initDataTable('.table-ams-setup-log', admin_url + 'asset_management/setup/changelog_table', [], [], {}, [0, 'desc']);
    });
</script>
</body>
</html>
