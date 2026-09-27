<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Purchase order PDF body (rendered by Ams_po_pdf, same layout helpers as core invoices).

$dimensions = $pdf->getPageDimensions();
$currency   = get_base_currency();

$right = '<span style="font-weight:bold;font-size:27px;">' . _l('ams_purchase_order') . '</span><br />';
$right .= '<b style="color:#4e4e4e;"># ' . e($po->po_number) . '</b><br />';
$right .= '<span style="color:#4e4e4e;">' . e(_l('ams_po_status_' . $po->status)) . '</span>';

pdf_multi_row(pdf_logo_url(), $right, $pdf, ($dimensions['wk'] / 2) - $dimensions['lm']);
$pdf->ln(10);

$from = '<div style="color:#424242;">' . format_organization_info() . '</div>';

$to = '<b>' . _l('ams_supplier') . ':</b><div style="color:#424242;">' . e($po->supplier_name);
if ($po->supplier_contact) {
    $to .= '<br />' . e($po->supplier_contact);
}
if ($po->supplier_address) {
    $to .= '<br />' . nl2br(e($po->supplier_address));
}
if ($po->supplier_phone) {
    $to .= '<br />' . e($po->supplier_phone);
}
if ($po->supplier_email) {
    $to .= '<br />' . e($po->supplier_email);
}
$to .= '</div><br />' . _l('ams_po_order_date') . ': ' . e(_d($po->order_date));
if ($po->expected_date) {
    $to .= '<br />' . _l('ams_po_expected_date') . ': ' . e(_d($po->expected_date));
}
if ($po->delivery_location_id) {
    $to .= '<br />' . _l('ams_po_deliver_to') . ': ' . e(ams_location_name($po->delivery_location_id));
}

pdf_multi_row($from, $to, $pdf, ($dimensions['wk'] / 2) - $dimensions['lm']);
$pdf->ln(6);

$tbl = '<table width="100%" cellspacing="0" cellpadding="6" border="0">
<thead><tr height="30" bgcolor="' . get_option('pdf_table_heading_color') . '" style="color:' . get_option('pdf_table_heading_text_color') . ';">
<th width="5%" align="center">#</th>
<th width="50%" align="left">' . _l('ams_description') . '</th>
<th width="13%" align="right">' . _l('ams_quantity') . '</th>
<th width="15%" align="right">' . _l('ams_unit_cost') . '</th>
<th width="17%" align="right">' . _l('ams_po_amount') . '</th>
</tr></thead><tbody>';

foreach ($po->lines as $i => $l) {
    $desc = e($l['description']);
    if ($l['line_type'] === 'item' && $l['sku']) {
        $desc .= '<br /><span style="color:#777777;font-size:' . ($font_size - 1) . 'px;">' . e($l['sku']) . '</span>';
    } elseif ($l['category_name']) {
        $desc .= '<br /><span style="color:#777777;font-size:' . ($font_size - 1) . 'px;">' . e($l['category_name']) . '</span>';
    }
    $tbl .= '<tr style="border-bottom:1px solid #ececec;">
        <td align="center">' . ($i + 1) . '</td>
        <td>' . $desc . '</td>
        <td align="right">' . ams_qty($l['qty']) . ($l['unit'] ? ' ' . e($l['unit']) : '') . '</td>
        <td align="right">' . e(app_format_money($l['unit_cost'], $currency)) . '</td>
        <td align="right">' . e(app_format_money($l['qty'] * $l['unit_cost'], $currency)) . '</td>
    </tr>';
}
$tbl .= '</tbody></table>';
$pdf->writeHTML($tbl, true, false, false, false, '');

$total = '<table width="100%" cellpadding="6"><tr><td width="66%" align="right"><b>' . _l('ams_po_total') . '</b></td>
    <td width="34%" align="right"><b>' . e(app_format_money($po->total, $currency)) . '</b></td></tr></table>';
$pdf->writeHTML($total, true, false, false, false, '');

if ($po->notes) {
    $pdf->ln(4);
    $pdf->writeHTMLCell('', '', '', '', '<b>' . _l('ams_notes') . ':</b><br />' . nl2br(e($po->notes)), 0, 1, false, true, 'L', true);
}
if ($po->terms) {
    $pdf->ln(4);
    $pdf->writeHTMLCell('', '', '', '', '<b>' . _l('ams_po_terms') . ':</b><br />' . nl2br(e($po->terms)), 0, 1, false, true, 'L', true);
}
if ($po->approved_by && in_array($po->status, ['approved', 'sent', 'partially_received', 'received'])) {
    $pdf->ln(8);
    $pdf->writeHTMLCell('', '', '', '', _l('ams_po_approved_by') . ': ' . e(get_staff_full_name($po->approved_by)) . ' - ' . e(_dt($po->approved_at)), 0, 1, false, true, 'L', true);
}
