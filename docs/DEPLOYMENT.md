# Deployment Guide

Four ways to get Bukas Hours running, from quickest to most developer-friendly, plus publishing the source to GitHub.

---

## 1. Install on a live site (zip upload)

1. Build the zip so the plugin folder is at the top level (the provided `bukas-hours.zip` already is):

   ```bash
   # from the folder that CONTAINS bukas-hours/
   zip -r bukas-hours.zip bukas-hours -x "bukas-hours/.git/*" "bukas-hours/node_modules/*" "bukas-hours/.wp-env.json"
   ```

2. Log in to wp-admin → **Plugins → Add New Plugin → Upload Plugin**.
3. Choose `bukas-hours.zip` → **Install Now** → **Activate Plugin**.
4. Go to **Settings → Bukas Hours**, check the timezone, set hours and holidays, then **Save hours**.
5. Add `[bukas_status]` to your header or the **Bukas Hours** block to your Contact page.
6. If you use a caching plugin, purge the cache once. After that the badge updates itself.

**Updating:** upload the new zip the same way; WordPress offers **Replace current with uploaded**.

## 2. Test locally with LocalWP (no command line)

1. Install [LocalWP](https://localwp.com/) and click **+ → Create a new site** (PHP 8.1+, any web server).
2. Right-click the site → **Reveal in Finder/Explorer** → open `app/public/wp-content/plugins/`.
3. Copy the `bukas-hours` folder there (or unzip the zip there).
4. Click **WP Admin**, activate **Bukas Hours** under Plugins.
5. Optional: in `wp-config.php` set `define( 'WP_DEBUG', true );` to catch notices while testing.

**Test checklist**

- [ ] Change the timezone and confirm the badge changes.
- [ ] Add a holiday for today with no times → badge says Closed and "Opens … at".
- [ ] Add special hours for today → table still shows weekly hours, badge follows the special hours.
- [ ] Insert the block, switch the Show option, preview.
- [ ] View source on the home page and validate the JSON-LD at https://validator.schema.org/.

## 3. Test locally with wp-env (Docker)

Requires Docker Desktop and Node.js 18+.

```bash
cd bukas-hours
npx @wordpress/env start      # first run downloads WordPress
# Site:  http://localhost:8888      Admin: http://localhost:8888/wp-admin  (admin / password)
npx @wordpress/env run cli wp plugin list
npx @wordpress/env stop
```

Lint before committing:

```bash
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
node --check assets/js/bukas-hours.js assets/js/admin.js blocks/status/index.js
```

Optional coding-standards check:

```bash
composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
phpcs --standard=WordPress --extensions=php .
```

Regenerate the translation template with WP-CLI:

```bash
npx @wordpress/env run cli wp i18n make-pot wp-content/plugins/bukas-hours wp-content/plugins/bukas-hours/languages/bukas-hours.pot
```

## 4. Push to GitHub

1. Create an empty repository named **bukas-hours** on GitHub (no README, no license; they already exist).
2. From inside the plugin folder:

   ```bash
   cd bukas-hours
   git init
   git add .
   git commit -m "Bukas Hours v1.0.0"
   git branch -M main
   git remote add origin https://github.com/<your-username>/bukas-hours.git
   git push -u origin main
   ```

3. On GitHub, open **About ⚙** and add the description and topics:
   `wordpress-plugin`, `business-hours`, `opening-hours`, `gutenberg-block`, `schema-org`, `local-seo`, `philippines`, `php`.

## 5. Publish a downloadable release

```bash
git tag -a v1.0.0 -m "v1.0.0"
git push origin v1.0.0
```

Then on GitHub: **Releases → Draft a new release** → choose tag `v1.0.0` → attach `bukas-hours.zip` → **Publish release**. Users can install directly from that zip.

With the GitHub CLI instead:

```bash
gh release create v1.0.0 ../bukas-hours.zip --title "Bukas Hours 1.0.0" --notes-file CHANGELOG.md
```

## 6. Optional: a demo page on GitHub Pages

A WordPress plugin can't run on GitHub Pages, but a static demo can. Put a standalone `index.html` demo in a `demo/` folder, then:

1. **Settings → Pages → Build and deployment → Source: Deploy from a branch**.
2. Branch **main**, folder **/ (root)** → **Save**.
3. Your demo is at `https://<your-username>.github.io/bukas-hours/demo/` within a minute or two.

Link it from the README so visitors can try the badge before installing.

## 7. Optional: WordPress.org Plugin Directory

1. Make sure `readme.txt` passes the [readme validator](https://wordpress.org/plugins/developers/readme-validator/).
2. Submit the zip at https://wordpress.org/plugins/developers/add/.
3. After approval you receive an SVN repository:

   ```bash
   svn co https://plugins.svn.wordpress.org/bukas-hours bukas-hours-svn
   cp -R bukas-hours/* bukas-hours-svn/trunk/
   cd bukas-hours-svn
   svn add --force trunk
   svn cp trunk tags/1.0.0
   svn ci -m "Release 1.0.0"
   ```
