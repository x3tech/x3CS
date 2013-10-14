<?php
namespace x3\CheckinSystem\Exception;

class AlreadyCheckedIn extends \Exception
{
    protected $attendee;

    public function __construct(array $attendee)
    {
        $this->attendee = $attendee;
        parent::__construct("Attendee already checked in: " . $attendee['name']);
    }

    public function getAttendee()
    {
        return $this->attendee;
    }
}
