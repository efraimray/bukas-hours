=== Bukas Hours ===
Contributors: efraimray
Donate link: https://www.efraimray.com/
Tags: business hours, opening hours, open now, holidays, local business
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Business hours with a cache-safe "Open now" badge, noon breaks, holiday closures and LocalBusiness schema. Shortcodes and a block.

== Description ==

"Bukas" is Filipino for *open*. Bukas Hours shows visitors whether your business is open right now, when it closes or opens next, your weekly hours and upcoming holiday closures.

* Live badge: Open now / Closing soon / Closed with "Opens tomorrow at 8:00 AM" detail.
* Cache-safe: recalculated in the browser every minute, so cached pages stay accurate.
* Two shifts per day for noon breaks.
* Holidays and special hours by date, plus a helper that adds Philippine fixed-date regular holidays.
* Shortcodes: `[bukas_status]`, `[bukas_hours]`, `[bukas_closures limit="5"]`.
* "Bukas Hours" block for the block editor.
* LocalBusiness JSON-LD with opening hours and special opening hours.
* Custom badge text in any language.
* No external requests, no jQuery; assets load only where used.

== Installation ==

1. Upload the plugin zip via Plugins → Add New → Upload Plugin, or copy the `bukas-hours` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to Settings → Bukas Hours and set your hours.
4. Add a shortcode or the Bukas Hours block to any page, widget area or template.

== Frequently Asked Questions ==

= Does it work with caching plugins? =

Yes. The page contains the next eight days of opening ranges and a small script updates the badge in the visitor's browser.

= Are movable holidays like Holy Week added automatically? =

No. Only fixed-date regular holidays are added by the helper. Add movable and special non-working days by hand after checking the official proclamation for the year.

= Can I show hours past midnight? =

Not yet. Overnight shifts are on the roadmap.

= Where is the structured data printed? =

On the front page only, when "Structured data" is ticked. Use the `bukas_hours_schema` filter to change it.

== Screenshots ==

1. Live status badge and weekly hours table on the front end.
2. Settings screen with weekly hours, noon break and holidays.
3. Bukas Hours block in the editor.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
