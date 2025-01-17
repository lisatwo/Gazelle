<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class DbTest extends TestCase {
    use Gazelle\Pg;

    public function testTableCoherency(): void {
        $db = Gazelle\DB::DB();
        $db->prepared_query($sql = "
             SELECT replace(table_name, 'deleted_', '') as table_name
             FROM information_schema.tables
             WHERE table_schema = ?
                AND table_name LIKE 'deleted_%'
            ", SQLDB
        );

        $dbMan = new Gazelle\DB;
        foreach ($db->collect(0, false) as $tableName) {
            [$ok, $message] = $dbMan->checkStructureMatch(SQLDB, $tableName, "deleted_$tableName");
            $this->assertTrue($ok, "mismatch -- $message");
        }
    }

    public function testGlobalStatus(): void {
        $status = (new Gazelle\DB)->globalStatus();
        $this->assertGreaterThan(500, count($status), 'db-global-status');
        $this->assertEquals('server-cert.pem', $status['Current_tls_cert']['Value'], 'db-current-tls-cert');
    }

    public function testGlobalVariables(): void {
        $list = (new Gazelle\DB)->globalVariables();
        $this->assertGreaterThan(500, count($list), 'db-global-variables');
        $this->assertEquals('ON', $list['foreign_key_checks']['Value'], 'db-foreign-key-checks-on');
    }

    public function testLongRunning(): void {
        $this->assertEquals(0, (new Gazelle\DB)->longRunning(), 'db-long-running');
    }

    public function testPg(): void {
        $this->assertInstanceOf(\PDO::class, $this->pg()->pdo(), 'db-pg-pdo');
        $num = random_int(100, 999);
        $this->assertEquals($num, (int)$this->pg()->scalar("select ?", $num), 'db-pg-scalar');

        $st = $this->pg()->prepare("
            create temporary table t (
                id_t integer not null primary key generated always as identity,
                label text not null,
                created timestamptz not null default current_date
            )
        ");
        $this->assertInstanceOf(\PDOStatement::class, $st, 'db-pg-st');
        $this->assertTrue($st->execute(), 'db-pg-create-tmp-table');

        $id = $this->pg()->insert("
            insert into t (label) values (?)
            ", 'phpunit'
        );
        $this->assertEquals(1, $id, 'db-pg-last-id');
        $this->assertEquals(4, $this->pg()->insert("
            insert into t (label) values (?), (?), (?)
            ", 'abc', 'def', 'ghi'),
            'db-pg-triple-insert'
        );
        $this->assertEquals([2], $this->pg()->row("
            select id_t from t where label = ?
            ", 'abc'),
            'db-pg-row'
        );
        $this->assertEquals(['i' => 3, 'j' => 'def'], $this->pg()->rowAssoc("
            select id_t as i, label as j from t where id_t = ?
            ", 3)
            , 'db-pg-assoc-row'
        );
        $this->assertEquals(['phpunit', 'abc', 'def', 'ghi'], $this->pg()->column("
            select label from t order by id_t
            ")
            , 'db-pg-column'
        );
        $all = $this->pg()->all("
            select id_t, label, created from t order by id_t desc
        ");
        $this->assertCount(4, $all, 'pg-all-total');
        $this->assertEquals(['id_t', 'label', 'created'], array_keys($all[0]), 'pg-all-column-names');
        $this->assertEquals('ghi', $all[0]['label'], 'pg-all-row-value');
        $this->assertEquals(
            3,
            $this->pg()->prepared_query("
                update t set label = upper(label) where char_length(label) = ?
                ", 3
            ),
            'db-pg-prepared-update'
        );
    }
}
