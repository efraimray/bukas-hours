# Tutorial: How Bukas Hours Works

This tutorial walks through the plugin from the moment WordPress loads it to the moment a visitor sees "Open now". It assumes you know basic PHP and have installed a WordPress plugin before. No build tools are needed.

---

## Step 1 — The big picture

```
            ┌──────────────────────── wp-admin ────────────────────────┐
            │ Settings → Bukas Hours  (Bukas_Hours_Settings)           │
            │   form → options.php → sanitize() → wp_options row       │
            └──────────────────────────────┬───────────────────────────┘
                                           │ get_option('bukas_hours_settings')
                                           ▼
                              Bukas_Hours_Schedule  (pure logic)
                       ranges_for() · status() · next_opening() · upcoming_ranges()
                         │                    │                         │
                         ▼                    ▼                         ▼
               Bukas_Hours_Render     Bukas_Hours_Schema        assets/js/bukas-hours.js
               (escaped HTML)         (JSON-LD in <head>)       (keeps badge live in browser)
                 ▲          ▲
   Shortcodes ───┘          └─── Block (block.json + render_callback)
```

One idea drives the design: **all time maths lives in one class with no output.** Everything else either collects settings or prints them.

## Step 2 — The bootstrap (`bukas-hours.php`)

1. The comment block at the top is the **plugin header**. WordPress reads `Plugin Name`, `Version`, `Text Domain`, `Requires PHP` and so on to list the plugin.
2. `if ( ! defined( 'ABSPATH' ) ) exit;` stops anyone loading the file directly from a URL.
3. Constants (`BUKAS_HOURS_DIR`, `BUKAS_HOURS_URL`, …) avoid repeating paths.
4. `bukas_hours_init()` runs on `plugins_loaded`. It creates one `Bukas_Hours_Schedule` from the saved settings, wraps it in a renderer, and calls `hooks()` on each feature class. This is simple **dependency injection**: each class receives what it needs in its constructor rather than reaching for globals.
5. Assets are *registered* on `wp_enqueue_scripts` but only *enqueued* when something renders, so pages without hours load no extra CSS or JS.
6. The activation hook seeds defaults; `uninstall.php` deletes the option when the plugin is removed.

## Step 3 — Settings (`class-bukas-hours-settings.php`)

All data lives in **one option**, `bukas_hours_settings`, as an array:

```php
[
  'timezone' => 'Asia/Manila',
  'days'     => [ 'mon' => [ 'closed' => 0, 'open' => '08:00', 'close' => '12:00', 'open2' => '13:00', 'close2' => '17:00' ], … ],
  'closures' => [ [ 'date' => '2026-12-25', 'label' => 'Christmas Day', 'open' => '', 'close' => '' ] ],
  …
]
```

Key functions:

- **`defaults()`** — sensible Philippine defaults: Mon–Fri 8–12 and 1–5, Saturday half-day, Sunday closed.
- **`get()`** — merges saved values over defaults with `wp_parse_args()`, so new settings added in future versions always have a value.
- **`register()`** — `register_setting()` ties the option to the `bukas_hours` group and to `sanitize()`.
- **`sanitize()`** — the security gate. Every field is cleaned: text with `sanitize_text_field()`, times with a strict `HH:MM` regex, the timezone against `timezone_identifiers_list()`, the business type against an allow-list. Malformed or duplicate holiday dates are dropped and the list is sorted.
- **`page()`** — prints the form. `settings_fields( 'bukas_hours' )` outputs the **nonce** and hidden fields; `options.php` checks the nonce and capability before saving. Every value printed is wrapped in `esc_attr()` / `esc_html()`.

The holiday repeater uses a `<template>` element containing one row with `__i__` as the index. `assets/js/admin.js` clones it and swaps `__i__` for a unique number, so PHP and JS share exactly the same markup.

## Step 4 — The schedule engine (`class-bukas-hours-schedule.php`)

Read this class slowly; it is the heart of the plugin.

| Method | What it does |
|---|---|
| `timezone()` | Business timezone, falling back to the site's, then `Asia/Manila`. |
| `closure_for( $date )` | Returns the holiday row for that date, if any. |
| `shifts_for( $date )` | Returns `HH:MM` pairs for a date. **Holidays override weekdays.** A holiday with times means special hours; without times, closed all day. |
| `ranges_for( $date )` | Turns shifts into real `DateTimeImmutable` start/end pairs on that date. |
| `status( $now )` | Checks whether `$now` falls inside a range. If so: `open`, or `closing_soon` when the end is within the window. Otherwise `closed` with `next_opening()`. |
| `next_opening( $now )` | Walks forward up to 14 days and returns the first range start after now. |
| `upcoming_ranges( 8 )` | Next 8 days of ranges as Unix timestamps, for the browser. |

**Why `DateTimeImmutable`?** Methods like `modify( '+1 day' )` return a *new* object instead of changing the original, which prevents a whole class of bugs when you loop over days.

**Why compare strings like `$close > $open`?** Zero-padded `HH:MM` strings sort the same way as times, so a string comparison is a safe, cheap check.

## Step 5 — Rendering (`class-bukas-hours-render.php`)

The renderer builds three pieces of HTML. Notice the pattern used everywhere:

```php
sprintf( '<strong class="bukas-status__label">%s</strong>', esc_html( $label ) );
```

Data is escaped **at the moment it is printed**, never earlier. That is the WordPress rule "escape late".

The badge carries a `data-bukas` attribute containing JSON: the 8 days of ranges, the timezone, the labels and translated sentence templates. It also has `role="status"` and `aria-live="polite"` so screen readers announce changes.

Times use `wp_date()` with the site's own time format, so a site set to 24-hour time shows `17:00` and a 12-hour site shows `5:00 PM`.

## Step 6 — Making it cache-safe (`assets/js/bukas-hours.js`)

Page caches save the HTML once and serve it for hours. A badge rendered by PHP at 11:55 would still say "Open" at 3 AM. The script solves this:

1. Read `data-bukas` and parse the JSON (inside `try/catch`; on failure the server markup stays).
2. `compute( cfg, nowTs )` — is `now` inside any `[start, end]`? That is the same logic as PHP's `status()`, but with plain numbers.
3. `detail()` builds "Opens tomorrow at 8:00 AM" with `Intl.DateTimeFormat` and the **business** timezone, so a visitor abroad still sees Manila hours.
4. `setInterval( run, 60000 )` repeats every minute.

Because timestamps are absolute, the browser never needs to know about weekdays, noon breaks or holidays; PHP already did that work.

## Step 7 — Shortcodes, block and schema

- **Shortcodes** (`class-bukas-hours-shortcodes.php`): `shortcode_atts()` merges user attributes with defaults; each callback *returns* HTML (never `echo` in a shortcode).
- **Block** (`blocks/status/`): `block.json` declares the name, attribute and editor script. `register_block_type()` reads it and adds a PHP `render_callback`, making it a **dynamic block**: `save()` returns `null` and PHP renders on every request. In the editor, `ServerSideRender` asks the REST API for that same HTML. `index.asset.php` lists script dependencies by hand because there is no build step.
- **Schema** (`class-bukas-hours-schema.php`): builds a schema.org array and prints it with `wp_json_encode()` using `JSON_HEX_TAG` so no value can close the `<script>` tag. Closed holidays use the schema.org convention `opens = closes = 00:00`.

## Key concepts you practised

- Plugin header, bootstrap and activation/uninstall hooks
- Settings API, nonces and capability checks
- Sanitize on input, escape on output
- Separating pure logic from presentation
- Timezone-correct date maths with `DateTimeImmutable`
- Dynamic blocks without a build step
- Progressive enhancement for cached pages
- Structured data for local SEO
- i18n with `__()`, `esc_html__()` and a `.pot` file

## Exercises

1. **Temporary notice.** Add a "notice" text and an "until" date to settings. In `status_badge()`, show the notice under the badge until that date passes. Remember to sanitize the date and escape the text.
2. **REST endpoint.** Register `GET /wp-json/bukas-hours/v1/status` with `register_rest_route()` that returns `state`, `label` and `next_change` (ISO 8601). Use `'permission_callback' => '__return_true'` since the data is public. Try it from a Messenger chatbot or n8n workflow.
3. **Overnight shifts.** Allow `close < open` (e.g. 18:00–02:00). In `ranges_for()`, when close is earlier than open, set the end to the next day. Then make `status()` also check *yesterday's* ranges so 1 AM counts as open. Write test cases for 23:00, 01:30 and 02:30 first.
