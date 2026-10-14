# portfolio

Local design portfolio site built with **Drupal 10** (Composer + DDEV).

Public repo: https://github.com/michaeljbuckley/portfolio

## Local (this Mac)

```bash
colima status || colima start --cpu 2 --memory 3 --disk 40
cd ~/Projects/path-usda-drupal-lab/drupal-portfolio
ddev start
```

- Site: https://drupal-portfolio.ddev.site  
- Admin one-time login: `ddev drush uli`

```bash
ddev composer install   # after clone
ddev drush deploy       # updb + cim + cache (when DB exists)
```

Config lives in **`config/sync/`** (outside the web root). Export after structural changes:

```bash
ddev drush cex -y
```

## What is in git

- `composer.json` / `composer.lock`
- Custom theme `web/themes/custom/portfolio`
- Config YAML `config/sync`
- `web/sites/default/settings.php` (safe defaults + DDEV include only when `IS_DDEV_PROJECT`)
- Bootstrap scripts under `scripts/`

## What is not in git

- `vendor/`, Drupal core/contrib (Composer)
- `web/sites/default/files/` (uploads / DB files)
- `settings.ddev.php`, `settings.local.php` (secrets / host DB)

## Fresh clone + host notes

1. `composer install`
2. Point the web root at `web/`
3. Create DB; copy `settings.php` and add host DB + `$settings['hash_salt']` via `settings.local.php`
4. `drush site:install --existing-config` **or** install then `drush cim -y`
5. Create content (case studies) in the UI — content is not in config

Content strategy: **config only in git**; portfolio nodes/media added on the host (or a private DB dump outside this repo).
