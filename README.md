# CAPO Inventory 1.0.0

WordPress plugin for the Cyprus Agricultural Payments Organisation's IT equipment register.
Author: George Oustas, Pixelcrafters.com.cy — https://www.pixelcrafters.com.cy

This file covers how the plugin works, the assumptions made while building it, and the state of the acceptance checks. End-user installation notes are in `readme.txt`.

---

## 1. What is in this version

| Area | Included |
|---|---|
| Asset Tag / Inventory Number (text, unique, required) | Yes |
| Description / Make and Model (optional) | Yes |
| Serial Number optional, non-blank values unique | Yes |
| Employee registration, change and removal requests | Yes |
| Approval with side-by-side comparison | Yes |
| All Assets and per-employee inventory views | Yes |
| Audit log with field-level changes | Yes |
| CSV export (Plain and Excel-friendly) | Yes |
| **Employee Equipment Acknowledgement PDF** | **No — deferred** (see section 7) |

## 2. Asset record

| Field | Rule |
|---|---|
| Internal ID | Auto-generated database ID, separate from the Asset Tag. |
| Asset Tag / Inventory Number | Required. Text, so `000123` keeps its leading zeros. Letters, numbers and hyphens; must start and end with a letter or number; up to 64 characters. Unique across **active and archived** assets and pending requests, ignoring letter case and surrounding whitespace. |
| Asset Type | Required dropdown (Settings → Asset Types). |
| Description / Make and Model | Optional, up to 190 characters. |
| Location | Required dropdown (Settings → Locations). |
| Serial Number | Optional for all types. Blank is always accepted. Non-blank values are unique (case and surrounding whitespace ignored). |
| Notes | Optional, up to 2,000 characters. |
| Assigned Employee | For employee submissions, always the logged-in account (never taken from the form). Managers choose from users with the CAPO Employee, Inventory Manager or Administrator role. |
| Record Status | Active or Archived. |
| Audit timestamps | Created, Last Updated, Archived — stored in UTC, shown in the WordPress timezone. |

Uniqueness is enforced twice: by the plugin before saving, and by `UNIQUE` indexes on the normalised tag and serial columns, so two simultaneous submissions cannot both succeed. Duplicate messages say only that the number is already registered or awaiting approval; they never name the other asset or its holder.

## 3. Roles and screens

| Role | Capability | Screens |
|---|---|---|
| CAPO Employee | `capo_inv_use` | CAPO Inventory → My Assets |
| Inventory Manager | `capo_inv_use`, `capo_inv_manage` | My Assets, All Assets, Employees, Requests, Audit Log, Settings (Asset Types, Locations) |
| Administrator | both, plus `manage_options` | Everything, plus Settings → General |

**Request workflow**

1. An employee registers an asset, or requests a change or removal of one of their own active assets.
2. The request is stored separately. The approved record is not touched.
3. A manager opens the request under Requests, sees the current approved values next to the proposed values (changed fields highlighted), and approves or rejects it with an optional comment.
4. On approval the plugin re-checks every rule (including uniqueness) inside a database transaction, then creates, updates or archives the asset and writes the audit entry.

Removal is a soft delete: the asset is archived, leaves the employee's active inventory, and keeps its Asset Tag reserved. Archived assets can be restored by a manager.

## 4. Export

All Assets → **CSV for Excel** or **Plain CSV**. Both export every row matching the current filters (not just the visible page), include a UTF-8 byte-order mark so Greek text displays correctly in Excel, and use the new field names as headings.

- **CSV for Excel** writes identifiers as `="000123"` so Excel keeps leading zeros when the file is double-clicked.
- **Plain CSV** contains the stored values exactly, for import into other systems.

Free-text cells starting with `=`, `+`, `-`, `@`, tab or carriage return are prefixed with `'` to block spreadsheet formula injection.

## 5. Assumptions

The original specification was not available while building this version. The following were decided without it and should be checked against the original. Each is marked **Assumption**.

1. **Assumption:** Employees submit requests; Inventory Managers and Administrators approve them. Employee registrations also require approval.
2. **Assumption:** Only one pending request per asset at a time. Employees may cancel their own pending requests.
3. **Assumption:** Inventory Managers cannot approve or reject their own requests; Administrators can.
4. **Assumption:** The "previously specified duplicate checks" for serial numbers are: non-blank serial numbers are unique across all assets (active and archived) and pending requests, ignoring case and surrounding whitespace. If the original rule was different (for example, unique per Asset Type, or a warning rather than a block), the `serial_norm` unique index and `CAPO_Inv_Assets::check_unique()` need changing.
5. **Assumption:** Asset Tags must start and end with a letter or number (a leading hyphen is refused). This keeps tags safe in spreadsheets.
6. **Assumption:** Employees cannot reassign an asset to someone else; only managers can.
7. **Assumption:** A removal request requires a reason; other comments are optional.
8. **Assumption:** Employees use the plugin inside wp-admin. There is no front-end shortcode.
9. **Assumption:** Minimum versions are WordPress 6.0 and PHP 7.4.
10. **Assumption:** Starter Asset Types (Laptop, Desktop, Monitor, Docking Station, Printer, Mobile Phone, Tablet, Other) are created on first activation. No Locations are created; a notice asks managers to add them.
11. **Assumption:** Data is kept when the plugin is deleted, unless an Administrator enables deletion under Settings → General.
12. **Not done:** Migration from an earlier version's "label" field. This repository had no earlier plugin code, so no migration was written. If an earlier version is installed on a live site from another source, its table and column names are needed to add one.

## 6. Acceptance checks

"Automated" means checked by the logic test script run during the build (63 checks, all passing) against an in-memory SQLite database standing in for WordPress's database layer. **The plugin has not yet been run inside a real WordPress and MySQL installation.** The admin screens were syntax-checked but not rendered.

| Check | Status |
|---|---|
| Asset Tag "000123" keeps leading zeros in storage and exports | Automated (storage, search, both CSV formats). Forms: needs manual check in WordPress. |
| Duplicate Asset Tags rejected, including archived assets' tags | Automated (case, whitespace, archived, pending, database index). |
| Assets can be registered without a serial number | Automated (several blank serials accepted). |
| Pending requests do not change approved details | Automated (change and removal). |
| Employees cannot act on another employee's asset | Automated. |
| Duplicate messages do not reveal other employees' assets | Automated. |
| Approval re-checks uniqueness | Automated. |
| Audit log records old and new values | Automated. |
| PDF checks (ownership, Greek text, multipage, signature area) | Not applicable — PDF deferred. |

**Manual test plan before going live:** install on a staging copy, add Locations, register `000123` as a manager, export both CSV formats and open them in Excel, log in as an employee and submit each request type, approve and reject from a manager account, try a duplicate of an archived tag, and check that Greek location names display correctly on screen and in exports.

## 7. Deferred: Employee Equipment Acknowledgement PDF

Not included. Findings from the attempt, for the next phase:

- **TCPDF 7.x** (7.0.21) is a thin compatibility layer over `tecnickcom/tc-lib-pdf`. It needs Composer to install its dependencies, a font build step, and PHP 8.2+ with the curl extension. The zip on its own does not work.
- **TCPDF 6.x** is end-of-life and receives no security fixes, according to the TCPDF project's security policy. Its release downloads have been withdrawn. Not suitable.
- **Recommended next step:** build a `vendor/` folder with `tecnickcom/tc-lib-pdf` (or another maintained library) once on a machine with Composer and network access, bundle it with its fonts and licence files in the plugin zip, and raise the plugin's minimum PHP version to match.

The data layer is ready for it: `CAPO_Inv_Assets::active_for_employee()` returns every active, approved asset for an employee with no pagination, which is what the PDF must list.

## 8. Files

```
capo-inventory/
├── capo-inventory.php                Plugin header, constants, bootstrap
├── uninstall.php                     Optional data removal
├── readme.txt                        End-user readme and changelog
├── README.md                         This file
├── includes/
│   ├── class-capo-inv-fields.php     Field labels, normalisation, validation
│   ├── class-capo-inv-install.php    Tables, roles, capabilities
│   ├── class-capo-inv-lists.php      Asset Types and Locations
│   ├── class-capo-inv-assets.php     Asset records and uniqueness
│   ├── class-capo-inv-requests.php   Request and approval workflow
│   ├── class-capo-inv-audit.php      Audit log
│   ├── class-capo-inv-export.php     CSV export
│   └── class-capo-inv-plugin.php     Hooks
├── admin/
│   ├── class-capo-inv-admin.php      Menus, form handlers
│   ├── class-capo-inv-assets-table.php
│   └── views/                        Screen templates
├── assets/css/admin.css
└── languages/capo-inventory.pot      Translation template
```

Database tables (with the site prefix): `capo_inv_assets`, `capo_inv_requests`, `capo_inv_audit`, `capo_inv_types`, `capo_inv_locations`.
