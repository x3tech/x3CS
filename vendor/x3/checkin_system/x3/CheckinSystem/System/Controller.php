<?php
use Symfony\Component\HttpFoundation\Request;

use x3\CheckinSystem\AttendeeManager;
use x3\CheckinSystem\Exception\AlreadyCheckedIn;
use x3\CheckinSystem\Exception\AttendeeNotFound;

function setup($app) 
{
    $app->get('/', function () use ($app) {
        return $app->redirect($app['url_generator']->generate('checkin'));
    });

    $app->get('/checkin', 'controller.checkin:showCheckin')->bind('checkin');
    $app->post('/checkin', 'controller.checkin:checkinPost')->bind('checkin_post');

    $app->get('/attendees/', 'controller.attendee_list:showAll')->bind('attendees');
    $app->get('/attendees/present/', 'controller.attendee_list:showPresent')->bind('attendees_present');
    $app->get('/attendees/absent/', 'controller.attendee_list:showAbsent')->bind('attendees_absent');

    $app->get('/import', 'controller.import:showImport')->bind('import');
    $app->post('/import', 'controller.import:performImport')->bind('import_post');

    if($app['x3cs.config']->reset_password) {
        $app->get('/reset', 'controller.reset:showReset')->bind('reset');
        $app->post('/reset', 'controller.reset:submitReset')->bind('reset_post');
    }
}
