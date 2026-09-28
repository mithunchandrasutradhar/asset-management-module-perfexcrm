<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$tile = function ($label, $value, $cls = '') {
    return '<div class="col-md-3 col-sm-6"><div class="panel_s"><div class="panel-body tw-py-3">'
        . '<p class="tw-text-neutral-500 tw-mb-0 tw-text-sm">' . $label . '</p>'
        . '<p class="tw-font-semibold tw-text-2xl tw-mb-0 ' . $cls . '">' . (int) $value . '</p></div></div></div>';
};
?>
<div id="wrapper">
    <div class="content">
        <?php $this->load->view(AMS_MODULE_NAME . '/hostbill/_header', ['tab' => 'inventory']); ?>

        <div class="row">
            <?= $tile(_l('ams_hb_state_in_stock'), $counts['in_stock'], 'text-success'); ?>
            <?= $tile(_l('ams_hb_state_low'), $counts['low'], $counts['low'] ? 'text-warning' : ''); ?>
            <?= $tile(_l('ams_hb_state_out'), $counts['out'], $counts['out'] ? 'text-danger' : ''); ?>
            <?php if (get_option('ams_hb_product_scope') === 'all') { ?>
            <?= $tile(_l('ams_hb_state_not_tracked'), $counts['not_tracked']); ?>
            <?php } ?>
        </div>

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
                    _l('ams_hb_product'), _l('ams_hb_group'), _l('ams_hb_hostbill_qty'), _l('ams_hb_low_level'),
                    _l('ams_status'), _l('ams_hb_visible'), _l('ams_hb_last_refreshed'),
                ], 'ams-hb-inventory', [], [
                    'data-last-order-identifier' => 'ams-hb-inventory',
                    'data-default-order'         => get_table_last_order('ams-hb-inventory'),
                ]); ?>
            </div>
        </div>
    </div>
</div>

<?php if (staff_can('edit', 'ams_hostbill') && get_option('ams_hb_allow_override') == '1') { ?>
<div class="modal fade" id="ams_hb_level_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <?= form_open('', ['id' => 'ams-hb-level-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('ams_hb_set_level'); ?>: <span class="ams-hb-level-name"></span></h4>
            </div>
            <div class="modal-body">
                <?= render_input('low_level', ams_label_help('ams_hb_low_level', _l('ams_hb_level_help', ams_qty(get_option('ams_hb_default_low_level')))), '', 'number', ['step' => '0.01', 'min' => 0, 'placeholder' => ams_qty(get_option('ams_hb_default_low_level'))]); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<?php } ?>

<?php init_tail(); ?>
<?php $this->load->view(AMS_MODULE_NAME . '/hostbill/_post_js'); ?>
<script>
    $(function() {
        initDataTable('.table-ams-hb-inventory', admin_url + 'asset_management/hostbill/table', [], [], {}, [4, 'desc']);

        $('#ams-hb-level-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            $.post(form.attr('action'), form.serialize()).done(function(r) {
                r = typeof r === 'string' ? JSON.parse(r) : r;
                if (r.success) {
                    window.location.reload();
                } else {
                    alert_float('danger', r.message);
                }
            });
        });
    });

    function ams_hb_level(id, level, name) {
        var modal = $('#ams_hb_level_modal');
        modal.find('form').attr('action', admin_url + 'asset_management/hostbill/set_level/' + id);
        modal.find('input[name="low_level"]').val(level);
        modal.find('.ams-hb-level-name').text(name);
        modal.modal('show');
    }
</script>
</body>
</html>
