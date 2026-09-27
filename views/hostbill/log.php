<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_hb_sync_log'); ?></h4>
                <?php $this->load->view(AMS_MODULE_NAME . '/hostbill/_status_bar'); ?>
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
                            _l('ams_date'), _l('ams_hb_direction'), _l('ams_hb_api_call'), _l('ams_status'),
                            _l('ams_hb_http'), _l('ams_hb_duration'), _l('ams_hb_error'), _l('ams_done_by'),
                        ], 'ams-hb-log'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ams_hb_log_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_hb_log_entry'); ?></h4>
            </div>
            <div class="modal-body">
                <p class="tw-font-semibold tw-mb-1"><?= _l('ams_hb_request'); ?></p>
                <pre class="ams-hb-request" style="max-height:200px;overflow:auto;white-space:pre-wrap"></pre>
                <p class="tw-font-semibold tw-mb-1"><?= _l('ams_hb_response'); ?></p>
                <pre class="ams-hb-response" style="max-height:360px;overflow:auto;white-space:pre-wrap"></pre>
                <p class="text-danger ams-hb-error"></p>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-ams-hb-log', admin_url + 'asset_management/hostbill/log_table', [], [], {}, [0, 'desc']);
    });

    function ams_hb_log_entry(id) {
        $.get(admin_url + 'asset_management/hostbill/log_entry/' + id, function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            var pretty = function(s) {
                try { return JSON.stringify(JSON.parse(s), null, 2); } catch (e) { return s || ''; }
            };
            var modal = $('#ams_hb_log_modal');
            modal.find('.ams-hb-request').text(pretty(r.request));
            modal.find('.ams-hb-response').text(pretty(r.response));
            modal.find('.ams-hb-error').text(r.error || '');
            modal.modal('show');
        });
    }
</script>
</body>
</html>
