<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
// Customer profile → "Hardware Orders" tab: HostBill orders matched to this
// customer by contact email. Rendered inside the core customer page.
?>
<h4 class="customer-profile-group-heading"><?= _l('ams_hb_customer_tab'); ?></h4>
<?php if (isset($client)) { ?>
<?php render_datatable([
    _l('ams_date'), _l('ams_hb_order'), _l('ams_hb_client'), _l('ams_hb_order_status'), _l('ams_hb_invoice_status'),
    _l('ams_hb_total'), _l('ams_hb_items'), _l('ams_hb_stock_state'), _l('ams_hb_fulfilment'), _l('ams_hb_synced'),
], 'ams-hb-customer-orders'); ?>
<script>
    window.addEventListener('load', function() {
        initDataTable('.table-ams-hb-customer-orders', admin_url + 'asset_management/hostbill/orders_table/<?= (int) $client->userid; ?>', [], [], {}, [0, 'desc'])
            .column(2).visible(false, false);
    });
</script>
<?php } ?>
