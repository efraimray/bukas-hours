# Bukas Hours

**Business hours, a live "Open now" badge, holiday closures and LocalBusiness schema for WordPress — built for small businesses with noon breaks and Philippine holidays.**

![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)
![WordPress 6.3+](https://img.shields.io/badge/WordPress-6.3%2B-21759b.svg)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)
![Block ready](https://img.shields.io/badge/Gutenberg-block-1e1e1e.svg)
![No build step](https://img.shields.io/badge/build-none-success.svg)

> 🔎 **Live demo:** open `demo/index.html` locally or enable GitHub Pages (see docs/DEPLOYMENT.md).
>
> 📸 **Screenshot:** add `docs/screenshot-badge.png` (front-end badge + hours table) and `docs/screenshot-admin.png` (settings screen) after installing on a test site, then reference them here.

"Bukas" is Filipino for *open*. Customers check a shop's website or Facebook page to see if it's open before they travel. Most small-business sites show a static list of hours that ignores lunch breaks, holidays and the fact that pages get cached. Bukas Hours fixes all three.

---

## Features

- **Live status badge** — *Open now*, *Closing soon* or *Closed*, plus "Closes at 5:00 PM" / "Opens tomorrow at 8:00 AM".
- **Cache-safe** — the badge is recalculated in the visitor's browser every minute from embedded opening ranges, so it stays correct behind WP Rocket, LiteSpeed Cache, Cloudflare and similar.
- **Split shifts** — two shifts per day for the noon break common in clinics, offices, LGUs and shops.
- **Holidays & special hours** — close all day or set shortened hours on any date.
- **PH holiday helper** — one click adds the fixed-date regular holidays for a chosen year (including National Heroes Day, the last Monday of August).
- **Three shortcodes** and a **Gutenberg block** (no build step, server-rendered).
- **SEO** — LocalBusiness JSON-LD with `openingHoursSpecification` and `specialOpeningHoursSpecification`.
- **Your words** — rename the badge text, e.g. *Bukas na!* / *Malapit nang magsara* / *Sarado*.
- **Lean** — CSS/JS load only on pages that show hours. No jQuery, no external requests.
- **Secure & translatable** — Settings API with nonces, strict sanitizing, escaped output, `bukas-hours` text domain and a `.pot` file.

## Tech Stack

| Layer | Tech |
|---|---|
| Platform | WordPress 6.3+ (Settings API, Shortcode API, Block API v3) |
| Server | PHP 7.4+ with `DateTimeImmutable` / `DateTimeZone` |
| Editor | Vanilla `wp.element.createElement` + `ServerSideRender` (no JSX/build) |
| Front end | ~3 KB vanilla JS using `Intl.DateTimeFormat`, plain CSS with `color-mix()` |
| Local dev | `@wordpress/env` (`.wp-env.json` included) or LocalWP |

## Folder Structure

```
bukas-hours/
├── bukas-hours.php                     # Plugin header, constants, bootstrap
├── uninstall.php                       # Removes the option on delete
├── includes/
│   ├── class-bukas-hours-schedule.php  # Pure logic: ranges, status, next opening
│   ├── class-bukas-hours-render.php    # Escaped HTML for badge, table, closures
│   ├── class-bukas-hours-settings.php  # Settings API page + sanitize callback
│   ├── class-bukas-hours-shortcodes.php
│   ├── class-bukas-hours-schema.php    # LocalBusiness JSON-LD
│   └── class-bukas-hours-block.php     # Registers the dynamic block
├── blocks/status/
│   ├── block.json
│   ├── index.js                        # Editor UI (no build)
│   └── index.asset.php                 # Script dependencies
├── assets/
│   ├── css/bukas-hours.css             # Front end
│   ├── css/admin.css
│   ├── js/bukas-hours.js               # Live, cache-safe badge
│   └── js/admin.js                     # Holiday rows, PH holidays, copy Monday
├── languages/bukas-hours.pot
├── demo/index.html                     # Standalone interactive demo (GitHub Pages)
├── docs/
│   ├── TUTORIAL.md
│   └── DEPLOYMENT.md
├── readme.txt                          # WordPress.org format
├── CHANGELOG.md
├── LICENSE
└── .wp-env.json
```

## Quick Start / Installation

**From a zip (fastest):**

1. Download `bukas-hours.zip`.
2. In wp-admin go to **Plugins → Add New → Upload Plugin**, choose the zip, click **Install Now**, then **Activate**.
3. Open **Settings → Bukas Hours**, set your hours, click **Save hours**.

**From source:**

```bash
cd wp-content/plugins
git clone https://github.com/<your-username>/bukas-hours.git
# then activate in wp-admin → Plugins
```

**Local sandbox with wp-env (needs Docker + Node):**

```bash
npx @wordpress/env start     # http://localhost:8888  (admin / password)
```

## Usage

| Shortcode | Output |
|---|---|
| `[bukas_status]` | Live badge: *Open now · Closes at 12:00 PM* |
| `[bukas_hours]` | Weekly table, today highlighted. `highlight_today="no"` to turn it off |
| `[bukas_closures limit="5"]` | Upcoming holidays / special hours |

**Block:** insert **Bukas Hours** (Widgets category). In the sidebar choose *Status badge only*, *Weekly hours only*, *Badge + weekly hours* or *Badge, hours and holidays*.

Typical placements: the header (badge), the Contact page (all three), the footer (hours table).

## Configuration

| Setting | Default | Notes |
|---|---|---|
| Business name / type | Site title / LocalBusiness | Type feeds schema.org `@type` |
| Address, phone | empty | Added to JSON-LD when filled |
| Timezone | Asia/Manila | Independent of the site timezone |
| Weekly hours | Mon–Fri 8–12 & 1–5, Sat 8–12, Sun closed | Second shift optional |
| Holidays | none | Blank times = closed all day |
| Badge text | Open now / Closing soon / Closed | Any language |
| Closing-soon window | 30 min | 0 disables it |
| Structured data | on | Printed on the front page only |

**Filter:**

```php
add_filter( 'bukas_hours_schema', function ( $data ) {
	$data['priceRange'] = '₱₱';
	return $data;
} );
```

## Deployment

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for zip upload, LocalWP, wp-env, pushing to GitHub and publishing GitHub releases.

## Customization

- **Colours:** override the custom properties in your theme: `.bukas-status { --bukas-open: #0a7d4f; --bukas-closed: #a4161a; }`
- **Markup:** all HTML is built in `includes/class-bukas-hours-render.php`.
- **More business types:** extend `Bukas_Hours_Settings::business_types()` with any schema.org LocalBusiness subtype.
- **Translations:** copy `languages/bukas-hours.pot` to `bukas-hours-fil.po`, translate in Poedit, save the `.mo` beside it.

## Roadmap

- [ ] Overnight shifts (e.g. 6 PM – 2 AM)
- [ ] Multiple locations / branches with a `location` shortcode attribute
- [ ] Temporary banner ("Closed today for inventory") with an expiry date
- [ ] REST endpoint `/wp-json/bukas-hours/v1/status` for chatbots and Messenger auto-replies
- [ ] Movable PH holidays importer from an iCal feed
- [ ] WooCommerce: pause checkout or show a notice while closed

## Contributing

1. Fork and create a branch: `git checkout -b feature/overnight-shifts`
2. Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/) (`phpcs --standard=WordPress`).
3. Run `php -l` on changed PHP files and `node --check` on JS.
4. Open a pull request describing the change and how you tested it.

## License

[GPL-2.0-or-later](LICENSE) © 2026 Efraim Ray.

## Author

**Efraim Ray** — web developer & AI automation specialist, Valencia City, Bukidnon, Philippines.
🌐 https://www.efraimray.com/
