<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Cleaned master data from the legacy Asset Management app
 * (dump: assets_20-05-2026-05-54-57.sql).
 *
 * Imported: all 44 categories (names unchanged, tag codes added, icons converted
 * to Font Awesome 6 names without the legacy colour classes), the 10 genuine
 * brands and the 2 storage locations.
 *
 * Deliberately NOT imported:
 * - products (2 rows) / asset_status (203 rows): test assets; 202 history rows are
 *   SQL-injection scanner payloads recorded on 2024-02-28.
 * - brands 1-4 and 16-19: injection output (DB version, hashes, "MvEbNNyB").
 * - contact / users: people now come from Perfex staff.
 * - purchase: empty. update_log / test: not relevant.
 *
 * 'id' / 'parent' are the legacy IDs, used only to rebuild the tree.
 */
return [
    'categories' => [
        // Parents
        ['id' => 1,   'parent' => 0,   'name' => 'Computer Accessories', 'code' => 'CA',  'icon' => null],
        ['id' => 2,   'parent' => 0,   'name' => 'Internet Accessories', 'code' => 'NET', 'icon' => null],
        ['id' => 3,   'parent' => 0,   'name' => 'Phones',               'code' => 'PHN', 'icon' => null],
        ['id' => 4,   'parent' => 0,   'name' => 'Accessories',          'code' => 'ACC', 'icon' => null],
        ['id' => 8,   'parent' => 0,   'name' => 'More',                 'code' => 'MOR', 'icon' => null],
        ['id' => 116, 'parent' => 0,   'name' => 'Crokarise',            'code' => 'CRK', 'icon' => null],

        // Computer Accessories
        ['id' => 9,   'parent' => 1,   'name' => 'Monitor',              'code' => 'MON', 'icon' => 'fa-solid fa-desktop'],
        ['id' => 10,  'parent' => 1,   'name' => 'Mini PC',              'code' => 'MPC', 'icon' => 'fa-regular fa-hdd'],
        ['id' => 11,  'parent' => 1,   'name' => 'Keyboard & Mouse',     'code' => 'KBM', 'icon' => 'fa-solid fa-keyboard'],
        ['id' => 13,  'parent' => 1,   'name' => 'Pendrive',             'code' => 'USB', 'icon' => 'fa-brands fa-usb'],
        ['id' => 15,  'parent' => 1,   'name' => 'Head Phones',          'code' => 'HPH', 'icon' => 'fa-solid fa-headphones'],
        ['id' => 96,  'parent' => 1,   'name' => 'Laptop',               'code' => 'LAP', 'icon' => 'fa-solid fa-laptop'],
        ['id' => 104, 'parent' => 1,   'name' => 'PC',                   'code' => 'PC',  'icon' => 'fa-solid fa-server'],
        ['id' => 106, 'parent' => 1,   'name' => 'SSD & HDD',            'code' => 'HDD', 'icon' => 'fa-solid fa-hdd'],
        ['id' => 108, 'parent' => 1,   'name' => 'RAM',                  'code' => 'RAM', 'icon' => 'fa-solid fa-memory'],
        ['id' => 134, 'parent' => 1,   'name' => 'Others',               'code' => 'CAO', 'icon' => null],

        // Internet Accessories
        ['id' => 17,  'parent' => 2,   'name' => 'Modem',                'code' => 'MDM', 'icon' => 'fa-solid fa-ethernet'],
        ['id' => 18,  'parent' => 2,   'name' => 'Router',               'code' => 'RTR', 'icon' => 'fa-solid fa-wifi'],
        ['id' => 19,  'parent' => 2,   'name' => 'Wifi Adapter',         'code' => 'WFA', 'icon' => 'fa-solid fa-broadcast-tower'],
        ['id' => 20,  'parent' => 2,   'name' => 'Internet Cable',       'code' => 'CBL', 'icon' => 'fa-solid fa-network-wired'],
        ['id' => 127, 'parent' => 2,   'name' => 'Phone',                'code' => 'IPP', 'icon' => null],
        ['id' => 135, 'parent' => 2,   'name' => 'Others',               'code' => 'NTO', 'icon' => null],

        // Phones
        ['id' => 22,  'parent' => 3,   'name' => 'Handset',              'code' => 'HST', 'icon' => 'fa-solid fa-mobile'],
        ['id' => 43,  'parent' => 3,   'name' => 'Wired Phones',         'code' => 'WPH', 'icon' => 'fa-solid fa-blender-phone'],
        ['id' => 136, 'parent' => 3,   'name' => 'Others',               'code' => 'PHO', 'icon' => null],

        // Accessories
        ['id' => 23,  'parent' => 4,   'name' => 'Printer',              'code' => 'PRN', 'icon' => 'fa-solid fa-print'],
        ['id' => 25,  'parent' => 4,   'name' => 'Fan',                  'code' => 'FAN', 'icon' => 'fa-solid fa-fan'],
        ['id' => 26,  'parent' => 4,   'name' => 'Mini Fan',             'code' => 'MFN', 'icon' => 'fa-solid fa-fan'],
        ['id' => 27,  'parent' => 4,   'name' => 'Bulp',                 'code' => 'BLB', 'icon' => 'fa-regular fa-lightbulb'],
        ['id' => 28,  'parent' => 4,   'name' => 'Multi-Plug',           'code' => 'MPL', 'icon' => 'fa-solid fa-plug'],
        ['id' => 110, 'parent' => 4,   'name' => 'Stationeries',         'code' => 'STN', 'icon' => 'fa-brands fa-stripe-s'],
        ['id' => 111, 'parent' => 4,   'name' => 'Kitchen Accessories',  'code' => 'KIT', 'icon' => 'fa-solid fa-sink'],
        ['id' => 112, 'parent' => 4,   'name' => 'Tools',                'code' => 'TLS', 'icon' => 'fa-solid fa-tools'],
        ['id' => 114, 'parent' => 4,   'name' => 'Furnitures',           'code' => 'FUR', 'icon' => 'fa-solid fa-couch'],
        ['id' => 137, 'parent' => 4,   'name' => 'Others',               'code' => 'ACO', 'icon' => null],

        // More
        ['id' => 64,  'parent' => 8,   'name' => 'Others',               'code' => 'OTH', 'icon' => 'fa-solid fa-expand-arrows-alt'],

        // Crokarise
        ['id' => 117, 'parent' => 116, 'name' => 'Half Plate',           'code' => 'HPL', 'icon' => null],
        ['id' => 118, 'parent' => 116, 'name' => 'Full Plate',           'code' => 'FPL', 'icon' => null],
        ['id' => 119, 'parent' => 116, 'name' => 'Spoon',                'code' => 'SPN', 'icon' => null],
        ['id' => 120, 'parent' => 116, 'name' => 'Glass',                'code' => 'GLS', 'icon' => 'fa-solid fa-glass-water'],
        ['id' => 121, 'parent' => 116, 'name' => 'Cup & Saucer',         'code' => 'CUP', 'icon' => null],
        ['id' => 123, 'parent' => 116, 'name' => 'Serving Tray',         'code' => 'TRY', 'icon' => null],
        ['id' => 124, 'parent' => 116, 'name' => 'Knife',                'code' => 'KNF', 'icon' => 'fa-solid fa-utensils'],
        ['id' => 138, 'parent' => 116, 'name' => 'Others',               'code' => 'CRO', 'icon' => null],
    ],

    'brands' => [
        'Redmi', 'HP', 'Grandstream', 'A4Tech', 'Rapoo', 'Gree', 'CITISUN', 'EPSON', 'Xiaomi', 'Plextone',
    ],

    'locations' => [
        ['id' => 1, 'parent' => 0, 'name' => 'Bogura Branch', 'type' => 'office',  'address' => 'Bogura Office'],
        ['id' => 2, 'parent' => 1, 'name' => 'Cabin 1',       'type' => 'cabinet', 'address' => null],
    ],
];
