# Akeeba Engage — Joomla 6 Compatibility Fork

**Unofficial fork with Joomla 6 compatibility patch.**

The original [Akeeba Engage](https://github.com/akeeba/engage) was archived by Akeeba Ltd in August 2025. This fork applies a minimal namespace fix so the extension works on Joomla 6.

**TL;DR:** Replace `Joomla\CMS\Filesystem` with `Joomla\Filesystem` in 4 use statements across 3 files. That's it. If you'd rather patch the original yourself instead of using this fork:

```bash
# From your Joomla root:
sed -i 's/use Joomla\\CMS\\Filesystem\\Path;/use Joomla\\Filesystem\\Path;/' administrator/components/com_engage/src/Mixin/ViewLoadAnyTemplateTrait.php
sed -i 's/use Joomla\\CMS\\Filesystem\\File;/use Joomla\\Filesystem\\File;/' administrator/components/com_engage/src/Model/UpdatesModel.php
sed -i 's/use Joomla\\CMS\\Filesystem\\File;/use Joomla\\Filesystem\\File;/' administrator/components/com_engage/src/Model/UpgradeModel.php
sed -i 's/use Joomla\\CMS\\Filesystem\\Folder;/use Joomla\\Filesystem\\Folder;/' administrator/components/com_engage/src/Model/UpgradeModel.php
```

## What was changed

4 namespace references updated to match Joomla 6's restructured Filesystem package:

| File | Old namespace | New namespace |
|------|--------------|---------------|
| `ViewLoadAnyTemplateTrait.php` | `Joomla\CMS\Filesystem\Path` | `Joomla\Filesystem\Path` |
| `UpdatesModel.php` | `Joomla\CMS\Filesystem\File` | `Joomla\Filesystem\File` |
| `UpgradeModel.php` | `Joomla\CMS\Filesystem\File` | `Joomla\Filesystem\File` |
| `UpgradeModel.php` | `Joomla\CMS\Filesystem\Folder` | `Joomla\Filesystem\Folder` |

In Joomla 6, the `Joomla\CMS\Filesystem` classes were moved to `Joomla\Filesystem`. Without this fix, the Akeeba Engage admin panel throws an "Unhandled Exception" error.

## Maintained by

This fork is maintained by [TheAIDirector.win](https://theaidirector.win) — a Joomla site dedicated to AI tools, automation, and practical guides for people who actually use AI.

## Disclaimer

- This patch was created using **Claude Code** (AI coding agent by Anthropic) for [TheAIDirector.win](https://theaidirector.win)
- This fork is **not affiliated with or endorsed by Akeeba Ltd**
- The original software was written by **Nicholas K. Dionysopoulos / Akeeba Ltd**
- **Use at your own risk** — always review changes before deploying to production
- No warranty is provided — test thoroughly in a staging environment first

## License

GNU General Public License version 3 or later. See [LICENSE](LICENSE).

## Original project

- Repository: https://github.com/akeeba/engage
- Author: Akeeba Ltd / Nicholas K. Dionysopoulos
- Final version: 3.4.3 (archived August 2025)
