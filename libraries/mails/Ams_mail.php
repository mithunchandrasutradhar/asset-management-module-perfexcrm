<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * One mail class for every Asset Management email template (type "ams").
 * The template (subject/body, enabled/disabled) is edited in
 * Setup → Email Templates → Asset Management; $slug picks which one.
 *
 * send_mail_template('Ams_mail', 'asset_management', $slug, $email, $staffId, $mergeFields)
 */
class Ams_mail extends App_mail_template
{
    protected $for = 'staff';

    public $rel_type = 'staff';

    protected $email;

    protected $staffid;

    protected $fields;

    protected $files;

    /**
     * @param array $files [['attachment' => file content (e.g. PDF Output('', 'S')), 'filename' => name, 'type' => mime], ...]
     */
    public function __construct($slug, $email, $staffId, $fields = [], $files = [])
    {
        parent::__construct();
        $this->slug    = $slug;
        $this->email   = $email;
        $this->staffid = $staffId;
        $this->fields  = $fields;
        $this->files   = $files;
    }

    public function build()
    {
        $this->to($this->email)
            ->set_rel_id($this->staffid)
            ->set_staff_id($this->staffid ?: null)
            ->set_merge_fields($this->fields);

        foreach ((array) $this->files as $file) {
            $this->add_attachment($file);
        }
    }
}
