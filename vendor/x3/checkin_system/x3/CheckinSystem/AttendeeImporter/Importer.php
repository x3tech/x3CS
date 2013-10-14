<?php
namespace x3\CheckinSystem\AttendeeImporter;

use Keboola\Csv\CsvFile;
use x3\Functional\Functional as F;

use Exception\ValidationError;

class Importer
{
    protected $conn;

    protected $ticketIds;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
        $this->ticketIds = array();
    }

    public function import($attendeeFile, $extrasFile)
    {
        if(!file_exists($attendeeFile)) {
            throw new \UnexpectedValueException("$attendeeFile does not exist");
        }
        if(!file_exists($extrasFile)) {
            throw new \UnexpectedValueException("$extrasFile does not exist");
        }

        $this->checkAttendees($attendeeFile);
        $this->checkExtras($extrasFile);

        $this->conn->beginTransaction();
        try {
            $this->importAttendees($attendeeFile);
            $this->importExtras($extrasFile);
        } catch (\PDOException $e) {
            $this->conn->rollback();
            throw $e;
        }
        $this->conn->commit();
    }

    protected function cleanRow(array $row)
    {
        return F::imap($row, function($col) {
            if (in_array(trim(strtolower($col)), array("yes", "no"))) {
                $col = strtolower($col);
            }
            return trim($col);
        });
    }

    protected function checkAttendees($filename)
    {
        $csv = new CsvFile($filename);
        $csv->next();
        $invalidRows = array_filter(F::imap($csv, function($row) {
            $row = $this->cleanRow($row);
            if($row[0] == "name") {
                return null;
            }
            if (count($row) < 5) {
                return array($row, "Column count not matching " . count($row) . " expected(5)");
            }
            if (!is_numeric($row[Attendee::COL_TICKET])) {
                return array($row, "Ticket not numeric: " . $row[Attendee::COL_TICKET]);
            }
            if (!in_array($row[Attendee::COL_SPONSOR], array("yes", "no"))) {
                return array($row, "Sponsor not yes/no: " . $row[Attendee::COL_SPONSOR]);
            }
            if (!in_array($row[Attendee::COL_SUITER], array("yes", "no"))) {
                return array($row, "Suiter not yes/no: " . $row[Attendee::COL_SUITER]);
            }
            if (in_array($row[Attendee::COL_TICKET], $this->ticketIds)) {
                return array($row, "Duplicate ticket: ". $row[Attendee::COL_TICKET]);
            }

            $this->ticketIds[] = $row[Attendee::COL_TICKET];
        }));

        if (count($invalidRows) > 0) {
            throw new ValidationError('Attendees', $invalidRows);
        }
    }

    protected function checkExtras($filename)
    {
        $csv = new CsvFile($filename);
        $invalidRows = array_filter(F::imap($csv, function($row) {
            $row = $this->cleanRow($row);
            if($row[0] == "name") {
                return null;
            }
            if (count($row) < 4) {
                return array($row, "Column count not matching " . count($row) . " expected(4)");
            }
            if (!is_numeric($row[Extra::COL_TICKET])) {
                return array($row, "Ticket not numeric: " . $row[Extra::COL_TICKET]);
            }
            if (!is_numeric($row[Extra::COL_QUANTITY])) {
                return array($row, "Ticket not numeric: " . $row[Extra::COL_TICKET]);
            }
            if (!in_array($row[Extra::COL_TICKET], $this->ticketIds)) {
                return array($row, "Ticket not found: ". $row[Extra::COL_TICKET]);
            }
        }));

        if (count($invalidRows) > 0) {
            throw new ValidationError('Extras', $invalidRows);
        }
    }

    protected function executePrepared($query, $values)
    {
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($values);
    }

    protected function importAttendees($filename)
    {
        $attendeesQuery = "
            INSERT INTO attendees
            (name, nickname, ticket, sponsor, suiter)
            VALUES (?, ?, ?, ?, ?)
        ";
        $csv = new CsvFile($filename);
        F::iwalk($csv, function($row) use ($attendeesQuery){
            if($row[Attendee::COL_NAME] == 'name') {
                return;
            }
            $row[Attendee::COL_SPONSOR] = $row[Attendee::COL_SPONSOR] == "yes";
            $row[Attendee::COL_SUITER] = $row[Attendee::COL_SUITER] == "yes";

            $this->executePrepared($attendeesQuery, $row); 
        });
    }

    protected function importExtras($filename)
    {
        $extrasQuery = "INSERT IGNORE INTO extras (name) VALUES (?)";
        $attendeesExtrasQuery = "
            INSERT INTO attendees_extras
            (extras_id, attendees_id, quantity, type)
            VALUES (
                (SELECT id FROM extras WHERE name LIKE ?),
                (SELECT id FROM attendees WHERE ticket = ?),
                ?,
                ?
            )
        ";
        $csv = new CsvFile($filename);

        F::iwalk($csv, function($row) use ($extrasQuery, $attendeesExtrasQuery) {
            if($row[Extra::COL_NAME] == 'name') {
                return;
            }
            $this->executePrepared($extrasQuery, array($row[Extra::COL_NAME]));
            $this->executePrepared($attendeesExtrasQuery, $row);
        });
    }
}

