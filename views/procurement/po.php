<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$isNew  = ! $po;
$posted = $posted ?? [];
$saved  = $isNew ? [] : (array) $po;
$v      = fn ($f, $d = '') => array_key_exists($f, $posted) ? $posted[$f] : ($saved[$f] ?? $d);
$dt     = fn ($f, $d = '') => array_key_exists($f, $posted) ? $posted[$f] : (isset($saved[$f]) && $saved[$f] ? _d($saved[$f]) : $d);

// Lines: re-posted > saved > from request > one empty row.
$lines = $posted['lines'] ?? ($po ? $po->lines : []);
if (! $lines && $request) {
    $lines = [[
        'line_type'   => $request->type === 'asset' ? 'asset' : 'item',
        'description' => $request->subject,
        'category_id' => $request->category_id,
        'item_id'     => $request->item_id,
        'qty'         => $request->qty,
        'unit_cost'   => '',
    ]];
}
if (! $lines) {
    $lines = [['line_type' => 'asset', 'description' => '', 'qty' => 1, 'unit_cost' => '']];
}

$opts = function ($rows, $selected) {
    $html = '<option value=""></option>';
    foreach ($rows as $r) {
        $html .= '<option value="' . (int) $r['id'] . '"' . ((string) $r['id'] === (string) $selected ? ' selected' : '') . (isset($r['cost']) ? ' data-cost="' . e($r['cost']) . '"' : '') . '>' . e($r['name']) . '</option>';
    }

    return $html;
};
?>
<div id="wrapper">
    <div class="content">
        <?= form_open(admin_url('asset_management/procurement/po' . ($isNew ? '' : '/' . $po->id)), ['id' => 'ams-po-form', 'autocomplete' => 'off']); ?>
        <input type="hidden" name="request_id" value="<?= $request ? (int) $request->id : ''; ?>">
        <div class="row">
            <div class="col-md-12"><h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= e($title); ?></h4></div>
            <?php if ($request) { ?>
            <div class="col-md-12"><div class="alert alert-info"><?= _l('ams_po_from_request', e($request->request_no . ' - ' . $request->subject)); ?></div></div>
            <?php } ?>
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-4"><?= render_select('supplier_id', $suppliers, ['id', 'name'], '<small class="req text-danger">* </small>' . _l('ams_supplier'), $v('supplier_id')); ?></div>
                            <div class="col-md-2"><?= render_date_input('order_date', 'ams_po_order_date', $dt('order_date', _d(date('Y-m-d')))); ?></div>
                            <div class="col-md-2"><?= render_date_input('expected_date', 'ams_po_expected_date', $dt('expected_date')); ?></div>
                            <div class="col-md-4"><?= render_select('delivery_location_id', $locations, ['id', 'name'], 'ams_po_deliver_to', $v('delivery_location_id')); ?></div>
                        </div>

                        <h4 class="tw-font-semibold tw-text-lg tw-mt-4"><?= _l('ams_po_lines'); ?></h4>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="ams-po-lines">
                                <thead>
                                    <tr>
                                        <th style="width:11%"><?= _l('ams_po_line_type'); ?></th>
                                        <th style="width:25%"><?= _l('ams_description'); ?></th>
                                        <th style="width:30%"><?= _l('ams_po_line_what'); ?></th>
                                        <th style="width:10%"><?= _l('ams_quantity'); ?></th>
                                        <th style="width:12%"><?= _l('ams_unit_cost'); ?></th>
                                        <th style="width:9%" class="text-right"><?= _l('ams_po_amount'); ?></th>
                                        <th style="width:3%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_values($lines) as $i => $l) {
                                        $type = ($l['line_type'] ?? 'asset') === 'item' ? 'item' : 'asset'; ?>
                                    <tr class="ams-po-line">
                                        <td>
                                            <select name="lines[<?= $i; ?>][line_type]" class="form-control ams-line-type">
                                                <option value="asset"<?= $type === 'asset' ? ' selected' : ''; ?>><?= _l('ams_po_type_asset'); ?></option>
                                                <option value="item"<?= $type === 'item' ? ' selected' : ''; ?>><?= _l('ams_po_type_item'); ?></option>
                                            </select>
                                        </td>
                                        <td><input type="text" name="lines[<?= $i; ?>][description]" class="form-control" value="<?= e($l['description'] ?? ''); ?>"></td>
                                        <td>
                                            <div class="ams-line-asset<?= $type === 'asset' ? '' : ' hide'; ?>">
                                                <select name="lines[<?= $i; ?>][category_id]" class="form-control tw-mb-1" title="<?= _l('ams_category'); ?>"><?= $opts($categories, $l['category_id'] ?? ''); ?></select>
                                                <div class="tw-flex tw-gap-1">
                                                    <select name="lines[<?= $i; ?>][brand_id]" class="form-control" title="<?= _l('ams_brand'); ?>"><?= $opts($brands, $l['brand_id'] ?? ''); ?></select>
                                                    <select name="lines[<?= $i; ?>][model_id]" class="form-control" title="<?= _l('ams_model'); ?>"><?= $opts($models, $l['model_id'] ?? ''); ?></select>
                                                </div>
                                            </div>
                                            <div class="ams-line-item<?= $type === 'item' ? '' : ' hide'; ?>">
                                                <select name="lines[<?= $i; ?>][item_id]" class="form-control ams-line-itemsel"><?= $opts($items, $l['item_id'] ?? ''); ?></select>
                                            </div>
                                        </td>
                                        <td><input type="number" step="0.01" min="0" name="lines[<?= $i; ?>][qty]" class="form-control ams-line-qty" value="<?= e(isset($l['qty']) && $l['qty'] !== '' ? ams_qty($l['qty']) : ''); ?>"></td>
                                        <td><input type="number" step="0.01" min="0" name="lines[<?= $i; ?>][unit_cost]" class="form-control ams-line-cost" value="<?= e($l['unit_cost'] ?? ''); ?>"></td>
                                        <td class="text-right ams-line-amount"></td>
                                        <td><a href="#" class="text-danger ams-line-remove"><i class="fa-regular fa-trash-can"></i></a></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5"><a href="#" id="ams-line-add"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('ams_po_add_line'); ?></a></td>
                                        <td class="text-right tw-font-semibold" id="ams-po-total"></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p class="text-muted tw-text-sm"><?= _l('ams_po_lines_help'); ?></p>

                        <div class="row">
                            <div class="col-md-6"><?= render_textarea('notes', 'ams_notes', $v('notes')); ?></div>
                            <div class="col-md-6"><?= render_textarea('terms', 'ams_po_terms', $v('terms', $isNew ? get_option('ams_po_terms') : '')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="btn-bottom-toolbar text-right">
                    <a href="<?= $isNew ? admin_url('asset_management/procurement') : admin_url('asset_management/procurement/view/' . $po->id); ?>" class="btn btn-default"><?= _l('cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?= _l('ams_po_save_draft'); ?></button>
                </div>
            </div>
        </div>
        <?= form_close(); ?>
        <div class="btn-bottom-pusher"></div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        var fmt = function(n) { return (Math.round(n * 100) / 100).toFixed(2); };

        function renumber() {
            $('#ams-po-lines tbody tr').each(function(i) {
                $(this).find('[name^="lines["]').each(function() {
                    this.name = this.name.replace(/^lines\[\d+\]/, 'lines[' + i + ']');
                });
            });
        }

        function recalc() {
            var total = 0;
            $('#ams-po-lines tbody tr').each(function() {
                var amount = (parseFloat($(this).find('.ams-line-qty').val()) || 0) * (parseFloat($(this).find('.ams-line-cost').val()) || 0);
                total += amount;
                $(this).find('.ams-line-amount').text(fmt(amount));
            });
            $('#ams-po-total').text(fmt(total));
        }

        $('#ams-po-lines').on('change', '.ams-line-type', function() {
            var tr = $(this).closest('tr');
            tr.find('.ams-line-asset').toggleClass('hide', this.value !== 'asset');
            tr.find('.ams-line-item').toggleClass('hide', this.value !== 'item');
        }).on('change', '.ams-line-itemsel', function() {
            var tr = $(this).closest('tr'), opt = $(this).find('option:selected');
            if (!tr.find('[name$="[description]"]').val()) {
                tr.find('[name$="[description]"]').val(opt.text());
            }
            if (!tr.find('.ams-line-cost').val() && opt.data('cost')) {
                tr.find('.ams-line-cost').val(opt.data('cost'));
            }
            recalc();
        }).on('input', '.ams-line-qty, .ams-line-cost', recalc)
          .on('click', '.ams-line-remove', function(e) {
            e.preventDefault();
            if ($('#ams-po-lines tbody tr').length > 1) {
                $(this).closest('tr').remove();
                renumber();
                recalc();
            }
        });

        $('#ams-line-add').on('click', function(e) {
            e.preventDefault();
            var row = $('#ams-po-lines tbody tr:last').clone();
            row.find('input').val('');
            row.find('select').each(function() { this.selectedIndex = 0; });
            row.find('.ams-line-asset').removeClass('hide');
            row.find('.ams-line-item').addClass('hide');
            row.find('.ams-line-qty').val(1);
            $('#ams-po-lines tbody').append(row);
            renumber();
            recalc();
        });

        appValidateForm($('#ams-po-form'), { supplier_id: 'required' });
        recalc();
    });
</script>
</body>
</html>
