<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
    // Action modals (.ams-action-form) post via AJAX; on success the page reloads
    // and shows the Perfex alert, on failure the message is shown in place.
    $(function() {
        $(document).on('submit', '.ams-action-form', function(e) {
            e.preventDefault();
            var form = $(this);
            var btn = form.find('button[type="submit"]');
            btn.prop('disabled', true);
            $.post(form.attr('action'), form.serialize()).done(function(response) {
                response = typeof response === 'string' ? JSON.parse(response) : response;
                if (response.success) {
                    window.location.reload();
                } else {
                    alert_float('danger', response.message);
                    btn.prop('disabled', false);
                }
            }).fail(function(xhr) {
                alert_float('danger', xhr.statusText);
                btn.prop('disabled', false);
            });
        });

        // Recipient pickers: show the staff or department select for the chosen type.
        $(document).on('change', '.ams-recipient-type', function() {
            var modal = $(this).closest('.modal-body');
            var type = $(this).val();
            modal.find('.ams-recipient').addClass('hide');
            modal.find('.ams-recipient-' + type).removeClass('hide');
            modal.find('.ams-recipient-dept').toggleClass('hide', type === 'department');
        });
    });
</script>
