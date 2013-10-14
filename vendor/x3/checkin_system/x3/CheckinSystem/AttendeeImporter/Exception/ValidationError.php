<?php
namespace x3\CheckinSystem\AttendeeImporter\Exception;

class ValidationError extends \Exception
{
    protected $errors;
    public function __construct($type, $errors)
    {
        $this->errors = $errors;

        parent::__construct("Validation error for: $type");
    }

    public function getErrors()
    {
        return $this->errors;
    }
}


