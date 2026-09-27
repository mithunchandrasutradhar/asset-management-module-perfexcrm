<?php

defined('BASEPATH') or exit('No direct script access allowed');

// The HostBill webhook is called server-to-server (no CRM session / CSRF token);
// it authenticates with its own secret token instead.
return [
    'asset_management/hostbill_webhook.*',
];
