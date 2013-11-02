<?php
namespace x3\CheckinSystem\Controllers;

use Symfony\Component\HttpFoundation\Request;
use x3\CheckinSystem\AttendeeManager;
use x3\CheckinSystem\Exception\AlreadyCheckedIn;
use x3\CheckinSystem\Exception\AttendeeNotFound;

class CheckinController
{
    protected $manager;
    protected $twig;

    public function __construct($twig, AttendeeManager $manager)
    {
        $this->twig = $twig;
        $this->manager = $manager;
    }

    public function checkinPost(Request $request)
    {
        $ticketId = $request->request->get('ticket_id');

        if(empty($ticketId)) {
            return $this->showCheckin();
        } else if(!is_numeric($ticketId)) {
            return $this->performSearch($ticketId);
        } else {
            return $this->performCheckin($ticketId);
        }
    }

    protected function performCheckin($ticketId)
    {
        try {
            $attendee = $this->manager->attemptCheckIn($ticketId);
        } catch(AttendeeNotFound $exc) {
            return $this->showMessage(
                'Attendee Not Found',
                'An attendee could not be found with the ID: ' . $exc->getTicketId()
            );
        } catch(AlreadyCheckedIn $exc) {
            return $this->showCheckinResult(false, $exc->getAttendee());
        }
        return $this->showCheckinResult(true, $attendee);
    }

    protected function performSearch($searchTerm)
    {
        # Prevent wildcard searching
        $searchTerm = str_replace(array('%', '?'), '', $searchTerm);
        if(strlen($searchTerm) < 3) {
            return $this->showMessage(
                'Search Too Short',
                'Search should be a minimum of 3 characters'
            );
        }

        $attendees = $this->manager->search($searchTerm);

        return $this->showCheckin(array(
            'attendees' => $attendees,
            'search' => $searchTerm
        ), 'search_result');
    }

    public function showCheckin(array $view = array(), $tpl = 'base')
    {
        return $this->twig->render('checkin/'. $tpl . '.html.twig', $this->getView($view));
    }

    protected function showCheckinResult($status, $attendee)
    {
        return $this->showCheckin(array(
            'status' => $status,
            'attendee' => $attendee
        ), 'result');
    }

    protected function showMessage($header, $text, $icon = 'warning')
    {
        return $this->showCheckin(array(
            'message' => array(
                'icon' => $icon,
                'header' => $header,
                'text' => $text
            )
        ));
    }

    protected function getView(array $baseView = array())
    {
        return array_merge(array(
            'flags' => array_chunk($this->manager->getFlags(), 2)
        ), $baseView);
    }
}
