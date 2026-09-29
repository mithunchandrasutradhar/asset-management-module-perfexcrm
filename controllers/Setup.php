<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Master data: categories, brands, models, statuses, locations, suppliers.
 * URL: asset_management/setup/index/<entity>
 */
class Setup extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        ams_post_only(['save', 'delete']);
        $this->load->model(AMS_MODULE_NAME . '/ams_setup_model');
    }

    public function index($entity = 'categories')
    {
        if (staff_cant('view', 'ams_setup')) {
            access_denied('ams_setup');
        }

        $cfg = $this->entity_or_404($entity);

        $data['title']  = _l($cfg['plural']);
        $data['entity'] = $entity;
        $data['cfg']    = $cfg;
        $data['table']  = App_table::find('ams_' . $entity);

        $this->load->view(AMS_MODULE_NAME . '/setup/manage', $data);
    }

    /** Setup → Change log: every create / change / delete of master data. */
    public function changelog()
    {
        if (staff_cant('view', 'ams_setup')) {
            access_denied('ams_setup');
        }
        $data['title'] = _l('ams_setup_changelog');
        $data['table'] = App_table::find('ams_setup_log');
        $this->load->view(AMS_MODULE_NAME . '/setup/changelog', $data);
    }

    public function changelog_table()
    {
        if (staff_cant('view', 'ams_setup')) {
            ajax_access_denied();
        }
        App_table::find('ams_setup_log')->output();
    }

    public function table($entity)
    {
        if (staff_cant('view', 'ams_setup')) {
            ajax_access_denied();
        }

        $this->entity_or_404($entity);
        App_table::find('ams_' . $entity)->output(['entity' => $entity]);
    }

    /**
     * Current options of the form's select fields (JSON). The form asks for them each
     * time it opens, so records added since the page loaded (e.g. a new parent
     * category) are offered straight away.
     */
    public function options($entity)
    {
        if (staff_cant('view', 'ams_setup')) {
            ajax_access_denied();
        }

        $cfg     = $this->entity_or_404($entity);
        $options = [];
        foreach ($cfg['fields'] as $field => $def) {
            if ($def['type'] === 'select' && ! empty($def['options']) && is_callable($def['options'])) {
                $options[$field] = array_map(fn ($o) => ['id' => (string) $o['id'], 'name' => (string) $o['name']], call_user_func($def['options']));
            }
        }

        header('Content-Type: application/json');
        echo json_encode($options);
    }

    public function get($entity, $id)
    {
        if (staff_cant('view', 'ams_setup')) {
            ajax_access_denied();
        }

        $this->entity_or_404($entity);
        $row = $this->ams_setup_model->get($entity, $id);

        header('Content-Type: application/json');
        echo json_encode($row ?: []);
    }

    public function save($entity)
    {
        $this->entity_or_404($entity);

        if (! $this->input->post()) {
            show_404();
        }

        $id = (int) $this->input->post('id');

        if (($id && staff_cant('edit', 'ams_setup')) || (! $id && staff_cant('create', 'ams_setup'))) {
            $result = ['success' => false, 'message' => _l('access_denied')];
        } else {
            $result = $this->ams_setup_model->save($entity, $this->input->post(), $id ?: null);
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    public function delete($entity, $id)
    {
        if (staff_cant('delete', 'ams_setup')) {
            access_denied('ams_setup');
        }

        $this->entity_or_404($entity);
        $result = $this->ams_setup_model->delete($entity, $id);
        set_alert($result['success'] ? 'success' : 'warning', $result['message']);

        redirect(admin_url('asset_management/setup/index/' . $entity));
    }

    private function entity_or_404($entity)
    {
        $cfg = $this->ams_setup_model->entity($entity);
        if (! $cfg) {
            show_404();
        }

        return $cfg;
    }
}
