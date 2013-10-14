<?php
date_default_timezone_set("Europe/Amsterdam");

require_once(dirname(dirname(__FILE__)) . "/config.php");
require_once(ROOT_DIR . "/vendor/autoload.php");

use Symfony\Component\HttpFoundation\Request;

use x3\CheckinSystem\AttendeeManager;
use x3\CheckinSystem\Exception\AlreadyCheckedIn;

$app = new Silex\Application();
$app['debug'] = true;

$conn = new PDO(X3_CHECKIN_DB_DSN, X3_CHECKIN_DB_USER, X3_CHECKIN_DB_PASS);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$app['db.conn'] = $conn;

$app['x3cs.attendee_manager'] = $app->share(function( $app) {
    return new AttendeeManager($app['db.conn']);
});

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => array(
        ROOT_DIR . '/tpl/' . X3_CHECKIN_THEME . '/',
        ROOT_DIR . '/tpl/default/'
    ),
    'twig.options' => array(
        'debug' => $app['debug']
    )
));
$app['twig']->addGlobal('site', array(
    'title' => X3_CHECKIN_TITLE
));

$app->get('/checkin', function () use ($app) {
    return $app['twig']->render('checkin.html.twig', array());
})->bind('checkin');

$app->post('/checkin', function (Request $request) use ($app) {
    $attendeeId = $request->request->get('ticket_id');
    if(!$attendeeId) {
        return $app['twig']->render('checkin.html.twig', array());
    }
    try{
        $attendee = $app['x3cs.attendee_manager']->attemptCheckIn($attendeeId);
    } catch(AlreadyCheckedIn $exc) {
        return $app['twig']->render('checkin.html.twig', array(
            'status' => false,
            'attendee' => $exc->getAttendee(),
            'flags' => array_chunk($app['x3cs.attendee_manager']->getFlags(), 2)
        ));
    }

    return $app['twig']->render('checkin.html.twig', array(
        'status' => true,
        'attendee' => $attendee,
        'flags' => array_chunk($app['x3cs.attendee_manager']->getFlags(), 2)
    ));
})->bind('try_checkin');

$app->get('/attendees/', function () use ($app) {
    return $app['twig']->render('attendees.html.twig', array(
        'attendees' => $app['x3cs.attendee_manager']->getAttendees(),
        'type' => 'all',
        'flags' => $app['x3cs.attendee_manager']->getFlags()
    ));
})->bind('attendees');

$app->get('/attendees/present/', function () use ($app) {
    return $app['twig']->render('attendees.html.twig', array(
        'attendees' => $app['x3cs.attendee_manager']->getAttendees(true),
        'type' => 'present',
        'flags' => $app['x3cs.attendee_manager']->getFlags()
    ));
})->bind('attendees_present');

$app->get('/attendees/absent/', function () use ($app) {
    return $app['twig']->render('attendees.html.twig', array(
        'attendees' => $app['x3cs.attendee_manager']->getAttendees(false),
        'type' => 'absent',
        'flags' => $app['x3cs.attendee_manager']->getFlags()
    ));
})->bind('attendees_absent');

$app->run();
