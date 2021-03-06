<?php

namespace Emonkak\Database\Tests;

use Emonkak\Database\PDOAdapter;

/**
 * @covers \Emonkak\Database\PDOAdapter
 */
class PDOAdapterTest extends AbstractPDOTest
{
    public function testGetPdo()
    {
        $this->assertInstanceOf('PDO', $this->pdo->getPdo());
    }

    protected function preparePdo()
    {
        return new PDOAdapter(new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]));
    }
}
