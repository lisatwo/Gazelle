<?php

namespace Gazelle\Schedule\Tasks;

class RatioRequirements extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\User)->updateRatioRequirements();
    }
}
