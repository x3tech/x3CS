<?php

namespace x3\CheckinSystem;

use \PDO;

use Silex\ServiceProviderInterface;
use Silex\Application;

use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;

use x3\CheckinSystem\AttendeeManager;
use x3\CheckinSystem\AttendeeImporter\Importer;

use x3\CheckinSystem\Controllers\AttendeeListController;
use x3\CheckinSystem\Controllers\ImportController;
use x3\CheckinSystem\Controllers\CheckinController;
use x3\CheckinSystem\Controllers\ResetController;

class CheckinServiceProvider implements ServiceProviderInterface
{
    function register(Application $app)
    {
        require_once(ROOT_DIR . "/config.php");

        date_default_timezone_set("Europe/Amsterdam");

        if (X3_CHECKIN_DEBUG) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }

        $app['debug'] = X3_CHECKIN_DEBUG;
        $app['x3cs.config'] = $config;
        
        # Providers
        $app->register(new UrlGeneratorServiceProvider());
        $app->register(new ServiceControllerServiceProvider());
        $app->register(new TwigServiceProvider(), array(
            'twig.path' => array(
                ROOT_DIR . '/tpl/' . X3_CHECKIN_THEME . '/',
                ROOT_DIR . '/tpl/default/'
            ),
            'twig.options' => array(
                'debug' => $app['debug']
            )
        ));
        
        # Database Connection
        $app['db.conn'] = $app->share(function($app) {
            $conn = new PDO(X3_CHECKIN_DB_DSN, X3_CHECKIN_DB_USER, X3_CHECKIN_DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $conn;
        });
       
        # Twig config
        $app['twig']->addGlobal('site', array(
            'title' => X3_CHECKIN_TITLE
        ));
        $app['twig']->addGlobal('config', array(
            'has_reset' => (bool)$app['x3cs.config']->reset_password
        ));


        # Services
        $app['x3cs.attendee_manager'] = $app->share(function($app) {
            return new AttendeeManager($app['db.conn']);
        });
        $app['x3cs.attendee_importer'] = $app->share(function($app) {
            return new Importer($app['db.conn'], $app['x3cs.config']->importer);
        });

        # Controllers
        $app['controller.attendee_list'] = $app->share(function() use ($app) {
            return new AttendeeListController($app['twig'], $app['x3cs.attendee_manager']);
        });
        $app['controller.checkin'] = $app->share(function() use ($app) {
            return new CheckinController($app['twig'], $app['x3cs.attendee_manager']);
        });
        $app['controller.import'] = $app->share(function() use ($app) {
            return new ImportController($app['twig'], $app['x3cs.attendee_importer']);
        });
        $app['controller.reset'] = $app->share(function() use ($app) {
            return new ResetController(
                $app['twig'], 
                $app['x3cs.attendee_manager'],
                $app['x3cs.config']
            );
        });
    }

    function boot(Application $app)
    {
    }
}
