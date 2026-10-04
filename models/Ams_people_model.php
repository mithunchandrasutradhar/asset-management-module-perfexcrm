<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * People workflows built on Perfex staff and departments:
 * acceptances (e-signature), requests & approvals, department approvers,
 * overdue reminders and staff deactivation / deletion handling.
 */
class Ams_people_model extends App_Model
{
    private function t($table)
    {
        return db_prefix() . $table;
    }

    // ─── Holdings ─────────────────────────────────────────────────────────

    /** What a staff member currently holds: ['assets' => n, 'acceptances' => n] */
    public function holdings($staffId)
    {
        return [
            'assets'      => (int) total_rows($this->t('ams_assets'), ['assigned_type' => 'staff', 'assigned_id' => (int) $staffId, 'is_deleted' => 0]),
            'acceptances' => (int) total_rows($this->t('ams_acceptances'), ['staff_id' => (int) $staffId, 'status' => 'pending']),
        ];
    }

    // ─── Acceptances ──────────────────────────────────────────────────────

    public function acceptance_required($categoryId)
    {
        $mode = get_option('ams_acceptance_mode');
        if ($mode === 'always') {
            return true;
        }
        if ($mode !== 'category' || ! $categoryId) {
            return false;
        }

        $cat = $this->db->select('require_acceptance, parent_id')->where('id', (int) $categoryId)->get($this->t('ams_categories'))->row();
        if ($cat && ! $cat->require_acceptance && $cat->parent_id) {
            $cat = $this->db->select('require_acceptance, parent_id')->where('id', (int) $cat->parent_id)->get($this->t('ams_categories'))->row();
        }

        return $cat && $cat->require_acceptance;
    }

    /** Category terms (or its parent's), falling back to the global terms. */
    public function terms_for($categoryId)
    {
        if ($categoryId) {
            $cat = $this->db->select('eula_text, parent_id')->where('id', (int) $categoryId)->get($this->t('ams_categories'))->row();
            if ($cat && trim((string) $cat->eula_text) !== '') {
                return $cat->eula_text;
            }
            if ($cat && $cat->parent_id) {
                $parent = $this->db->select('eula_text')->where('id', (int) $cat->parent_id)->get($this->t('ams_categories'))->row();
                if ($parent && trim((string) $parent->eula_text) !== '') {
                    return $parent->eula_text;
                }
            }
        }

        return (string) get_option('ams_acceptance_terms');
    }

    /** Hook: asset checked out. Only staff check-outs need acknowledgement. */
    public function on_asset_checkout($data)
    {
        if (($data['assign_type'] ?? '') !== 'staff') {
            return;
        }
        $asset = $this->db->where('id', (int) $data['asset_id'])->get($this->t('ams_assets'))->row();
        if ($asset) {
            $this->assigned('asset', (int) $asset->id, (int) $data['assign_id'], $asset->category_id, $asset->asset_tag . ' - ' . $asset->name);
        }
    }

    public function cancel_pending($relType, $relId)
    {
        $this->db->where('rel_type', $relType)->where('rel_id', (int) $relId)->where('status', 'pending')
            ->update($this->t('ams_acceptances'), ['status' => 'cancelled', 'date_responded' => date('Y-m-d H:i:s')]);
    }

    private function assigned($relType, $relId, $staffId, $categoryId, $label)
    {
        $required = $this->acceptance_required($categoryId);
        $link     = admin_url('asset_management/my_assets');

        if ($required) {
            $this->cancel_pending($relType, $relId);
            $this->db->insert($this->t('ams_acceptances'), [
                'rel_type'     => $relType,
                'rel_id'       => $relId,
                'staff_id'     => $staffId,
                'status'       => 'pending',
                'terms'        => $this->terms_for($categoryId),
                'requested_by' => get_staff_user_id() ?: null,
                'date_created' => date('Y-m-d H:i:s'),
            ]);
        }

        ams_notify([$staffId], $required ? 'ams_notify_acceptance_pending' : 'ams_notify_asset_assigned', [$label], 'asset_management/my_assets');
        ams_send_email('ams-asset-assigned', $staffId, [
            '{ams_item}'        => $label,
            '{ams_by}'          => get_staff_full_name(),
            '{ams_action_text}' => $required ? _l('ams_email_please_accept') : '',
            '{ams_link}'        => $link,
        ]);
    }

    public function get_acceptance($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t('ams_acceptances'))->row();
    }

    /** Human label of what an acceptance is about. */
    public function acceptance_label($acc)
    {
        $a = $this->db->select('asset_tag, name')->where('id', (int) $acc->rel_id)->get($this->t('ams_assets'))->row();

        return $a ? $a->asset_tag . ' - ' . $a->name : '#' . $acc->rel_id;
    }

    /**
     * The assignee accepts (with a drawn signature and typed name) or declines.
     * Only the staff member the acceptance belongs to can respond, once.
     */
    public function respond($id, $accept, $input)
    {
        $acc = $this->get_acceptance($id);
        if (! $acc || (int) $acc->staff_id !== (int) get_staff_user_id()) {
            return ['success' => false, 'message' => _l('access_denied')];
        }
        if ($acc->status !== 'pending') {
            return ['success' => false, 'message' => _l('ams_acceptance_not_pending')];
        }

        $now    = date('Y-m-d H:i:s');
        $label  = $this->acceptance_label($acc);
        $update = [
            'date_responded' => $now,
            'ip_address'     => $this->input->ip_address(),
            'user_agent'     => mb_substr((string) $this->input->user_agent(), 0, 255),
        ];

        if ($accept) {
            $name = trim((string) ($input['signed_name'] ?? ''));
            if ($name === '') {
                return ['success' => false, 'message' => _l('ams_field_required', _l('ams_signed_name'))];
            }
            $file = $this->save_signature($input['signature'] ?? '', $acc->id);
            if (! $file) {
                return ['success' => false, 'message' => _l('ams_signature_required')];
            }
            $update += ['status' => 'accepted', 'signature_file' => $file, 'signed_name' => mb_substr($name, 0, 191)];
        } else {
            $note = trim((string) ($input['note'] ?? ''));
            if ($note === '') {
                return ['success' => false, 'message' => _l('ams_field_required', _l('ams_decline_reason'))];
            }
            $update += ['status' => 'declined', 'note' => $note];
        }

        $this->db->where('id', (int) $acc->id)->where('status', 'pending')->update($this->t('ams_acceptances'), $update);
        if ($this->db->affected_rows() !== 1) {
            return ['success' => false, 'message' => _l('ams_acceptance_not_pending')];
        }

        if ($acc->rel_type === 'asset') {
            $this->db->insert($this->t('ams_asset_history'), [
                'asset_id'     => (int) $acc->rel_id,
                'action'       => $accept ? 'accepted' : 'declined',
                'note'         => $accept ? _l('ams_signed_by', $update['signed_name']) : $update['note'],
                'staff_id'     => get_staff_user_id(),
                'date_created' => $now,
            ]);
        }

        if (! $accept) {
            $link = admin_url('asset_management/assets/view/' . (int) $acc->rel_id);
            foreach (ams_manager_recipients() as $managerId) {
                ams_send_email('ams-acceptance-declined', $managerId, [
                    '{ams_staff_name}' => get_staff_full_name(),
                    '{ams_item}'       => $label,
                    '{ams_details}'    => $update['note'],
                    '{ams_link}'       => $link,
                ]);
            }
            ams_notify(ams_manager_recipients(), 'ams_notify_acceptance_declined', [get_staff_full_name(), $label], str_replace(admin_url(), '', $link));
        }

        log_activity('AMS acceptance ' . ($accept ? 'accepted' : 'declined') . ' [' . $label . ']');

        return ['success' => true, 'message' => _l($accept ? 'ams_acceptance_accepted' : 'ams_acceptance_declined')];
    }

    /** Stores a PNG data-URL signature in the protected uploads folder. */
    private function save_signature($dataUrl, $acceptanceId)
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', trim((string) $dataUrl), $m)) {
            return false;
        }
        $png = base64_decode($m[1], true);
        if ($png === false || strlen($png) < 100 || strlen($png) > 2 * 1024 * 1024 || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return false;
        }

        $dir = AMS_UPLOAD_PATH . 'signatures/';
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return false;
        }
        if (! file_exists($dir . 'index.html')) {
            @file_put_contents($dir . 'index.html', '');
        }

        $file = 'acceptance_' . (int) $acceptanceId . '_' . bin2hex(random_bytes(6)) . '.png';

        return file_put_contents($dir . $file, $png) ? $file : false;
    }

    // ─── Department approvers ─────────────────────────────────────────────

    /** Active approvers of a department (optionally without one staff member, e.g. the requester). */
    public function department_approvers($departmentId, $exceptStaffId = 0)
    {
        $rows = $this->db->select('a.staff_id')->from($this->t('ams_department_approvers') . ' a')
            ->join($this->t('staff') . ' s', 's.staffid = a.staff_id AND s.active = 1')
            ->where('a.department_id', (int) $departmentId)->get()->result_array();

        return array_values(array_filter(array_map('intval', array_column($rows, 'staff_id')), fn ($id) => $id !== (int) $exceptStaffId));
    }

    public function save_department_approvers($departmentId, $staffIds)
    {
        if (total_rows($this->t('departments'), ['departmentid' => (int) $departmentId]) === 0) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }

        $staffIds = array_values(array_unique(array_filter(array_map('intval', (array) $staffIds))));
        if ($staffIds) {
            $staffIds = array_map('intval', array_column($this->db->select('staffid')->where_in('staffid', $staffIds)->where('active', 1)->get($this->t('staff'))->result_array(), 'staffid'));
        }
        $this->db->trans_begin();
        $this->db->where('department_id', (int) $departmentId)->delete($this->t('ams_department_approvers'));
        foreach ($staffIds as $staffId) {
            $this->db->insert($this->t('ams_department_approvers'), ['department_id' => (int) $departmentId, 'staff_id' => $staffId]);
        }
        $this->db->trans_status() === false ? $this->db->trans_rollback() : $this->db->trans_commit();

        log_activity('AMS department approvers updated [Department #' . (int) $departmentId . ': ' . count($staffIds) . ' approver(s)]');

        return ['success' => true, 'message' => _l('updated_successfully', _l('ams_department_approvers'))];
    }

    // ─── Requests ─────────────────────────────────────────────────────────

    public function get_request($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t('ams_requests'))->row();
    }

    public function can_view_request($req)
    {
        if (! $req) {
            return false;
        }

        return (int) $req->staff_id === (int) get_staff_user_id()
            || staff_can('view', 'ams_requests')
            || staff_can('approve', 'ams_requests')
            || in_array((int) $req->department_id, ams_my_approver_departments(), true);
    }

    public function create_request($input)
    {
        $types = ['asset', 'issue'];
        $type  = $input['type'] ?? '';
        if (! in_array($type, $types)) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_req_type'))];
        }

        $subject = trim((string) ($input['subject'] ?? ''));
        $qty     = (float) ($input['qty'] ?? 1);
        $assetId = (int) ($input['asset_id'] ?? 0) ?: null;

        if ($subject === '') {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_req_subject'))];
        }
        if ($qty <= 0) {
            return ['success' => false, 'message' => _l('ams_qty_positive')];
        }
        // Issue reports are only for assets the requester currently holds
        // ("asset:ID" from the form; asset_id alone still works).
        if ($type === 'issue') {
            if (preg_match('/^asset:(\d+)$/', (string) ($input['issue_ref'] ?? ''), $m)) {
                $assetId = (int) $m[1];
            }
            if (! $assetId || total_rows($this->t('ams_assets'), ['id' => $assetId, 'assigned_type' => 'staff', 'assigned_id' => (int) get_staff_user_id(), 'is_deleted' => 0]) === 0) {
                return ['success' => false, 'message' => _l('ams_req_issue_own_asset')];
            }
            $qty = 1;
        } else {
            $assetId = null;
        }

        $categoryId = (int) ($input['category_id'] ?? 0) ?: null;
        if ($categoryId && total_rows($this->t('ams_categories'), ['id' => $categoryId]) === 0) {
            return ['success' => false, 'message' => _l('ams_invalid_value', _l('ams_category'))];
        }

        $staffId = (int) get_staff_user_id();
        $dept    = $this->approval_department($staffId);
        // Department stage only when someone other than the requester can approve it there.
        $status  = $dept && $this->department_approvers($dept, $staffId) ? 'pending_dept' : 'pending_manager';

        $this->db->trans_begin();
        $this->db->insert($this->t('ams_requests'), [
            'staff_id'      => $staffId,
            'department_id' => $dept,
            'type'          => $type,
            'category_id'   => $categoryId,
            'asset_id'      => $assetId,
            'qty'           => $qty,
            'subject'       => mb_substr($subject, 0, 191),
            'description'   => trim((string) ($input['description'] ?? '')) ?: null,
            'priority'      => in_array($input['priority'] ?? '', ['low', 'normal', 'high', 'urgent']) ? $input['priority'] : 'normal',
            'needed_by'     => ! empty($input['needed_by']) ? to_sql_date($input['needed_by']) : null,
            'status'        => $status,
            'date_created'  => date('Y-m-d H:i:s'),
        ]);
        $id  = (int) $this->db->insert_id();
        $num = strtoupper(trim((string) get_option('ams_request_prefix')) ?: 'REQ') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        $this->db->where('id', $id)->update($this->t('ams_requests'), ['request_no' => $num]);
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_db_error')];
        }
        $this->db->trans_commit();

        $this->notify_approvers($this->get_request($id));
        log_activity('AMS request created [' . $num . ']');

        return ['success' => true, 'id' => $id, 'message' => _l('ams_req_submitted', $num)];
    }

    /**
     * The department whose approvers handle a request: the requester's primary Perfex
     * department, or else the first of their other departments that has an approver
     * (other than the requester). Stored on the request.
     */
    private function approval_department($staffId)
    {
        $primary = ams_staff_primary_department($staffId);
        if ($primary && $this->department_approvers($primary, $staffId)) {
            return $primary;
        }
        $depts = array_map('intval', array_column($this->db->select('departmentid')->where('staffid', (int) $staffId)
            ->order_by('departmentid', 'asc')->get($this->t('staff_departments'))->result_array(), 'departmentid'));
        foreach ($depts as $dept) {
            if ($this->department_approvers($dept, $staffId)) {
                return $dept;
            }
        }

        return $primary;
    }

    /** Stage 1: department approvers of the requester's Perfex department; stage 2: staff with "approve". */
    private function notify_approvers($req)
    {
        $recipients = $req->status === 'pending_dept'
            ? $this->department_approvers($req->department_id, $req->staff_id)
            : ams_staff_with_capability('ams_requests', 'approve');

        ams_notify($recipients, 'ams_notify_request_pending', [$req->request_no, get_staff_full_name($req->staff_id)], 'asset_management/requests/view/' . (int) $req->id);
        foreach ($recipients as $staffId) {
            if ((int) $staffId === (int) get_staff_user_id()) {
                continue;
            }
            ams_send_email('ams-request-submitted', $staffId, [
                '{ams_staff_name}' => get_staff_full_name($req->staff_id),
                '{ams_request_no}' => $req->request_no,
                '{ams_item}'       => $req->subject,
                '{ams_details}'    => (string) $req->description,
                '{ams_link}'       => admin_url('asset_management/requests/view/' . (int) $req->id),
            ]);
        }
    }

    private function notify_requester($req, $details = '')
    {
        $status = _l('ams_req_status_' . $req->status);
        ams_notify([$req->staff_id], 'ams_notify_request_updated', [$req->request_no, $status], 'asset_management/requests/view/' . (int) $req->id);
        ams_send_email('ams-request-updated', $req->staff_id, [
            '{ams_request_no}' => $req->request_no,
            '{ams_item}'       => $req->subject,
            '{ams_status}'     => $status,
            '{ams_details}'    => $details,
            '{ams_link}'       => admin_url('asset_management/requests/view/' . (int) $req->id),
        ]);
    }

    /** Can the current user decide on this request at its current stage? */
    public function can_decide($req)
    {
        if (! $req || (int) $req->staff_id === (int) get_staff_user_id() && ! is_admin()) {
            return false; // nobody approves their own request (admins excepted)
        }
        if ($req->status === 'pending_dept') {
            return in_array((int) $req->department_id, ams_my_approver_departments(), true) || staff_can('approve', 'ams_requests');
        }

        return $req->status === 'pending_manager' && staff_can('approve', 'ams_requests');
    }

    public function decide($id, $approve, $note)
    {
        $req = $this->get_request($id);
        if (! $this->can_decide($req)) {
            return ['success' => false, 'message' => _l('access_denied')];
        }

        $note = trim((string) $note) ?: null;
        $now  = date('Y-m-d H:i:s');

        if (! $approve && ! $note) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_req_reject_reason'))];
        }

        // Department stage by a department approver; anyone with "approve" gives the final decision directly.
        if ($req->status === 'pending_dept' && staff_cant('approve', 'ams_requests')) {
            $this->db->where('id', (int) $id)->where('status', $req->status)->update($this->t('ams_requests'), [
                'status'           => $approve ? 'pending_manager' : 'rejected',
                'dept_approver_id' => get_staff_user_id(),
                'dept_decision_at' => $now,
                'dept_note'        => $note,
            ]);
        } else {
            // A manager deciding (also allowed to decide a request still at department stage).
            $this->db->where('id', (int) $id)->where('status', $req->status)->update($this->t('ams_requests'), [
                'status'        => $approve ? 'approved' : 'rejected',
                'approver_id'   => get_staff_user_id(),
                'decision_at'   => $now,
                'decision_note' => $note,
            ]);
        }

        if ($this->db->affected_rows() !== 1) {
            return ['success' => false, 'message' => _l('ams_changed_meanwhile')];
        }
        $req = $this->get_request($id);
        if ($req->status === 'pending_manager') {
            $this->notify_approvers($req);
        }
        $this->notify_requester($req, (string) $note);
        log_activity('AMS request ' . $req->request_no . ' -> ' . $req->status);

        return ['success' => true, 'message' => _l('ams_req_now', _l('ams_req_status_' . $req->status))];
    }

    /**
     * Fulfil an approved asset request through the normal asset check-out
     * (so history and acceptances apply); an issue report is simply marked resolved.
     */
    public function fulfil($id, $input)
    {
        // Lock the request for the whole fulfilment: a double submit waits, then finds it fulfilled.
        $this->db->trans_begin();
        $req = $this->db->query('SELECT * FROM ' . $this->t('ams_requests') . ' WHERE id = ? FOR UPDATE', [(int) $id])->row();
        if (! $req || $req->status !== 'approved') {
            $this->db->trans_rollback();

            return ['success' => false, 'message' => _l('ams_req_not_approved')];
        }
        $result = $this->fulfil_locked($req, $input);
        if (! $result['success'] || $this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return $result['success'] ? ['success' => false, 'message' => _l('ams_db_error')] : $result;
        }
        $this->db->trans_commit();

        $req = $this->get_request($id);
        $this->notify_requester($req, (string) $req->fulfilment_note);
        log_activity('AMS request ' . $req->request_no . ' fulfilled');

        return ['success' => true, 'message' => _l('ams_req_fulfilled_msg', $req->request_no)];
    }

    private function fulfil_locked($req, $input)
    {
        $id     = (int) $req->id;
        $note   = trim((string) ($input['fulfilment_note'] ?? '')) ?: null;
        $update = ['fulfilled_by' => get_staff_user_id(), 'fulfilled_at' => date('Y-m-d H:i:s'), 'fulfilment_note' => $note, 'status' => 'fulfilled'];

        if ($req->type === 'asset') {
            if (staff_cant('checkout', 'ams_assets')) {
                return ['success' => false, 'message' => _l('access_denied')];
            }
            $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
            $assetId = (int) ($input['asset_id'] ?? 0);
            $result  = $this->ams_assets_model->checkout($assetId, [
                'assign_type' => 'staff',
                'assign_id'   => (int) $req->staff_id,
                'note'        => $req->request_no . ($note ? ' - ' . $note : ''),
            ]);
            if (! $result['success']) {
                return $result;
            }
            $update['fulfilled_asset_id'] = $assetId;
        } elseif (! staff_can('approve', 'ams_requests')) {
            return ['success' => false, 'message' => _l('access_denied')];
        }

        $this->db->where('id', (int) $id)->update($this->t('ams_requests'), $update);

        return ['success' => true];
    }

    public function cancel($id)
    {
        $req = $this->get_request($id);
        if (! $req || (int) $req->staff_id !== (int) get_staff_user_id() || ! in_array($req->status, ['pending_dept', 'pending_manager'])) {
            return ['success' => false, 'message' => _l('access_denied')];
        }
        $this->db->where('id', (int) $id)->where_in('status', ['pending_dept', 'pending_manager'])->update($this->t('ams_requests'), ['status' => 'cancelled']);
        if ($this->db->affected_rows() !== 1) {
            return ['success' => false, 'message' => _l('ams_changed_meanwhile')];
        }
        log_activity('AMS request cancelled by requester [' . $req->request_no . ']');

        // Tell whoever was asked to decide that it is no longer needed.
        $approvers = $req->status === 'pending_dept'
            ? $this->department_approvers($req->department_id, $req->staff_id)
            : ams_staff_with_capability('ams_requests', 'approve');
        ams_notify($approvers, 'ams_notify_request_cancelled', [$req->request_no, get_staff_full_name($req->staff_id)], 'asset_management/requests/view/' . (int) $req->id);

        return ['success' => true, 'message' => _l('ams_req_cancelled_msg')];
    }

    public function delete_request($id)
    {
        $req = $this->get_request($id);
        // Approved / fulfilled requests are the approval trail of what was handed out: keep them.
        if (! $req || in_array($req->status, ['approved', 'fulfilled'], true)) {
            return false;
        }
        $this->db->trans_begin();
        $this->db->where('request_id', (int) $id)->update($this->t('ams_purchase_orders'), ['request_id' => null]);
        $this->db->where('request_id', (int) $id)->update($this->t('ams_maintenance'), ['request_id' => null]);
        $this->db->where('id', (int) $id)->delete($this->t('ams_requests'));
        $this->db->trans_status() === false ? $this->db->trans_rollback() : $this->db->trans_commit();
        log_activity('AMS request deleted [' . $req->request_no . ']');

        return true;
    }

    // ─── Overdue reminders (cron) ─────────────────────────────────────────

    public function send_overdue_reminders()
    {
        $days   = max(1, (int) get_option('ams_overdue_reminder_days'));
        $before = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $today  = date('Y-m-d');
        $sent   = 0;

        $assets = $this->db->query('SELECT id, asset_tag, name, assigned_id, expected_checkin FROM ' . $this->t('ams_assets') . '
            WHERE is_deleted = 0 AND assigned_type = "staff" AND expected_checkin IS NOT NULL AND expected_checkin < ?
            AND (overdue_notified_at IS NULL OR overdue_notified_at < ?)', [$today, $before])->result_array();

        foreach ($assets as $a) {
            $this->remind((int) $a['assigned_id'], $a['asset_tag'] . ' - ' . $a['name'], $a['expected_checkin'], 'asset_management/assets/view/' . $a['id']);
            $this->db->where('id', (int) $a['id'])->update($this->t('ams_assets'), ['overdue_notified_at' => date('Y-m-d H:i:s')]);
            $sent++;
        }


        return $sent;
    }

    private function remind($staffId, $label, $due, $link)
    {
        ams_notify([$staffId], 'ams_notify_overdue', [$label, _d($due)], 'asset_management/my_assets');
        ams_send_email('ams-overdue-return', $staffId, [
            '{ams_item}'     => $label,
            '{ams_due_date}' => _d($due),
            '{ams_link}'     => admin_url('asset_management/my_assets'),
        ]);
        ams_notify(ams_manager_recipients(), 'ams_notify_overdue_manager', [$label, get_staff_full_name($staffId), _d($due)], $link);
    }

    // ─── Staff lifecycle (Perfex hooks) ───────────────────────────────────

    /** Filter before_staff_status_change: warn (or block) deactivating someone who still holds assets. */
    public function on_staff_status_change($status, $staffId)
    {
        if ((int) $status !== 0) {
            return $status;
        }

        $h = $this->holdings($staffId);
        $this->load->model(AMS_MODULE_NAME . '/ams_maintenance_model');
        $jobs = $this->ams_maintenance_model->open_jobs_for_staff($staffId);
        if ($h['assets'] === 0 && $jobs === 0) {
            return $status;
        }

        // Open maintenance jobs only warn: the "block" setting is about held assets.
        $jobsNote = $jobs ? ' ' . _l('ams_staff_has_mt_jobs', [get_staff_full_name($staffId), $jobs]) : '';
        if ($h['assets'] === 0) {
            set_alert('warning', trim($jobsNote));

            return $status;
        }

        $message = _l('ams_staff_holds_items', [get_staff_full_name($staffId), $h['assets']]) . $jobsNote;

        if (get_option('ams_block_staff_deactivation') == '1') {
            set_alert('danger', $message . ' ' . _l('ams_staff_deactivation_blocked'));

            return 1;
        }

        set_alert('warning', $message);

        return $status;
    }

    /** Action before_delete_staff_member: move everything they hold to the "transfer data to" staff member. */
    public function on_staff_delete($data)
    {
        $from = (int) ($data['id'] ?? 0);
        $to   = (int) ($data['transfer_data_to'] ?? 0);
        if (! $from || ! $to) {
            return;
        }

        $now   = date('Y-m-d H:i:s');
        $note  = _l('ams_transferred_on_delete', [get_staff_full_name($from), get_staff_full_name($to)]);
        $dept  = ams_staff_primary_department($to);

        foreach ($this->db->where('assigned_type', 'staff')->where('assigned_id', $from)->get($this->t('ams_assets'))->result_array() as $a) {
            $this->db->where('id', (int) $a['id'])->update($this->t('ams_assets'), ['assigned_id' => $to, 'department_id' => $dept, 'date_updated' => $now]);
            $this->db->insert($this->t('ams_asset_history'), [
                'asset_id'           => (int) $a['id'],
                'action'             => 'transfer',
                'status_to'          => (int) $a['status_id'],
                'assigned_type_from' => 'staff',
                'assigned_id_from'   => $from,
                'assigned_type_to'   => 'staff',
                'assigned_id_to'     => $to,
                'department_id'      => $dept,
                'note'               => $note,
                'staff_id'           => get_staff_user_id() ?: null,
                'date_created'       => $now,
            ]);
        }

        $this->db->where('staff_id', $from)->where('status', 'pending')
            ->update($this->t('ams_acceptances'), ['status' => 'cancelled', 'date_responded' => $now, 'note' => $note]);
        $this->db->where('staff_id', $from)->where_in('status', ['pending_dept', 'pending_manager'])
            ->update($this->t('ams_requests'), ['status' => 'cancelled']);
        $this->db->where('staff_id', $from)->delete($this->t('ams_department_approvers'));
        $this->load->model(AMS_MODULE_NAME . '/ams_license_model');
        $this->ams_license_model->transfer_staff_seats($from, $to);
        $this->load->model(AMS_MODULE_NAME . '/ams_maintenance_model');
        $this->ams_maintenance_model->transfer_staff($from, $to);

        log_activity('AMS: assets of deleted staff #' . $from . ' transferred to staff #' . $to);
    }
}
