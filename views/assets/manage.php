<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('ams_assets'); ?></h4>

                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'ams_assets')) { ?>
                        <a href="<?= admin_url('asset_management/assets/asset'); ?>" class="btn btn-primary">
                            <i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_new_asset'); ?>
                        </a>
                        <?php } ?>
                        <a href="<?= admin_url('asset_management/scan'); ?>" class="btn btn-default"><i class="fa-solid fa-qrcode tw-mr-1"></i><?= _l('ams_scan'); ?></a>
                        <div id="vueApp" class="tw-inline pull-right tw-ml-0 sm:tw-ml-1.5 rtl:tw-mr-1.5 rtl:tw-ml-0">
                            <app-filters id="<?= $table->id(); ?>"
                                view="<?= $table->viewName(); ?>"
                                :saved-filters="<?= $table->filtersJs(); ?>"
                                :available-rules="<?= $table->rulesJs(); ?>">
                            </app-filters>
                        </div>
                    </div>
                </div>

                <?php if ($status_id || $category_id) { ?>
                <div class="alert alert-info tw-flex tw-justify-between tw-items-center">
                    <span>
                        <?php if ($status_id) {
                            $s = ams_get_status($status_id); ?>
                        <?= _l('ams_filtered_by_status'); ?> <strong><?= e($s['name'] ?? ''); ?></strong>
                        <?php } ?>
                        <?php if ($category_id) { ?>
                        <?= _l('ams_filtered_by_category'); ?> <strong><?= e(ams_option_label(ams_category_options(), $category_id)); ?></strong>
                        <?php } ?>
                    </span>
                    <a href="<?= admin_url('asset_management/assets'); ?>"><?= _l('ams_clear_filter'); ?></a>
                </div>
                <?php } ?>
                <?= form_hidden('ams_status_id', $status_id); ?>
                <?= form_hidden('ams_category_id', $category_id); ?>

                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php
                        // Selection also serves label printing, so anyone with global view gets the checkboxes.
                        $canBulk = staff_can('view', 'ams_assets') || staff_can('edit', 'ams_assets') || staff_can('delete', 'ams_assets');
                        if ($canBulk) { ?>
                        <a href="#" data-toggle="modal" data-target="#ams_assets_bulk_actions"
                            class="hide bulk-actions-btn table-btn" data-table=".table-ams-assets"><?= _l('bulk_actions'); ?></a>
                        <?php } ?>
                        <?php
                        $table_data = [
                            [
                                'name'     => '<span class="hide"> - </span><div class="checkbox mass_select_all_wrap"><input type="checkbox" id="mass_select_all" data-to-table="ams-assets"><label></label></div>',
                                'th_attrs' => ['class' => $canBulk ? '' : 'not_visible'],
                            ],
                            _l('ams_asset_tag'),
                            _l('ams_asset_name'),
                            _l('ams_category'),
                            _l('ams_brand_model'),
                            _l('ams_serial_no'),
                            _l('ams_status'),
                            _l('ams_assigned_to'),
                            _l('ams_department'),
                            _l('ams_location'),
                            _l('ams_warranty'),
                            _l('ams_purchase_cost'),
                            _l('ams_purchase_date'),
                        ];

                        foreach (get_custom_fields('ams_assets', ['show_on_table' => 1]) as $field) {
                            $table_data[] = [
                                'name'     => $field['name'],
                                'th_attrs' => ['data-type' => $field['type'], 'data-custom-field' => 1],
                            ];
                        }

                        render_datatable($table_data, 'ams-assets', [], [
                            'data-last-order-identifier' => 'ams-assets',
                            'data-default-order'         => get_table_last_order('ams-assets'),
                        ]);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canBulk) { ?>
<div class="modal fade bulk_actions" id="ams_assets_bulk_actions" tabindex="-1" role="dialog" data-table=".table-ams-assets">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('bulk_actions'); ?></h4>
            </div>
            <div class="modal-body">
                <?php if (staff_can('delete', 'ams_assets')) { ?>
                <div class="checkbox checkbox-danger">
                    <input type="checkbox" name="mass_delete" id="ams_mass_delete">
                    <label for="ams_mass_delete"><?= _l('mass_delete'); ?></label>
                </div>
                <hr class="mass_delete_separator" />
                <?php } ?>
                <?php if (staff_can('edit', 'ams_assets')) { ?>
                <div id="ams_bulk_change">
                    <?= render_select('ams_bulk_status', array_values(array_filter($statuses, fn ($s) => $s['type'] !== 'deployed')), ['id', 'name'], 'ams_change_status_to'); ?>
                    <?= render_select('ams_bulk_location', $locations, ['id', 'name'], 'ams_move_to_location'); ?>
                    <?= render_textarea('ams_bulk_note', 'ams_note'); ?>
                    <p class="text-muted tw-text-sm"><?= _l('ams_bulk_help'); ?></p>
                </div>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-default pull-left" onclick="ams_bulk_labels(); return false;"><i class="fa-solid fa-qrcode tw-mr-1"></i><?= _l('ams_print_labels_selected'); ?></a>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button>
                <?php if (staff_can('edit', 'ams_assets') || staff_can('delete', 'ams_assets')) { ?>
                <a href="#" class="btn btn-primary" onclick="ams_bulk_action(this); return false;"><?= _l('confirm'); ?></a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php init_tail(); ?>
<script>
    $(function() {
        // Serial, department, cost and purchase date start hidden (column toggle shows them).
        initDataTable('.table-ams-assets', admin_url + 'asset_management/assets/table', [0], [0], {
            ams_status_id: '[name="ams_status_id"]',
            ams_category_id: '[name="ams_category_id"]'
        }, [1, 'desc']).columns([5, 8, 11, 12]).visible(false, false).columns.adjust();
    });

    function ams_selected_asset_ids() {
        var ids = [];
        $('.table-ams-assets').find('tbody tr').each(function() {
            var checkbox = $(this).find('td').eq(0).find('input');
            if (checkbox.prop('checked') === true) {
                ids.push(checkbox.val());
            }
        });
        return ids;
    }

    function ams_bulk_labels() {
        var ids = ams_selected_asset_ids();
        if (ids.length) {
            window.open(admin_url + 'asset_management/assets/labels?ids=' + ids.join(','), '_blank');
        }
    }

    function ams_bulk_action(el) {
        var massDelete = $('#ams_mass_delete').prop('checked');
        var data = {};

        if (massDelete) {
            if (!confirm_delete()) {
                return;
            }
            data.mass_delete = true;
        } else {
            data.status_id = $('#ams_bulk_status').selectpicker('val') || '';
            data.location_id = $('#ams_bulk_location').selectpicker('val') || '';
            data.note = $('#ams_bulk_note').val();
            if (data.status_id === '' && data.location_id === '') {
                return;
            }
        }

        data.ids = [];
        $('.table-ams-assets').find('tbody tr').each(function() {
            var checkbox = $(this).find('td').eq(0).find('input');
            if (checkbox.prop('checked') === true) {
                data.ids.push(checkbox.val());
            }
        });

        if (!data.ids.length) {
            return;
        }

        $(el).addClass('disabled');
        $.post(admin_url + 'asset_management/assets/bulk_action', data).always(function() {
            window.location.reload();
        });
    }
</script>
</body>
</html>
