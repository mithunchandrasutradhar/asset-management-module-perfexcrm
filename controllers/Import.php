<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Import wizard: 1) upload CSV / .xlsx, 2) map columns + options,
 * 3) preview (validation only), 4) import, with a downloadable result report.
 * The parsed file lives in Perfex's temp folder under a random token kept in
 * the session; nothing is written to the module tables before step 4.
 */
class Import extends AdminController
{
    private const MAX_ROWS = 5000;

    private const MAX_MB = 10;

    public function __construct()
    {
        parent::__construct();
        ams_post_only(['upload']);
        if (! ams_can_import()) {
            access_denied('ams_assets');
        }
        $this->load->model(AMS_MODULE_NAME . '/ams_import_model');
    }

    public function index()
    {
        $data['title'] = _l('ams_import');
        $data['types'] = $this->ams_import_model->types();
        $data['max']   = self::MAX_ROWS;
        $data['maxMb'] = self::MAX_MB;
        $this->load->view(AMS_MODULE_NAME . '/import/index', $data);
    }

    public function upload()
    {
        $type  = (string) $this->input->post('type');
        $types = $this->ams_import_model->types();
        if (! isset($types[$type]) || empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            set_alert('warning', _l('ams_import_choose_file'));
            redirect(admin_url('asset_management/import'));
        }
        if ($_FILES['file']['size'] > self::MAX_MB * 1024 * 1024) {
            set_alert('warning', _l('ams_file_too_large', self::MAX_MB));
            redirect(admin_url('asset_management/import'));
        }

        $this->load->library(AMS_MODULE_NAME . '/ams_spreadsheet_reader');
        $parsed = $this->ams_spreadsheet_reader->read($_FILES['file']['tmp_name'], $_FILES['file']['name'], self::MAX_ROWS);
        if (isset($parsed['error'])) {
            set_alert('warning', $parsed['error']);
            redirect(admin_url('asset_management/import'));
        }

        $this->clear();
        $token = bin2hex(random_bytes(16));
        file_put_contents($this->path($token, 'data'), json_encode($parsed['rows']));
        $this->session->set_userdata('ams_import', ['token' => $token, 'type' => $type, 'file' => mb_substr(basename($_FILES['file']['name']), 0, 120)]);

        redirect(admin_url('asset_management/import/map'));
    }

    /** Mapping form; POST action=preview runs validation only, action=import writes. */
    public function map()
    {
        $job  = $this->session->userdata('ams_import');
        $rows = $job ? $this->load_json($job['token'], 'data') : null;
        if (! $rows || ! isset($this->ams_import_model->types()[$job['type']])) {
            $this->clear();
            redirect(admin_url('asset_management/import'));
        }

        $headers = array_shift($rows);
        $fields  = $this->ams_import_model->fields($job['type']);
        $map     = $this->ams_import_model->auto_map($job['type'], $headers);
        $options = ['mode' => 'skip', 'date_format' => 'd/m/Y', 'create_missing' => 0, 'default_kind' => 'stock', 'notify' => 0];
        $result  = null;
        $action  = $this->input->post('action');

        if ($action) {
            $map = [];
            foreach (array_keys($fields) as $key) {
                $col = $this->input->post('map')[$key] ?? '';
                if ($col !== '' && isset($headers[(int) $col])) {
                    $map[$key] = (int) $col;
                }
            }
            $options = [
                'mode'           => $this->input->post('mode') === 'update' ? 'update' : 'skip',
                'date_format'    => (string) $this->input->post('date_format'),
                'create_missing' => $this->input->post('create_missing') ? 1 : 0,
                'default_kind'   => (string) $this->input->post('default_kind'),
                'notify'         => $this->input->post('notify') ? 1 : 0,
            ];

            // In update mode existing records keep unmapped values, so only the match column is required
            // (rows that turn out to be new still fail validation without a name / category).
            $matchKey = ['assets' => 'asset_tag', 'items' => 'sku', 'suppliers' => 'name'][$job['type']];
            $missing  = $options['mode'] === 'update'
                ? (isset($map[$matchKey]) ? [] : [$matchKey])
                : array_filter(array_keys($fields), fn ($k) => $fields[$k]['required'] && ! isset($map[$k]) && ! ($job['type'] === 'items' && $k === 'kind'));
            if ($missing) {
                set_alert('warning', _l('ams_import_map_required', implode(', ', array_map(fn ($k) => _l($fields[$k]['label']), $missing))));
            } else {
                $commit = $action === 'import';
                $result = $this->ams_import_model->run($job['type'], $rows, $map, $options, $commit);
                file_put_contents($this->path($job['token'], 'result'), json_encode(['rows' => $rows, 'headers' => $headers, 'result' => $result['results']]));
                if ($commit) {
                    // The file is consumed: a refresh must not import it twice.
                    @unlink($this->path($job['token'], 'data'));
                    $this->session->set_userdata('ams_import', $job + ['done' => true]);
                }
            }
        }

        $data['title']   = _l('ams_import');
        $data['job']     = $job;
        $data['headers'] = $headers;
        $data['sample']  = array_slice($rows, 0, 5);
        $data['total']   = count($rows);
        $data['fields']  = $fields;
        $data['map']     = $map;
        $data['options'] = $options;
        $data['result']  = $result;
        $data['action']  = $action;
        $this->load->view(AMS_MODULE_NAME . '/import/map', $data);
    }

    /** Result report as CSV: the original row plus status and message. ?errors=1 for errors only. */
    public function report()
    {
        $job = $this->session->userdata('ams_import');
        $res = $job ? $this->load_json($job['token'], 'result') : null;
        if (! $res) {
            show_404();
        }
        $onlyErrors = $this->input->get('errors') === '1';
        // Cells from the uploaded file must not become live formulas when the report is opened in Excel.
        $safe = fn ($row) => array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v, $row);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="import-result-' . $job['type'] . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $safe(array_merge([_l('ams_import_row'), _l('ams_status'), _l('ams_import_reference'), _l('ams_import_message')], $res['headers'])));
        foreach ($res['result'] as $i => $r) {
            if ($onlyErrors && $r['status'] !== 'error') {
                continue;
            }
            fputcsv($out, $safe(array_merge([$r['row'], _l('ams_import_status_' . $r['status']), $r['ref'], strip_tags(str_replace('<br>', ' ', $r['message']))], $res['rows'][$i] ?? [])));
        }
        fclose($out);
        exit;
    }

    /** Template CSV with every importable column and one example row. */
    public function sample($type = 'assets')
    {
        if (! isset($this->ams_import_model->types()[$type])) {
            show_404();
        }
        $examples = [
            'assets'    => ['asset_tag' => '', 'name' => 'Dell Latitude 5440', 'serial_no' => '5CG1234XYZ', 'category' => 'Laptops', 'brand' => 'Dell', 'status' => 'In Store', 'location' => 'Head Office Store', 'purchase_date' => date('Y-m-d'), 'purchase_cost' => '85000', 'warranty_end' => date('Y-m-d', strtotime('+3 years'))],
            'items'     => ['sku' => '', 'name' => 'USB Headset', 'kind' => 'stock', 'category' => 'Headphones', 'unit' => 'pcs', 'cost' => '1200', 'sale_price' => '1800', 'reorder_level' => '5', 'is_sellable' => 'yes', 'opening_qty' => '20', 'opening_location' => 'Main Store'],
            'suppliers' => ['name' => 'Tech Supplies Ltd', 'contact_person' => 'Sam Seller', 'phone' => '+8801700000000', 'email' => 'sales@example.com'],
        ];
        $keys = array_keys($this->ams_import_model->fields($type));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ams-import-' . $type . '-template.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $keys);
        fputcsv($out, array_map(fn ($k) => $examples[$type][$k] ?? '', $keys));
        fclose($out);
        exit;
    }

    public function cancel()
    {
        $this->clear();
        redirect(admin_url('asset_management/import'));
    }

    private function path($token, $kind)
    {
        return rtrim(app_temp_dir(), '/\\') . '/ams_import_' . preg_replace('/[^a-f0-9]/', '', $token) . '_' . $kind . '.json';
    }

    private function load_json($token, $kind)
    {
        $file = $this->path($token, $kind);

        return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    private function clear()
    {
        $job = $this->session->userdata('ams_import');
        if ($job) {
            @unlink($this->path($job['token'], 'data'));
            @unlink($this->path($job['token'], 'result'));
        }
        $this->session->unset_userdata('ams_import');
    }
}
