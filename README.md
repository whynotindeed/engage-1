# Akeeba Engage — Joomla 6 fork + CComment importer

**Unofficial** fork of [Akeeba Engage](https://github.com/akeeba/engage) (a comments component for Joomla articles). Lineage:

1. **Akeeba Ltd / Nicholas K. Dionysopoulos** — original project (archived in August 2025, last release 3.4.3).
2. **[whynotindeed/engage-1](https://github.com/whynotindeed/engage-1)** — Joomla 6 compatibility patch (`Filesystem` namespaces).
3. **[cubakumori/engage-k](https://github.com/cubakumori/engage-k)** — adds Joomla 7 readiness, a self-contained `make-zip.sh`, and a **CComment comments importer**.

## What this build includes

### Joomla 6 compatibility (inherited from the upstream fork)
The `Joomla\CMS\Filesystem\{Path,File,Folder}` classes moved to `Joomla\Filesystem\…` in Joomla 6. The upstream fork updated the 4 references across 3 files; without that change the Engage panel throws an exception on J6.

### Joomla 7 readiness (added in this build)
The deprecated `Factory::getUser()` is replaced with `Factory::getApplication()->getIdentity()` in 3 component files (`View/Comments/HtmlView.php`, `Service/Html/Engage.php`, `Helper/HtmlFilter.php`). Works on J5/J6 and survives J7.

### `make-zip.sh` — self-contained package builder
Akeeba uses a phing build (`build.xml`) that depends on Akeeba's *buildfiles*, which are not included here. `make-zip.sh` reproduces the installable package without that toolchain:

```bash
./make-zip.sh
# -> dist/pkg_engage-<version>.zip
```

Requires **Composer** (for the backend dependencies: htmlpurifier). If `component/backend/vendor` is missing, the script runs `composer install` automatically. It assembles the component + module + 10 plugins and wraps them with the package manifest (`pkg_engage.xml`), the installer script and the language files. The resulting zip installs via **Extensions → Install → Upload**.

### CComment → Engage importer (`import/ccomment-to-engage.php`)
**Standalone, on-demand** tool (it is not installed and never runs on its own) to migrate comments from the abandoned **CComment** (Compojoom) to Engage. It does not need to boot Joomla: it reads the credentials from a `configuration.php`, takes the comments from `<prefix>comment`, resolves each article's `asset_id` from `<prefix>content`, and inserts into `<prefix>engage_comments`.

```bash
# 1) Dry run (changes nothing; shows what it would do):
php import/ccomment-to-engage.php --config=/path/to/configuration.php

# 2) After reviewing and backing up the DB, run for real:
php import/ccomment-to-engage.php --config=/path/to/configuration.php --commit
```

Features: **dry-run by default**, re-runnable without duplicating (skips comments already imported, matched by `asset_id`+date+email), skips comments on deleted articles, maps published/unpublished state, and converts plain text to safe HTML. Options: `--source-table`, `--user-agent`, `--include-spam`, `--no-skip-existing`, `--limit`, `--help`.

Requirements: PHP CLI with `pdo_mysql`. Engage must be installed (so that `<prefix>engage_comments` exists).

## Disclaimer and license

- This fork is **not actively maintained** and **not affiliated** with Akeeba Ltd. Original software by Nicholas K. Dionysopoulos / Akeeba Ltd.
- The J6 patch was the work of [TheAIDirector.win](https://theaidirector.win); cubakumori/engage-k contributions (importer, `make-zip.sh`, J7 readiness) were made on top of that fork.
- **Use at your own risk**: test on a staging environment and back up the database before importing.
- License: **GNU General Public License v3 or later** (see [LICENSE](LICENSE)).

## Original project
- Repository: https://github.com/akeeba/engage
- Author: Akeeba Ltd / Nicholas K. Dionysopoulos — last release 3.4.3 (archived August 2025)
