<?php

use Phinx\Migration\AbstractMigration;

class CreateCheckinsTable extends AbstractMigration
{
    public function change()
    {
        $this
            ->table('checkins')
            ->addColumn('attendees_id', 'integer')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('attendees_id', 'attendees', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
