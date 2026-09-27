<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Purchase orders → approval → sent to supplier → goods receipt.
 * Receiving an "asset" line creates one asset per unit (serials, supplier,
 * PO number, invoice, cost, warranty); an "item" line receives stock through
 * the inventory ledger. Asset requests can be turned into a draft PO.
 */
class Ams_procurement_model extends App_Model
{
    private function t($table)
    {
        return db_prefix() . $table;
    }

    public function get($id)
    {
        $po = $this->db->select('po.*, sp.name supplier_name, sp.email supplier_email, sp.phone supplier_phone, sp.address supplier_address, sp.contact_person supplier_contact')
            ->from($this->t('ams_purchase_orders') . ' po')
            ->join($this->t('ams_suppliers') . ' sp', 'sp.id = po.supplier_id', 'left')
            ->where('po.id', (int) $id)->get()->row();

        if ($po) {
            $po->lines = $this->db->query('SELECT l.*, i.sku, i.name item_name, i.unit, IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) category_name
                FROM ' . $this->t('ams_po_lines') . ' l
                LEFT JOIN ' . $this->t('ams_items') . ' i ON i.id = l.item_id
                LEFT JOIN ' . $this->t('ams_categories') . ' c ON c.id = l.category_id
                LEFT JOIN ' . $this->t('ams_categories') . ' pc ON pc.id = c.parent_id
                WHERE l.po_id = ? ORDER BY l.sort_order, l.id', [(int) $id])->result_array();
        }

        return $po;
    }

    public function editable($po)
    {
        return $po && in_array($po->status, ['draft', 'rejected']);
    }

    /** Create / update a PO (header + lines). Only drafts (or rejected POs) can be edited. */
    public function save($input, $id = null)
    {
        $po = $id ? $this->get($id) : null;
        if ($id && ! $this->editable($po)) {
            return ['success' => false, 'message' => _l('ams_po_not_editable')];
        }

        $supplierId = (int) ($input['supplier_id'] ?? 0);
        if (total_rows($this->t('ams_suppliers'), ['id' => $supplierId]) === 0) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_supplier'))];
        }

        $lines = [];
        foreach ((array) ($input['lines'] ?? []) as $i => $l) {
            $desc = trim((string) ($l['description'] ?? ''));
            $qty  = (float) ($l['qty'] ?? 0);
            $cost = (float) str_replace(',', '', (string) ($l['unit_cost'] ?? 0));
            $type = ($l['line_type'] ?? '') === 'item' ? 'item' : 'asset';

            if ($desc === '' && ! $qty) {
                continue; // empty row
            }
            if ($desc === '' || $qty <= 0 || $cost < 0) {
                return ['success' => false, 'message' => _l('ams_po_line_invalid', $i + 1)];
            }
            if ($type === 'asset' && floor($qty) != $qty) {
                return ['success' => false, 'message' => _l('ams_po_asset_qty_whole', $i + 1)];
            }
            if ($type === 'item' && total_rows($this->t('ams_items'), ['id' => (int) ($l['item_id'] ?? 0)]) === 0) {
                return ['success' => false, 'message' => _l('ams_po_item_required', $i + 1)];
            }
            if ($type === 'asset' && total_rows($this->t('ams_categories'), ['id' => (int) ($l['category_id'] ?? 0)]) === 0) {
                return ['success' => false, 'message' => _l('ams_po_category_required', $i + 1)];
            }

            $lines[] = [
                'line_type'   => $type,
                'description' => mb_substr($desc, 0, 255),
                'category_id' => $type === 'asset' ? (int) $l['category_id'] : null,
                'brand_id'    => $type === 'asset' ? ((int) ($l['brand_id'] ?? 0) ?: null) : null,
                'model_id'    => $type === 'asset' ? ((int) ($l['model_id'] ?? 0) ?: null) : null,
                'item_id'     => $type === 'item' ? (int) $l['item_id'] : null,
                'qty'         => $qty,
                'unit_cost'   => round($cost, 2),
                'sort_order'  => count($lines),
            ];
        }
        if (! $lines) {
            return ['success' => false, 'message' => _l('ams_po_no_lines')];
        }

        $data = [
            'supplier_id'          => $supplierId,
            'order_date'           => ! empty($input['order_date']) ? to_sql_date($input['order_date']) : date('Y-m-d'),
            'expected_date'        => ! empty($input['expected_date']) ? to_sql_date($input['expected_date']) : null,
            'delivery_location_id' => (int) ($input['delivery_location_id'] ?? 0) ?: null,
            'notes'                => trim((string) ($input['notes'] ?? '')) ?: null,
            'terms'                => trim((string) ($input['terms'] ?? '')) ?: null,
            'total'                => round(array_sum(array_map(fn ($l) => $l['qty'] * $l['unit_cost'], $lines)), 2),
        ];

        $this->db->trans_begin();

        if ($id) {
            $data['status'] = 'draft'; // editing a rejected PO sends it back to draft
            $this->db->where('id', (int) $id)->update($this->t('ams_purchase_orders'), $data);
            $this->db->where('po_id', (int) $id)->delete($this->t('ams_po_lines'));
        } else {
            $data += [
                'status'       => 'draft',
                'request_id'   => (int) ($input['request_id'] ?? 0) ?: null,
                'created_by'   => get_staff_user_id() ?: null,
                'date_created' => date('Y-m-d H:i:s'),
            ];
            $this->db->insert($this->t('ams_purchase_orders'), $data);
            $id = (int) $this->db->insert_id();
            $number = strtoupper(trim((string) get_option('ams_po_prefix')) ?: 'PO') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $this->db->where('id', $id)->update($this->t('ams_purchase_orders'), ['po_number' => $number]);
        }

        foreach ($lines as $line) {
            $this->db->insert($this->t('ams_po_lines'), $line + ['po_id' => (int) $id]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_db_error')];
        }
        $this->db->trans_commit();

        return ['success' => true, 'id' => (int) $id, 'message' => _l('ams_po_saved')];
    }

    public function submit($id)
    {
        $po = $this->get($id);
        if (! $po || $po->status !== 'draft') {
            return ['success' => false, 'message' => _l('ams_po_wrong_status')];
        }

        $needsApproval = get_option('ams_po_require_approval') == '1';
        $this->db->where('id', (int) $id)->update($this->t('ams_purchase_orders'), [
            'status'       => $needsApproval ? 'pending_approval' : 'approved',
            'submitted_by' => get_staff_user_id() ?: null,
            'submitted_at' => date('Y-m-d H:i:s'),
            'approved_by'  => $needsApproval ? null : (get_staff_user_id() ?: null),
            'approved_at'  => $needsApproval ? null : date('Y-m-d H:i:s'),
        ]);

        if ($needsApproval) {
            ams_notify(ams_staff_with_capability('ams_procurement', 'approve_po'), 'ams_notify_po_pending', [$po->po_number, $po->supplier_name], 'asset_management/procurement/view/' . (int) $id);
        }

        return ['success' => true, 'message' => _l($needsApproval ? 'ams_po_submitted' : 'ams_po_approved')];
    }

    public function decide($id, $approve, $note)
    {
        $po = $this->get($id);
        if (! $po || $po->status !== 'pending_approval') {
            return ['success' => false, 'message' => _l('ams_po_wrong_status')];
        }
        if ((int) $po->submitted_by === (int) get_staff_user_id() && ! is_admin()) {
            return ['success' => false, 'message' => _l('ams_po_no_self_approval')];
        }
        $note = trim((string) $note) ?: null;
        if (! $approve && ! $note) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_req_reject_reason'))];
        }

        $this->db->where('id', (int) $id)->update($this->t('ams_purchase_orders'), [
            'status'        => $approve ? 'approved' : 'rejected',
            'approved_by'   => get_staff_user_id() ?: null,
            'approved_at'   => date('Y-m-d H:i:s'),
            'decision_note' => $note,
        ]);

        if ($po->submitted_by) {
            ams_notify([$po->submitted_by], 'ams_notify_po_decided', [$po->po_number, _l('ams_po_status_' . ($approve ? 'approved' : 'rejected'))], 'asset_management/procurement/view/' . (int) $id);
        }
        log_activity('AMS PO ' . $po->po_number . ' ' . ($approve ? 'approved' : 'rejected'));

        return ['success' => true, 'message' => _l($approve ? 'ams_po_approved' : 'ams_po_rejected')];
    }

    /** Mark as sent; optionally email the PDF to the supplier. */
    public function send($id, $email)
    {
        $po = $this->get($id);
        if (! $po || ! in_array($po->status, ['approved', 'sent'])) {
            return ['success' => false, 'message' => _l('ams_po_wrong_status')];
        }

        $mailed = false;
        if ($email) {
            if (! filter_var($po->supplier_email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => _l('ams_po_supplier_no_email')];
            }
            // PDF content (not a file path), as Perfex core does, so it survives the email queue.
            $mailed = ams_send_email_to('ams-purchase-order', $po->supplier_email, [
                '{ams_po_number}' => $po->po_number,
                '{ams_supplier}'  => $po->supplier_contact ?: $po->supplier_name,
                '{ams_details}'   => (string) $po->notes,
            ], [['attachment' => $this->pdf($po)->Output('', 'S'), 'filename' => $po->po_number . '.pdf', 'type' => 'application/pdf']]);
            if (! $mailed) {
                return ['success' => false, 'message' => _l('ams_po_email_failed')];
            }
        }

        $this->db->where('id', (int) $id)->update($this->t('ams_purchase_orders'), ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')]);
        log_activity('AMS PO ' . $po->po_number . ' sent' . ($mailed ? ' by email to ' . $po->supplier_email : ''));

        return ['success' => true, 'message' => _l($mailed ? 'ams_po_emailed' : 'ams_po_marked_sent')];
    }

    public function cancel($id)
    {
        $po = $this->get($id);
        if (! $po || in_array($po->status, ['received', 'cancelled', 'partially_received'])) {
            return ['success' => false, 'message' => _l('ams_po_wrong_status')];
        }
        $this->db->where('id', (int) $id)->update($this->t('ams_purchase_orders'), ['status' => 'cancelled']);

        return ['success' => true, 'message' => _l('ams_po_cancelled')];
    }

    public function delete($id)
    {
        $po = $this->get($id);
        if (! $po || ! in_array($po->status, ['draft', 'rejected', 'cancelled'])) {
            return ['success' => false, 'message' => _l('ams_po_cannot_delete')];
        }
        $this->db->where('id', (int) $id)->delete($this->t('ams_purchase_orders'));
        $this->db->where('po_id', (int) $id)->delete($this->t('ams_po_lines'));

        return ['success' => true, 'message' => _l('deleted', _l('ams_purchase_order'))];
    }

    /**
     * Goods receipt. $input: receipt_date, location_id, invoice_no, note,
     * qty[po_line_id], serials[po_line_id] (one per line), warranty_months[po_line_id].
     * Everything is validated first; then assets / stock receipts are created.
     */
    public function receive($id, $input)
    {
        $po = $this->get($id);
        if (! $po || ! in_array($po->status, ['approved', 'sent', 'partially_received'])) {
            return ['success' => false, 'message' => _l('ams_po_wrong_status')];
        }

        $location = (int) ($input['location_id'] ?? 0);
        if (total_rows($this->t('ams_locations'), ['id' => $location]) === 0) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_location'))];
        }
        $date    = ! empty($input['receipt_date']) ? to_sql_date($input['receipt_date']) : date('Y-m-d');
        $invoice = trim((string) ($input['invoice_no'] ?? '')) ?: null;

        // ── validate
        $plan = [];
        foreach ($po->lines as $line) {
            $qty = (float) ($input['qty'][$line['id']] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $remaining = (float) $line['qty'] - (float) $line['received_qty'];
            if ($qty > $remaining + 0.0001) {
                return ['success' => false, 'message' => _l('ams_po_receive_too_much', [e($line['description']), ams_qty($remaining)])];
            }
            $serials = [];
            if ($line['line_type'] === 'asset') {
                if (floor($qty) != $qty) {
                    return ['success' => false, 'message' => _l('ams_po_asset_qty_whole', e($line['description']))];
                }
                $serials = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) ($input['serials'][$line['id']] ?? '')))));
                if ($serials && count($serials) !== (int) $qty) {
                    return ['success' => false, 'message' => _l('ams_po_serial_count', [e($line['description']), (int) $qty, count($serials)])];
                }
            }
            $plan[] = ['line' => $line, 'qty' => $qty, 'serials' => $serials, 'warranty' => max(0, (int) ($input['warranty_months'][$line['id']] ?? 0))];
        }
        if (! $plan) {
            return ['success' => false, 'message' => _l('ams_po_nothing_to_receive')];
        }

        // ── execute
        $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
        $this->load->model(AMS_MODULE_NAME . '/ams_inventory_model');
        $status = ams_get_status_by_key('in_store');

        $this->db->insert($this->t('ams_goods_receipts'), [
            'po_id'        => (int) $id,
            'receipt_date' => $date,
            'location_id'  => $location,
            'invoice_no'   => $invoice,
            'note'         => trim((string) ($input['note'] ?? '')) ?: null,
            'staff_id'     => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ]);
        $receiptId = (int) $this->db->insert_id();
        $created   = 0;
        $errors    = [];

        foreach ($plan as $p) {
            $line     = $p['line'];
            $received = 0;
            $assetIds = [];

            if ($line['line_type'] === 'asset') {
                for ($n = 0; $n < (int) $p['qty']; $n++) {
                    $r = $this->ams_assets_model->add([
                        'name'            => $line['description'],
                        'serial_no'       => $p['serials'][$n] ?? '',
                        'category_id'     => $line['category_id'],
                        'brand_id'        => $line['brand_id'],
                        'model_id'        => $line['model_id'],
                        'asset_condition' => 'new',
                        'source'          => 'purchase',
                        'supplier_id'     => $po->supplier_id,
                        'purchased_by'    => $po->created_by,
                        'purchase_date'   => _d($date),
                        'purchase_cost'   => $line['unit_cost'],
                        'invoice_no'      => $invoice,
                        'order_no'        => $po->po_number,
                        'warranty_start'  => $p['warranty'] ? _d($date) : '',
                        'warranty_end'    => $p['warranty'] ? _d(date('Y-m-d', strtotime($date . ' +' . $p['warranty'] . ' months'))) : '',
                        'status_id'       => $status['id'] ?? 0,
                        'location_id'     => $location,
                        'initial_note'    => _l('ams_po_received_note', $po->po_number),
                    ]);
                    if ($r['success']) {
                        $assetIds[] = (int) $r['id'];
                        $received++;
                        $created++;
                    } else {
                        $errors[] = e($line['description']) . ': ' . $r['message'];
                        break;
                    }
                }
            } else {
                $r = $this->ams_inventory_model->receive($line['item_id'], [
                    'qty'         => $p['qty'],
                    'location_id' => $location,
                    'unit_cost'   => $line['unit_cost'],
                    'supplier_id' => $po->supplier_id,
                    'reference'   => trim($po->po_number . ' ' . $invoice),
                    'note'        => _l('ams_po_received_note', $po->po_number),
                ]);
                if ($r['success']) {
                    $received = $p['qty'];
                } else {
                    $errors[] = e($line['description']) . ': ' . $r['message'];
                }
            }

            if ($received > 0) {
                $this->db->where('id', (int) $line['id'])->set('received_qty', 'received_qty + ' . (float) $received, false)->update($this->t('ams_po_lines'));
                $this->db->insert($this->t('ams_goods_receipt_lines'), [
                    'receipt_id' => $receiptId,
                    'po_line_id' => (int) $line['id'],
                    'qty'        => $received,
                    'asset_ids'  => $assetIds ? implode(',', $assetIds) : null,
                ]);
            }
        }

        // PO status from what is left to receive.
        $left = (float) $this->db->query('SELECT IFNULL(SUM(qty - received_qty), 0) l FROM ' . $this->t('ams_po_lines') . ' WHERE po_id = ?', [(int) $id])->row()->l;
        $got  = (float) $this->db->query('SELECT IFNULL(SUM(received_qty), 0) g FROM ' . $this->t('ams_po_lines') . ' WHERE po_id = ?', [(int) $id])->row()->g;
        $this->db->where('id', (int) $id)->update($this->t('ams_purchase_orders'), ['status' => $left <= 0.0001 ? 'received' : ($got > 0 ? 'partially_received' : $po->status)]);

        log_activity('AMS PO ' . $po->po_number . ' received (receipt #' . $receiptId . ', ' . $created . ' asset(s) created)');

        if ($errors) {
            return ['success' => false, 'message' => _l('ams_po_received_with_errors') . '<br>' . implode('<br>', $errors)];
        }

        return ['success' => true, 'message' => _l('ams_po_received', $created)];
    }

    public function receipts($poId)
    {
        return $this->db->query('SELECT r.*, IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name)) location_name
            FROM ' . $this->t('ams_goods_receipts') . ' r
            LEFT JOIN ' . $this->t('ams_locations') . ' l ON l.id = r.location_id
            LEFT JOIN ' . $this->t('ams_locations') . ' pl ON pl.id = l.parent_id
            WHERE r.po_id = ? ORDER BY r.id', [(int) $poId])->result_array();
    }

    public function receipt_lines($receiptId)
    {
        return $this->db->query('SELECT rl.*, pol.description FROM ' . $this->t('ams_goods_receipt_lines') . ' rl
            JOIN ' . $this->t('ams_po_lines') . ' pol ON pol.id = rl.po_line_id WHERE rl.receipt_id = ?', [(int) $receiptId])->result_array();
    }

    // ─── PDF ──────────────────────────────────────────────────────────────

    public function pdf($po)
    {
        include_once module_libs_path(AMS_MODULE_NAME) . 'pdf/Ams_po_pdf.php';

        return (new Ams_po_pdf($po))->prepare();
    }
}
