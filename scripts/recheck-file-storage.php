<?php

/* Scan all the torrent storage directory and look for things that
 * don't belong, and orphaned files of torrents that have been
 * deleted (via moderation or catastrophe).
 * Once it has been determined that all files reported do need to
 * be removed, the output can be piped as follows:
 *
 *   ... | awk '$1 != "##" {print $1}' | xargs rm
 *
 * and these extranenous files will be unlinked.
 */

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../classes/classloader.php');
require_once(__DIR__.'/../classes/util.php');

$DB    = new DB_MYSQL;
$Cache = new CACHE($MemcachedServers);

$allConfig = [
    '-html' => [
        'CHECK' => 'SELECT 1 FROM torrents_logs WHERE TorrentID = ? AND LogID = ?',
        'FILER' => new \Gazelle\File\RipLogHTML($DB, $Cache),
        'PIPE'  => '/usr/bin/find ' . STORAGE_PATH_RIPLOGHTML . ' -type f',
        'MATCH' => '~/(\d+)_(\d+)\.html$~',
    ],
    '-log' => [
        'CHECK' => 'SELECT 1 FROM torrents_logs WHERE TorrentID = ? AND LogID = ?',
        'FILER' => new \Gazelle\File\RipLog($DB, $Cache),
        'PIPE'  => '/usr/bin/find ' . STORAGE_PATH_RIPLOG . ' -type f',
        'MATCH' => '~/(\d+)_(\d+)\.log$~',
    ],
    '-torrent' => [
        'CHECK' => 'SELECT 1 FROM torrents WHERE ID = ?',
        'FILER' => new \Gazelle\File\Torrent($DB, $Cache),
        'PIPE'  => '/usr/bin/find ' . STORAGE_PATH_TORRENT . ' -type f',
        'MATCH' => '~/(\d+)\.torrent$~',
    ],
];

if ($argc < 2 || !isset($allConfig[$argv[1]])) {
    die('usage: ' . basename($argv[0]) . " <-html|-log|-torrent>\n");
}
$config = $allConfig[$argv[1]];

$Debug = new DEBUG;
$Debug->handle_errors();

ini_set('max_execution_time', -1);

$find = popen($config['PIPE'], 'r');
if ($find === false) {
    die("Could not popen(" . $config['PIPE'] . ")\n");
}

$begin     = microtime(true);
$processed = 0;
$orphan    = 0;
$alien     = 0;

while (($file = fgets($find)) !== false) {
    $file = trim($file);
    ++$processed;

    if (!preg_match($config['MATCH'], $file, $match)) {
        ++$alien;
        echo "$file is alien\n";
        continue;
    }

    if (!$DB->scalar($config['CHECK'], ...array_slice($match, 1))) {
        ++$orphan;
        echo "$file is orphan\n";
        continue;
    }
}

$delta = microtime(true) - $begin;
printf("## Processed %d files in %0.1f seconds (%0.2f file/sec), %d orphans, %d aliens.\n",
    $processed, $delta, $delta > 0 ? $processed / $delta : 0, $orphan, $alien
);