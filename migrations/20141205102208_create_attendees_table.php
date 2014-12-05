<?php

use Phinx\Migration\AbstractMigration;

class CreateAttendeesTable extends AbstractMigration
{
    public function change()
    {
        $this
            ->table('attendees')
            ->addColumn('name', 'string', ['limit' => 200])
            ->addColumn('nickname', 'string', ['limit' => 200])
            ->addColumn('ticket', 'string', ['limit' => 8])
            ->addIndex(['ticket'], ['unique' => true])
            ->create();
    }
}
