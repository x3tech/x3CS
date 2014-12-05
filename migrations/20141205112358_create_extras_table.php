<?php

use Phinx\Migration\AbstractMigration;

class CreateExtrasTable extends AbstractMigration
{
    public function change()
    {
        $this
            ->table('extras')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addIndex(['name'], ['unique' => true])
            ->create();
    }
}
