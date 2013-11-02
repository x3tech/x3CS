<?php
namespace x3\CheckinSystem\Controllers;

use Symfony\Component\HttpFoundation\Request;
use x3\CheckinSystem\AttendeeManager;

class ResetController
{
    protected $manager;
    protected $twig;
    protected $config;

    public function __construct($twig, AttendeeManager $manager, \stdClass $config)
    {
        $this->twig = $twig;
        $this->manager = $manager;
        $this->config = $config;
    }

    public function showReset($status = null)
    {
        $view = $status !== null ? array('status' => $status) : array();
        return $this->twig->render('reset.html.twig', $view);
    }

    public function submitReset(Request $request)
    {
        $password = $request->request->get('password');
        $correctPassword = $password == $this->config->reset_password;

        if($correctPassword) {
            $this->manager->emptyDatabase();
        }

        return $this->showReset($correctPassword);
    }
}
