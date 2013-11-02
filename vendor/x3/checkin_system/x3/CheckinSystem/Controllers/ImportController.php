<?php
namespace x3\CheckinSystem\Controllers;

use Symfony\Component\HttpFoundation\Request;

use x3\CheckinSystem\AttendeeImporter\Importer;
use x3\CheckinSystem\AttendeeImporter\Exception\ValidationError;

class ImportController
{
    protected $importer;
    protected $twig;

    public function __construct($twig, Importer $importer)
    {
        $this->twig = $twig;
        $this->importer = $importer;
    }

    public function showImport()
    {
        return $this->twig->render('import.html.twig');
    }

    public function performImport(Request $request)
    {
        try {
            $attendeesFile = $request->files->get('attendees');
            $extrasFile = $request->files->get('extras');

            $result = $this->importer->import(
                $attendeesFile->getRealPath(),
                $extrasFile ? $extrasFile->getRealPath() : null
            );
        } catch(ValidationError $e) {
            return $this->twig->render('import.html.twig', array(
                'errors' => $e->getErrors(),
                'error_message' => $e->getMessage(),
                'status' => false
            ));
        }

        return $this->twig->render('import.html.twig', array(
            'result' => $result,
            'status' => true
        ));
    }
}
