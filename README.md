# Asset Management for Perfex CRM

IT asset management built into Perfex CRM. It covers:

- an asset register, with check-out / check-in and a full history;
- maintenance, software licences and purchase orders;
- depreciation, QR / barcode labels, physical audits and reports;
- a read-only HostBill inventory view with low-stock alerts.

Quantity stock (accessories, consumables, goods for sale) is not tracked here: product stock is managed in HostBill, and anything worth tracking inside the company, including small items such as a mouse or headset, is registered as an asset.

It uses Perfex's own UI, roles, staff, departments, email templates, notifications and data tables. There is no separate user system and no HR-module dependency.

| | |
|---|---|
| Module folder | `modules/asset_management` |
| Requires | Perfex CRM 3.3.x, PHP 8.1+, MySQL / MariaDB, PHP `zip` (for .xlsx import) and `curl` (for HostBill) |
| Languages | English, Bangla |
| Database prefix | every table is `tblams_*`; permissions, options and language keys start with `ams_` |
| Author | Alpha Net BD |

---

## Installation

1. Copy this folder to `modules/asset_management` in your Perfex installation.
2. In Perfex go to **Setup → Modules**, find **Asset Management** and click **Activate**. The tables, options, email templates and default statuses are created automatically.
3. Make sure the Perfex cron job runs every few minutes (**Setup → Settings → Cron Job**).
4. Give people access: **Setup → Staff → Roles**, where every module permission starts with **"AMS - "** (see [Permissions](#permissions)).
5. Configure the module in **Assets → Setup** (see [First steps](#first-steps)).

**Updating:** replace the files. The database is upgraded automatically on the next admin page load (or cron run); there is no need to deactivate and reactivate.

**Web server:** uploads (asset photos, files, signatures) are stored under `uploads/asset_management/`. Apache is protected by the `.htaccess` file the module writes. On **nginx**, add:

```nginx
location ^~ /uploads/asset_management/ { deny all; return 404; }
```

---

## Features

Everything lives in the **Assets** menu in the Perfex sidebar.

### Asset register

- Asset list with Perfex filters (including saved filters), search, sort, export and bulk actions.
- Auto-generated asset tags, e.g. `ANB-LAP-00042`: prefix + category code + sequence, all configurable. Tags may use letters, digits, `.`, `-` and `_`, so they work in QR codes and barcodes.
- Asset details:
  - serial number, category / sub-category, brand, model, supplier, condition, location, notes;
  - Perfex custom fields.
- **Source:** purchased, gift (from a staff member, a supplier or a free-text giver), leased (with lease end date) or transferred in.
- **Purchase and warranty:** cost and currency (Perfex currencies), invoice and order number, purchased by, warranty dates, provider and notes, with a valid / expiring / expired indicator.
- **Photos and files:** a cover image, a gallery and documents. Files are served only after a permission check.
- **Asset page tabs:** Overview, History (every movement), Change log (field-level old → new values), Photos & files, Maintenance, Licences, Finance.
- **Clone** an asset. The details are copied; the new asset gets its own tag and needs a new serial number.
- **Duplicate serial warning** after saving.
- **Delete with a reason**, and **Deleted assets** with **Restore**. Deleting keeps the history and closes the asset's open jobs, schedules and licence seats.
- **Bulk actions:**
  - check out to a staff member, or check in;
  - change status, move location, delete, print labels.
- **Assets by Staff** and **My Assets.** Every staff member sees what they hold, signs receipts and raises requests. There is also a shortcut from the Perfex staff profile.

### Check-out, check-in and statuses

- Check out to a **Perfex staff member**, a **Perfex department** (shared equipment) or a **location**, with an expected return date and condition.
- Check in to a location with a status and condition.
- **Statuses** are configurable, each with a type:
  - *deployable* (e.g. In Store), *deployed* (Assigned), *pending* (Maintenance), *undeployable* (Damaged) or *archived* (Sold, Donated, Stolen / Lost, Retired);
  - flags for "requires location" and "requires note".
- Archived statuses are set only through **Dispose** and left only through **Reinstate**, so the disposal register always matches.
- **Signed receipts:**
  - the staff member accepts an assignment with a drawn signature;
  - terms can be set per category or globally;
  - mode *always* / *per category* / *never*;
  - managers are notified of declines.
- **Overdue return reminders** to the holder and the managers, repeated every N days.
- **Staff deactivation and deletion:**
  - deactivating a staff member who holds assets gives a warning, or a hard block (setting);
  - on deletion, held assets move to Perfex's "transfer data to" staff member.

### Requests and approvals

- Staff request an asset, or report an issue with an asset they hold.
- Two-stage approval:
  1. the **department approvers** of the requester's Perfex department (Assets → Setup → Department Approvers);
  2. staff with **Approve** permission.
- Nobody approves their own request (admins excepted). When no one other than the requester can approve at department level, the request goes straight to the managers.
- **Fulfilment** checks out an In Store asset to the requester. An approved request can also be turned into a purchase order.
- An issue report can be turned into a maintenance job; completing the job closes the request.
- Approvers are notified of cancelled requests.

### Maintenance

- Maintenance jobs per asset: repair, upgrade, preventive, inspection, software or other, with vendor, due date, cost, downtime and resolution.
- Starting a job can put the asset into "Maintenance". The holder keeps it, and completing the job restores the previous status.
- **Preventive schedules**, e.g. "clean the AC every 6 months":
  - jobs are opened automatically a set number of days before the due date, and the asset managers are notified;
  - the next date counts from when the work was actually done;
  - a cancelled or deleted job moves the schedule on to its next date.
- **Responsible staff:** each job and schedule can have responsible staff and / or Perfex departments.
  - Jobs opened by a schedule copy its responsible people; a "not working" check can open a follow-up repair job for the same people.
  - Responsible staff are notified when assigned, when the job is due and when it is overdue (repeated every N days). Asset managers also get the alerts (setting), and are told once when a job is not acknowledged after N days.
  - They see their jobs in **My Assets → My Maintenance Jobs** and on the job page can **Acknowledge**, add **progress notes**, **Start** and **Complete** (with "Is the asset working?": working / partly / not working) without the Maintenance *Edit* permission.
  - The list has a Responsible column, "My jobs", filters (responsible, unassigned, not acknowledged, check result) and bulk **Change responsible**. The Maintenance Cost report can be grouped by responsible.
  - The reporter of an issue is told who is handling it. Deactivating a responsible staff member warns; deleting one moves their jobs to the "transfer data to" person.

### Software licences

- Licence type, seats, licensed to, supplier, order number, purchase cost, expiry, renewal cost, auto-renew.
- Seats are assigned to staff or to assets. The number of seats can't drop below the number in use.
- **Licence keys:**
  - stored encrypted;
  - shown only with **View licence keys**, and every reveal is logged;
  - can be removed.
- Expiry reminders to the asset managers, once per expiry date and again after renewal.

### Purchase orders

- Suppliers are kept as setup records (contact, email, website, address).
- **Purchase orders** are for assets (category / brand / model, quantity, unit cost). The flow:
  1. draft;
  2. approval (optional, no self-approval);
  3. PDF emailed to the supplier, or marked as sent;
  4. goods receipt.
- **Goods receipt** is all-or-nothing and can be partial. Each received unit becomes an asset, with serial, warranty months and invoice number.
- **Purchase register** with totals by period, category and supplier.

### Finance

- **Depreciation**, straight-line or declining balance:
  - useful life and salvage % per category;
  - sub-categories inherit from their parent category;
  - override per asset.
- Book values are refreshed on save, daily and with "Recalculate now". A disposed asset's value is frozen at its disposal date.
- **Total cost of ownership**: purchase + completed maintenance + the licence seats currently on the asset.
- **Disposal**:
  - method, date, proceeds, recipient and reference, recording book value and gain / loss;
  - closes jobs, schedules and seats;
  - can be reversed.

### Labels, scanning and physical audits

- **QR code or Code 128 labels** as PDF (Perfex's TCPDF), one per page (thermal printer) or an A4 sheet. Size, fields and logo are set in Settings.
- **Scan:** the label's QR code opens the asset on a phone. A USB scanner or a typed tag / serial works too.
- **Physical audits:**
  - scope by location, department and category;
  - starting takes a snapshot;
  - record by scanner, phone camera ("scan mode") or ticking;
  - results: found, misplaced, unexpected, missing;
  - completing sets the last-audit dates and can move misplaced assets.

### HostBill inventory (read-only)

- **Assets → HostBill Inventory** shows the product stock from HostBill: in stock / low / out tiles, filters, search, "Refresh now" and a sync log.
- Perfex only **reads** from HostBill (order pages and products) and never writes to it.
- Everything is configurable in **Assets → Setup → HostBill Settings**:
  - connection;
  - automatic refresh and interval;
  - which products are shown, including hidden ones;
  - the default low-stock level, and whether each product can have its own level;
  - low / out / refresh-failure alerts, email on/off and recipients;
  - log retention.
- Alerts are sent once per drop and reset when stock is refilled. The API key is stored encrypted.

### Dashboard and reports

- **Dashboard:**
  - assets by status, total asset value and book value, top categories, latest assets;
  - warranties and licences expiring;
  - open and overdue maintenance, POs awaiting approval, running audits, HostBill stock.
- **Reports hub** (Assets → Reports):
  - asset register, asset history, assets by staff, warranty expiry, purchase register;
  - valuation (by category, location or department), depreciation forecast (chart), maintenance cost (chart), disposal register;
  - audits, maintenance, licences, purchase orders, HostBill inventory.

  Every grid is a standard Perfex table with Excel / CSV / PDF / print export.

### Import and data

- **Import** (Assets → Import) of assets and suppliers from CSV or .xlsx, up to 5,000 rows / 10 MB:
  1. automatic column mapping;
  2. a validation-only preview;
  3. the import itself;
  4. a result / error report as CSV.
- Existing records can be skipped or updated. Missing masters can be created. Notifications are off during import unless ticked.
- **Legacy import** (admin only) for the master data of the previous asset application.
- **Setup → Change log**: who created, changed or deleted categories, brands, models, statuses, locations and suppliers.

### Notifications and email

Bell notifications, plus 12 editable email templates in **Setup → Email Templates → Asset Management**:

- asset assigned;
- acceptance declined;
- request submitted;
- request updated;
- overdue return;
- HostBill low / out-of-stock alert;
- HostBill inventory refresh alert;
- purchase order to supplier;
- maintenance due;
- maintenance assigned (to responsible staff);
- maintenance overdue (to responsible staff);
- licence expiring.

Email for staff notifications can be switched off globally.

---

## Permissions

All permissions are standard Perfex permissions (**Setup → Staff → Roles**):

| Permission | Capabilities |
|---|---|
| AMS - Assets | View own (My Assets) / view all / create / edit / delete, **Check out**, **Check in**, **Dispose** |
| AMS - Asset Requests | Create and view own, view all, **Approve** |
| AMS - Maintenance | View own (jobs I am responsible for) / view all / create / edit / delete (jobs and schedules) |
| AMS - Software Licences | View own / view all / create / edit / delete, **View licence keys** |
| AMS - Procurement | View / create / edit / delete, **Approve purchase orders** |
| AMS - Physical Audits | View / create / edit (scan, complete) / delete |
| AMS - Reports | View |
| AMS - HostBill Integration | View, **Edit low-stock levels**, **Refresh now** |
| AMS - Setup (Masters) | Categories, brands, models, statuses, locations, suppliers, department approvers, change log |
| AMS - Settings | View / edit the module settings |

All permissions are checked on the server:
- every page, table, AJAX action and file download checks its permission;
- "view own" users only reach their own records;
- every change is a POST request protected by Perfex's CSRF token.

---

## First steps

1. **Assets → Setup → General Settings:**
   - asset tag format;
   - **asset manager recipients**, who get the reminders (active admins when empty);
   - acceptance mode and terms;
   - label size.
2. **Setup → Categories:**
   - add each category with its **code** (used in tags) and depreciation values;
   - then statuses, locations, brands, models and suppliers.
3. **Setup → Department Approvers:** who approves requests for each Perfex department.
4. Print one test label on the real printer and adjust the size.
5. Import existing assets (**Assets → Import**), reviewing the preview first.
6. Optional: **HostBill Settings**:
   1. create a read-only API key in HostBill and allow the CRM server's IP;
   2. **Test connection**;
   3. **Refresh now**.

---

## Cron jobs

The module runs on the Perfex cron (never call anything else):

| Job | Frequency |
|---|---|
| Database upgrade check after a module update | every run |
| HostBill inventory refresh and alerts | every *N* minutes (setting) |
| Overdue-return reminders | every 6 hours |
| Maintenance schedules, overdue / not-acknowledged maintenance alerts, licence expiry reminders | every 6 hours |
| Book value (depreciation) refresh | daily |

---

## Uninstalling

**Setup → Modules → Asset Management → Uninstall** removes everything the module added:

- all `tblams_*` tables;
- the `ams_*` options and permissions;
- the module's email templates, custom fields and notifications.

Take a database backup first. Uploaded files stay in `uploads/asset_management/` until you delete them.

---

## Security notes

- HostBill API keys and licence keys are stored encrypted. Every licence-key reveal is logged.
- Table filter values that look like SQL expressions are rejected on the module's tables. Perfex core's `App_table::wrapValueInQuotes` leaves such values unquoted on **all** Perfex tables, so apply that fix to Perfex itself as well.
- Uploads are served only through a permission check. Only plain images display inline; everything else downloads.

---

## Not included yet

These are planned but not built:

- parent / child assets and components;
- bulk-creating several assets from one form;
- a transfer workflow with approval;
- disposal certificate upload, data-wipe confirmation and approval;
- attachments on maintenance jobs;
- images on asset models;
- posting purchases as Perfex Expenses;
- a support-ticket link;
- a REST API.
