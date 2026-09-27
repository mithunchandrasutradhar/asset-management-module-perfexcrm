<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Disposal workflow: records how an asset left the company (sold, donated,
 * scrapped, lost, ...), with proceeds, book value at the disposal date and the
 * resulting gain / loss, and moves the asset to an archived status. Open
 * maintenance is cancelled, schedules stopped and licence seats freed.
 * A disposal recorded by mistake can be reversed (asset back to In Store).
 */
class Ams_disposal_model extends App_Model
{
    private function t($table)
    {
        return db_prefix() . $table;
    }

    public function get_by_asset($assetId)
    {
        return $this->db->where('asset_id', (int) $assetId)->get($this->t('ams_disposals'))->row();
    }

    public function dispose($assetId, $input)
    {
        $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
        $this->load->model(AMS_MODULE_NAME . '/ams_finance_model');

        $asset = $this->ams_assets_model->get($assetId);
        if (! $asset) {
            return ['success' => false, 'message' => _l('ams_not_found')];
        }
        if ($this->get_by_asset($assetId)) {
            return ['success' => false, 'message' => _l('ams_disp_already')];
        }

        $method = $input['method'] ?? '';
        if (! in_array($method, ams_disposal_methods(), true)) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_disp_method'))];
        }

        $date = ! empty($input['disposal_date']) ? to_sql_date($input['disposal_date']) : date('Y-m-d');
        if (! $date || $date > date('Y-m-d')) {
            return ['success' => false, 'message' => _l('ams_disp_date_future')];
        }

        $status = ams_get_status($input['status_id'] ?? 0);
        if (! $status || ! $status['active'] || $status['type'] !== 'archived') {
            return ['success' => false, 'message' => _l('ams_disp_status_archived')];
        }

        $proceeds = str_replace([',', ' '], '', trim((string) ($input['proceeds'] ?? '')));
        if ($proceeds !== '' && (! is_numeric($proceeds) || (float) $proceeds < 0)) {
            return ['success' => false, 'message' => _l('ams_negative_not_allowed')];
        }
        $proceeds = $proceeds === '' ? null : round((float) $proceeds, 2);

        $schedule  = $this->ams_finance_model->schedule($asset, $date);
        $bookValue = $schedule['book_value'] !== null ? round((float) $schedule['book_value'], 2) : null;
        $gainLoss  = $bookValue !== null ? round(($proceeds ?? 0) - $bookValue, 2) : null;

        $methodLabel = _l('ams_disp_method_' . $method);
        $note        = trim(_l('ams_disp_history', $methodLabel) . ' ' . trim((string) ($input['reason'] ?? '')));

        // Status change first: it checks the "dispose" permission and ends any assignment.
        $r = $this->ams_assets_model->change_status($assetId, [
            'status_id'       => $status['id'],
            'note'            => $note,
            '_history_action' => 'dispose',
        ]);
        if (! $r['success']) {
            return $r;
        }

        $this->db->insert($this->t('ams_disposals'), [
            'asset_id'      => (int) $assetId,
            'method'        => $method,
            'disposal_date' => $date,
            'status_id'     => (int) $status['id'],
            'status_before' => (int) $asset->status_id,
            'proceeds'      => $proceeds,
            'currency'      => $asset->currency ?: null,
            'book_value'    => $bookValue,
            'gain_loss'     => $gainLoss,
            'recipient'     => mb_substr(trim((string) ($input['recipient'] ?? '')), 0, 191) ?: null,
            'reference'     => mb_substr(trim((string) ($input['reference'] ?? '')), 0, 100) ?: null,
            'reason'        => trim((string) ($input['reason'] ?? '')) ?: null,
            'staff_id'      => get_staff_user_id() ?: null,
            'date_created'  => date('Y-m-d H:i:s'),
        ]);

        $this->close_related($assetId);
        $this->ams_finance_model->recalculate([$assetId]);

        log_activity('AMS asset disposed [Tag: ' . $asset->asset_tag . ', Method: ' . $method . ', Proceeds: ' . ($proceeds ?? '-') . ']');
        hooks()->do_action('ams_after_asset_disposed', (int) $assetId);

        return ['success' => true, 'message' => _l('ams_disp_done', e($asset->asset_tag))];
    }

    /** Disposed assets keep no running work: cancel open jobs, stop schedules, free licence seats. */
    private function close_related($assetId)
    {
        $this->load->model(AMS_MODULE_NAME . '/ams_maintenance_model');
        $open = $this->db->select('id')->where('asset_id', (int) $assetId)->where_in('status', ['scheduled', 'in_progress'])->get($this->t('ams_maintenance'))->result_array();
        foreach ($open as $job) {
            $this->ams_maintenance_model->cancel($job['id']);
        }
        $this->db->where('asset_id', (int) $assetId)->update($this->t('ams_maintenance_schedules'), ['active' => 0]);

        $this->load->model(AMS_MODULE_NAME . '/ams_license_model');
        $seats = $this->db->select('id')->where('assigned_type', 'asset')->where('assigned_id', (int) $assetId)->where('released_at IS NULL', null, false)
            ->get($this->t('ams_license_seats'))->result_array();
        foreach ($seats as $seat) {
            $this->ams_license_model->release_seat($seat['id']);
        }
    }

    /** Undo a disposal recorded by mistake: asset back to In Store at a location. */
    public function reinstate($assetId, $input)
    {
        $this->load->model(AMS_MODULE_NAME . '/ams_assets_model');
        $this->load->model(AMS_MODULE_NAME . '/ams_finance_model');

        $asset    = $this->ams_assets_model->get($assetId);
        $disposal = $this->get_by_asset($assetId);
        if (! $asset || ! $disposal) {
            return ['success' => false, 'message' => _l('ams_disp_not_disposed')];
        }

        $inStore  = ams_get_status_by_key('in_store');
        $location = (int) ($input['location_id'] ?? 0) ?: (int) $asset->location_id;
        if (! $location) {
            return ['success' => false, 'message' => _l('ams_field_required', _l('ams_location'))];
        }

        $r = $this->ams_assets_model->change_status($assetId, [
            'status_id'       => $inStore['id'] ?? 0,
            'location_id'     => $location,
            'note'            => trim(_l('ams_disp_reinstated') . ' ' . trim((string) ($input['note'] ?? ''))),
            '_history_action' => 'reinstate',
        ]);
        if (! $r['success']) {
            return $r;
        }

        $this->db->where('id', (int) $disposal->id)->delete($this->t('ams_disposals'));
        $this->ams_finance_model->recalculate([$assetId]);
        log_activity('AMS asset disposal reversed [Tag: ' . $asset->asset_tag . ']');

        return ['success' => true, 'message' => _l('ams_disp_reinstated')];
    }
}
