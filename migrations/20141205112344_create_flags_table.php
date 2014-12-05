<?php

use Phinx\Migration\AbstractMigration;

class CreateFlagsTable extends AbstractMigration
{
    public function change()
    {
        $this
            ->table('flags')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addIndex(['name'], ['unique' => true])
            ->create();
    }
}
