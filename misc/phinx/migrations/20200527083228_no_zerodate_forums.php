<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateForums extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE forums
            MODIFY LastPostTime datetime DEFAULT NULL
        ");
    }

    public function down(): void {
        $this->execute("ALTER TABLE forums
            MODIFY LastPostTime datetime
        ");
    }
}

