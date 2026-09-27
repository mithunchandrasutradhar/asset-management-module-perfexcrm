<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (staff_can('checkout', 'ams_accessories')) { ?>
<div class="modal fade" id="ams_item_checkin_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open('', ['class' => 'ams-action-form', 'id' => 'ams-item-checkin-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_checkin'); ?></h4>
            </div>
            <div class="modal-body">
                <?= render_input('qty', 'ams_return_qty', '', 'number', ['step' => '0.01', 'min' => '0.01']); ?>
                <p class="text-muted tw-text-sm -tw-mt-2"><?= _l('ams_outstanding'); ?>: <strong class="ams-checkin-outstanding"></strong></p>
                <?= render_select('location_id', $locations, ['id', 'name'], 'ams_return_to_location', '', [], [], '', '', false); ?>
                <?= render_textarea('note', 'ams_note'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-info"><?= _l('ams_checkin'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<script>
    function ams_checkin(checkoutId, outstanding, locationId) {
        var modal = $('#ams_item_checkin_modal');
        modal.find('form').attr('action', admin_url + 'asset_management/inventory/checkin/' + checkoutId);
        modal.find('input[name="qty"]').val(outstanding).attr('max', outstanding);
        modal.find('.ams-checkin-outstanding').text(outstanding);
        modal.find('select[name="location_id"]').selectpicker('val', String(locationId));
        modal.modal('show');
    }
</script>
<?php } ?>
