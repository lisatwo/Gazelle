<?php

namespace Gazelle\Schedule\Tasks;

class DisableLeechingRatioWatch extends \Gazelle\Schedule\Task {
    public function run(): void {
        self::$db->prepared_query("
            SELECT ID, torrent_pass
            FROM users_info AS i
            INNER JOIN users_main AS m ON (m.ID = i.UserID)
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = i.UserID)
            WHERE i.RatioWatchEnds IS NOT NULL
                AND i.RatioWatchDownload + 10 * 1024 * 1024 * 1024 < uls.Downloaded
                AND m.Enabled = '1'
                AND m.can_leech = '1'"
        );
        $users = self::$db->to_pair('torrent_pass', 'ID');

        if (count($users) > 0) {
            $userMan = new \Gazelle\Manager\User;
            $subject = 'Leeching Disabled';
            $message = 'You have downloaded more than 10 GB while on Ratio Watch. Your leeching privileges have been suspended. Please reread the rules and refer to this guide on [url=wiki.php?action=article&amp;name=ratiotips]how to improve your ratio[/url]';
            $tracker = new \Gazelle\Tracker;
            foreach ($users as $torrentPass => $userID) {
                $userMan->sendPM($userID, 0, $subject, $message);
                $tracker->update_tracker('update_user', ['passkey' => $torrentPass, 'can_leech' => '0']);
                $this->processed++;
                $this->debug("Disabling leech for $userID", $userID);
            }

            self::$db->prepared_query("
                UPDATE users_info AS i
                INNER JOIN users_main AS m ON (m.ID = i.UserID)
                SET
                    m.can_leech = '0',
                    i.AdminComment = CONCAT(now(), ' - Leeching privileges suspended by ratio watch system for downloading more than 10 GBs on ratio watch. - required ratio: ', m.RequiredRatio, '\n\n', i.AdminComment)
                WHERE m.ID IN (" . placeholders($users) . ")
            ", ...array_values($users));
        }
    }
}
