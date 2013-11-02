<?php
namespace x3\CheckinSystem\Exception;

class AttendeeNotFound extends \Exception
{
    protected $ticketId;

    public function __construct($ticketId)
    {
        $this->ticketId = $ticketId;
        parent::__construct("Attendee not found with ticket ID: " . $ticketId);
    }

    public function getTicketId()
    {
        return $this->ticketId;
    }
}

