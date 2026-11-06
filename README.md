# CAPO Inventory 1.3.0

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
| Front-end shortcodes (employee, requests, assets) | Yes (1.1.0) |
| Quick Edit on All Assets | Yes (1.3.0) |
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
| Assigned Employee | For employee submissions, always the logged-in account (never taken from the form). Managers choose from users whose role may submit assets (see Roles below). |
| Record Status | Active or Archived. |
| Audit timestamps | Created, Last Updated, Archived — stored in UTC, shown in the WordPress timezone. |

Uniqueness is enforced twice: by the plugin before saving, and by `UNIQUE` indexes on the normalised tag and serial columns, so two simultaneous submissions cannot both succeed. Duplicate messages say only that the number is already registered or awaiting approval; they never name the other asset or its holder.

## 3. Roles and screens

| Role | Submits assets / My Assets | Manages inventory |
|---|---|---|
| Any role on the site (Subscriber, Editor, Administrator, custom roles, CAPO Employee…) | Yes, unless the role is excluded | Administrators only |
| Inventory Manager | No (excluded by default) | Yes: All Assets, Employees, Requests, Audit Log, Settings (Asset Types, Locations) |
| Administrator | Yes (unless excluded) | Yes, plus Settings → General |

Who may submit is decided at runtime (filter `user_has_cap` on `capo_inv_use`, class `CAPO_Inv_Access`): every user with a role on the site, except users holding a role listed under **Settings → General → Who can submit assets** (default: Inventory Manager). Roles added later by other plugins are covered automatically. Managing (`capo_inv_manage`) comes from the Inventory Manager and Administrator roles.

When the plugin is updated by uploading a new zip, it re-applies its roles and capabilities on the next page load (activation does not run on updates).

**Request workflow**

1. An employee registers an asset, or requests a change or removal of one of their own active assets.
2. The request is stored separately. The approved record is not touched.
3. A manager opens the request under Requests, sees the current approved values next to the proposed values (changed fields highlighted), and approves or rejects it with an optional comment.
4. On approval the plugin re-checks every rule (including uniqueness) inside a database transaction, then creates, updates or archives the asset and writes the audit entry. A change request applies only the fields the employee changed, so edits a manager made in the meantime are kept. A change or removal is refused if the asset has been reassigned to someone else since the request was made.

Removal is a soft delete: the asset is archived, leaves the employee's active inventory, and keeps its Asset Tag reserved. Archived assets can be restored by a manager.

## 3b. Quick Edit (All Assets)

A **Quick Edit** row action opens an inline editor for Asset Tag, Asset Type, Description / Make and Model, Serial Number, Location, Assigned Employee, Record Status and Notes. Last Updated is set automatically.

- Saves through `admin-ajax.php` (`capo_inv_quick_edit`): requires `capo_inv_manage` and a nonce, then runs the same `CAPO_Inv_Assets` update, archive and restore methods as the full form, so validation, uniqueness and the audit log are identical.
- Field changes and a status change run in one database transaction. Archiving is refused while the asset has a pending request, and the field changes are rolled back with it.
- Each row carries the asset's `updated_at`. The save is refused if the asset changed since the page loaded.
- The server returns the re-rendered row, which replaces the old one in place.
- Inputs have no `name` attributes, so pressing Enter saves the asset instead of submitting the surrounding filter form.

## 3a. Front-end shortcodes

| Shortcode | Who | What |
|---|---|---|
| `[capo_my_assets]` | Anyone with `capo_inv_use` | Own active assets; register; request change or removal; own requests with cancel |
| `[capo_requests]` | `capo_inv_manage` | Queue with status tabs; review with comparison; approve or reject |
| `[capo_assets]` | `capo_inv_manage` | Read-only search and filters, per-employee inventory, CSV export; "Edit in dashboard" links |

All take `per_page` (5–100, default 20).

How it works:

- The forms post to the same `admin-post.php` handlers as the dashboard, so the same capability, nonce and ownership checks apply. Each form also posts `capo_return` (the page URL). The handler accepts it only if `wp_validate_redirect()` confirms it is on the same site, and sends the user back there with `capo_*` arguments (`capo_view`, `capo_asset`, `capo_request`, `capo_status`, `capo_paged`, `capo_s`, `capo_type_id`, `capo_location_id`, `capo_employee_id`). The prefix avoids clashes with WordPress's own `page` and `s` query variables.
- Logged-out visitors get `wp_login_form()` redirecting back to the page. Logged-in users without the capability get a short access message and no data.
- Templates are in `templates/` and can be overridden in `your-theme/capo-inventory/`. Every value is escaped in the templates; an override must keep that.
- Pages containing a shortcode send `nocache_headers()` and define `DONOTCACHEPAGE`.
- **Requirement:** employees must be able to reach `/wp-admin/admin-post.php`, because the forms submit there. Some security plugins block all of `/wp-admin/` for non-administrators; if so, allow `admin-post.php`.

## 4. Export

All Assets → **CSV for Excel** or **Plain CSV**. Both export every row matching the current filters (not just the visible page), include a UTF-8 byte-order mark so Greek text displays correctly in Excel, and use the new field names as headings.

- **CSV for Excel** writes identifiers as `="000123"` so Excel keeps leading zeros when the file is double-clicked.
- **Plain CSV** contains the stored values for import into other systems. Asset Tags are exported exactly; other text values that start with a formula character are prefixed as described below.

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
8. Employees can use the plugin in wp-admin or on front-end pages through the shortcodes (added in 1.1.0).
9. **Assumption:** Minimum versions are WordPress 6.0 and PHP 7.4.
10. **Assumption:** Starter Asset Types (Laptop, Desktop, Monitor, Docking Station, Printer, Mobile Phone, Tablet, Other) are created on first activation. No Locations are created; a notice asks managers to add them.
11. **Assumption:** Data is kept when the plugin is deleted, unless an Administrator enables deletion under Settings → General.
12. **Not done:** Migration from an earlier version's "label" field. This repository had no earlier plugin code, so no migration was written. If an earlier version is installed on a live site from another source, its table and column names are needed to add one.

## 6. Acceptance checks

"Automated" means checked by the logic test script run during the build (74 data checks, 43 front-end and access checks, 5 Quick Edit handler checks, and 39 Quick Edit checks in headless Chrome, all passing) against an in-memory SQLite database standing in for WordPress's database layer. **The plugin has not yet been run inside a real WordPress and MySQL installation.** The admin screens were syntax-checked but not rendered.

| Check | Status |
|---|---|
| Asset Tag "000123" keeps leading zeros in storage and exports | Automated (storage, search, both CSV formats). Forms: needs manual check in WordPress. |
| Duplicate Asset Tags rejected, including archived assets' tags | Automated (case, whitespace, archived, pending, database index). |
| Assets can be registered without a serial number | Automated (several blank serials accepted). |
| Pending requests do not change approved details | Automated (change and removal). |
| Employees cannot act on another employee's asset | Automated. |
| Duplicate messages do not reveal other employees' assets | Automated. |
| Approval re-checks uniqueness | Automated. |
| Approving a change keeps manager edits made after the request was submitted | Automated. |
| Approving a removal is refused if the asset was reassigned meanwhile | Automated. |
| Typed text is stored as entered (no silent removal of `<`, `%20` etc.) | Automated. |
| Audit log records old and new values | Automated. |
| Shortcodes: logged-out login form, role checks, no other employee's data via URL tampering | Automated (render checks with stubbed WordPress functions). |
| Shortcodes: form errors and submitted values are escaped | Automated. |
| Shortcodes: return URL accepted only for the same site | Automated. |
| Every role except Inventory Manager can submit; setting changes the excluded roles | Automated. |
| Quick Edit: open, cancel, save, inline errors, archive/restore, keyboard and focus, stale-edit and nonce refusal | Automated in headless Chrome against the plugin's real handler and test database. WordPress core admin styles were not loaded. |
| Quick Edit: refused archive rolls back the field changes | Automated. |
| Shortcodes: appearance in the CAPO theme, mobile layout | Needs manual check. |
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
│   ├── class-capo-inv-access.php     Who may submit assets (by role)
│   ├── class-capo-inv-lists.php      Asset Types and Locations
│   ├── class-capo-inv-assets.php     Asset records and uniqueness
│   ├── class-capo-inv-requests.php   Request and approval workflow
│   ├── class-capo-inv-audit.php      Audit log
│   ├── class-capo-inv-export.php     CSV export
│   ├── class-capo-inv-plugin.php     Hooks
│   └── class-capo-inv-frontend.php   Shortcodes
├── admin/
│   ├── class-capo-inv-admin.php      Menus, form handlers
│   ├── class-capo-inv-assets-table.php
│   └── views/                        Screen templates
├── templates/                        Front-end templates (theme-overridable)
├── assets/css/admin.css
├── assets/css/frontend.css
├── assets/js/admin-quick-edit.js     Quick Edit on All Assets
└── languages/capo-inventory.pot      Translation template
```

Database tables (with the site prefix): `capo_inv_assets`, `capo_inv_requests`, `capo_inv_audit`, `capo_inv_types`, `capo_inv_locations`.
