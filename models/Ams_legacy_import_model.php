<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * One-time import of the cleaned legacy master data (data/legacy_master_data.php).
 * Repeat-safe: rows are matched by name (and parent), existing rows are never
 * duplicated or overwritten; an existing category without a tag code gets one.
 */
class Ams_legacy_import_model extends App_Model
{
    private $data;

    public function __construct()
    {
        parent::__construct();
        $this->data = require module_dir_path(AMS_MODULE_NAME, 'data/legacy_master_data.php');
    }

    /**
     * @return array rows: [section, name, parent, code, state] where state = new | exists | code_added
     */
    public function preview()
    {
        return $this->process(true);
    }

    /**
     * @return array ['created' => int, 'codes' => int, 'skipped' => int]
     */
    public function run()
    {
        $this->db->trans_begin();
        $rows = $this->process(false);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return false;
        }
        $this->db->trans_commit();

        $summary = ['created' => 0, 'codes' => 0, 'skipped' => 0];
        foreach ($rows as $row) {
            $summary[$row['state'] === 'new' ? 'created' : ($row['state'] === 'code_added' ? 'codes' : 'skipped')]++;
        }

        update_option('ams_legacy_import_date', date('Y-m-d H:i:s'));
        log_activity('AMS legacy master data imported [created: ' . $summary['created'] . ', codes added: ' . $summary['codes'] . ', skipped: ' . $summary['skipped'] . ']');

        return $summary;
    }

    private function process($dryRun)
    {
        $rows  = [];
        $now   = date('Y-m-d H:i:s');
        $staff = get_staff_user_id();
        $p     = db_prefix();

        // ─── Categories (parents first, so children can resolve their new parent id)
        $map        = []; // legacy id => new id (or null in a dry run for not-yet-created parents)
        $categories = $this->data['categories'];
        usort($categories, fn ($a, $b) => ($a['parent'] > 0) <=> ($b['parent'] > 0));

        foreach ($categories as $cat) {
            $parentId   = $cat['parent'] ? ($map[$cat['parent']] ?? null) : 0;
            $parentName = $cat['parent'] ? $this->legacy_category_name($cat['parent']) : '';
            $existing   = $parentId === null ? null : $this->find($p . 'ams_categories', $cat['name'], $parentId);

            if ($existing) {
                $state = 'exists';
                if (empty($existing->code)) {
                    $state = 'code_added';
                    if (! $dryRun) {
                        $this->db->where('id', $existing->id)->update($p . 'ams_categories', ['code' => $cat['code']]);
                    }
                }
                $map[$cat['id']] = (int) $existing->id;
            } else {
                $state = 'new';
                if (! $dryRun) {
                    $this->db->insert($p . 'ams_categories', [
                        'parent_id'    => $parentId,
                        'name'         => $cat['name'],
                        'code'         => $cat['code'],
                        'icon'         => $cat['icon'],
                        'active'       => 1,
                        'created_by'   => $staff,
                        'date_created' => $now,
                    ]);
                    $map[$cat['id']] = (int) $this->db->insert_id();
                } else {
                    $map[$cat['id']] = null;
                }
            }

            $rows[] = ['section' => 'ams_category', 'name' => $cat['name'], 'parent' => $parentName, 'code' => $cat['code'], 'state' => $state];
        }

        // ─── Brands
        foreach ($this->data['brands'] as $name) {
            $existing = $this->find($p . 'ams_brands', $name);
            if (! $existing && ! $dryRun) {
                $this->db->insert($p . 'ams_brands', ['name' => $name, 'active' => 1, 'created_by' => $staff, 'date_created' => $now]);
            }
            $rows[] = ['section' => 'ams_brand', 'name' => $name, 'parent' => '', 'code' => '', 'state' => $existing ? 'exists' : 'new'];
        }

        // ─── Locations
        $locMap = [];
        foreach ($this->data['locations'] as $loc) {
            $parentId = $loc['parent'] ? ($locMap[$loc['parent']] ?? null) : 0;
            $existing = $parentId === null ? null : $this->find($p . 'ams_locations', $loc['name'], $parentId);

            if ($existing) {
                $locMap[$loc['id']] = (int) $existing->id;
            } elseif (! $dryRun) {
                $this->db->insert($p . 'ams_locations', [
                    'parent_id'    => $parentId,
                    'name'         => $loc['name'],
                    'type'         => $loc['type'],
                    'address'      => $loc['address'],
                    'active'       => 1,
                    'created_by'   => $staff,
                    'date_created' => $now,
                ]);
                $locMap[$loc['id']] = (int) $this->db->insert_id();
            } else {
                $locMap[$loc['id']] = null;
            }

            $parentName = '';
            foreach ($this->data['locations'] as $l) {
                if ($l['id'] === $loc['parent']) {
                    $parentName = $l['name'];
                }
            }
            $rows[] = ['section' => 'ams_location', 'name' => $loc['name'], 'parent' => $parentName, 'code' => '', 'state' => $existing ? 'exists' : 'new'];
        }

        return $rows;
    }

    /** Match by name (case-insensitive via the table collation) and parent when the table is a tree. */
    private function find($table, $name, $parentId = null)
    {
        $this->db->where('name', $name);
        if ($parentId !== null) {
            $this->db->where('parent_id', (int) $parentId);
        }

        return $this->db->get($table)->row();
    }

    private function legacy_category_name($legacyId)
    {
        foreach ($this->data['categories'] as $cat) {
            if ($cat['id'] === $legacyId) {
                return $cat['name'];
            }
        }

        return '';
    }
}
