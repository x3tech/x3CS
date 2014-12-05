<?php

use Phinx\Migration\AbstractMigration;

class CreateAttendeesFlagsTable extends AbstractMigration
{
    public function change()
    {
        $this
            ->table('attendees_flags')
            ->addColumn('attendees_id', 'integer')
            ->addColumn('flags_id', 'integer')
            ->addForeignKey('attendees_id', 'attendees', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('flags_id', 'flags', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
