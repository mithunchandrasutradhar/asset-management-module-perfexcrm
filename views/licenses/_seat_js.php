<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
    function ams_release_seat(seatId) {
        if (!confirm_delete()) {
            return;
        }
        $.post(admin_url + 'asset_management/licenses/release/' + seatId).done(function(r) {
            r = typeof r === 'string' ? JSON.parse(r) : r;
            r.success ? window.location.reload() : alert_float('danger', r.message);
        });
    }
</script>
