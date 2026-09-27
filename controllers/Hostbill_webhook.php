<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public webhook: HostBill (or any HostBill hook/plugin) notifies that an order
 * changed. URL: {site}/asset_management/hostbill_webhook?token=SECRET&order_id=123
 * (token/order_id may also be POSTed, or the token sent as X-AMS-Token header).
 *
 * The payload is never trusted: the order is re-read through the HostBill API
 * and processed exactly like the scheduled sync (idempotent per order line).
 */
class Hostbill_webhook extends App_Controller
{
    public function index()
    {
        header('Content-Type: application/json');

        if (get_option('ams_hb_enabled') != '1' || get_option('ams_hb_webhook_enabled') != '1') {
            $this->respond(404, 'Webhook disabled');
        }

        $allowed = array_filter(array_map('trim', explode(',', (string) get_option('ams_hb_webhook_ips'))));
        if ($allowed && ! in_array($this->input->ip_address(), $allowed, true)) {
            $this->respond(403, 'IP not allowed');
        }

        $token = (string) ($this->input->get_request_header('X-AMS-Token') ?: ($this->input->post('token') ?: $this->input->get('token')));
        $secret = (string) get_option('ams_hb_webhook_secret');
        if ($secret === '' || ! hash_equals($secret, $token)) {
            $this->respond(401, 'Invalid token');
        }

        $orderId = (int) ($this->input->post('order_id') ?: ($this->input->get('order_id') ?: ($this->input->post('id') ?: $this->input->get('id'))));
        if ($orderId <= 0) {
            $raw = json_decode((string) file_get_contents('php://input'), true);
            $orderId = (int) ($raw['order_id'] ?? ($raw['id'] ?? 0));
        }
        if ($orderId <= 0) {
            $this->respond(400, 'order_id is required');
        }

        $this->load->model(AMS_MODULE_NAME . '/ams_hostbill_model');

        $this->db->insert(db_prefix() . 'ams_hb_sync_log', [
            'direction'    => 'webhook',
            'api_call'     => 'order:' . $orderId,
            'request'      => json_encode(['order_id' => $orderId, 'ip' => $this->input->ip_address()]),
            'success'      => 1,
            'date_created' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->ams_hostbill_model->process_order($orderId);
        $this->ams_hostbill_model->push_pending();

        $this->respond($result['success'] ? 200 : 502, strip_tags($result['message']));
    }

    private function respond($code, $message)
    {
        http_response_code($code);
        echo json_encode(['success' => $code === 200, 'message' => $message]);
        exit;
    }
}
