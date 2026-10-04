<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * HostBill inventory (read-only).
 *
 * HostBill stays the only owner of its product stock: Perfex reads the
 * products of every order page (getOrderPages + getProducts), keeps a local
 * copy in tblams_hb_products, shows it, and raises low / out-of-stock alerts.
 * Nothing is ever written to HostBill.
 *
 * Everything is set in Assets → Setup → HostBill Settings: refresh interval,
 * which products are shown, default low-stock level and whether products may
 * override it, which alerts are sent (low, out of stock, sync failure, email)
 * and to whom.
 *
 * Alert state per product: ok → low → out. An alert is sent when the state gets
 * worse (ok→low, ok/low→out), once; it resets when the stock is back above the level.
 */
class Ams_hostbill_model extends App_Model
{
    private const SEVERITY = ['ok' => 0, 'low' => 1, 'out' => 2];

    public function __construct()
    {
        parent::__construct();
        $this->load->library(AMS_MODULE_NAME . '/ams_hostbill_client');
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

    // ─── Refresh ──────────────────────────────────────────────────────────

    /**
     * Reads every product from HostBill, updates the local copy, then checks the
     * low-stock levels. Products no longer returned by HostBill are flagged removed.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function refresh()
    {
        if (! $this->enabled()) {
            return ['success' => false, 'message' => _l(get_option('ams_hb_enabled') == '1' ? 'ams_hb_not_configured' : 'ams_hb_not_enabled')];
        }

        // One refresh at a time (cron and "Refresh now"), so the same alert is never sent twice.
        $lock = 'ams_hb_refresh_' . md5((string) $this->db->database);
        if ((int) $this->db->query('SELECT GET_LOCK(?, 0) l', [$lock])->row()->l !== 1) {
            return ['success' => false, 'message' => _l('ams_hb_refresh_running')];
        }
        try {
            return $this->refresh_locked();
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private function refresh_locked()
    {
        $pages = $this->api()->call('getOrderPages');
        if (! $pages['success']) {
            return $this->finish(false, $pages['error']);
        }

        $count  = 0;
        $failed = 0;
        $now    = date('Y-m-d H:i:s');
        foreach ($this->rows($pages['data']['categories'] ?? []) as $page) {
            $r = $this->api()->call('getProducts', ['id' => (int) $page['id']]);
            if (! $r['success']) {
                $failed++;
                continue;
            }
            foreach ($this->rows($r['data']['products'] ?? []) as $product) {
                if (empty($product['id'])) {
                    continue;
                }
                $this->db->query('INSERT INTO ' . $this->t('ams_hb_products') . ' (hb_product_id, name, orderpage_id, orderpage_name, stock_enabled, qty, visible, synced_at, is_removed)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
                    ON DUPLICATE KEY UPDATE name = VALUES(name), orderpage_id = VALUES(orderpage_id), orderpage_name = VALUES(orderpage_name),
                        stock_enabled = VALUES(stock_enabled), qty = VALUES(qty), visible = VALUES(visible), synced_at = VALUES(synced_at), is_removed = 0', [
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

        // Only a complete, non-empty read may flag products as removed from HostBill
        // (an empty answer is more likely an API permission problem than an empty shop).
        if (! $failed && $count > 0) {
            $this->db->where('synced_at <', $now)->or_where('synced_at IS NULL', null, false)->update($this->t('ams_hb_products'), ['is_removed' => 1]);
        }

        $alerts = $this->check_levels();

        return $this->finish(true, _l('ams_hb_refreshed', [$count, $alerts]) . ($failed ? ' ' . _l('ams_hb_pages_failed', $failed) : ''));
    }

    /** Records the result, raises / clears the sync-failure alert and prunes the log. */
    private function finish($success, $message)
    {
        $previous = get_option('ams_hb_last_sync_status');
        update_option('ams_hb_last_sync', date('Y-m-d H:i:s'));
        update_option('ams_hb_last_sync_status', $success ? 'ok' : 'error');
        update_option('ams_hb_last_sync_message', mb_substr(strip_tags(html_entity_decode((string) $message)), 0, 250));

        if (get_option('ams_hb_alert_sync_fail') == '1' && ($success ? $previous === 'error' : $previous !== 'error')) {
            $details = $success ? _l('ams_hb_sync_recovered') : _l('ams_hb_sync_failed_details', strip_tags(html_entity_decode((string) $message)));
            foreach ($this->recipients() as $staffId) {
                $this->notify($staffId, $success ? 'ams_notify_hb_sync_ok' : 'ams_notify_hb_sync_failed', [], 'asset_management/hostbill/log');
                if (get_option('ams_hb_alert_email') == '1') {
                    ams_send_email('ams-system-alert', $staffId, [
                        '{ams_status}'  => _l($success ? 'ams_hb_sync_ok_title' : 'ams_hb_sync_failed_title'),
                        '{ams_details}' => $details,
                        '{ams_link}'    => admin_url('asset_management/hostbill/log'),
                    ]);
                }
            }
            $this->push_bell();
        }

        $this->prune_log();

        return ['success' => $success, 'message' => $message];
    }

    // ─── Low-stock levels & alerts ────────────────────────────────────────

    /** The level used for a product: its own override (if allowed) or the default. */
    public function level_for($row)
    {
        $row = (array) $row;
        if (get_option('ams_hb_allow_override') == '1' && $row['low_level'] !== null && $row['low_level'] !== '') {
            return (float) $row['low_level'];
        }

        return (float) get_option('ams_hb_default_low_level');
    }

    /** not_tracked | out | low | in_stock */
    public function state_for($row)
    {
        $row = (array) $row;
        if (! (int) $row['stock_enabled'] || $row['qty'] === null) {
            return 'not_tracked';
        }
        if ((float) $row['qty'] <= 0) {
            return 'out';
        }

        return (float) $row['qty'] <= $this->level_for($row) ? 'low' : 'in_stock';
    }

    /** Compares every watched product with its level and alerts when it got worse. Returns the number of alerts. */
    public function check_levels()
    {
        $alerts = 0;
        $rows   = $this->db->where('is_removed', 0)->get($this->t('ams_hb_products'))->result_array();

        foreach ($rows as $row) {
            if (! $this->in_scope($row)) {
                continue;
            }
            $state = $this->state_for($row);
            $now   = in_array($state, ['low', 'out'], true) ? $state : 'ok';
            $was   = self::SEVERITY[$row['alert_state']] ?? 0;

            if (self::SEVERITY[$now] > $was && $this->alert_enabled($now)) {
                $this->send_stock_alert($row, $now);
                $alerts++;
            }
            if ($now !== ($row['alert_state'] ?: 'ok')) {
                $this->db->where('hb_product_id', (int) $row['hb_product_id'])->update($this->t('ams_hb_products'), [
                    'alert_state' => $now,
                    'alerted_at'  => self::SEVERITY[$now] > $was ? date('Y-m-d H:i:s') : $row['alerted_at'],
                ]);
            }
        }
        if ($alerts) {
            $this->push_bell();
        }

        return $alerts;
    }

    private function alert_enabled($state)
    {
        return get_option($state === 'out' ? 'ams_hb_alert_out' : 'ams_hb_alert_low') == '1';
    }

    private function send_stock_alert($row, $state)
    {
        $label   = $row['name'] . ($row['orderpage_name'] ? ' (' . $row['orderpage_name'] . ')' : '');
        $qty     = ams_qty($row['qty']);
        $link    = 'asset_management/hostbill';
        foreach ($this->recipients() as $staffId) {
            $this->notify($staffId, $state === 'out' ? 'ams_notify_hb_out' : 'ams_notify_hb_low', [$label, $qty], $link);
            if (get_option('ams_hb_alert_email') == '1') {
                ams_send_email('ams-low-stock', $staffId, [
                    '{ams_item}'    => 'HostBill: ' . $label,
                    '{ams_status}'  => _l($state === 'out' ? 'ams_hb_state_out' : 'ams_hb_state_low'),
                    '{ams_details}' => $qty . ' (' . _l('ams_hb_low_level') . ': ' . ams_qty($this->level_for($row)) . ')',
                    '{ams_link}'    => admin_url($link),
                ]);
            }
        }
    }

    /** Staff who get HostBill alerts (HostBill Settings → Alerts). */
    public function recipients()
    {
        $ids = json_decode((string) get_option('ams_hb_alert_staff'), true);

        return is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
    }

    private $notified = [];

    private function notify($staffId, $langKey, $data, $link)
    {
        add_notification([
            'description'     => $langKey,
            'touserid'        => (int) $staffId,
            'fromcompany'     => true,
            'link'            => $link,
            // Product names come from HostBill; Perfex prints these unescaped in the bell.
            'additional_data' => serialize(array_map(fn ($v) => e((string) $v), array_values($data))),
        ]);
        $this->notified[(int) $staffId] = true;
    }

    private function push_bell()
    {
        if ($this->notified) {
            pusher_trigger_notification(array_keys($this->notified));
            $this->notified = [];
        }
    }

    /** Products shown / watched, per settings: tracked only or all; hidden ones optional. */
    public function in_scope($row)
    {
        $row = (array) $row;
        if (get_option('ams_hb_product_scope') !== 'all' && ! (int) $row['stock_enabled']) {
            return false;
        }

        return get_option('ams_hb_include_hidden') == '1' || (int) $row['visible'] === 1;
    }

    /** SQL fragment matching in_scope() (for the table and dashboard counts). */
    public function scope_sql($alias)
    {
        $sql = $alias . '.is_removed = 0';
        if (get_option('ams_hb_product_scope') !== 'all') {
            $sql .= ' AND ' . $alias . '.stock_enabled = 1';
        }
        if (get_option('ams_hb_include_hidden') != '1') {
            $sql .= ' AND ' . $alias . '.visible = 1';
        }

        return $sql;
    }

    /** Per-product low-stock level (empty = use the default). */
    public function set_level($hbProductId, $value)
    {
        if (get_option('ams_hb_allow_override') != '1') {
            return ['success' => false, 'message' => _l('ams_hb_override_disabled')];
        }
        $value = trim((string) $value);
        if ($value !== '' && (! is_numeric($value) || (float) $value < 0)) {
            return ['success' => false, 'message' => _l('ams_negative_not_allowed')];
        }
        $row = $this->db->where('hb_product_id', (int) $hbProductId)->get($this->t('ams_hb_products'))->row();
        if (! $row) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }

        $this->db->where('hb_product_id', (int) $hbProductId)->update($this->t('ams_hb_products'), ['low_level' => $value === '' ? null : round((float) $value, 2)]);
        log_activity('AMS HostBill low-stock level [' . $row->name . ': ' . ($value === '' ? 'default' : $value) . ']');
        $this->check_levels();

        return ['success' => true, 'message' => _l('ams_hb_level_saved')];
    }

    /** Dashboard / menu counts for the products in scope. */
    public function counts()
    {
        $counts = ['in_stock' => 0, 'low' => 0, 'out' => 0, 'not_tracked' => 0];
        foreach ($this->db->where($this->scope_sql($this->t('ams_hb_products')), null, false)->get($this->t('ams_hb_products'))->result_array() as $row) {
            $counts[$this->state_for($row)]++;
        }

        return $counts;
    }

    // ─── Internals ────────────────────────────────────────────────────────

    private function prune_log()
    {
        $days = max(1, (int) get_option('ams_hb_log_retention_days'));
        $this->db->where('date_created <', date('Y-m-d H:i:s', strtotime('-' . $days . ' days')))->delete($this->t('ams_hb_sync_log'));
    }

    private function rows($value)
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
