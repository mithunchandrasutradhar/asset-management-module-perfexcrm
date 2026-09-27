<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $staffOptions = array_map(fn ($s) => ['id' => $s['staffid'], 'name' => $s['firstname'] . ' ' . $s['lastname']], ams_staff_options()); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l($cfg['plural']); ?></h4>

                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'ams_setup')) { ?>
                        <a href="#" onclick="ams_setup_new(); return false;" class="btn btn-primary">
                            <i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_new_record', _l($cfg['singular'])); ?>
                        </a>
                        <?php } ?>
                        <div class="btn-group">
                            <?php foreach (ams_setup_entities() as $key => $e) { ?>
                            <a href="<?= admin_url('asset_management/setup/index/' . $key); ?>" class="btn btn-default<?= $key === $entity ? ' active' : ''; ?>"><?= _l($e['plural']); ?></a>
                            <?php } ?>
                        </div>
                        <div id="vueApp" class="tw-inline pull-right tw-ml-0 sm:tw-ml-1.5 rtl:tw-mr-1.5 rtl:tw-ml-0">
                            <app-filters id="<?= $table->id(); ?>"
                                view="<?= $table->viewName(); ?>"
                                :saved-filters="<?= $table->filtersJs(); ?>"
                                :available-rules="<?= $table->rulesJs(); ?>">
                            </app-filters>
                        </div>
                    </div>
                </div>

                <?php if ($entity === 'statuses') { ?>
                <div class="alert alert-info"><?= _l('ams_statuses_help'); ?></div>
                <?php } ?>

                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable(ams_setup_table_headings($entity), 'ams-setup', [], [
                            'data-last-order-identifier' => 'ams-setup-' . $entity,
                            'data-default-order'         => get_table_last_order('ams-setup-' . $entity),
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ams_setup_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url('asset_management/setup/save/' . $entity), ['id' => 'ams-setup-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span class="edit-title"><?= _l('ams_edit_record', _l($cfg['singular'])); ?></span>
                    <span class="add-title"><?= _l('ams_new_record', _l($cfg['singular'])); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" value="">
                <?php foreach ($cfg['fields'] as $field => $def) {
                    $label = (! empty($def['required']) ? '<small class="req text-danger">* </small>' : '') . _l($def['label']);
                    $help  = ! empty($def['help']) ? '<p class="text-muted tw-text-sm -tw-mt-2">' . _l($def['help']) . '</p>' : '';

                    switch ($def['type']) {
                        case 'textarea':
                            echo render_textarea($field, $label);
                            break;
                        case 'select':
                            echo render_select($field, call_user_func($def['options']), ['id', 'name'], $label, '', [], [], '', '', empty($def['required']));
                            break;
                        case 'staff':
                            echo render_select($field, $staffOptions, ['id', 'name'], $label);
                            break;
                        case 'color':
                            echo render_color_picker($field, $label);
                            break;
                        case 'number':
                            echo render_input($field, $label, '', 'number', ! empty($def['step']) ? ['step' => $def['step'], 'min' => 0] : []);
                            break;
                        case 'checkbox':
                            echo '<div class="checkbox checkbox-primary"><input type="checkbox" name="' . $field . '" id="' . $field . '" value="1" data-default="' . (int) ($def['default'] ?? 0) . '"><label for="' . $field . '">' . $label . '</label></div>';
                            break;
                        default:
                            echo render_input($field, $label);
                    }
                    echo $help;
                } ?>
                <p class="text-muted tw-text-sm ams-system-note hide"><?= _l('ams_status_system_note'); ?></p>
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
    var AMS_SETUP_ENTITY = <?= json_encode($entity); ?>;
    var AMS_SETUP_FIELDS = <?= json_encode(array_map(fn ($d) => $d['type'], $cfg['fields'])); ?>;
    var AMS_SETUP_REQUIRED = <?= json_encode(array_keys(array_filter($cfg['fields'], fn ($d) => ! empty($d['required'])))); ?>;

    $(function() {
        initDataTable('.table-ams-setup', admin_url + 'asset_management/setup/table/' + AMS_SETUP_ENTITY, [], [], {}, [0, 'asc']);

        var rules = {};
        AMS_SETUP_REQUIRED.forEach(function(f) { rules[f] = 'required'; });
        appValidateForm($('#ams-setup-form'), rules, ams_setup_submit);

        $('#ams_setup_modal').on('hidden.bs.modal', function() {
            ams_setup_fill({});
        });
    });

    function ams_setup_fill(data) {
        var form = $('#ams-setup-form');
        form.find('input[name="id"]').val(data.id || '');

        $.each(AMS_SETUP_FIELDS, function(field, type) {
            var el = form.find('[name="' + field + '"]');
            var value = data[field] !== undefined && data[field] !== null ? data[field] : '';

            if (type === 'checkbox') {
                el.prop('checked', data.id ? value == 1 : el.data('default') == 1);
            } else if (type === 'select' || type === 'staff') {
                el.selectpicker('val', value == 0 && field === 'parent_id' ? '' : value);
            } else if (type === 'color') {
                el.val(value).trigger('change');
                el.closest('.colorpicker-input').colorpicker && el.closest('.colorpicker-input').colorpicker('setValue', value || '#64748b');
            } else {
                el.val(value);
            }
        });

        // System statuses: type and active are locked (used by check-out / check-in).
        var isSystem = AMS_SETUP_ENTITY === 'statuses' && !!data.system_key;
        form.find('[name="type"]').prop('disabled', isSystem).selectpicker('refresh');
        form.find('[name="active"]').prop('disabled', isSystem);
        $('.ams-system-note').toggleClass('hide', !isSystem);
    }

    function ams_setup_new() {
        ams_setup_fill({});
        $('#ams_setup_modal .edit-title').addClass('hide');
        $('#ams_setup_modal .add-title').removeClass('hide');
        $('#ams_setup_modal').modal('show');
    }

    function ams_setup_edit(id) {
        $.get(admin_url + 'asset_management/setup/get/' + AMS_SETUP_ENTITY + '/' + id, function(data) {
            data = typeof data === 'string' ? JSON.parse(data) : data;
            ams_setup_fill(data);
            $('#ams_setup_modal .add-title').addClass('hide');
            $('#ams_setup_modal .edit-title').removeClass('hide');
            $('#ams_setup_modal').modal('show');
        });
    }

    function ams_setup_submit(form) {
        var $form = $(form);
        // Disabled fields are not serialized; send the locked values anyway (server ignores them for system rows).
        var disabled = $form.find(':disabled').prop('disabled', false);
        var data = $form.serialize();
        disabled.prop('disabled', true);

        $.post(form.action, data).done(function(response) {
            response = typeof response === 'string' ? JSON.parse(response) : response;
            if (response.success) {
                alert_float('success', response.message);
                $('.table-ams-setup').DataTable().ajax.reload(null, false);
                $('#ams_setup_modal').modal('hide');
            } else {
                alert_float('danger', response.message);
            }
        });

        return false;
    }
</script>
</body>
</html>
