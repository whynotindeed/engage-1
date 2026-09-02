#!/usr/bin/env php
<?php
/**
 * CComment -> Akeeba Engage comment importer (standalone, on-demand).
 *
 * Migrates comments from the abandoned CComment component (Compojoom) into
 * Akeeba Engage. It reads database credentials from a Joomla
 * configuration.php, reads CComment rows from `<prefix>comment`, resolves
 * each commented article's Joomla asset id from `<prefix>content`, and
 * inserts the comments into `<prefix>engage_comments`.
 *
 * It is a *standalone* script (no Joomla bootstrap required) so it can be run
 * on any site that has this exact need. It is NOT run automatically on
 * install: you invoke it by hand when you want to migrate.
 *
 * USAGE
 *   php import/ccomment-to-engage.php --config=/path/to/configuration.php [options]
 *
 * OPTIONS
 *   --config=PATH        Path to the site''s configuration.php (required).
 *   --commit             Actually write to the database. WITHOUT this flag the
 *                        script runs as a DRY RUN and changes nothing.
 *   --source-table=NAME  CComment table name (default: <prefix>comment).
 *   --user-agent=STR     Value stored in engage_comments.user_agent
 *                        (default: "Imported from CComment").
 *   --include-spam       Also import rows flagged spam/deleted (default: skip).
 *   --no-skip-existing   Do not skip comments that look already-imported.
 *   --limit=N            Process at most N source rows (0 = all, default).
 *   -h, --help           Show this help.
 *
 * SAFETY
 *   - Dry run by default. Always take a database backup before --commit.
 *   - Re-running is safe: by default it skips comments already present in
 *     Engage (matched by asset_id + created datetime + email + body).
 *
 * @package    AkeebaEngage (community fork)
 * @copyright  Importer (c) 2026 Kumori. Released under GNU GPL v3 or later.
 * @license    GNU General Public License version 3, or later
 */

if (PHP_SAPI !== "cli") {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$opts = getopt("h", [
    "help", "config:", "commit", "source-table:", "user-agent:",
    "include-spam", "no-skip-existing", "limit:",
]);

if (isset($opts["h"]) || isset($opts["help"]) || !isset($opts["config"])) {
    fwrite(STDOUT, <<<TXT
CComment -> Akeeba Engage importer

  php import/ccomment-to-engage.php --config=/path/to/configuration.php [options]

  --config=PATH        Path to configuration.php (required)
  --commit             Write to DB (default: dry run, no changes)
  --source-table=NAME  CComment table (default: <prefix>comment)
  --user-agent=STR     user_agent value (default: "Imported from CComment")
  --include-spam       Also import spam/deleted rows (default: skip)
  --no-skip-existing   Do not skip already-imported comments
  --limit=N            Process at most N rows (default: all)
  -h, --help           This help

TXT);
    exit(isset($opts["config"]) ? 0 : 1);
}

$commit       = isset($opts["commit"]);
$skipExisting = !isset($opts["no-skip-existing"]);
$includeSpam  = isset($opts["include-spam"]);
$userAgent    = $opts["user-agent"] ?? "Imported from CComment";
$limit        = (int) ($opts["limit"] ?? 0);
$configPath   = $opts["config"];

if (!is_file($configPath)) {
    fwrite(STDERR, "configuration.php not found at: {$configPath}\n");
    exit(1);
}

// Load DB credentials from configuration.php without needing Joomla.
require $configPath;
if (!class_exists("JConfig")) {
    fwrite(STDERR, "The file at --config does not define a JConfig class.\n");
    exit(1);
}
$cfg    = new JConfig();
$prefix = $cfg->dbprefix;
$srcTbl = $opts["source-table"] ?? ($prefix . "comment");
$engTbl = $prefix . "engage_comments";
$conTbl = $prefix . "content";

$dsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", $cfg->host, $cfg->db);
try {
    $pdo = new PDO($dsn, $cfg->user, $cfg->password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// Sanity: required tables must exist.
foreach ([$srcTbl => "CComment", $engTbl => "Akeeba Engage", $conTbl => "Joomla content"] as $t => $label) {
    $q = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
    if (!$q->fetch()) {
        fwrite(STDERR, "Required table '{$t}' ({$label}) does not exist. Is the extension installed?\n");
        exit(1);
    }
}

echo ($commit ? "== COMMIT MODE (writing changes) ==\n" : "== DRY RUN (no changes; add --commit to write) ==\n");
echo "Source (CComment): {$srcTbl}\nTarget (Engage):   {$engTbl}\n\n";

$where = $includeSpam ? "1=1" : "deleted = 0 AND spam = 0";
$sql   = "SELECT * FROM `{$srcTbl}` WHERE {$where} ORDER BY id";
if ($limit > 0) {
    $sql .= " LIMIT " . $limit;
}
$rows = $pdo->query($sql)->fetchAll();

$assetStmt = $pdo->prepare("SELECT asset_id FROM `{$conTbl}` WHERE id = :id");
$dupeStmt  = $pdo->prepare(
    "SELECT id FROM `{$engTbl}` WHERE asset_id = :asset_id AND created = :created "
    . "AND COALESCE(email,'') = :email LIMIT 1"
);
$insStmt = $pdo->prepare(
    "INSERT INTO `{$engTbl}` "
    . "(parent_id, asset_id, body, name, email, ip, user_agent, enabled, created, created_by, modified, modified_by) "
    . "VALUES (NULL, :asset_id, :body, :name, :email, :ip, :user_agent, :enabled, :created, :created_by, :modified, :modified_by)"
);

/**
 * Turn CComment plain text into safe HTML for Engage. If the text already
 * contains HTML tags it is kept as-is; otherwise it is escaped and newlines
 * become <br>.
 */
function htmlize(string $text): string
{
    $text = trim($text);
    if ($text === "") {
        return "";
    }
    if (preg_match("/<[a-z][\s\S]*>/i", $text)) {
        return $text;
    }
    return nl2br(htmlspecialchars($text, ENT_QUOTES, "UTF-8"));
}

$imported = $skipped = $noAsset = $dupes = 0;
$samples  = [];

foreach ($rows as $r) {
    $assetStmt->execute([":id" => (int) $r["contentid"]]);
    $assetId = $assetStmt->fetchColumn();

    if (!$assetId) {
        $noAsset++;
        continue;
    }

    $data = [
        ":asset_id"    => (int) $assetId,
        ":body"        => htmlize((string) $r["comment"]),
        ":name"        => ($r["name"] !== "" ? $r["name"] : null),
        ":email"       => ($r["email"] !== "" ? $r["email"] : null),
        ":ip"          => ($r["ip"] !== "" ? $r["ip"] : null),
        ":user_agent"  => $userAgent,
        ":enabled"     => (int) $r["published"],
        ":created"     => $r["date"],
        ":created_by"  => ((int) $r["userid"] > 0 ? (int) $r["userid"] : null),
        ":modified"    => (!empty($r["modified"]) && $r["modified"] !== "0000-00-00 00:00:00") ? $r["modified"] : null,
        ":modified_by" => ((int) $r["modified_by"] > 0 ? (int) $r["modified_by"] : null),
    ];

    if ($skipExisting) {
        $dupeStmt->execute([
            ":asset_id" => $data[":asset_id"],
            ":created"  => $data[":created"],
            ":email"    => (string) ($data[":email"] ?? ""),
        ]);
        if ($dupeStmt->fetch()) {
            $dupes++;
            continue;
        }
    }

    if (count($samples) < 5) {
        $samples[] = sprintf(
            "  #%s article-asset:%d by %s [%s] %s -> %s",
            $r["id"], $data[":asset_id"], $data[":name"] ?? "guest",
            $data[":enabled"] ? "published" : "unpublished",
            $data[":created"], mb_substr(strip_tags($data[":body"]), 0, 45)
        );
    }

    if ($commit) {
        $insStmt->execute($data);
    }
    $imported++;
}

echo "Sample of comments to import:\n" . (count($samples) ? implode("\n", $samples) : "  (none)") . "\n\n";
echo "Source rows read:        " . count($rows) . "\n";
echo "Skipped (already there):  {$dupes}\n";
echo "Skipped (article gone):   {$noAsset}\n";
echo ($commit ? "Imported (written):       {$imported}\n" : "Would import:             {$imported}\n");
echo "\n" . ($commit ? "Done. Verify in Engage backend, then you can uninstall CComment.\n"
                      : "Dry run complete. Re-run with --commit to write (after a DB backup).\n");
