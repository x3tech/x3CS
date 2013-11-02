<?php
namespace x3\CheckinSystem\Controllers;

use x3\CheckinSystem\AttendeeManager;

class AttendeeListController
{
    protected $manager;
    protected $twig;

    public function __construct($twig, AttendeeManager $manager)
    {
        $this->twig = $twig;
        $this->manager = $manager;
    }

    public function showAll()
    {
        return $this->twig->render('attendees.html.twig', array(
            'attendees' => $this->manager->getAttendees(null),
            'type' => 'all',
            'flags' => $this->manager->getFlags()
        ));
    }

    public function showPresent()
    {
        return $this->twig->render('attendees.html.twig', array(
            'attendees' => $this->manager->getAttendees(true),
            'type' => 'present',
            'flags' => $this->manager->getFlags()
        ));
    }
    public function showAbsent()
    {
        return $this->twig->render('attendees.html.twig', array(
            'attendees' => $this->manager->getAttendees(false),
            'type' => 'absent',
            'flags' => $this->manager->getFlags()
        ));
    }
}
