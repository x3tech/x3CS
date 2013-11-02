<?php
namespace x3\CheckinSystem;

use Silex\Application;
use Silex\ControllerProviderInterface;

class CheckinRoutesProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $routes = $app['controllers_factory'];    

        $routes->get('/', function () use ($app) {
            return $app->redirect($app['url_generator']->generate('checkin'));
        });

        $routes->get('/checkin', 'controller.checkin:showCheckin')->bind('checkin');
        $routes->post('/checkin', 'controller.checkin:checkinPost')->bind('checkin_post');

        $routes->get('/attendees/', 'controller.attendee_list:showAll')->bind('attendees');
        $routes->get('/attendees/present/', 'controller.attendee_list:showPresent')->bind('attendees_present');
        $routes->get('/attendees/absent/', 'controller.attendee_list:showAbsent')->bind('attendees_absent');

        $routes->get('/import', 'controller.import:showImport')->bind('import');
        $routes->post('/import', 'controller.import:performImport')->bind('import_post');

        if($app['x3cs.config']->reset_password) {
            $routes->get('/reset', 'controller.reset:showReset')->bind('reset');
            $routes->post('/reset', 'controller.reset:submitReset')->bind('reset_post');
        }

        return $routes;
    }
}
