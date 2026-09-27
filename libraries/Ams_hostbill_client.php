<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Thin wrapper around the HostBill Admin API (POST {url}/admin/api.php).
 * Credentials and behaviour come from the module settings (ams_hb_*).
 * Every call is written to tblams_hb_sync_log with the API key masked.
 *
 * Calls used: getHostBillversion, getOrderPages, getProducts, getProductDetails,
 * getOrders, getOrderDetails, getAccountDetails, getClientDetails, editProduct.
 */
class Ams_hostbill_client
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function is_configured()
    {
        return get_option('ams_hb_url') !== '' && get_option('ams_hb_api_id') !== '' && $this->api_key() !== '';
    }

    public function api_key()
    {
        $stored = (string) get_option('ams_hb_api_key');
        if ($stored === '') {
            return '';
        }
        $this->CI->load->library('encryption');
        $plain = $this->CI->encryption->decrypt($stored);

        return $plain === false ? '' : $plain;
    }

    public function endpoint()
    {
        $url = rtrim(trim((string) get_option('ams_hb_url')), '/');
        if ($url === '') {
            return '';
        }
        // Accept either the HostBill base URL or the full api.php URL.
        if (substr($url, -8) === '/api.php') {
            return $url;
        }
        if (substr($url, -6) === '/admin') {
            return $url . '/api.php';
        }

        return $url . '/admin/api.php';
    }

    /**
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null, 'http_code' => int]
     */
    public function call($method, $params = [], $direction = 'pull')
    {
        if (! $this->is_configured()) {
            return $this->result(false, null, _l('ams_hb_not_configured'), 0);
        }
        if (! function_exists('curl_init')) {
            return $this->result(false, null, 'PHP cURL extension is not installed.', 0);
        }

        $payload = array_merge($params, [
            'api_id'  => get_option('ams_hb_api_id'),
            'api_key' => $this->api_key(),
            'call'    => $method,
        ]);

        $start = microtime(true);
        $ch    = curl_init($this->endpoint());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => max(5, (int) get_option('ams_hb_timeout')),
            CURLOPT_SSL_VERIFYPEER => get_option('ams_hb_verify_ssl') == '1',
            CURLOPT_SSL_VERIFYHOST => get_option('ams_hb_verify_ssl') == '1' ? 2 : 0,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'PerfexCRM-AssetManagement/1.0',
        ]);
        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        $ms = (int) round((microtime(true) - $start) * 1000);

        $data  = null;
        $error = null;

        if ($body === false) {
            $error = 'Connection error: ' . $curlErr;
        } else {
            $data = json_decode($body, true);
            if (! is_array($data)) {
                $error = 'Invalid response (HTTP ' . $httpCode . '): ' . mb_substr(strip_tags((string) $body), 0, 200);
                $data  = null;
            } elseif (empty($data['success'])) {
                $error = $this->error_text($data) ?: 'HostBill returned success=false (HTTP ' . $httpCode . ')';
            }
        }

        $this->log($direction, $method, $params, $body === false ? null : $body, $httpCode, $error === null, $error, $ms);

        return $this->result($error === null, $data, $error, $httpCode);
    }

    private function error_text($data)
    {
        if (! empty($data['error'])) {
            return is_array($data['error']) ? implode('; ', array_map('strval', $data['error'])) : (string) $data['error'];
        }

        return null;
    }

    private function result($success, $data, $error, $httpCode)
    {
        // Error text comes from the remote server and ends up in alerts: escape it here.
        return ['success' => $success, 'data' => $data, 'error' => $error !== null ? e($error) : null, 'http_code' => $httpCode];
    }

    private function log($direction, $method, $params, $response, $httpCode, $success, $error, $ms)
    {
        $this->CI->db->insert(db_prefix() . 'ams_hb_sync_log', [
            'direction'    => $direction,
            'api_call'     => $method,
            'request'      => json_encode($params, JSON_UNESCAPED_UNICODE),
            'response'     => $response !== null ? mb_substr($response, 0, 60000) : null,
            'http_code'    => $httpCode ?: null,
            'success'      => $success ? 1 : 0,
            'error'        => $error,
            'duration_ms'  => $ms,
            'staff_id'     => get_staff_user_id() ?: null,
            'date_created' => date('Y-m-d H:i:s'),
        ]);
    }
}
