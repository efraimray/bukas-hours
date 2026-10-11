# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [1.0.0] — 2026-10-11

### Added
- Settings → Bukas Hours screen with weekly hours and an optional second shift per day (noon break).
- Holiday & special-hours list with a one-click "Add PH fixed-date regular holidays" helper.
- `[bukas_status]` live badge (Open now / Closing soon / Closed) with "Closes at…" / "Opens tomorrow at…" detail.
- Cache-safe badge: the browser recalculates status every minute from embedded opening ranges.
- `[bukas_hours]` weekly table with today highlighted, and `[bukas_closures]` upcoming closures list.
- "Bukas Hours" dynamic block (no build step) with a Show option.
- LocalBusiness JSON-LD with `openingHoursSpecification` and `specialOpeningHoursSpecification`.
- Customisable badge text (e.g. "Bukas na!" / "Sarado"), closing-soon window and timezone.
- `bukas_hours_schema` filter; clean uninstall.
