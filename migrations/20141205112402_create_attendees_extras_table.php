<?php

use Phinx\Migration\AbstractMigration;

class CreateAttendeesExtrasTable extends AbstractMigration
{
    public function change()
    {
        $this
            ->table('attendees_extras')
            ->addColumn('attendees_id', 'integer')
            ->addColumn('extras_id', 'integer')
            ->addColumn('quantity', 'integer')
            ->addColumn('type', 'string', ['limit' => 200])
            ->addForeignKey('attendees_id', 'attendees', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('extras_id', 'extras', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
