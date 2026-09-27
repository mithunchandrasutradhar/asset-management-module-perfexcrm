<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
    $(function() {
        appValidateForm($('#ams-audit-form'), { title: 'required' }, function(form) {
            var btn = $(form).find('button[type="submit"]').prop('disabled', true);
            $.post(form.action, $(form).serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                if (r.success) {
                    window.location.href = r.redirect || window.location.href;
                } else {
                    alert_float('danger', r.message);
                    btn.prop('disabled', false);
                }
            });
            return false;
        });
    });

    function ams_audit_edit(id) {
        var form = $('#ams-audit-form');
        form[0].reset();
        form.find('input[name="id"]').val('');
        form.find('select').selectpicker('val', '');
        $('.ams-audit-scope').removeClass('hide');
        $('.ams-audit-scope-locked').addClass('hide');
        if (!id) {
            $('#ams_audit_modal').modal('show');
            return;
        }
        $.get(admin_url + 'asset_management/audits/get/' + id, function(a) {
            a = typeof a === 'string' ? JSON.parse(a) : a;
            form.find('input[name="id"]').val(a.id);
            form.find('input[name="title"]').val(a.title);
            form.find('select[name="location_id"]').selectpicker('val', a.location_id || '');
            form.find('select[name="department_id"]').selectpicker('val', a.department_id || '');
            form.find('select[name="category_id"]').selectpicker('val', a.category_id || '');
            form.find('input[name="due_date"]').val(a.due_date);
            form.find('input[name="next_audit_date"]').val(a.next_audit_date);
            form.find('textarea[name="notes"]').val(a.notes || '');
            var locked = a.status !== 'draft';
            $('.ams-audit-scope').toggleClass('hide', locked);
            $('.ams-audit-scope-locked').toggleClass('hide', !locked);
            $('#ams_audit_modal').modal('show');
        });
    }
</script>
