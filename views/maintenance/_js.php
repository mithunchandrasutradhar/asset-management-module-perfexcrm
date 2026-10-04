<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
    function ams_mt_reload() {
        $('.table-ams-maintenance, .table-ams-asset-maintenance, .table-ams-my-maintenance, .table-ams-staff-maintenance').each(function() {
            if ($.fn.DataTable.isDataTable(this)) {
                $(this).DataTable().ajax.reload(null, false);
            }
        });
    }

    // Reload without ?request_id (it re-opens the pre-filled form and invites a duplicate job).
    function ams_mt_done() {
        var url = window.location.href.replace(/([?&])request_id=\d+&?/, '$1').replace(/[?&]$/, '');
        url === window.location.href ? window.location.reload() : (window.location.href = url);
    }

    function ams_mt_new() {
        var f = $('#ams-mt-form');
        f[0].reset();
        f.find('select.selectpicker').selectpicker('refresh');
        f.find('#ams_mt_responsible').selectpicker('val', []);
        f.find('[name="id"]').val('');
        f.find('.ams-mt-new-only, .ams-mt-asset').removeClass('hide');
        $('#ams_mt_modal').modal('show');
    }

    function ams_mt_edit(id) {
        $.get(admin_url + 'asset_management/maintenance/get/' + id, function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            var f = $('#ams-mt-form');
            f.find('[name="id"]').val(r.id);
            f.find('[name="asset_id"]').val(r.asset_id);
            f.find('[name="title"]').val(r.title);
            f.find('[name="type"]').selectpicker('val', r.type);
            f.find('[name="supplier_id"]').selectpicker('val', r.supplier_id || '');
            f.find('#ams_mt_responsible').selectpicker('val', r.responsible || []);
            f.find('[name="due_date"]').val(r.due_date);
            f.find('[name="notes"]').val(r.notes || '');
            f.find('.ams-mt-new-only, .ams-mt-asset').addClass('hide');
            $('#ams_mt_modal').modal('show');
        });
    }

    // type: the job type, so the "is it working" check can be marked required for inspections / preventive work.
    function ams_mt_action(action, id, type) {
        var modal = $('#ams_mt_' + action + '_modal');
        modal.find('form').attr('action', admin_url + 'asset_management/maintenance/' + action + '/' + id);
        if (action === 'complete') {
            modal.find('.ams-mt-check-req').toggleClass('hide', ['inspection', 'preventive'].indexOf(type) === -1);
            modal.find('#ams_mt_check_result').selectpicker('val', '');
            modal.find('.ams-mt-not-working').addClass('hide');
        }
        modal.modal('show');
    }

    function ams_mt_cancel(id) {
        if (!confirm_delete()) {
            return;
        }
        $.post(admin_url + 'asset_management/maintenance/cancel/' + id).done(function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            r.success ? ams_mt_done() : alert_float('danger', r.message);
        });
    }

    $(function() {
        $('#ams_mt_check_result').on('change', function() {
            $('.ams-mt-not-working').toggleClass('hide', $(this).val() !== 'not_working');
        });
        $('#ams-mt-form, .ams-mt-action-form').on('submit', function(e) {
            e.preventDefault();
            var btn = $(this).find('[type="submit"]').prop('disabled', true);
            $.post($(this).attr('action'), $(this).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                r.success ? ams_mt_done() : alert_float('danger', r.message);
            }).always(function() {
                btn.prop('disabled', false);
            });
        });
    });
</script>
