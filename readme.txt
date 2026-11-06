=== CAPO Inventory ===
Author: George Oustas, Pixelcrafters.com.cy
Author URI: https://www.pixelcrafters.com.cy
Tags: inventory, assets, equipment, approval, audit
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

IT equipment inventory for the Cyprus Agricultural Payments Organisation, with employee requests, approval, audit log and CSV export.

== Description ==

CAPO Inventory keeps a register of IT equipment and the employee each item is assigned to.

* **Asset Tag / Inventory Number**: the number on the physical inventory sticker. It is required and stored as text, so leading zeros are kept (000123). It may contain letters, numbers and hyphens. It is unique across active and archived assets, ignoring letter case and surrounding spaces.
* **Description / Make and Model** is optional, for example "Dell Latitude 5540".
* **Serial Number** is optional for every asset type. Blank serial numbers are always accepted; a non-blank serial number must be unique.
* **Employees** register their equipment and request changes or removal from **My Assets**. Requests do not change the approved records until an Inventory Manager or Administrator approves them.
* **Inventory Managers** see all assets and each employee's inventory, review requests with a side-by-side comparison, and export CSV files.
* Every change is recorded in the **Audit Log**.

Developed by George Oustas, Pixelcrafters.com.cy (https://www.pixelcrafters.com.cy).

== Installation ==

1. In WordPress, go to Plugins → Add New → Upload Plugin and upload `capo-inventory.zip`.
2. Activate the plugin.
3. Under Users, give staff the **CAPO Employee** role and inventory staff the **Inventory Manager** role. Administrators have full access automatically.
4. Go to CAPO Inventory → Settings and add the **Locations**. Edit the starter **Asset Types** as needed.

== Frequently Asked Questions ==

= Why does Excel drop the leading zeros? =

Use the **CSV for Excel** export button. It writes Asset Tags and serial numbers in a form Excel keeps as text. The **Plain CSV** file contains the values exactly as stored, for importing into other systems.

= Can an employee change an approved record directly? =

No. Employees submit requests; approved records change only when a request is approved.

= What happens to data if I delete the plugin? =

Nothing, unless an Administrator ticks "Permanently delete all inventory data" under Settings → General before deleting the plugin.

== Changelog ==

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
