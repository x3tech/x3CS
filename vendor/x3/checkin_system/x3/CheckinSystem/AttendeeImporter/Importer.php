<?php
namespace x3\CheckinSystem\AttendeeImporter;

use Keboola\Csv\CsvFile;
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
        return array_map(function($col) {
            if (in_array(trim(strtolower($col)), array("yes", "no"))) {
                $col = strtolower($col);
            }
            return trim($col);
        }, $row);
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
        $invalidRows = [];

        foreach($csv as $row) {
            $row = $this->cleanRow($row);
            if($row[0] == "name") {
                continue;
            }

            if (count($row) < $this->getAttendeeRowCount()) {
                $invalidRows[] = array($row, "Column count not matching got " . count($row) . ", expected " . $this->getAttendeeRowCount());
            } elseif (in_array($row[Attendee::COL_TICKET], $this->ticketIds)) {
                $invalidRows[] = array($row, "Duplicate ticket: ". $row[Attendee::COL_TICKET]);
            } elseif ($flagResult = $this->checkFlags($row)) {
                $invalidRows[] = $flagResult;
            }

            $this->ticketIds[] = $row[Attendee::COL_TICKET];
        }

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

        $invalidFlags = [];
        foreach ($columns as $index => $column) {
            if(!in_array($column, array('yes', 'no'))) {
                $invalidFlags[] = array($row, sprintf("Value for flag %s isn't yes or no: %s",
                    $this->config->flags[$index], $column
                ));
            }
        }

        return count($invalidFlags) > 0 ? reset($invalidFlags) : null;
    }

    protected function checkExtras($filename)
    {
        $csv = new CsvFile($filename);

        $invalidRows = [];
        foreach ($csv as $row) {
            $row = $this->cleanRow($row);

            if($row[0] == "name") {
                continue;
            }

            if (count($row) < 4) {
                $invalidRows[] = array($row, "Column count not matching " . count($row) . " expected(4)");
            } else if (!is_numeric($row[Extra::COL_TICKET])) {
                $invalidRows[] = array($row, "Ticket not numeric: " . $row[Extra::COL_TICKET]);
            } else if (!is_numeric($row[Extra::COL_QUANTITY])) {
                $invalidRows[] = array($row, "Ticket not numeric: " . $row[Extra::COL_TICKET]);
            } else if (!in_array($row[Extra::COL_TICKET], $this->ticketIds)) {
                $invalidRows[] = array($row, "Ticket not found: ". $row[Extra::COL_TICKET]);
            }
        }

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
        foreach ($csv as $row) {
            if($row[Attendee::COL_NAME] == 'name') {
                continue;
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
        }

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

        foreach ($csv as $row) {
            if($row[Extra::COL_NAME] == 'name') {
                continue;
            }

            $this->executePrepared($extrasQuery, array($row[Extra::COL_NAME]));
            $this->executePrepared($attendeesExtrasQuery, $row);

            $count++;
        }

        return $count;
    }
}

