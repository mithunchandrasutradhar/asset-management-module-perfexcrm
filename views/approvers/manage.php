<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_department_approvers'); ?></h4>
                <div class="alert alert-info"><?= _l('ams_department_approvers_help'); ?></div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([_l('ams_department'), _l('ams_approvers'), _l('ams_members'), _l('ams_waiting_approval')], 'ams-approvers'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ams_approvers_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/approvers/save'), ['id' => 'ams-approvers-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_department_approvers'); ?>: <span class="ams-dept-name"></span></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="department_id" value="">
                <?= render_select('staff_ids[]', $staff, ['id', 'name'], 'ams_approvers', '', ['multiple' => true, 'data-actions-box' => true], [], '', '', false); ?>
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
        initDataTable('.table-ams-approvers', admin_url + 'asset_management/approvers/table', [], [], {}, [0, 'asc']);
        $('#ams-approvers-form').on('submit', function(e) {
            e.preventDefault();
            $.post(this.action, $(this).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                alert_float(r.success ? 'success' : 'danger', r.message);
                if (r.success) {
                    $('#ams_approvers_modal').modal('hide');
                    $('.table-ams-approvers').DataTable().ajax.reload(null, false);
                }
            });
        });
    });

    function ams_edit_approvers(departmentId, name) {
        $.get(admin_url + 'asset_management/approvers/get/' + departmentId, function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            var m = $('#ams_approvers_modal');
            m.find('[name="department_id"]').val(departmentId);
            m.find('.ams-dept-name').text(name);
            m.find('select').selectpicker('val', (r.staff_ids || []).map(String));
            m.modal('show');
        });
    }
</script>
</body>
</html>
