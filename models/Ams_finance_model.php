<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Depreciation, book value and total cost of ownership.
 *
 * Policy: the asset's own method / useful life / salvage value when set,
 * otherwise its category's (a sub-category without a method uses its parent's).
 * Depreciation is charged for every full month since the purchase date:
 * - straight line: (cost - salvage) / life per month;
 * - declining balance: book value x (factor / life) per month (factor 2 =
 *   double-declining), switching to straight line when that is higher, so the
 *   book value reaches the salvage value exactly at the end of the life.
 * Current values are stored on the asset (dep_accumulated / dep_book_value) so
 * the valuation report can search, sort and filter them; they are refreshed on
 * asset / category save, daily by cron, and on demand. A disposed asset's value
 * is frozen at its disposal date.
 */
class Ams_finance_model extends App_Model
{
    private $categoryCache = [];

    private function t($table)
    {
        return db_prefix() . $table;
    }

    private function category($id)
    {
        $id = (int) $id;
        if (! $id) {
            return null;
        }
        if (! array_key_exists($id, $this->categoryCache)) {
            $this->categoryCache[$id] = $this->db->select('id, parent_id, name, depreciation_method, useful_life_months, salvage_percent')
                ->where('id', $id)->get($this->t('ams_categories'))->row_array();
        }

        return $this->categoryCache[$id];
    }

    /** The category (or its parent) that defines a depreciation method. */
    private function policy_category($categoryId)
    {
        $cat = $this->category($categoryId);
        if (! $cat) {
            return null;
        }
        if ($cat['depreciation_method']) {
            return $cat;
        }
        $parent = $cat['parent_id'] ? $this->category($cat['parent_id']) : null;

        return $parent && $parent['depreciation_method'] ? $parent : $cat;
    }

    /**
     * @param object|array $asset needs category_id, purchase_cost, depreciation_method, useful_life_months, salvage_value
     * @return array method, life, salvage, source (asset|category|none), category
     */
    public function policy($asset)
    {
        $asset = (object) $asset;
        $cat   = $this->policy_category($asset->category_id ?? null);
        $cost  = $asset->purchase_cost !== null ? (float) $asset->purchase_cost : null;

        $method = null;
        $source = 'none';
        if (! empty($asset->depreciation_method)) {
            $method = $asset->depreciation_method;
            $source = 'asset';
        } elseif ($cat && $cat['depreciation_method']) {
            $method = $cat['depreciation_method'];
            $source = 'category';
        }
        if (! in_array($method, ['straight_line', 'declining_balance'], true)) {
            $method = 'none';
        }

        $life = (int) ($asset->useful_life_months ?? 0) ?: (int) ($cat['useful_life_months'] ?? 0);

        if (isset($asset->salvage_value) && $asset->salvage_value !== null && $asset->salvage_value !== '') {
            $salvage = (float) $asset->salvage_value;
        } else {
            $salvage = $cost !== null ? round($cost * (float) ($cat['salvage_percent'] ?? 0) / 100, 2) : 0.0;
        }
        if ($cost !== null) {
            $salvage = min(max(0, $salvage), $cost);
        }

        return ['method' => $method, 'life' => $life, 'salvage' => $salvage, 'source' => $source, 'category' => $cat['name'] ?? null];
    }

    /** Full months from $from to $to (0 when $to is before $from). */
    public function months_between($from, $to)
    {
        $a = new DateTime($from);
        $b = new DateTime($to);
        if ($b < $a) {
            return 0;
        }
        $d = $a->diff($b);

        return $d->y * 12 + $d->m;
    }

    /**
     * Monthly schedule + values as of $asOf (default today).
     *
     * @return array applicable, reason, policy, rows[period, date, depreciation, accumulated, book_value],
     *               months_elapsed, accumulated, book_value, end_date
     */
    public function schedule($asset, $asOf = null)
    {
        $asset  = (object) $asset;
        $policy = $this->policy($asset);
        $asOf   = $asOf ?: date('Y-m-d');
        $cost   = $asset->purchase_cost !== null ? (float) $asset->purchase_cost : null;

        $result = ['applicable' => false, 'reason' => '', 'policy' => $policy, 'rows' => [], 'months_elapsed' => 0,
            'accumulated' => $cost !== null ? 0.0 : null, 'book_value' => $cost, 'end_date' => null];

        if ($policy['method'] === 'none') {
            $result['reason'] = _l('ams_dep_reason_none');

            return $result;
        }
        if ($cost === null || $cost <= 0 || empty($asset->purchase_date)) {
            $result['reason'] = _l('ams_dep_reason_data');

            return $result;
        }
        if ($policy['life'] < 1) {
            $result['reason'] = _l('ams_dep_reason_life');

            return $result;
        }

        $life    = $policy['life'];
        $salvage = $policy['salvage'];
        $bv      = $cost;
        $acc     = 0.0;
        $factor  = (float) get_option('ams_declining_factor') > 0 ? (float) get_option('ams_declining_factor') : 2.0;
        $rows    = [];

        for ($k = 1; $k <= $life; $k++) {
            $left = $bv - $salvage;
            if ($k === $life) {
                $dep = $left; // last period lands exactly on the salvage value
            } elseif ($policy['method'] === 'straight_line') {
                $dep = ($cost - $salvage) / $life;
            } else {
                $dep = max($bv * $factor / $life, $left / ($life - $k + 1));
            }
            $dep = round(min(max(0, $dep), $left), 2);
            $bv  = round($bv - $dep, 2);
            $acc = round($acc + $dep, 2);

            $rows[] = [
                'period'       => $k,
                'date'         => date('Y-m-d', strtotime($asset->purchase_date . ' +' . $k . ' months')),
                'depreciation' => $dep,
                'accumulated'  => $acc,
                'book_value'   => $bv,
            ];
        }

        $elapsed = min($life, $this->months_between($asset->purchase_date, $asOf));

        $result['applicable']     = true;
        $result['rows']           = $rows;
        $result['months_elapsed'] = $elapsed;
        $result['accumulated']    = $elapsed ? $rows[$elapsed - 1]['accumulated'] : 0.0;
        $result['book_value']     = $elapsed ? $rows[$elapsed - 1]['book_value'] : $cost;
        $result['end_date']       = $rows[$life - 1]['date'];

        return $result;
    }

    /** Refresh the stored book values (all assets, or the given IDs). Returns the number of rows changed. */
    public function recalculate($assetIds = null)
    {
        $this->categoryCache = [];

        // Validate the IDs before touching the query builder: returning early with a
        // half-built query would leak it into the next query of the request.
        if ($assetIds !== null) {
            $assetIds = array_values(array_filter(array_map('intval', (array) $assetIds)));
            if (! $assetIds) {
                return 0;
            }
        }

        $this->db->select('a.id, a.category_id, a.purchase_cost, a.purchase_date, a.depreciation_method, a.useful_life_months,
            a.salvage_value, a.dep_accumulated, a.dep_book_value, d.disposal_date')
            ->from($this->t('ams_assets') . ' a')
            ->join($this->t('ams_disposals') . ' d', 'd.asset_id = a.id', 'left')
            ->where('a.is_deleted', 0);
        if ($assetIds !== null) {
            $this->db->where_in('a.id', $assetIds);
        }

        @set_time_limit(600);
        $today = date('Y-m-d');
        $fmt   = fn ($v) => $v === null ? null : number_format($v, 2, '.', '');
        $rows  = [];
        foreach ($this->db->get()->result() as $a) {
            $s   = $this->schedule($a, $a->disposal_date ?: $today);
            $acc = $s['accumulated'] !== null ? $fmt(round((float) $s['accumulated'], 2)) : null;
            $bv  = $s['book_value'] !== null ? $fmt(round((float) $s['book_value'], 2)) : null;

            // Only rows whose values moved are written (book values change once a month).
            if ($a->dep_accumulated !== $acc || $a->dep_book_value !== $bv) {
                $rows[] = ['id' => (int) $a->id, 'dep_accumulated' => $acc, 'dep_book_value' => $bv, 'dep_calculated_at' => $today];
            }
        }

        // One transaction, batched: thousands of single autocommitted UPDATEs are slow on most disks.
        $this->db->trans_start();
        foreach (array_chunk($rows, 500) as $chunk) {
            $this->db->update_batch($this->t('ams_assets'), $chunk, 'id');
        }
        $this->db->trans_complete();

        return count($rows);
    }

    /** A category's depreciation defaults changed: refresh its assets and its sub-categories' assets. */
    public function recalculate_category($categoryId)
    {
        $ids = ams_category_with_children($categoryId);
        if (! $ids) {
            return 0;
        }
        $assetIds = array_column($this->db->select('id')->where_in('category_id', $ids)->where('is_deleted', 0)->get($this->t('ams_assets'))->result_array(), 'id');

        return $assetIds ? $this->recalculate($assetIds) : 0;
    }

    /**
     * Total cost of ownership: purchase + completed maintenance + share of the
     * licences installed on the asset (licence cost / seats, per seat).
     */
    public function tco($asset)
    {
        $asset = (object) $asset;
        $maint = (float) $this->db->query('SELECT IFNULL(SUM(cost), 0) c FROM ' . $this->t('ams_maintenance') . ' WHERE asset_id = ? AND status = "completed"', [(int) $asset->id])->row()->c;
        $lic   = (float) $this->db->query('SELECT IFNULL(SUM(l.purchase_cost / GREATEST(l.seats, 1)), 0) c
            FROM ' . $this->t('ams_license_seats') . ' s JOIN ' . $this->t('ams_licenses') . ' l ON l.id = s.license_id
            WHERE s.assigned_type = "asset" AND s.assigned_id = ? AND l.purchase_cost IS NOT NULL', [(int) $asset->id])->row()->c;

        $purchase = $asset->purchase_cost !== null ? (float) $asset->purchase_cost : 0.0;
        $base     = get_base_currency();
        // Maintenance and licence costs are in the base currency.
        $mixed = $asset->purchase_cost !== null && ($maint > 0 || $lic > 0) && (int) $asset->currency !== (int) $base->id;

        return [
            'purchase'    => $purchase,
            'maintenance' => round($maint, 2),
            'licenses'    => round($lic, 2),
            'total'       => $mixed ? null : round($purchase + $maint + $lic, 2),
            'mixed'       => $mixed,
        ];
    }

    /**
     * Totals by category / location / department (one row per group and currency).
     * Disposed (archived-status) assets are left out unless $includeArchived.
     */
    public function valuation_summary($groupBy, $includeArchived = false)
    {
        $p = db_prefix();
        switch ($groupBy) {
            case 'location':
                $label = 'IF(pl.id IS NULL, l.name, CONCAT(pl.name, " › ", l.name))';
                $join  = 'LEFT JOIN ' . $p . 'ams_locations l ON l.id = a.location_id LEFT JOIN ' . $p . 'ams_locations pl ON pl.id = l.parent_id';
                $key   = 'a.location_id';
                break;
            case 'department':
                $label = 'dp.name';
                $join  = 'LEFT JOIN ' . $p . 'departments dp ON dp.departmentid = a.department_id';
                $key   = 'a.department_id';
                break;
            default:
                $label = 'IF(pc.id IS NULL, c.name, CONCAT(pc.name, " › ", c.name))';
                $join  = 'LEFT JOIN ' . $p . 'ams_categories c ON c.id = a.category_id LEFT JOIN ' . $p . 'ams_categories pc ON pc.id = c.parent_id';
                $key   = 'a.category_id';
        }

        $where = 'a.is_deleted = 0';
        if (! $includeArchived) {
            $where .= ' AND a.status_id NOT IN (SELECT id FROM ' . $p . 'ams_statuses WHERE type = "archived")';
        }

        return $this->db->query('SELECT ' . $label . ' label, a.currency, COUNT(*) assets,
                SUM(a.purchase_cost) cost, SUM(a.dep_accumulated) accumulated, SUM(a.dep_book_value) book_value
            FROM ' . $p . 'ams_assets a ' . $join . '
            WHERE ' . $where . '
            GROUP BY ' . $key . ', a.currency
            ORDER BY label IS NULL, label, a.currency')->result_array();
    }

    /** Net book value of the assets still in service, per currency (dashboard). */
    public function book_value_totals()
    {
        return $this->db->query('SELECT a.currency, SUM(a.dep_book_value) total FROM ' . $this->t('ams_assets') . ' a
            WHERE a.is_deleted = 0 AND a.dep_book_value IS NOT NULL ' . ams_assets_scope_where('a') . '
            AND a.status_id NOT IN (SELECT id FROM ' . $this->t('ams_statuses') . ' WHERE type = "archived")
            GROUP BY a.currency')->result_array();
    }
}
