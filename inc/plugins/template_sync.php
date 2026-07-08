<?php

/**
 * Template File Sync
 * - Writes templates to disk on every admin save.
 * - Detects FTP-uploaded changes and pushes them to the DB every ~10 seconds.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

define('TEMPLATE_SYNC_INTERVAL', 10);
define('TEMPLATE_SYNC_DIR',      'templates/One_Piece_Gaiden_Templates');
define('TEMPLATE_SYNC_CACHE',    'cache/template_sync_last.php');

function template_sync_info()
{
    return [
        'name'          => 'Template File Sync',
        'description'   => 'Syncs templates between the DB and /templates/: saves to disk on admin edits, and imports FTP-uploaded changes every ~10 seconds.',
        'author'        => 'OPG',
        'version'       => '1.2',
        'compatibility' => '18*',
    ];
}

function template_sync_is_installed()
{
    return file_exists(MYBB_ROOT . TEMPLATE_SYNC_CACHE);
}

function template_sync_install()
{
    // Mark all existing files as already synced — only files uploaded after this moment will be imported.
    file_put_contents(MYBB_ROOT . TEMPLATE_SYNC_CACHE, TIME_NOW);
}

function template_sync_uninstall()
{
    @unlink(MYBB_ROOT . TEMPLATE_SYNC_CACHE);
}

// ── Hook: file → DB on every forum request (throttled) ───────────────────────

$plugins->add_hook('global_start', 'template_sync_check_files');

function template_sync_check_files()
{
    global $db;

    $cache_file = MYBB_ROOT . TEMPLATE_SYNC_CACHE;
    $last_sync  = file_exists($cache_file) ? (int) file_get_contents($cache_file) : 0;

    if (TIME_NOW - $last_sync < TEMPLATE_SYNC_INTERVAL) {
        return;
    }

    $dir = MYBB_ROOT . TEMPLATE_SYNC_DIR;
    if (!is_dir($dir)) {
        return;
    }

    $now     = TIME_NOW;
    $updated = 0;

    foreach (glob($dir . '/*.html') as $file) {
        if (filemtime($file) <= $last_sync) {
            continue;
        }

        $title   = basename($file, '.html');
        $content = file_get_contents($file);

        $db->update_query(
            'templates',
            [
                'template' => $db->escape_string(rtrim($content)),
                'dateline' => $now,
            ],
            "title='" . $db->escape_string($title) . "' AND sid != '-2'"
        );

        $updated++;
    }

    file_put_contents($cache_file, $now);
}

// ── Hook: DB → file on every admin save ──────────────────────────────────────

if (defined('IN_ADMINCP')) {
    $plugins->add_hook('admin_style_templates_edit_template_commit', 'template_sync_write_file', 5);
}

function template_sync_write_file()
{
    global $mybb, $db;

    $title   = $mybb->input['title'];
    $content = rtrim($mybb->input['template']);
    $sid     = (int) $mybb->input['sid'];

    if ($sid === -2) {
        $set_name = 'master';
    } elseif ($sid <= 0) {
        $set_name = 'custom';
    } else {
        $query    = $db->simple_select('templatesets', 'title', "sid='{$sid}'");
        $row      = $db->fetch_array($query);
        $set_name = $row ? $row['title'] : 'set_' . $sid;
    }

    $set_name  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $set_name);
    $file_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $title) . '.html';

    $dir = MYBB_ROOT . 'templates/' . $set_name;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir . '/' . $file_name, $content);
}
