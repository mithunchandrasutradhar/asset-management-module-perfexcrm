<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lists the Asset Management merge fields in the email template editor.
 * Values are supplied by the module when sending (see ams_send_email()).
 */
class Ams_merge_fields extends App_merge_fields
{
    public function build()
    {
        $all = ['ams-asset-assigned', 'ams-acceptance-declined', 'ams-request-submitted', 'ams-request-updated', 'ams-overdue-return', 'ams-low-stock', 'ams-system-alert', 'ams-maintenance-due', 'ams-license-expiring', 'ams-purchase-order'];

        $field = fn ($name, $key, $templates) => ['name' => $name, 'key' => $key, 'available' => ['ams'], 'templates' => $templates];

        return [
            $field('Staff Firstname (recipient)', '{staff_firstname}', $all),
            $field('Staff Lastname (recipient)', '{staff_lastname}', $all),
            $field('Asset / Item', '{ams_item}', $all),
            $field('Details', '{ams_details}', $all),
            $field('Link', '{ams_link}', $all),
            $field('Status', '{ams_status}', ['ams-request-updated', 'ams-low-stock', 'ams-system-alert']),
            $field('Done by', '{ams_by}', ['ams-asset-assigned']),
            $field('Action text', '{ams_action_text}', ['ams-asset-assigned']),
            $field('Staff name (subject of the email)', '{ams_staff_name}', ['ams-acceptance-declined', 'ams-request-submitted']),
            $field('Request number', '{ams_request_no}', ['ams-request-submitted', 'ams-request-updated']),
            $field('Due date', '{ams_due_date}', ['ams-overdue-return', 'ams-maintenance-due', 'ams-license-expiring']),
            $field('PO number', '{ams_po_number}', ['ams-purchase-order']),
            $field('Supplier name', '{ams_supplier}', ['ams-purchase-order']),
            $field('Company name', '{ams_company}', ['ams-purchase-order']),
        ];
    }

    public function format($fields = [])
    {
        return (array) $fields;
    }
}
