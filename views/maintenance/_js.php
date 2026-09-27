<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
    function ams_mt_reload() {
        $('.table-ams-maintenance, .table-ams-asset-maintenance').each(function() {
            if ($.fn.DataTable.isDataTable(this)) {
                $(this).DataTable().ajax.reload(null, false);
            }
        });
    }

    function ams_mt_new() {
        var f = $('#ams-mt-form');
        f.find('[name="id"]').val('');
        f.find('.ams-mt-new-only, .ams-mt-asset').removeClass('hide');
        $('#ams_mt_modal').modal('show');
    }

    function ams_mt_edit(id) {
        $.get(admin_url + 'asset_management/maintenance/get/' + id, function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            var f = $('#ams-mt-form');
            f.find('[name="id"]').val(r.id);
            f.find('[name="title"]').val(r.title);
            f.find('[name="type"]').selectpicker('val', r.type);
            f.find('[name="supplier_id"]').selectpicker('val', r.supplier_id || '');
            f.find('[name="due_date"]').val(r.due_date);
            f.find('[name="notes"]').val(r.notes || '');
            f.find('.ams-mt-new-only, .ams-mt-asset').addClass('hide');
            $('#ams_mt_modal').modal('show');
        });
    }

    function ams_mt_action(action, id) {
        var modal = $('#ams_mt_' + action + '_modal');
        modal.find('form').attr('action', admin_url + 'asset_management/maintenance/' + action + '/' + id);
        modal.modal('show');
    }

    function ams_mt_cancel(id) {
        if (!confirm_delete()) {
            return;
        }
        $.post(admin_url + 'asset_management/maintenance/cancel/' + id).done(function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            r.success ? window.location.reload() : alert_float('danger', r.message);
        });
    }

    $(function() {
        $('#ams-mt-form, .ams-mt-action-form').on('submit', function(e) {
            e.preventDefault();
            $.post($(this).attr('action'), $(this).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? window.location.reload() : alert_float('danger', r.message);
            });
        });
    });
</script>
