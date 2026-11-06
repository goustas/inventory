=== CAPO Inventory ===
Author: George Oustas, Pixelcrafters.com.cy
Author URI: https://www.pixelcrafters.com.cy
Tags: inventory, assets, equipment, approval, audit
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

IT equipment inventory for the Cyprus Agricultural Payments Organisation, with employee requests, approval, audit log, CSV export and front-end shortcodes.

== Description ==

CAPO Inventory keeps a register of IT equipment and the employee each item is assigned to.

* **Asset Tag / Inventory Number**: the number on the physical inventory sticker. It is required and stored as text, so leading zeros are kept (000123). It may contain letters, numbers and hyphens. It is unique across active and archived assets, ignoring letter case and surrounding spaces.
* **Description / Make and Model** is optional, for example "Dell Latitude 5540".
* **Serial Number** is optional for every asset type. Blank serial numbers are always accepted; a non-blank serial number must be unique.
* **Employees** (every role except Inventory Manager, configurable) register their equipment and request changes or removal from **My Assets**. Requests do not change the approved records until an Inventory Manager or Administrator approves them.
* **Inventory Managers** see all assets and each employee's inventory, review requests with a side-by-side comparison, and export CSV files.
* Every change is recorded in the **Audit Log**.
* **Front-end shortcodes** put the employee and manager screens on ordinary pages (see Shortcodes below).

== Shortcodes ==

Place each shortcode on its own page. Logged-out visitors see a login form that returns them to the same page.

* `[capo_my_assets]` — for employees. Shows their active assets, lets them register an asset and request changes or removal, and lists their requests (with cancel for pending ones).
* `[capo_requests]` — for Inventory Managers and Administrators. Request queue with status tabs; each request opens a side-by-side comparison with Approve and Reject.
* `[capo_assets]` — for Inventory Managers and Administrators. Read-only, searchable list with filters (status, type, location, employee), per-employee inventory and CSV export. Editing stays in the dashboard.

Optional attribute: `per_page` (5–100, default 20), for example `[capo_requests per_page="50"]`.

Templates can be overridden by copying files from the plugin's `templates/` folder to `your-theme/capo-inventory/`. Colours can be adjusted with the CSS custom properties at the top of `assets/css/frontend.css`.

Pages containing these shortcodes send no-cache headers and define `DONOTCACHEPAGE`, so page-caching plugins should not store personal inventory data.

Developed by George Oustas, Pixelcrafters.com.cy (https://www.pixelcrafters.com.cy).

== Installation ==

1. In WordPress, go to Plugins → Add New → Upload Plugin and upload `capo-inventory.zip`.
2. Activate the plugin.
3. Under Users, give inventory staff the **Inventory Manager** role. Every other role (Administrators included) can submit assets and has a personal inventory; Administrators can change which roles are excluded under CAPO Inventory → Settings → General.
4. Go to CAPO Inventory → Settings and add the **Locations**. Edit the starter **Asset Types** as needed.

== Frequently Asked Questions ==

= Why does Excel drop the leading zeros? =

Use the **CSV for Excel** export button. It writes Asset Tags and serial numbers in a form Excel keeps as text. The **Plain CSV** file contains the values exactly as stored, for importing into other systems.

= Can an employee change an approved record directly? =

No. Employees submit requests; approved records change only when a request is approved.

= What happens to data if I delete the plugin? =

Nothing, unless an Administrator ticks "Permanently delete all inventory data" under Settings → General before deleting the plugin.

== Changelog ==

= 1.2.0 - 2026-11-06 =
* Change: Every user with a role on the site can now submit assets and has a personal inventory (Administrators, Editors, Subscribers and custom roles included), except Inventory Managers. Previously only CAPO Employee, Inventory Manager and Administrator could.
* New: Settings → General → "Who can submit assets" lets Administrators choose which roles are excluded (default: Inventory Manager).
* Change: Inventory Managers no longer see My Assets; their CAPO Inventory menu opens on All Assets.
* Fix: Updating the plugin by uploading a new zip now re-applies its roles and capabilities, which previously only happened on first activation.
* Improvement: Clearer front-end messages: Inventory Managers are told they have no personal inventory, and Administrators who are refused are told which setting to check.

= 1.1.0 - 2026-11-06 =
* New: `[capo_my_assets]` shortcode — employees view their assets, register assets, request changes or removal, and see or cancel their requests on the front end.
* New: `[capo_requests]` shortcode — Inventory Managers review requests with the side-by-side comparison and approve or reject them on the front end.
* New: `[capo_assets]` shortcode — Inventory Managers search and filter all assets, view each employee's inventory and export CSV on the front end (read-only).
* New: Logged-out visitors see a login form that returns them to the same page; users without the right role see an access message.
* New: Front-end templates can be overridden from the theme (`your-theme/capo-inventory/`).
* New: Responsive front-end stylesheet; tables become stacked cards on small screens.
* New: Pages with the shortcodes are excluded from page caching.
* Change: Form handlers return users to the front-end page they came from (same-site URLs only).

= 1.0.1 - 2026-11-06 =
* Fix: Approving a change request no longer overwrites fields the employee did not change. Edits a manager made after the request was submitted are kept, and the approval comparison highlights only the employee's changes.
* Fix: A pending change request reserves an Asset Tag or serial number only when it changes that field.
* Fix: Approving a removal request is refused if the asset has been reassigned to another employee since the request was made.
* Fix: Reassigning an asset now always takes effect, even when the submitted data already carries an employee.
* Fix: Text is stored as typed. Characters such as "<" or "%20" were silently removed from descriptions, serial numbers and notes, and could change a mistyped Asset Tag into a different valid one; mistyped tags are now rejected instead.
* Fix: The request status update during approval is checked; on failure the whole approval is rolled back.
* Improvement: Filter and search URLs on All Assets no longer carry the list table's nonce and referer fields.
* Improvement: Export help text now explains the formula-protection prefix.

= 1.0.0 - 2026-11-06 =
* New: Asset Tag / Inventory Number field (replaces the earlier "label"). Stored as text with leading zeros kept, letters/numbers/hyphens only, required, unique across active and archived assets (case and surrounding spaces ignored). The internal database ID is kept separate.
* New: Optional Description / Make and Model field.
* New: Serial Number is optional for all asset types; non-blank serial numbers must be unique.
* New: Duplicate messages that do not reveal another employee's asset.
* New: My Assets screen for employees with registration, change and removal requests.
* New: Request review with side-by-side comparison of approved and proposed values.
* New: All Assets and per-employee inventory views with search and filters.
* New: Audit log with field-level before/after values.
* New: CSV export (Plain CSV and CSV for Excel) with the new field names, UTF-8 for Greek text and protection against formula injection.
* New: CAPO Employee and Inventory Manager roles.
* Deferred: the Employee Equipment Acknowledgement PDF is not included in this version.
