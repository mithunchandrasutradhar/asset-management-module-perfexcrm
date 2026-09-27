<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
    // Buttons with .ams-hb-post call a JSON endpoint; success reloads (Perfex alert
    // shows after reload), failure is shown in place.
    $(function() {
        $(document).on('click', '.ams-hb-post', function() {
            var btn = $(this);
            btn.prop('disabled', true).find('i').addClass('fa-spin');
            $.post(btn.data('url')).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                if (r.success) {
                    window.location.reload();
                } else {
                    alert_float('danger', r.message);
                    btn.prop('disabled', false).find('i').removeClass('fa-spin');
                }
            }).fail(function(xhr) {
                alert_float('danger', xhr.statusText);
                btn.prop('disabled', false).find('i').removeClass('fa-spin');
            });
        });
    });
</script>
