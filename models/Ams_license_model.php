<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Software licences with seats assigned to Perfex staff or to assets.
 * The licence key is stored encrypted and only revealed with the
 * "View licence keys" capability.
 */
class Ams_license_model extends App_Model
{
    private function t($table)
    {
        return db_prefix() . $table;
    }

    public function types()
    {
        return array_map(fn ($t) => ['id' => $t, 'name' => _l('ams_lic_type_' . $t)], ['subscription', 'perpetual', 'per_device', 'site', 'oem', 'open_source']);
    }

    public function get($id)
    {
        $lic = $this->db->select('l.*, b.name brand_name, sp.name supplier_name')
            ->from($this->t('ams_licenses') . ' l')
            ->join($this->t('ams_brands') . ' b', 'b.id = l.brand_id', 'left')
            ->join($this->t('ams_suppliers') . ' sp', 'sp.id = l.supplier_id', 'left')
            ->where('l.id', (int) $id)->get()->row();

        if ($lic) {
            $lic->seats_used = $this->seats_used($id);
            $lic->has_key    = (string) $lic->license_key !== '';
        }

        return $lic;
    }

    public function seats_used($licenseId)
    {
        return (int) total_rows($this->t('ams_license_seats'), ['license_id' => (int) $licenseId, 'released_at' => null]);
    }

    public function save($input, $id = null)
    {
        $name  = trim((string) ($input['name'] ?? ''));
        $seats = (int) ($input['seats'] ?? 0);

        if ($name === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_lic_name'))];
        }
        if ($seats < 1) {
            return ['success' => false, 'message' => _l('ams_lic_seats_invalid')];
        }
        if ($id && $seats < $this->seats_used($id)) {
            return ['success' => false, 'message' => _l('ams_lic_seats_below_used', $this->seats_used($id))];
        }

        $money = function ($v) {
            $v = str_replace([',', ' '], '', trim((string) $v));

            return $v !== '' && is_numeric($v) ? round((float) $v, 2) : null;
        };

        $data = [
            'name'          => mb_substr($name, 0, 191),
            'brand_id'      => (int) ($input['brand_id'] ?? 0) ?: null,
            'category_id'   => (int) ($input['category_id'] ?? 0) ?: null,
            'license_type'  => in_array($input['license_type'] ?? '', array_column($this->types(), 'id')) ? $input['license_type'] : 'subscription',
            'seats'         => $seats,
            'licensed_to'   => trim((string) ($input['licensed_to'] ?? '')) ?: null,
            'supplier_id'   => (int) ($input['supplier_id'] ?? 0) ?: null,
            'order_no'      => trim((string) ($input['order_no'] ?? '')) ?: null,
            'purchase_date' => ! empty($input['purchase_date']) ? to_sql_date($input['purchase_date']) : null,
            'purchase_cost' => $money($input['purchase_cost'] ?? ''),
            'expiry_date'   => ! empty($input['expiry_date']) ? to_sql_date($input['expiry_date']) : null,
            'renewal_cost'  => $money($input['renewal_cost'] ?? ''),
            'auto_renew'    => ! empty($input['auto_renew']) ? 1 : 0,
            'notes'         => trim((string) ($input['notes'] ?? '')) ?: null,
            'active'        => ! empty($input['active']) ? 1 : 0,
        ];

        // Key: encrypted; an empty field on edit keeps the current key. Only key-viewers may change it.
        $key = trim((string) ($input['license_key'] ?? ''));
        if ($key !== '' && (! $id || staff_can('view_keys', 'ams_licenses'))) {
            $this->load->library('encryption');
            $data['license_key'] = $this->encryption->encrypt($key);
        } elseif (! $id) {
            $data['license_key'] = null;
        }

        if ($id) {
            $old = $this->get($id);
            if (! $old) {
                return ['success' => false, 'message' => _l('ams_not_found')];
            }
            if ($old->expiry_date !== $data['expiry_date']) {
                $data['reminded_for'] = null; // renewed: remind again before the new date
            }
            $this->db->where('id', (int) $id)->update($this->t('ams_licenses'), $data);

            return ['success' => true, 'id' => (int) $id, 'message' => _l('updated_successfully', _l('ams_license'))];
        }

        $data['created_by']   = get_staff_user_id() ?: null;
        $data['date_created'] = date('Y-m-d H:i:s');
        $this->db->insert($this->t('ams_licenses'), $data);
        $newId = (int) $this->db->insert_id();
        log_activity('AMS licence created [' . $data['name'] . ']');

        return ['success' => true, 'id' => $newId, 'message' => _l('added_successfully', _l('ams_license'))];
    }

    public function delete($id)
    {
        $lic = $this->get($id);
        if (! $lic) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }
        if ($lic->seats_used > 0) {
            return ['success' => false, 'message' => _l('ams_lic_has_seats')];
        }
        $this->db->where('id', (int) $id)->delete($this->t('ams_licenses'));
        $this->db->where('license_id', (int) $id)->delete($this->t('ams_license_seats'));
        log_activity('AMS licence deleted [' . $lic->name . ']');

        return ['success' => true, 'message' => _l('deleted', _l('ams_license'))];
    }

    /** Decrypted key (caller checks the view_keys capability); every reveal is logged. */
    public function reveal_key($id)
    {
        $row = $this->db->select('name, license_key')->where('id', (int) $id)->get($this->t('ams_licenses'))->row();
        if (! $row || (string) $row->license_key === '') {
            return '';
        }
        $this->load->library('encryption');
        log_activity('AMS licence key viewed [' . $row->name . ']');

        return (string) $this->encryption->decrypt($row->license_key);
    }

    public function assign_seat($licenseId, $input)
    {
        $lic = $this->get($licenseId);
        if (! $lic || ! $lic->active) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }
        if ($lic->seats_used >= (int) $lic->seats) {
            return ['success' => false, 'message' => _l('ams_lic_no_free_seats')];
        }

        $type = $input['assign_type'] ?? '';
        $id   = (int) ($input['assign_id_' . $type] ?? 0);

        if ($type === 'staff' && total_rows($this->t('staff'), ['staffid' => $id, 'active' => 1]) === 0) {
            return ['success' => false, 'message' => _l('ams_select_recipient')];
        }
        if ($type === 'asset' && total_rows($this->t('ams_assets'), ['id' => $id, 'is_deleted' => 0]) === 0) {
            return ['success' => false, 'message' => _l('ams_select_recipient')];
        }
        if (! in_array($type, ['staff', 'asset'])) {
            return ['success' => false, 'message' => _l('ams_select_recipient')];
        }
        if (total_rows($this->t('ams_license_seats'), ['license_id' => (int) $licenseId, 'assigned_type' => $type, 'assigned_id' => $id, 'released_at' => null]) > 0) {
            return ['success' => false, 'message' => _l('ams_lic_already_assigned')];
        }

        $this->db->insert($this->t('ams_license_seats'), [
            'license_id'    => (int) $licenseId,
            'assigned_type' => $type,
            'assigned_id'   => $id,
            'note'          => mb_substr(trim((string) ($input['note'] ?? '')), 0, 255) ?: null,
            'assigned_by'   => get_staff_user_id() ?: null,
            'assigned_at'   => date('Y-m-d H:i:s'),
        ]);

        if ($type === 'staff') {
            ams_notify([$id], 'ams_notify_license_assigned', [$lic->name], 'asset_management/my_assets');
        } else {
            $this->db->insert($this->t('ams_asset_history'), [
                'asset_id'     => $id,
                'action'       => 'license',
                'note'         => _l('ams_lic_history_assigned', $lic->name),
                'staff_id'     => get_staff_user_id() ?: null,
                'date_created' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['success' => true, 'message' => _l('ams_lic_seat_assigned')];
    }

    public function get_seat($seatId)
    {
        return $this->db->where('id', (int) $seatId)->get($this->t('ams_license_seats'))->row();
    }

    public function release_seat($seatId)
    {
        $seat = $this->get_seat($seatId);
        if (! $seat || $seat->released_at) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }
        $this->db->where('id', (int) $seatId)->update($this->t('ams_license_seats'), ['released_at' => date('Y-m-d H:i:s'), 'released_by' => get_staff_user_id() ?: null]);

        if ($seat->assigned_type === 'asset') {
            $lic = $this->get($seat->license_id);
            $this->db->insert($this->t('ams_asset_history'), [
                'asset_id'     => (int) $seat->assigned_id,
                'action'       => 'license',
                'note'         => _l('ams_lic_history_released', $lic ? $lic->name : ''),
                'staff_id'     => get_staff_user_id() ?: null,
                'date_created' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['success' => true, 'message' => _l('ams_lic_seat_released')];
    }

    /** Staff deleted in Perfex: their seats move to the "transfer data to" staff member. */
    public function transfer_staff_seats($fromStaff, $toStaff)
    {
        // Where the receiver already holds a seat of the same licence, free the leaver's seat instead.
        $this->db->query('UPDATE ' . $this->t('ams_license_seats') . ' s
            JOIN ' . $this->t('ams_license_seats') . ' o ON o.license_id = s.license_id AND o.assigned_type = "staff" AND o.assigned_id = ? AND o.released_at IS NULL
            SET s.released_at = NOW(), s.released_by = ?
            WHERE s.assigned_type = "staff" AND s.assigned_id = ? AND s.released_at IS NULL', [(int) $toStaff, get_staff_user_id() ?: null, (int) $fromStaff]);

        $this->db->where('assigned_type', 'staff')->where('assigned_id', (int) $fromStaff)->where('released_at IS NULL', null, false)
            ->update($this->t('ams_license_seats'), ['assigned_id' => (int) $toStaff]);
    }

    /** Cron: licences expiring within the reminder window → asset managers, once per expiry date. */
    public function send_expiry_reminders()
    {
        $until = date('Y-m-d', strtotime('+' . max(1, (int) get_option('ams_license_reminder_days')) . ' days'));
        $rows  = $this->db->query('SELECT id, name, expiry_date, auto_renew FROM ' . $this->t('ams_licenses') . '
            WHERE active = 1 AND expiry_date IS NOT NULL AND expiry_date <= ? AND (reminded_for IS NULL OR reminded_for <> expiry_date)', [$until])->result_array();

        foreach ($rows as $l) {
            $details = $l['auto_renew'] ? _l('ams_lic_auto_renews') : _l('ams_lic_renew_needed');
            ams_notify(ams_manager_recipients(), 'ams_notify_license_expiring', [$l['name'], _d($l['expiry_date'])], 'asset_management/licenses/view/' . (int) $l['id']);
            foreach (ams_manager_recipients() as $staffId) {
                ams_send_email('ams-license-expiring', $staffId, [
                    '{ams_item}'     => $l['name'],
                    '{ams_due_date}' => _d($l['expiry_date']),
                    '{ams_details}'  => $details,
                    '{ams_link}'     => admin_url('asset_management/licenses/view/' . (int) $l['id']),
                ]);
            }
            $this->db->where('id', (int) $l['id'])->update($this->t('ams_licenses'), ['reminded_for' => $l['expiry_date']]);
        }

        return count($rows);
    }
}
