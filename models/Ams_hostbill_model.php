<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * HostBill <-> Perfex stock integration.
 *
 * Perfex is the master of physical stock. HostBill orders are pulled (cron,
 * "Sync now", or a webhook asking for one order) and each order line moves
 * through a state machine that reserves, deducts, releases or returns stock
 * through Ams_inventory_model::hb_operation(). The resulting available
 * quantity is pushed back to the mapped HostBill products (editProduct qty).
 *
 * Line states (what Perfex currently holds for the line):
 *   none / released / returned / short / unmapped / ignored -> holds nothing
 *   reserved -> holds a reservation      deducted -> stock was taken
 */
class Ams_hostbill_model extends App_Model
{
    private $clientCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->library(AMS_MODULE_NAME . '/ams_hostbill_client');
        $this->load->model(AMS_MODULE_NAME . '/ams_inventory_model');
    }

    private function t($table)
    {
        return db_prefix() . $table;
    }

    public function api()
    {
        return $this->ams_hostbill_client;
    }

    public function enabled()
    {
        return get_option('ams_hb_enabled') == '1' && $this->api()->is_configured();
    }

    // ─── Connection / products ────────────────────────────────────────────

    /** @return array ['success' => bool, 'message' => string] */
    public function test_connection()
    {
        $r = $this->api()->call('getHostBillversion', [], 'test');
        if ($r['success']) {
            $version = $r['data']['version'] ?? ($r['data']['hostbill_version'] ?? '');

            return ['success' => true, 'message' => _l('ams_hb_test_ok', $version ?: '?')];
        }

        // Some installs restrict getHostBillversion; fall back to a harmless read call.
        $r2 = $this->api()->call('getOrderPages', [], 'test');

        return $r2['success']
            ? ['success' => true, 'message' => _l('ams_hb_test_ok', '?')]
            : ['success' => false, 'message' => _l('ams_hb_test_failed', $r2['error'] ?: $r['error'])];
    }

    /** Refreshes the local product cache from every HostBill order page. */
    public function refresh_products()
    {
        $pages = $this->api()->call('getOrderPages');
        if (! $pages['success']) {
            return ['success' => false, 'message' => $pages['error']];
        }

        $count = 0;
        $now   = date('Y-m-d H:i:s');
        foreach ($this->rows($pages['data']['categories'] ?? []) as $page) {
            $r = $this->api()->call('getProducts', ['id' => (int) $page['id']]);
            if (! $r['success']) {
                continue;
            }
            foreach ($this->rows($r['data']['products'] ?? []) as $product) {
                if (empty($product['id'])) {
                    continue;
                }
                $this->db->query('INSERT INTO ' . $this->t('ams_hb_products') . ' (hb_product_id, name, orderpage_id, orderpage_name, stock_enabled, qty, visible, synced_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE name = VALUES(name), orderpage_id = VALUES(orderpage_id), orderpage_name = VALUES(orderpage_name),
                        stock_enabled = VALUES(stock_enabled), qty = VALUES(qty), visible = VALUES(visible), synced_at = VALUES(synced_at)', [
                    (int) $product['id'],
                    mb_substr((string) ($product['name'] ?? ('#' . $product['id'])), 0, 191),
                    (int) $page['id'],
                    mb_substr((string) ($page['name'] ?? ''), 0, 191),
                    ! empty($product['stock']) ? 1 : 0,
                    isset($product['qty']) && $product['qty'] !== '' ? (float) $product['qty'] : null,
                    isset($product['visible']) ? (int) (bool) $product['visible'] : 1,
                    $now,
                ]);
                $count++;
            }
        }

        return ['success' => true, 'message' => _l('ams_hb_products_refreshed', $count)];
    }

    public function product_options()
    {
        return $this->db->query('SELECT hb_product_id id, CONCAT("#", hb_product_id, " - ", name, IF(orderpage_name IS NULL OR orderpage_name = "", "", CONCAT(" (", orderpage_name, ")"))) name
            FROM ' . $this->t('ams_hb_products') . ' ORDER BY orderpage_name, name')->result_array();
    }

    // ─── Mappings ─────────────────────────────────────────────────────────

    public function get_mapping($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t('ams_hb_product_map'))->row_array();
    }

    public function save_mapping($input, $id = null)
    {
        $itemId     = (int) ($input['item_id'] ?? 0);
        $productId  = (int) ($input['hb_product_id'] ?? 0);
        $multiplier = (float) str_replace(',', '', (string) ($input['qty_multiplier'] ?? '1'));

        $item = $this->db->where('id', $itemId)->get($this->t('ams_items'))->row();
        if (! $item || $item->kind !== 'stock') {
            return ['success' => false, 'message' => _l('ams_hb_select_stock_item')];
        }
        if ($productId <= 0) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_hb_product'))];
        }
        if ($multiplier <= 0) {
            return ['success' => false, 'message' => _l('ams_qty_positive')];
        }
        if (total_rows($this->t('ams_hb_product_map'), ['hb_product_id' => $productId, 'id !=' => (int) $id]) > 0) {
            return ['success' => false, 'message' => _l('ams_hb_product_already_mapped')];
        }

        $data = [
            'item_id'        => $itemId,
            'hb_product_id'  => $productId,
            'qty_multiplier' => $multiplier,
            'location_id'    => (int) ($input['location_id'] ?? 0) ?: null,
            'push_stock'     => ! empty($input['push_stock']) ? 1 : 0,
            'active'         => ! empty($input['active']) ? 1 : 0,
            'push_pending'   => 1,
        ];

        if ($id) {
            $this->db->where('id', (int) $id)->update($this->t('ams_hb_product_map'), $data);
        } else {
            $data['created_by']   = get_staff_user_id();
            $data['date_created'] = date('Y-m-d H:i:s');
            $this->db->insert($this->t('ams_hb_product_map'), $data);
            $id = (int) $this->db->insert_id();
        }

        log_activity('AMS HostBill mapping saved [HostBill product #' . $productId . ' -> item ' . $item->sku . ']');

        return ['success' => true, 'id' => (int) $id, 'message' => _l('ams_hb_mapping_saved')];
    }

    public function delete_mapping($id)
    {
        $map = $this->get_mapping($id);
        if (! $map) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }
        if (total_rows($this->t('ams_hb_order_lines'), ['hb_product_id' => $map['hb_product_id'], 'state' => 'reserved']) > 0) {
            return ['success' => false, 'message' => _l('ams_hb_mapping_has_reservations')];
        }

        $this->db->where('id', (int) $id)->delete($this->t('ams_hb_product_map'));
        log_activity('AMS HostBill mapping deleted [HostBill product #' . $map['hb_product_id'] . ']');

        return ['success' => true, 'message' => _l('deleted', _l('ams_hb_mapping'))];
    }

    // ─── Order sync ───────────────────────────────────────────────────────

    /**
     * Pull recent orders (within the look-back window) and process new or
     * changed ones; retry lines that were short of stock; then push stock.
     */
    public function sync()
    {
        if (! $this->enabled()) {
            return ['success' => false, 'message' => _l('ams_hb_not_enabled')];
        }

        $lock = $this->db->query("SELECT GET_LOCK('ams_hb_sync', 0) l")->row()->l;
        if ((int) $lock !== 1) {
            return ['success' => false, 'message' => _l('ams_hb_sync_running')];
        }

        $cutoff    = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) get_option('ams_hb_lookback_days')) . ' days'));
        $maxPages  = max(1, (int) get_option('ams_hb_max_pages'));
        $processed = 0;
        $seen      = 0;
        $errors    = [];
        $prevFirst = null;

        try {
            for ($page = 0; $page < $maxPages; $page++) {
                $r = $this->api()->call('getOrders', ['list' => 'all', 'page' => $page]);
                if (! $r['success']) {
                    $errors[] = $r['error'];
                    break;
                }

                $orders = $this->rows($r['data']['orders'] ?? []);
                if (! $orders || ($orders[0]['id'] ?? null) === $prevFirst) {
                    break;
                }
                $prevFirst = $orders[0]['id'] ?? null;

                $allOlder = true;
                foreach ($orders as $o) {
                    if (empty($o['id'])) {
                        continue;
                    }
                    $date = $o['date_created'] ?? null;
                    if ($date && $date < $cutoff) {
                        continue;
                    }
                    $allOlder = false;
                    $seen++;

                    if ($this->order_changed($o)) {
                        $result = $this->process_order((int) $o['id'], $o);
                        if ($result['success']) {
                            $processed++;
                        } else {
                            $errors[] = '#' . $o['id'] . ': ' . $result['message'];
                        }
                    }
                }

                $totalPages = (int) ($r['data']['sorter']['totalpages'] ?? 0);
                if ($allOlder || ($totalPages && $page + 1 >= $totalPages)) {
                    break;
                }
            }

            // Lines that were short of stock (or waiting for a mapping) get another chance.
            foreach ($this->db->query('SELECT DISTINCT hb_order_id FROM ' . $this->t('ams_hb_order_lines') . ' WHERE state = "short"')->result_array() as $row) {
                $order = $this->db->where('hb_order_id', $row['hb_order_id'])->get($this->t('ams_hb_orders'))->row_array();
                if ($order) {
                    $this->apply_order_lines($order);
                }
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('ams_hb_sync')");
        }

        $push = $this->push_pending();

        $ok      = ! $errors;
        $summary = _l('ams_hb_sync_summary', [$seen, $processed, $push['pushed']]) . ($errors ? ' ' . _l('ams_hb_sync_errors', count($errors)) : '');
        $this->record_sync_status($ok, $summary, $errors);
        $this->purge_old_logs();

        return ['success' => $ok, 'message' => $summary . ($errors ? ' - ' . e(implode(' | ', array_slice($errors, 0, 3))) : '')];
    }

    private function order_changed($o)
    {
        $row = $this->db->select('status, invoice_status, balance')->where('hb_order_id', (int) $o['id'])->get($this->t('ams_hb_orders'))->row();
        if (! $row) {
            return true;
        }

        return (string) $row->status !== (string) ($o['status'] ?? '')
            || (string) $row->invoice_status !== (string) ($o['invstatus'] ?? $row->invoice_status)
            || (string) $row->balance !== (string) ($o['balance'] ?? $row->balance);
    }

    /**
     * Fetch one order's details, store it with its lines and apply the stock
     * state machine. $summary is the getOrders row when available.
     */
    public function process_order($hbOrderId, $summary = null)
    {
        $r = $this->api()->call('getOrderDetails', ['id' => (int) $hbOrderId]);
        if (! $r['success']) {
            return ['success' => false, 'message' => $r['error']];
        }

        $d = $r['data']['details'] ?? [];
        if (! $d) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }

        $invoiceStatus = $summary['invstatus'] ?? ($d['invstatus'] ?? null);
        if ($invoiceStatus === null && ! empty($d['invoice_id'])) {
            $inv = $this->api()->call('getInvoiceDetails', ['id' => (int) $d['invoice_id']]);
            if ($inv['success']) {
                $invoiceStatus = $inv['data']['invoice']['status'] ?? null;
            }
        }

        $clientId = (int) ($d['client_id'] ?? ($summary['client_id'] ?? 0));
        $client   = $this->client_info($clientId);
        $name     = trim(($summary['firstname'] ?? $client['firstname'] ?? '') . ' ' . ($summary['lastname'] ?? $client['lastname'] ?? ''));
        $now      = date('Y-m-d H:i:s');

        $order = [
            'hb_order_id'      => (int) $hbOrderId,
            'order_number'     => $d['number'] ?? ($summary['number'] ?? null),
            'hb_client_id'     => $clientId ?: null,
            'client_name'      => $name ?: ($client['companyname'] ?? null),
            'client_email'     => $client['email'] ?? null,
            'perfex_client_id' => $this->perfex_client_by_email($client['email'] ?? null),
            'status'           => $d['status'] ?? ($summary['status'] ?? null),
            'invoice_status'   => $invoiceStatus,
            'balance'          => $d['balance'] ?? ($summary['balance'] ?? null),
            'total'            => isset($d['total']) ? (float) $d['total'] : (isset($summary['total']) ? (float) $summary['total'] : null),
            'currency_id'      => isset($summary['currency_id']) ? (int) $summary['currency_id'] : null,
            'order_date'       => $d['date_created'] ?? ($summary['date_created'] ?? null),
            'raw_json'         => json_encode($d, JSON_UNESCAPED_UNICODE),
            'last_synced_at'   => $now,
        ];

        $existing = $this->db->select('id')->where('hb_order_id', (int) $hbOrderId)->get($this->t('ams_hb_orders'))->row();
        if ($existing) {
            $this->db->where('id', $existing->id)->update($this->t('ams_hb_orders'), $order);
        } else {
            $order['date_created'] = $now;
            $this->db->insert($this->t('ams_hb_orders'), $order);
        }

        // Order lines: HostBill "hosting" entries are accounts (product_id, qty).
        foreach ($this->rows($d['hosting'] ?? []) as $line) {
            $accountId = (int) ($line['id'] ?? 0);
            $productId = (int) ($line['product_id'] ?? 0);
            $qty       = isset($line['qty']) ? (float) $line['qty'] : null;

            if ((! $productId || $qty === null) && $accountId) {
                $acc = $this->api()->call('getAccountDetails', ['id' => $accountId]);
                if ($acc['success']) {
                    $details   = $acc['data']['details'] ?? ($acc['data']['account'] ?? []);
                    $productId = $productId ?: (int) ($details['product_id'] ?? 0);
                    $qty       = $qty ?? (isset($details['qty']) ? (float) $details['qty'] : null);
                }
            }

            $key = 'account:' . ($accountId ?: md5(json_encode($line)));
            $this->db->query('INSERT IGNORE INTO ' . $this->t('ams_hb_order_lines') . ' (hb_order_id, line_key, hb_product_id, product_name, hb_qty, state, date_updated)
                VALUES (?, ?, ?, ?, ?, "none", ?)', [(int) $hbOrderId, $key, $productId ?: null, mb_substr((string) ($line['name'] ?? ($line['product_name'] ?? '')), 0, 191), max(1, (float) ($qty ?? 1)), $now]);

            // Product/quantity may only change while the line holds nothing.
            $this->db->query('UPDATE ' . $this->t('ams_hb_order_lines') . ' SET hb_product_id = ?, hb_qty = ?
                WHERE hb_order_id = ? AND line_key = ? AND state NOT IN ("reserved", "deducted")', [$productId ?: null, max(1, (float) ($qty ?? 1)), (int) $hbOrderId, $key]);
        }

        $orderRow = $this->db->where('hb_order_id', (int) $hbOrderId)->get($this->t('ams_hb_orders'))->row_array();
        $this->apply_order_lines($orderRow);

        return ['success' => true, 'message' => _l('ams_hb_order_synced', e($orderRow['order_number'] ?: $hbOrderId))];
    }

    /** Runs the state machine for every line of an order, then refreshes the order flags. */
    public function apply_order_lines($order)
    {
        $lines   = $this->db->where('hb_order_id', (int) $order['hb_order_id'])->get($this->t('ams_hb_order_lines'))->result_array();
        $touched = [];

        foreach ($lines as $line) {
            $touched = array_merge($touched, $this->apply_line((int) $line['id'], $order));
        }

        $mapped    = total_rows($this->t('ams_hb_order_lines'), ['hb_order_id' => (int) $order['hb_order_id'], 'state !=' => 'unmapped']) > 0;
        $attention = total_rows($this->t('ams_hb_order_lines'), ['hb_order_id' => (int) $order['hb_order_id'], 'state' => 'short']) > 0;
        $this->db->where('hb_order_id', (int) $order['hb_order_id'])->update($this->t('ams_hb_orders'), [
            'has_mapped'      => $mapped ? 1 : 0,
            'needs_attention' => $attention ? 1 : 0,
        ]);

        $touched = array_unique($touched);
        foreach ($touched as $itemId) {
            $this->ams_inventory_model->check_stock_alert($itemId);
        }
        $this->mark_push_pending($touched);
    }

    /**
     * One line, one transaction, row-locked: safe against the cron and a
     * webhook processing the same order at the same time. Returns touched item ids.
     */
    private function apply_line($lineId, $order)
    {
        $this->db->trans_begin();

        $line = $this->db->query('SELECT * FROM ' . $this->t('ams_hb_order_lines') . ' WHERE id = ? FOR UPDATE', [$lineId])->row_array();
        $held = in_array($line['state'], ['reserved', 'deducted']) ? $line['state'] : 'none';
        $map  = $line['hb_product_id']
            ? $this->db->where('hb_product_id', (int) $line['hb_product_id'])->where('active', 1)->get($this->t('ams_hb_product_map'))->row_array()
            : null;

        $update = ['date_updated' => date('Y-m-d H:i:s')];

        if ($held === 'none') {
            if (! $map) {
                return $this->finish_line($lineId, $update + ['state' => 'unmapped', 'message' => null], []);
            }
            // Orders placed before the product was mapped are history, not new sales.
            if (! empty($order['order_date']) && $order['order_date'] < $map['date_created']) {
                return $this->finish_line($lineId, $update + ['state' => 'ignored', 'message' => _l('ams_hb_line_before_mapping')], []);
            }

            $location = (int) ($map['location_id'] ?: get_option('ams_hb_sales_location_id'));
            if (! $location) {
                $item     = $this->db->select('default_location_id')->where('id', (int) $map['item_id'])->get($this->t('ams_items'))->row();
                $location = (int) ($item->default_location_id ?? 0);
            }
            if (! $location) {
                return $this->finish_line($lineId, $update + ['state' => 'short', 'message' => _l('ams_hb_no_sales_location')], []);
            }

            $line['item_id']     = (int) $map['item_id'];
            $line['units']       = round((float) $line['hb_qty'] * (float) $map['qty_multiplier'], 2);
            $line['location_id'] = $location;
        }

        $desired = $this->desired_state($order);
        $extra   = [
            'ref_id'    => (int) $order['hb_order_id'],
            'reference' => 'HostBill #' . ($order['order_number'] ?: $order['hb_order_id']),
            'note'      => trim((string) $order['client_name']) ?: null,
        ];

        $ops      = [];
        $newState = $held;

        if ($held === 'none') {
            if ($desired === 'reserved') {
                $ops      = ['reserve'];
                $newState = 'reserved';
            } elseif ($desired === 'deducted') {
                $ops      = ['sale'];
                $newState = 'deducted';
            } elseif ($desired === 'released') {
                $newState = $line['state'] === 'returned' ? 'returned' : 'released';
            } else {
                $newState = 'none';
            }
        } elseif ($held === 'reserved') {
            if ($desired === 'deducted') {
                $ops      = ['release', 'sale'];
                $newState = 'deducted';
            } elseif ($desired === 'released' || $desired === 'none') {
                $ops      = ['release'];
                $newState = $desired === 'released' ? 'released' : 'none';
            }
        } elseif ($held === 'deducted' && $desired === 'released') {
            if (get_option('ams_hb_restock_on_cancel') == '1') {
                $ops      = ['sale_return'];
                $newState = 'returned';
            } else {
                $update['message'] = _l('ams_hb_cancelled_not_returned');
            }
        }

        foreach ($ops as $op) {
            $res = $this->ams_inventory_model->hb_operation($op, $line['item_id'], $line['location_id'], $line['units'], $extra);
            if ($res !== true) {
                $this->db->trans_rollback();
                $this->ams_inventory_model->take_touched_items();

                // Keep what it already held (e.g. the reservation) and flag it for review.
                $this->db->where('id', $lineId)->update($this->t('ams_hb_order_lines'), [
                    'state'        => $held === 'none' ? 'short' : $held,
                    'message'      => mb_substr(strip_tags($res), 0, 255),
                    'date_updated' => date('Y-m-d H:i:s'),
                ]);
                if ($held === 'none') {
                    $this->db->where('hb_order_id', (int) $order['hb_order_id'])->update($this->t('ams_hb_orders'), ['needs_attention' => 1]);
                }

                return [];
            }
        }

        $update += [
            'state'       => $newState,
            'item_id'     => $line['item_id'],
            'units'       => $line['units'],
            'location_id' => $line['location_id'],
        ];
        if ($ops) {
            $update['message'] = null;
        }

        return $this->finish_line($lineId, $update, $ops ? [(int) $line['item_id']] : []);
    }

    private function finish_line($lineId, $update, $touched)
    {
        $this->db->where('id', $lineId)->update($this->t('ams_hb_order_lines'), $update);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return [];
        }
        $this->db->trans_commit();
        $this->ams_inventory_model->take_touched_items();

        return $touched;
    }

    /**
     * What Perfex should hold for an order's lines, from its HostBill status:
     * released (cancelled / fraud / refunded) > deducted (per "deduct on"
     * setting) > reserved (pending or active, not yet deducted) > none.
     */
    public function desired_state($order)
    {
        $status  = strtolower((string) $order['status']);
        $invoice = strtolower((string) $order['invoice_status']);

        if (in_array($status, ['cancelled', 'fraud']) || ($invoice === 'refunded' && get_option('ams_hb_release_on_refund') == '1')) {
            return 'released';
        }

        $deductOn = get_option('ams_hb_deduct_on');
        $paid     = $invoice === 'paid';
        $active   = $status === 'active';
        if (($deductOn === 'paid' && $paid) || ($deductOn === 'active' && $active) || ($deductOn === 'paid_or_active' && ($paid || $active))) {
            return 'deducted';
        }

        if (in_array($status, ['pending', 'active']) && get_option('ams_hb_reserve_on_pending') == '1') {
            return 'reserved';
        }

        return 'none';
    }

    // ─── Stock push-back ──────────────────────────────────────────────────

    public function mark_push_pending($itemIds)
    {
        $itemIds = array_values(array_filter(array_map('intval', (array) $itemIds)));
        if ($itemIds) {
            $this->db->where_in('item_id', $itemIds)->where('push_stock', 1)->update($this->t('ams_hb_product_map'), ['push_pending' => 1]);
        }
    }

    /** Quantity to publish for a mapping: floor((available at its location - buffer) / multiplier). */
    public function publishable_qty($map)
    {
        $location = (int) ($map['location_id'] ?: get_option('ams_hb_sales_location_id'));
        $sql      = 'SELECT IFNULL(SUM(on_hand - reserved), 0) a FROM ' . $this->t('ams_stock_levels') . ' WHERE item_id = ?';
        $params   = [(int) $map['item_id']];
        if ($location) {
            $sql .= ' AND location_id = ?';
            $params[] = $location;
        }
        $available = (float) $this->db->query($sql, $params)->row()->a;
        $available -= max(0, (float) get_option('ams_hb_stock_buffer'));

        return max(0, (int) floor($available / max(0.0001, (float) $map['qty_multiplier'])));
    }

    /** Pushes every pending mapping (optionally only for some items). */
    public function push_pending($itemIds = null)
    {
        $result = ['pushed' => 0, 'failed' => 0];
        if (! $this->enabled() || get_option('ams_hb_push_enabled') != '1') {
            return $result;
        }

        $this->db->where('active', 1)->where('push_stock', 1)->where('push_pending', 1);
        if ($itemIds !== null) {
            $ids = array_values(array_filter(array_map('intval', (array) $itemIds)));
            if (! $ids) {
                return $result;
            }
            $this->db->where_in('item_id', $ids);
        }

        foreach ($this->db->get($this->t('ams_hb_product_map'))->result_array() as $map) {
            $this->push_one($map) ? $result['pushed']++ : $result['failed']++;
        }

        return $result;
    }

    public function push_one($map)
    {
        $qty = $this->publishable_qty($map);
        $r   = $this->api()->call('editProduct', ['id' => (int) $map['hb_product_id'], 'stock_usage' => 'on', 'qty' => $qty], 'push');

        if ($r['success']) {
            $this->db->where('id', (int) $map['id'])->update($this->t('ams_hb_product_map'), [
                'push_pending'    => 0,
                'last_pushed_qty' => $qty,
                'last_pushed_at'  => date('Y-m-d H:i:s'),
                'last_push_error' => null,
            ]);
            $this->db->where('hb_product_id', (int) $map['hb_product_id'])->update($this->t('ams_hb_products'), ['qty' => $qty, 'stock_enabled' => 1]);

            return true;
        }

        $firstFailure = empty($map['last_push_error']);
        $this->db->where('id', (int) $map['id'])->update($this->t('ams_hb_product_map'), ['last_push_error' => mb_substr((string) $r['error'], 0, 1000)]);
        if ($firstFailure) {
            $this->alert('ams_hb_notify_push_failed', ['#' . $map['hb_product_id'], mb_substr((string) $r['error'], 0, 120)]);
        }

        return false;
    }

    /** Reads HostBill's current qty for every mapped product (reconciliation). */
    public function refresh_mapped_quantities()
    {
        $count = 0;
        foreach ($this->db->get($this->t('ams_hb_product_map'))->result_array() as $map) {
            $r = $this->api()->call('getProductDetails', ['id' => (int) $map['hb_product_id']]);
            if (! $r['success']) {
                continue;
            }
            $p = $r['data']['product'] ?? ($r['data']['details'] ?? []);
            $this->db->query('INSERT INTO ' . $this->t('ams_hb_products') . ' (hb_product_id, name, stock_enabled, qty, synced_at) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), stock_enabled = VALUES(stock_enabled), qty = VALUES(qty), synced_at = VALUES(synced_at)', [
                (int) $map['hb_product_id'],
                mb_substr((string) ($p['name'] ?? ('#' . $map['hb_product_id'])), 0, 191),
                ! empty($p['stock']) ? 1 : 0,
                isset($p['qty']) && $p['qty'] !== '' ? (float) $p['qty'] : null,
                date('Y-m-d H:i:s'),
            ]);
            $count++;
        }

        return ['success' => true, 'message' => _l('ams_hb_products_refreshed', $count)];
    }

    // ─── Fulfilment ───────────────────────────────────────────────────────

    public function set_fulfilment($orderId, $status, $note)
    {
        if (! in_array($status, ['pending', 'picked', 'delivered'])) {
            return ['success' => false, 'message' => _l('ams_invalid_request')];
        }

        $this->db->where('id', (int) $orderId)->update($this->t('ams_hb_orders'), [
            'fulfilment'      => $status,
            'fulfilment_note' => trim((string) $note) ?: null,
            'fulfilled_by'    => $status === 'pending' ? null : get_staff_user_id(),
            'fulfilled_at'    => $status === 'pending' ? null : date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => _l('ams_hb_fulfilment_updated')];
    }

    public function get_order($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t('ams_hb_orders'))->row_array();
    }

    public function get_order_lines($hbOrderId)
    {
        return $this->db->query('SELECT l.*, i.sku, i.name item_name, i.unit, IF(pl.id IS NULL, loc.name, CONCAT(pl.name, " › ", loc.name)) location_name
            FROM ' . $this->t('ams_hb_order_lines') . ' l
            LEFT JOIN ' . $this->t('ams_items') . ' i ON i.id = l.item_id
            LEFT JOIN ' . $this->t('ams_locations') . ' loc ON loc.id = l.location_id
            LEFT JOIN ' . $this->t('ams_locations') . ' pl ON pl.id = loc.parent_id
            WHERE l.hb_order_id = ? ORDER BY l.id', [(int) $hbOrderId])->result_array();
    }

    // ─── Internals ────────────────────────────────────────────────────────

    /** HostBill returns lists either as arrays or as id-keyed objects. */
    private function rows($value)
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private function client_info($clientId)
    {
        if (! $clientId) {
            return [];
        }
        if (! isset($this->clientCache[$clientId])) {
            $r                              = $this->api()->call('getClientDetails', ['id' => $clientId]);
            $this->clientCache[$clientId] = $r['success'] ? ($r['data']['client'] ?? []) : [];
        }

        return $this->clientCache[$clientId];
    }

    private function perfex_client_by_email($email)
    {
        if (! $email) {
            return null;
        }
        $row = $this->db->select('userid')->where('email', $email)->limit(1)->get($this->t('contacts'))->row();

        return $row ? (int) $row->userid : null;
    }

    private function record_sync_status($ok, $summary, $errors)
    {
        $wasOk = get_option('ams_hb_last_sync_status') !== 'error';
        update_option('ams_hb_last_sync', date('Y-m-d H:i:s'));
        update_option('ams_hb_last_sync_status', $ok ? 'ok' : 'error');
        update_option('ams_hb_last_sync_message', mb_substr($summary, 0, 500));

        // Alert once when syncing starts failing (not on every failed run).
        if (! $ok && $wasOk) {
            $this->alert('ams_hb_notify_sync_failed', [mb_substr((string) ($errors[0] ?? ''), 0, 150)]);
        }
    }

    private function alert($langKey, $data)
    {
        $users = json_decode((string) get_option('ams_hb_alert_staff'), true);
        $users = is_array($users) ? array_values(array_filter(array_map('intval', $users))) : [];
        foreach ($users as $userId) {
            add_notification([
                'description'     => $langKey,
                'touserid'        => $userId,
                'fromcompany'     => true,
                'link'            => 'asset_management/hostbill/log',
                'additional_data' => serialize($data),
            ]);
            ams_send_email('ams-system-alert', $userId, [
                '{ams_status}'  => _l('ams_hb_settings_tab'),
                '{ams_details}' => _l($langKey, $data),
                '{ams_link}'    => admin_url('asset_management/hostbill/log'),
            ]);
        }
        if ($users) {
            pusher_trigger_notification($users);
        }
    }

    private function purge_old_logs()
    {
        $days = max(1, (int) get_option('ams_hb_log_retention_days'));
        $this->db->where('date_created <', date('Y-m-d H:i:s', strtotime('-' . $days . ' days')))->delete($this->t('ams_hb_sync_log'));
    }
}
