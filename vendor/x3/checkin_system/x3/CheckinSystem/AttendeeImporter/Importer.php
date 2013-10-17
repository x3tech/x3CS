<?php
namespace x3\CheckinSystem\AttendeeImporter;

use Keboola\Csv\CsvFile;
use x3\Functional\Functional as F;

use x3\CheckinSystem\AttendeeImporter\Exception\ValidationError;

class Importer
{
    protected $conn;
    protected $config;

    protected $ticketIds;

    public function __construct(\PDO $conn, \stdClass $config)
    {
        $this->conn = $conn;
        $this->config = $config;

        $this->ticketIds = array();
    }

    public function import($attendeeFile, $extrasFile)
    {
        if(!file_exists($attendeeFile)) {
            throw new \UnexpectedValueException("$attendeeFile does not exist");
        }
        if($extrasFile && !file_exists($extrasFile)) {
            throw new \UnexpectedValueException("$extrasFile does not exist");
        }

        $this->conn->beginTransaction();
        try {
            $this->checkAttendees($attendeeFile);
            $attendeesCount = $this->importAttendees($attendeeFile);
            if($extrasFile) {
                $this->checkExtras($extrasFile);
                $extrasCount = $this->importExtras($extrasFile);
            }
        } catch (\PDOException $e) {
            $this->conn->rollback();
            throw $e;
        }
        $this->conn->commit();

        return array(
            'attendees' => $attendeesCount,
            'extras' => $extrasFile ? $extrasCount : 0
        );
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

    protected function getAttendeeRowCount()
    {
        return 3 + count($this->config->flags);
    }

    protected function hasFlags()
    {
        return count($this->config->flags) > 0;
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
            if (count($row) < $this->getAttendeeRowCount()) {
                return array($row, "Column count not matching " . count($row) . " expected(" . $this->getAttendeeRowCount() . ")");
            }
            if (in_array($row[Attendee::COL_TICKET], $this->ticketIds)) {
                return array($row, "Duplicate ticket: ". $row[Attendee::COL_TICKET]);
            }
            $flagResult = $this->checkFlags($row);
            if($flagResult) {
                return $flagResult;
            }

            $this->ticketIds[] = $row[Attendee::COL_TICKET];
        }));

        if (count($invalidRows) > 0) {
            throw new ValidationError('Attendees', $invalidRows);
        }
    }

    protected function checkFlags($row)
    {
        if(!$this->hasFlags()) {
            return;
        }

        $columns = array_slice($row, Attendee::COL_FLAG_START);
        $invalidFlags = array_filter(F::imap($columns, function($column, $index) use ($row) {
            if(!in_array(strtolower($column), array('yes', 'no'))) {
                return array($row, sprintf("Value for flag %s isn't yes or no: %s",
                    $this->config->flags[$index], $column
                ));
            }
        }));

        return count($invalidFlags) > 0 ? $invalidFlags : null;
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
            (name, nickname, ticket)
            VALUES (?, ?, ?)
        ";
        $csv = new CsvFile($filename);
        $count = 0;
        F::iwalk($csv, function($row) use ($attendeesQuery, &$count){
            if($row[Attendee::COL_NAME] == 'name') {
                return;
            }

            $this->executePrepared(
                $attendeesQuery,
                array_slice($row, 0, Attendee::COL_FLAG_START)
            );

            if(count($this->config->flags)) {
                $this->importFlags($row[Attendee::COL_TICKET], array_slice(
                    $row, Attendee::COL_FLAG_START
                ));
            }
            $count++;
        });

        return $count;
    }

    protected function importFlags($ticketId, $flags)
    {
        $callback = function($flagValue, $flagIndex) use ($ticketId) {
            if(strtolower($flagValue) == "yes") {
                $flagName = $this->config->flags[$flagIndex];
                $this->insertFlag($flagName, $ticketId);
            }
        };

        array_walk($flags, $callback);
    }

    protected function insertFlag($flagName, $ticketId)
    {
        $flagsQuery = "INSERT IGNORE INTO flags (name) VALUES (?)";
        $attendeesFlagsQuery = "
            INSERT INTO attendees_flags
            (flags_id, attendees_id)
            VALUES (
                (SELECT id FROM flags WHERE name LIKE ?),
                (SELECT id FROM attendees WHERE ticket = ?)
            )
        ";

        $this->executePrepared($flagsQuery, array($flagName));
        $this->executePrepared($attendeesFlagsQuery, array($flagName, $ticketId));
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
        $count = 0;

        F::iwalk($csv, function($row) use ($extrasQuery, $attendeesExtrasQuery, &$count) {
            if($row[Extra::COL_NAME] == 'name') {
                return;
            }
            $this->executePrepared($extrasQuery, array($row[Extra::COL_NAME]));
            $this->executePrepared($attendeesExtrasQuery, $row);

            $count++;
        });

        return $count;
    }
}

