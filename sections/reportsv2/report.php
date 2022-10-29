<?php
/*
 * This is the frontend of reporting a torrent, it's what users see when
 * they visit reportsv2.php?id=xxx
 */

$torMan = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$torrentId = (int)($_GET['id'] ?? 0);
$torrent   = $torMan->findById($torrentId);
if (is_null($torrent)) {
    // Deleted torrent
    header("Location: log.php?search=Torrent+$torrentId");
    exit;
}

$reportMan = new Gazelle\Manager\Torrent\Report($torMan);
$Types     = $reportMan->types();
$snatcher  = new Gazelle\User\Snatch($Viewer);
$urlStem   = (new Gazelle\User\Stylesheet($Viewer))->imagePath();
$userMan   = new Gazelle\Manager\User;

$tgroup        = $torrent->group();
$GroupID       = $tgroup->id();
$CategoryID    = $tgroup->categoryId();
$remasterTuple = false;
$FirstUnknown  = $torrent->isRemasteredUnknown();
$Reports       = $torMan->reportList($Viewer, $torrentId);
$NumReports    = count($Reports);
$Reported      = $NumReports > 0;
$EditionID     = 0;

if (empty($Types[$CategoryID])) {
    $TypeList = $Types['master'];
} else {
    $TypeList = $Types['master'] + $Types[$CategoryID];
    $Priorities = [];
    foreach ($TypeList as $Key => $Value) {
        $Priorities[$Key] = $Value['priority'];
    }
    array_multisort($Priorities, SORT_ASC, $TypeList);
}

View::show_header('Report', ['js' => 'reportsv2,browse,torrent,bbcode']);
?>
<div class="thin">
    <div class="header">
        <h2>Report a torrent</h2>
    </div>
    <div class="header">
        <h3><?= $tgroup->link() . " [{$tgroup->year()}] [{$tgroup->releaseTypeName()}]" ?></h3>
    </div>
    <div class="thin">
        <table class="torrent_table details<?= $snatcher->showSnatch($torrent->id()) ? ' snatched' : '' ?>" id="torrent_details">
            <tr class="colhead_dark">
                <td width="80%"><strong>Reported torrent</strong></td>
                <td><strong>Size</strong></td>
                <td class="sign snatches"><img src="<?= $urlStem ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="sign seeders"><img src="<?= $urlStem ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="sign leechers"><img src="<?= $urlStem ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php if ($tgroup->categoryName() == 'Music' && ($FirstUnknown || $remasterTuple != $torrent->remasterTuple())) { ?>
                <tr class="releases_<?= $tgroup->releaseType() ?> groupid_<?= $GroupID ?> edition group_torrent">
                    <td colspan="5" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?= $GroupID ?>, <?= $EditionID ?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?= $torrent->edition() ?></strong></td>
                </tr>
<?php
    $EditionID++;
}
$remasterTuple = $torrent->remasterTuple();
?>
                <tr class="torrent_row releases_<?= $tgroup->releaseType() ?> groupid_<?=($GroupID)?> edition_<?=($EditionID)?> group_torrent<?=
                    $snatcher->showSnatch($torrent->id()) ? ' snatched_torrent' : '' ?>" style="font-weight: normal;" id="torrent<?= $torrentId ?>">
                    <td>
                        <?= $Twig->render('torrent/action-v2.twig', [
                            'edit'    => true,
                            'pl'      => true,
                            'remove'  => true,
                            'torrent' => $torrent,
                            'viewer'  => $Viewer,
                        ]) ?>
                        &raquo; <a href="#" onclick="$('#torrent_<?= $torrentId ?>').gtoggle(); return false;"><?=
                            implode(' / ', $torrent->labelList()) ?></a>
                    </td>
                    <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
                </tr>
                <tr class="releases_<?= $tgroup->releaseType() ?> groupid_<?=($GroupID)?> edition_<?=($EditionID)?> torrentdetails pad<?php if (!isset($_GET['torrentid']) || $_GET['torrentid'] != $torrentId) { ?> hidden<?php } ?>" id="torrent_<?= $torrentId ?>">
                    <td colspan="5">
                        <blockquote>
                            Uploaded by <?= $torrent->uploader()->link() ?> <?=time_diff($torrent->uploadDate()) ?>
<?php
    if (!$torrent->seederTotal()) {
        $LastActive = $torrent->lastActiveDate();
        if (!is_null($LastActive) && time() - strtotime($LastActive) >= 1209600) {
?>
                                <br /><strong>Last active: <?=time_diff($LastActive);?></strong>
<?php   } else { ?>
                                <br />Last active: <?=time_diff($LastActive);?>
<?php
        }
        if (!is_null($LastActive) && time() - strtotime($LastActive) >= 345678 && (is_null($torrent->lastReseedRequest()) || time() - strtotime($torrent->lastReseedRequest()) >= 864000)) {
?>)
                                <br /><a href="torrents.php?action=reseed&amp;torrentid=<?= $torrentId ?>&amp;groupid=<?=($GroupID)?>" class="brackets">Request re-seed</a>
<?php
        }
    }
?>
                        </blockquote>
<?php if ($Viewer->permitted('site_moderate_requests')) { ?>
                        <div class="linkbox">
                            <a href="torrents.php?action=masspm&amp;id=<?=($GroupID)?>&amp;torrentid=<?= $torrentId ?>" class="brackets">Mass PM snatchers</a>
                        </div>
<?php } ?>
                        <div class="linkbox">
<?php if ($Viewer->permitted('site_view_torrent_snatchlist')) { ?>
                            <a href="#" class="brackets tooltip" onclick="show_downloads('<?= $torrentId ?>', 0); return false;" title="View the list of users that have clicked the &quot;DL&quot; button.">View downloaders</a>
                            <a href="#" class="brackets tooltip" onclick="show_snatches('<?= $torrentId ?>', 0); return false;" title="View the list of users that have reported a snatch to the tracker.">View snatchers</a>
<?php } ?>
                            <a href="#" class="brackets" onclick="show_seeders('<?= $torrentId ?>', 0); return false;">View seeders</a>
                            <a href="#" class="brackets" onclick="show_files('<?= $torrentId ?>'); return false;">View contents</a>
<?php if ($Reported) { ?>
                            <a href="#" class="brackets" onclick="show_reported('<?= $torrentId ?>'); return false;">View report information</a>
<?php } ?>
                        </div>
                        <div id="peers_<?= $torrentId ?>" class="hidden"></div>
                        <div id="downloads_<?= $torrentId ?>" class="hidden"></div>
                        <div id="snatches_<?= $torrentId ?>" class="hidden"></div>
                        <div id="files_<?= $torrentId ?>" class="hidden">
    <table class="filelist_table">
        <tr class="colhead_dark">
            <td>
                <div class="filelist_title" style="float: left;">File Names<?=
                    $Viewer->permitted('users_mod') ? (' <a href="torrents.php?action=regen_filelist&amp;torrentid=' . $torrentId . '" class="brackets">Regenerate</a>') : '' ?></div>
                <div class="filelist_path" style="float: right;"><?= $torrent->path() ? ("/" . $torrent->path() . "/") : '' ?></div>
            </td>
            <td>
                <strong>Size</strong>
            </td>
        </tr>
<?php
$fileList = $torrent->fileList();
foreach ($fileList as $File) {
    $FileInfo = $torMan->splitMetaFilename($File);
?>
            <tr><td><?= display_str($FileInfo['name']) ?></td><td class="number_column"><?= Format::get_size($FileInfo['size']) ?></td></tr>
<?php } ?>
</table>
</div>

<?php if ($Reported) { ?>
                        <div id="reported_<?= $torrentId ?>" class="hidden">
    <table class="reportinfo_table">
        <tr class="colhead_dark" style="font-weight: bold;">
            <td>This torrent has <?= $NumReports ?> active report<?= plural($NumReports) ?>:</td>
        </tr>
<?php
    foreach ($Reports as $Report) {
        if ($Viewer->permitted('admin_reports')) {
            $ReporterID = $Report['ReporterID'];
            $ReporterName = $userMan->findById($ReporterID)->username();
            $ReportLinks = "<a href=\"user.php?id=$ReporterID\">$ReporterName</a> <a href=\"reportsv2.php?view=report&amp;id={$Report['ID']}\">reported it</a>";
        } else {
            $ReportLinks = 'Someone reported it';
        }
        if (isset($Types[$CategoryID][$Report['Type']])) {
            $ReportType = $Types[$CategoryID][$Report['Type']];
        } elseif (isset($Types['master'][$Report['Type']])) {
            $ReportType = $Types['master'][$Report['Type']];
        } else {
            //There was a type but it wasn't an option!
            $ReportType = $Types['master']['other'];
        }
?>
        <tr>
            <td><?= $ReportLinks ?><?= time_diff($Report['ReportedTime'], 2) ?> for the reason <?= $ReportType['title'] ?>:
                <blockquote><?= Text::full_format($Report['UserComment']) ?></blockquote>
            </td>
        </tr>
<?php } ?>
    </table>
</div>
<?php
}
if (!empty($torrent->description())) {
?>
                            <blockquote><?= Text::full_format($torrent->description()) ?></blockquote>
<?php } ?>
                    </td>
                </tr>
        </table>
    </div>

    <form class="create_form" name="report" action="reportsv2.php?action=takereport" enctype="multipart/form-data" method="post" id="reportform">
        <div>
            <input type="hidden" name="submit" value="true" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="torrentid" value="<?=$torrentId?>" />
            <input type="hidden" name="categoryid" value="<?= $CategoryID ?>" />
        </div>

        <h3>Report Information</h3>
        <div class="box pad">
            <table class="layout">
                <tr>
                    <td class="label">Reason:</td>
                    <td>
                        <select id="type" name="type" onchange="ChangeReportType();">
<?php foreach ($TypeList as $Type => $Data) { ?>
            <option value="<?=($Type)?>"><?=($Data['title'])?></option>
<?php } ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p>Fields that contain lists of values (for example, listing more than one track number) should be separated by a space.</p>
            <br />
            <p><strong>Following the below report type specific guidelines will help the moderators deal with your report in a timely fashion. </strong></p>
            <br />

            <div id="dynamic_form">
                <input id="sitelink" type="hidden" name="sitelink" size="50" value="<?=(!empty($_POST['sitelink']) ? display_str($_POST['sitelink']) : '')?>" />
                <input id="image" type="hidden" name="image" size="50" value="<?=(!empty($_POST['image']) ? display_str($_POST['image']) : '')?>" />
                <input id="track" type="hidden" name="track" size="8" value="<?=(!empty($_POST['track']) ? display_str($_POST['track']) : '')?>" />
                <input id="link" type="hidden" name="link" size="50" value="<?=(!empty($_POST['link']) ? display_str($_POST['link']) : '')?>" />
                <input id="extra" type="hidden" name="extra" value="<?=(!empty($_POST['extra']) ? display_str($_POST['extra']) : '')?>" />

                <script type="text/javascript">ChangeReportType();</script>
            </div>
        </div>
    <input type="submit" value="Submit report" />
    </form>
</div>
<?php
View::show_footer();
