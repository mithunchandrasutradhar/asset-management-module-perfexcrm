<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Quantity-tracked items (accessories, consumables, stock items).
 *
 * All stock changes go through move(), the single writer of the ledger
 * (tblams_stock_movements) and its cached projection (tblams_stock_levels).
 * Stock on hand is always derivable from the ledger; see verify_levels().
 */
class Ams_inventory_model extends App_Model
{
    /** Items moved during the current request (re-checked for stock alerts after commit). */
    private $touched = [];

    /** ID of the last ledger row written by move(). */
    private $lastMovementId = null;

    private function t($table)
    {
        return db_prefix() . $table;
    }

    // ─── Items ────────────────────────────────────────────────────────────

    public function get_item($id)
    {
        $p = db_prefix();
        $this->db->select('i.*, IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name)) as category_name, b.name as brand_name, dl.name as default_location_name', false);
        $this->db->from($p . 'ams_items i');
        $this->db->join($p . 'ams_categories c', 'c.id = i.category_id', 'left');
        $this->db->join($p . 'ams_categories pc', 'pc.id = c.parent_id', 'left');
        $this->db->join($p . 'ams_brands b', 'b.id = i.brand_id', 'left');
        $this->db->join($p . 'ams_locations dl', 'dl.id = i.default_location_id', 'left');
        $this->db->where('i.id', (int) $id);
        $item = $this->db->get()->row();

        if ($item) {
            $item = (object) array_merge((array) $item, $this->totals($id));
        }

        return $item;
    }

    /** on_hand, reserved, available, checked_out, owned, value */
    public function totals($itemId)
    {
        $lv = $this->db->query('SELECT IFNULL(SUM(on_hand),0) on_hand, IFNULL(SUM(reserved),0) reserved FROM ' . $this->t('ams_stock_levels') . ' WHERE item_id = ?', [(int) $itemId])->row();
        $co = $this->db->query('SELECT IFNULL(SUM(qty - returned_qty),0) q FROM ' . $this->t('ams_item_checkouts') . ' WHERE item_id = ? AND status = "open"', [(int) $itemId])->row();
        $cost = $this->db->select('cost')->where('id', (int) $itemId)->get($this->t('ams_items'))->row();

        $onHand = (float) $lv->on_hand;

        return [
            'on_hand'     => $onHand,
            'reserved'    => (float) $lv->reserved,
            'available'   => $onHand - (float) $lv->reserved,
            'checked_out' => (float) $co->q,
            'owned'       => $onHand + (float) $co->q,
            'value'       => $cost && $cost->cost !== null ? round(($onHand + (float) $co->q) * (float) $cost->cost, 2) : null,
        ];
    }

    /** Per-location levels for an item (only rows with stock or reservations). */
    public function levels($itemId)
    {
        return $this->db->query('SELECT sl.location_id, sl.on_hand, sl.reserved, IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name)) location_name
            FROM ' . $this->t('ams_stock_levels') . ' sl
            JOIN ' . $this->t('ams_locations') . ' l ON l.id = sl.location_id
            LEFT JOIN ' . $this->t('ams_locations') . ' pl ON pl.id = l.parent_id
            WHERE sl.item_id = ? AND (sl.on_hand <> 0 OR sl.reserved <> 0)
            ORDER BY location_name', [(int) $itemId])->result_array();
    }

    public function save_item($input, $id = null)
    {
        $kinds = ams_item_kinds();
        $old   = $id ? $this->db->where('id', (int) $id)->get($this->t('ams_items'))->row_array() : null;

        if ($id && ! $old) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }

        $kind = $old ? $old['kind'] : ($input['kind'] ?? '');
        if (! isset($kinds[$kind])) {
            return ['success' => false, 'message' => _l('ams_invalid_request')];
        }

        $data = [
            'name'                => trim($input['name'] ?? ''),
            'category_id'         => (int) ($input['category_id'] ?? 0) ?: null,
            'brand_id'            => (int) ($input['brand_id'] ?? 0) ?: null,
            'model_no'            => trim($input['model_no'] ?? '') ?: null,
            'unit'                => trim($input['unit'] ?? '') ?: 'pcs',
            'cost'                => $this->decimal($input['cost'] ?? ''),
            'sale_price'          => $this->decimal($input['sale_price'] ?? ''),
            'reorder_level'       => (float) $this->decimal($input['reorder_level'] ?? '0'),
            'reorder_qty'         => (float) $this->decimal($input['reorder_qty'] ?? '0'),
            'default_location_id' => (int) ($input['default_location_id'] ?? 0) ?: null,
            'is_sellable'         => ! empty($input['is_sellable']) ? 1 : 0,
            'description'         => trim($input['description'] ?? '') ?: null,
            'notes'               => trim($input['notes'] ?? '') ?: null,
            'active'              => ! empty($input['active']) ? 1 : 0,
        ];

        if ($data['name'] === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_item_name'))];
        }
        if ($data['reorder_level'] < 0 || $data['reorder_qty'] < 0 || ($data['cost'] !== null && $data['cost'] < 0)) {
            return ['success' => false, 'message' => _l('ams_negative_not_allowed')];
        }

        $sku = strtoupper(trim($input['sku'] ?? ''));
        if ($sku !== '' && total_rows($this->t('ams_items'), ['sku' => $sku, 'id !=' => (int) $id]) > 0) {
            return ['success' => false, 'message' => _l('ams_sku_exists', e($sku))];
        }
        if ($id && $sku === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_sku'))];
        }

        $now = date('Y-m-d H:i:s');

        if ($id) {
            $data['sku'] = $sku;
            $changes     = [];
            foreach ($data as $field => $value) {
                if ((string) ($old[$field] ?? '') !== (string) ($value ?? '')) {
                    $changes[$field] = [$old[$field], $value];
                }
            }
            $cfChanged = isset($input['custom_fields']) && handle_custom_fields_post($id, $input['custom_fields']);

            if ($changes) {
                $data['updated_by']   = get_staff_user_id();
                $data['date_updated'] = $now;
                $this->db->where('id', (int) $id)->update($this->t('ams_items'), $data);
                $this->audit($id, 'update', $changes);
                // Reorder level may have changed the stock state.
                $this->check_stock_alert($id);
            }

            return ['success' => true, 'id' => (int) $id, 'message' => $changes || $cfChanged ? _l('updated_successfully', _l($kinds[$kind]['singular'])) : _l('ams_no_changes')];
        }

        $openingQty = (float) $this->decimal($input['opening_qty'] ?? '0');
        $openingLoc = (int) ($input['opening_location_id'] ?? 0) ?: $data['default_location_id'];
        if ($openingQty < 0) {
            return ['success' => false, 'message' => _l('ams_negative_not_allowed')];
        }
        if ($openingQty > 0 && ! $openingLoc) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_location'))];
        }

        $this->db->trans_begin();

        $data['kind']         = $kind;
        $data['sku']          = $sku !== '' ? $sku : $this->generate_sku();
        $data['created_by']   = get_staff_user_id();
        $data['date_created'] = $now;
        $this->db->insert($this->t('ams_items'), $data);
        $newId = (int) $this->db->insert_id();

        if ($openingQty > 0) {
            $moved = $this->move($newId, $openingLoc, $openingQty, 'receive', [
                'unit_cost' => $data['cost'],
                'reference' => _l('ams_opening_stock'),
            ]);
            if ($moved !== true) {
                $this->db->trans_rollback();

                return ['success' => false, 'message' => $moved];
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_db_error')];
        }
        $this->db->trans_commit();

        if (isset($input['custom_fields'])) {
            handle_custom_fields_post($newId, $input['custom_fields']);
        }

        $this->audit($newId, 'create', null);
        $this->check_stock_alert($newId);
        log_activity('AMS item created [ID: ' . $newId . ', SKU: ' . $data['sku'] . ']');

        return ['success' => true, 'id' => $newId, 'message' => _l('added_successfully', _l($kinds[$kind]['singular']))];
    }

    /** Items with stock history cannot be deleted (deactivate them instead). */
    public function delete_item($id)
    {
        $item = $this->db->where('id', (int) $id)->get($this->t('ams_items'))->row();
        if (! $item) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }
        if (total_rows($this->t('ams_stock_movements'), ['item_id' => (int) $id]) > 0) {
            return ['success' => false, 'message' => _l('ams_item_has_movements')];
        }

        $this->db->where('id', (int) $id)->delete($this->t('ams_items'));
        $this->db->where('item_id', (int) $id)->delete($this->t('ams_stock_levels'));
        $this->db->where('relid', (int) $id)->where('fieldto', 'ams_items')->delete($this->t('customfieldsvalues'));
        $this->audit($id, 'delete', ['sku' => [$item->sku, null], 'name' => [$item->name, null]]);
        log_activity('AMS item deleted [ID: ' . $id . ', SKU: ' . $item->sku . ']');

        return ['success' => true, 'message' => _l('deleted', _l('ams_item'))];
    }

    private function generate_sku()
    {
        $prefix = strtoupper(trim(get_option('ams_item_sku_prefix'))) ?: 'ITM';
        $key    = 'SKU:' . $prefix;
        $digits = max(1, (int) get_option('ams_asset_tag_digits'));

        $row = $this->db->query('SELECT next_number FROM ' . $this->t('ams_tag_sequences') . ' WHERE code = ? FOR UPDATE', [$key])->row();
        if (! $row) {
            $this->db->insert($this->t('ams_tag_sequences'), ['code' => $key, 'next_number' => 1]);
            $next = 1;
        } else {
            $next = (int) $row->next_number;
        }

        do {
            $sku = $prefix . '-' . str_pad((string) $next, $digits, '0', STR_PAD_LEFT);
            $next++;
        } while (total_rows($this->t('ams_items'), ['sku' => $sku]) > 0);

        $this->db->where('code', $key)->update($this->t('ams_tag_sequences'), ['next_number' => $next]);

        return $sku;
    }

    // ─── Stock operations ─────────────────────────────────────────────────

    public function receive($itemId, $input)
    {
        $item = $this->active_item($itemId);
        $qty  = (float) $this->decimal($input['qty'] ?? '');
        $loc  = (int) ($input['location_id'] ?? 0);

        if (is_string($item)) {
            return $this->fail($item);
        }
        if ($qty <= 0) {
            return $this->fail(_l('ams_qty_positive'));
        }
        if (! $this->location_exists($loc)) {
            return $this->fail(_l('ams_field_required', _l('ams_location')));
        }

        $unitCost = $this->decimal($input['unit_cost'] ?? '');

        return $this->transaction(function () use ($itemId, $loc, $qty, $input, $unitCost, $item) {
            $moved = $this->move($itemId, $loc, $qty, 'receive', [
                'unit_cost'   => $unitCost ?? $item->cost,
                'supplier_id' => (int) ($input['supplier_id'] ?? 0) ?: null,
                'reference'   => trim($input['reference'] ?? '') ?: null,
                'note'        => trim($input['note'] ?? '') ?: null,
            ]);
            if ($moved === true && $unitCost !== null && ! empty($input['update_cost'])) {
                $this->db->where('id', (int) $itemId)->update($this->t('ams_items'), ['cost' => $unitCost]);
            }

            return $moved;
        }, _l('ams_received_success', ams_qty($qty) . ' ' . $item->unit));
    }

    /** Consumables / stock items given to a Perfex staff member or department (not returned). */
    public function issue($itemId, $input)
    {
        $item = $this->active_item($itemId);
        if (is_string($item)) {
            return $this->fail($item);
        }

        $qty = (float) $this->decimal($input['qty'] ?? '');
        $loc = (int) ($input['location_id'] ?? 0);
        [$type, $to, $dept, $err] = $this->assignee($input);

        if ($qty <= 0) {
            return $this->fail(_l('ams_qty_positive'));
        }
        if (! $this->location_exists($loc)) {
            return $this->fail(_l('ams_field_required', _l('ams_location')));
        }
        if ($err) {
            return $this->fail($err);
        }

        return $this->transaction(function () use ($itemId, $loc, $qty, $type, $to, $dept, $input, $item) {
            return $this->move($itemId, $loc, -$qty, 'issue', [
                'unit_cost'     => $item->cost,
                'assigned_type' => $type,
                'assigned_id'   => $to,
                'department_id' => $dept,
                'reference'     => trim($input['reference'] ?? '') ?: null,
                'note'          => trim($input['note'] ?? '') ?: null,
            ]);
        }, _l('ams_issued_success', [ams_qty($qty) . ' ' . $item->unit, e(ams_assignee_text($type, $to))]));
    }

    /** Accessories: out to a Perfex staff member / department, expected back later. */
    public function checkout($itemId, $input)
    {
        $item = $this->active_item($itemId);
        if (is_string($item)) {
            return $this->fail($item);
        }
        if ($item->kind !== 'accessory') {
            return $this->fail(_l('ams_invalid_request'));
        }

        $qty = (float) $this->decimal($input['qty'] ?? '');
        $loc = (int) ($input['location_id'] ?? 0);
        [$type, $to, $dept, $err] = $this->assignee($input);

        if ($qty <= 0) {
            return $this->fail(_l('ams_qty_positive'));
        }
        if (! $this->location_exists($loc)) {
            return $this->fail(_l('ams_field_required', _l('ams_location')));
        }
        if ($err) {
            return $this->fail($err);
        }

        $expected   = ! empty($input['expected_return']) ? to_sql_date($input['expected_return']) : null;
        $note       = trim($input['note'] ?? '') ?: null;
        $checkoutId = null;

        $result = $this->transaction(function () use ($itemId, $loc, $qty, $type, $to, $dept, $expected, $note, $item, &$checkoutId) {
            $this->db->insert($this->t('ams_item_checkouts'), [
                'item_id'         => (int) $itemId,
                'location_id'     => $loc,
                'qty'             => $qty,
                'assigned_type'   => $type,
                'assigned_id'     => $to,
                'department_id'   => $dept,
                'expected_return' => $expected,
                'status'          => 'open',
                'note'            => $note,
                'staff_id'        => get_staff_user_id(),
                'date_created'    => date('Y-m-d H:i:s'),
            ]);
            $checkoutId = (int) $this->db->insert_id();

            return $this->move($itemId, $loc, -$qty, 'checkout', [
                'ref_type'      => 'checkout',
                'ref_id'        => $checkoutId,
                'unit_cost'     => $item->cost,
                'assigned_type' => $type,
                'assigned_id'   => $to,
                'department_id' => $dept,
                'note'          => $note,
            ]);
        }, _l('ams_checked_out_success', e(ams_assignee_text($type, $to))));

        if ($result['success']) {
            $result['checkout_id'] = $checkoutId;
            hooks()->do_action('ams_after_item_checkout', ['checkout_id' => $checkoutId, 'item_id' => (int) $itemId, 'assign_type' => $type, 'assign_id' => $to, 'qty' => $qty]);
        }

        return $result;
    }

    /** Return (all or part of) an accessory checkout to a location. */
    public function checkin($checkoutId, $input)
    {
        $co = $this->get_checkout($checkoutId);
        if (! $co || $co->status !== 'open') {
            return $this->fail(_l('ams_not_checked_out'));
        }

        $outstanding = (float) $co->qty - (float) $co->returned_qty;
        $qty         = (float) $this->decimal($input['qty'] ?? '') ?: $outstanding;
        $loc         = (int) ($input['location_id'] ?? 0) ?: (int) $co->location_id;

        if ($qty <= 0 || $qty > $outstanding) {
            return $this->fail(_l('ams_return_qty_invalid', ams_qty($outstanding)));
        }
        if (! $this->location_exists($loc)) {
            return $this->fail(_l('ams_field_required', _l('ams_location')));
        }

        $closed = abs($outstanding - $qty) < 0.0001;

        $result = $this->transaction(function () use ($co, $qty, $loc, $closed, $input) {
            $this->db->where('id', (int) $co->id)->update($this->t('ams_item_checkouts'), [
                'returned_qty' => (float) $co->returned_qty + $qty,
                'status'       => $closed ? 'closed' : 'open',
                'date_closed'  => $closed ? date('Y-m-d H:i:s') : null,
            ]);

            return $this->move($co->item_id, $loc, $qty, 'return', [
                'ref_type'      => 'checkout',
                'ref_id'        => (int) $co->id,
                'assigned_type' => $co->assigned_type,
                'assigned_id'   => $co->assigned_id,
                'department_id' => $co->department_id,
                'note'          => trim($input['note'] ?? '') ?: null,
            ]);
        }, _l('ams_returned_success', ams_qty($qty)));

        if ($result['success'] && $closed) {
            hooks()->do_action('ams_after_item_checkout_closed', (int) $co->id);
        }

        return $result;
    }

    public function transfer($itemId, $input)
    {
        $item = $this->active_item($itemId);
        if (is_string($item)) {
            return $this->fail($item);
        }

        $qty  = (float) $this->decimal($input['qty'] ?? '');
        $from = (int) ($input['from_location_id'] ?? 0);
        $to   = (int) ($input['to_location_id'] ?? 0);

        if ($qty <= 0) {
            return $this->fail(_l('ams_qty_positive'));
        }
        if (! $this->location_exists($from) || ! $this->location_exists($to) || $from === $to) {
            return $this->fail(_l('ams_transfer_locations_invalid'));
        }

        $note = trim($input['note'] ?? '') ?: null;

        return $this->transaction(function () use ($itemId, $from, $to, $qty, $note, $item) {
            $out = $this->move($itemId, $from, -$qty, 'transfer_out', ['unit_cost' => $item->cost, 'note' => $note]);
            if ($out !== true) {
                return $out;
            }
            $outId = $this->lastMovementId;
            $this->db->where('id', $outId)->update($this->t('ams_stock_movements'), ['ref_type' => 'transfer', 'ref_id' => $outId]);

            return $this->move($itemId, $to, $qty, 'transfer_in', ['ref_type' => 'transfer', 'ref_id' => $outId, 'unit_cost' => $item->cost, 'note' => $note]);
        }, _l('ams_transferred_success', ams_qty($qty) . ' ' . $item->unit));
    }

    /** Signed correction at one location with a reason (found, damaged, lost, ...). */
    public function adjust($itemId, $input)
    {
        $item = $this->active_item($itemId, true);
        if (is_string($item)) {
            return $this->fail($item);
        }

        $qty    = (float) $this->decimal($input['qty'] ?? '');
        $loc    = (int) ($input['location_id'] ?? 0);
        $reason = $input['reason'] ?? '';
        $note   = trim($input['note'] ?? '');

        if ($qty == 0) {
            return $this->fail(_l('ams_adjust_qty_nonzero'));
        }
        if (! $this->location_exists($loc)) {
            return $this->fail(_l('ams_field_required', _l('ams_location')));
        }
        if (! in_array($reason, array_column(ams_adjust_reason_options(), 'id'))) {
            return $this->fail(_l('ams_field_required', _l('ams_reason')));
        }
        if ($note === '') {
            return $this->fail(_l('ams_field_required', _l('ams_note')));
        }

        return $this->transaction(function () use ($itemId, $loc, $qty, $reason, $note, $item) {
            return $this->move($itemId, $loc, $qty, 'adjust', ['unit_cost' => $item->cost, 'reason' => $reason, 'note' => $note]);
        }, _l('ams_adjusted_success'));
    }

    /**
     * HostBill order operations, called by Ams_hostbill_model inside ITS transaction.
     * reserve (+reserved) | release (-reserved) | sale (-on hand) | sale_return (+on hand).
     * Returns true or an error message.
     */
    public function hb_operation($op, $itemId, $locationId, $units, $extra = [])
    {
        $units = abs((float) $units);
        $extra = array_merge(['ref_type' => 'hostbill_order'], $extra);

        switch ($op) {
            case 'reserve':
                return $this->move($itemId, $locationId, $units, 'reserve', $extra, 'reserved');
            case 'release':
                return $this->move($itemId, $locationId, -$units, 'release', $extra, 'reserved');
            case 'sale':
                return $this->move($itemId, $locationId, -$units, 'sale', $extra);
            case 'sale_return':
                return $this->move($itemId, $locationId, $units, 'sale_return', $extra);
        }

        return _l('ams_invalid_request');
    }

    /** Items touched by hb_operation() calls, for the caller to alert/push after commit. */
    public function take_touched_items()
    {
        return $this->touched_items();
    }

    /**
     * The only writer of stock. Must run inside a transaction.
     * Locks the level row, refuses to take more than is available (on hand
     * minus reserved) when negative stock is blocked, writes the ledger row and
     * updates the cached level. $column is 'on_hand' or 'reserved' (HostBill
     * reservations). Returns true or an error message.
     */
    private function move($itemId, $locationId, $qty, $type, $extra = [], $column = 'on_hand')
    {
        $levels = $this->t('ams_stock_levels');
        $column = $column === 'reserved' ? 'reserved' : 'on_hand';

        $this->db->query('INSERT IGNORE INTO ' . $levels . ' (item_id, location_id, on_hand, reserved) VALUES (?, ?, 0, 0)', [(int) $itemId, (int) $locationId]);
        $level = $this->db->query('SELECT on_hand, reserved FROM ' . $levels . ' WHERE item_id = ? AND location_id = ? FOR UPDATE', [(int) $itemId, (int) $locationId])->row();

        // Taking stock = lowering on hand, or raising reserved; both reduce "available".
        $reducesAvailable = ($column === 'on_hand' && $qty < 0) || ($column === 'reserved' && $qty > 0);
        if ($reducesAvailable && get_option('ams_block_negative_stock') == '1') {
            $available = (float) $level->on_hand - (float) $level->reserved;
            if ($available - abs($qty) < -0.0001) {
                return _l('ams_insufficient_stock', [ams_qty($available), e(ams_location_name($locationId))]);
            }
        }
        if ($column === 'reserved' && $qty < 0 && (float) $level->reserved + $qty < -0.0001) {
            return _l('ams_release_exceeds_reserved');
        }

        $this->db->insert($this->t('ams_stock_movements'), array_merge([
            'item_id'      => (int) $itemId,
            'location_id'  => (int) $locationId,
            'qty'          => $qty,
            'type'         => $type,
            'staff_id'     => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ], $extra));
        $this->lastMovementId = (int) $this->db->insert_id();
        $this->touched[]      = (int) $itemId;

        $this->db->query('UPDATE ' . $levels . ' SET ' . $column . ' = ' . $column . ' + ? WHERE item_id = ? AND location_id = ?', [$qty, (int) $itemId, (int) $locationId]);

        hooks()->do_action('ams_after_stock_movement', ['item_id' => (int) $itemId, 'location_id' => (int) $locationId, 'qty' => $qty, 'type' => $type]);

        return true;
    }

    // ─── Low / out-of-stock alerts ────────────────────────────────────────

    /**
     * stock_alert: 0 = ok, 1 = low notified, 2 = out notified. Notifies the
     * configured staff (Perfex in-app notification) only when the state gets
     * worse, and resets when stock recovers, so there is one alert per dip.
     */
    public function check_stock_alert($itemId)
    {
        $item = $this->db->where('id', (int) $itemId)->get($this->t('ams_items'))->row();
        if (! $item || ! $item->active) {
            return;
        }

        $totals = $this->totals($itemId);
        $state  = ams_stock_state($totals['available'], $item->reorder_level);
        // Alerts are opt-in per item: only items with a reorder level are watched.
        $level  = (float) $item->reorder_level > 0 ? ['ok' => 0, 'low' => 1, 'out' => 2][$state] : 0;

        // A newly created item that has never had stock is not "running out".
        if ($level > 0 && total_rows($this->t('ams_stock_movements'), ['item_id' => (int) $itemId]) === 0) {
            return;
        }

        if ($level > (int) $item->stock_alert) {
            $users = ams_low_stock_recipients();
            foreach ($users as $userId) {
                ams_send_email('ams-low-stock', $userId, [
                    '{ams_item}'    => $item->sku . ' - ' . $item->name,
                    '{ams_status}'  => _l($level === 2 ? 'ams_stock_state_out' : 'ams_stock_state_low'),
                    '{ams_details}' => ams_qty($totals['available']) . ' ' . $item->unit,
                    '{ams_link}'    => admin_url('asset_management/inventory/view/' . (int) $itemId),
                ]);
                add_notification([
                    'description'     => $level === 2 ? 'ams_notify_out_of_stock' : 'ams_notify_low_stock',
                    'touserid'        => $userId,
                    'fromcompany'     => true,
                    'link'            => 'asset_management/inventory/view/' . (int) $itemId,
                    'additional_data' => serialize([$item->sku . ' - ' . $item->name, ams_qty($totals['available']) . ' ' . $item->unit]),
                ]);
            }
            if ($users) {
                pusher_trigger_notification($users);
            }
        }

        if ($level !== (int) $item->stock_alert) {
            $this->db->where('id', (int) $itemId)->update($this->t('ams_items'), ['stock_alert' => $level]);
        }
    }

    // ─── Checkouts ────────────────────────────────────────────────────────

    public function get_checkout($id)
    {
        return $this->db->select('co.*, i.kind, i.name as item_name, i.sku, i.unit')
            ->from($this->t('ams_item_checkouts') . ' co')
            ->join($this->t('ams_items') . ' i', 'i.id = co.item_id')
            ->where('co.id', (int) $id)
            ->get()->row();
    }

    // ─── Integrity ────────────────────────────────────────────────────────

    /**
     * Compares cached levels with the ledger. Returns mismatches; with $repair
     * the cache is rebuilt from the ledger (the ledger is the source of truth).
     */
    public function verify_levels($repair = false)
    {
        // on_hand = all movements except reservations; reserved = reserve/release movements.
        $mismatches = $this->db->query('SELECT m.item_id, m.location_id, m.total, m.reserved_total,
                IFNULL(sl.on_hand, 0) cached, IFNULL(sl.reserved, 0) cached_reserved
            FROM (SELECT item_id, location_id,
                    SUM(IF(type IN ("reserve", "release"), 0, qty)) total,
                    SUM(IF(type IN ("reserve", "release"), qty, 0)) reserved_total
                  FROM ' . $this->t('ams_stock_movements') . ' GROUP BY item_id, location_id) m
            LEFT JOIN ' . $this->t('ams_stock_levels') . ' sl ON sl.item_id = m.item_id AND sl.location_id = m.location_id
            WHERE ABS(m.total - IFNULL(sl.on_hand, 0)) > 0.0001 OR ABS(m.reserved_total - IFNULL(sl.reserved, 0)) > 0.0001')->result_array();

        if ($repair) {
            foreach ($mismatches as $m) {
                $this->db->query('INSERT INTO ' . $this->t('ams_stock_levels') . ' (item_id, location_id, on_hand, reserved) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE on_hand = VALUES(on_hand), reserved = VALUES(reserved)', [$m['item_id'], $m['location_id'], $m['total'], $m['reserved_total']]);
            }
        }

        return $mismatches;
    }

    // ─── Dashboard ────────────────────────────────────────────────────────

    public function dashboard_stats()
    {
        $kinds = ams_item_viewable_kinds();
        $stats = ['value' => 0, 'low' => 0, 'out' => 0, 'accessories_out' => 0, 'alerts' => [], 'kinds' => $kinds];

        if (! $kinds) {
            return $stats;
        }

        $in   = "'" . implode("','", $kinds) . "'";
        $rows = $this->db->query('SELECT i.id, i.sku, i.name, i.kind, i.unit, i.cost, i.reorder_level,
                IFNULL(lv.on_hand, 0) on_hand, IFNULL(lv.reserved, 0) reserved, IFNULL(co.out_qty, 0) out_qty
            FROM ' . $this->t('ams_items') . ' i
            LEFT JOIN (SELECT item_id, SUM(on_hand) on_hand, SUM(reserved) reserved FROM ' . $this->t('ams_stock_levels') . ' GROUP BY item_id) lv ON lv.item_id = i.id
            LEFT JOIN (SELECT item_id, SUM(qty - returned_qty) out_qty FROM ' . $this->t('ams_item_checkouts') . ' WHERE status = "open" GROUP BY item_id) co ON co.item_id = i.id
            WHERE i.active = 1 AND i.kind IN (' . $in . ')')->result_array();

        foreach ($rows as $r) {
            $available = (float) $r['on_hand'] - (float) $r['reserved'];
            $state     = ams_stock_state($available, $r['reorder_level']);
            $stats['value'] += ((float) $r['on_hand'] + (float) $r['out_qty']) * (float) $r['cost'];
            $stats['accessories_out'] += $r['kind'] === 'accessory' ? (float) $r['out_qty'] : 0;

            if ($state !== 'ok' && (float) $r['reorder_level'] > 0) {
                $stats[$state]++;
                $stats['alerts'][] = $r + ['available' => $available, 'state' => $state];
            }
        }

        usort($stats['alerts'], fn ($a, $b) => [$a['state'] !== 'out', $a['available']] <=> [$b['state'] !== 'out', $b['available']]);
        $stats['alerts'] = array_slice($stats['alerts'], 0, 10);

        return $stats;
    }

    // ─── Internals ────────────────────────────────────────────────────────

    private function audit($itemId, $action, $changes)
    {
        $this->db->insert($this->t('ams_audit_log'), [
            'rel_type'     => 'ams_items',
            'rel_id'       => (int) $itemId,
            'action'       => $action,
            'changes'      => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
            'staff_id'     => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Item object, or an error message string. */
    private function active_item($itemId, $allowInactive = false)
    {
        $item = $this->db->where('id', (int) $itemId)->get($this->t('ams_items'))->row();
        if (! $item) {
            return _l('ams_not_found');
        }
        if (! $item->active && ! $allowInactive) {
            return _l('ams_item_inactive');
        }

        return $item;
    }

    /** [type, id, department_id, error] for a staff / department recipient (Perfex tables). */
    private function assignee($input)
    {
        $type = $input['assign_type'] ?? '';
        $id   = (int) ($input['assign_id_' . $type] ?? 0);

        if ($type === 'staff' && $id && total_rows(db_prefix() . 'staff', ['staffid' => $id, 'active' => 1]) > 0) {
            $dept = (int) ($input['department_id'] ?? 0) ?: ams_staff_primary_department($id);

            return ['staff', $id, $dept, null];
        }
        if ($type === 'department' && $id && total_rows(db_prefix() . 'departments', ['departmentid' => $id]) > 0) {
            return ['department', $id, $id, null];
        }

        return [null, null, null, _l('ams_select_recipient')];
    }

    private function location_exists($id)
    {
        return $id > 0 && total_rows($this->t('ams_locations'), ['id' => (int) $id]) > 0;
    }

    private function decimal($value)
    {
        $value = str_replace([',', ' '], '', trim((string) $value));

        return $value !== '' && is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function fail($message)
    {
        return ['success' => false, 'message' => $message];
    }

    /** Runs $work (returning true | error) in a transaction and re-checks stock alerts. */
    private function transaction($work, $successMessage)
    {
        $this->db->trans_begin();
        $result = $work();

        if ($result !== true || $this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return $this->fail($result === true ? _l('ams_db_error') : $result);
        }
        $this->db->trans_commit();

        // Re-check alerts for every item touched in this request, then let
        // listeners (HostBill stock push) react to the committed change.
        $items = $this->touched_items();
        foreach ($items as $itemId) {
            $this->check_stock_alert($itemId);
        }
        hooks()->do_action('ams_stock_committed', $items);

        return ['success' => true, 'message' => $successMessage];
    }

    private function touched_items()
    {
        $items         = array_unique($this->touched);
        $this->touched = [];

        return $items;
    }
}
