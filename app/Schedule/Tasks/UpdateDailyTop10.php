<?php

namespace Gazelle\Schedule\Tasks;

class UpdateDailyTop10 extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\Torrent)->storeTop10('Daily', 'day', 1);
    }
}
