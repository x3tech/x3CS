<?php
namespace x3\CheckinSystem;

use x3\CheckinSystem\Exception\AlreadyCheckedIn;
use x3\CheckinSystem\Exception\AttendeeNotFound;

class AttendeeManager
{
    protected $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    protected function getAttendeeStatement($append = "")
    {
        return $this->conn->prepare("
            SELECT
                a.id AS id,
                a.ticket AS ticket_id,
                c.created_at AS checked_in,
                a.name AS name,
                a.nickname AS nickname,
                e.name AS extra_name,
                ae.quantity AS extra_quantity,
                ae.type AS extra_type,
                f.name AS flag_name
            FROM
                attendees AS a
            LEFT JOIN
                checkins AS c ON c.attendees_id = a.id
            LEFT JOIN
                attendees_extras AS ae ON ae.attendees_id = a.id
            LEFT JOIN
                extras AS e ON e.id = ae.extras_id
            LEFT JOIN
                attendees_flags AS af ON af.attendees_id = a.id
            LEFT JOIN
                flags AS f ON f.id = af.flags_id
        " . $append);
    }

    public function getFlags()
    {
        $stmt = $this->conn->query("SELECT name FROM flags");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    public function findByTicket($ticketId)
    {
        $stmt = $this->getAttendeeStatement("WHERE a.ticket = ?");
        $stmt->execute(array($ticketId));

        if($stmt->rowCount() == 0) {
            throw new AttendeeNotFound($ticketId);
        }

        return $this->parseDatabaseAttendee($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function parseDatabaseAttendee($rows)
    {
        $attendee = array_intersect_key(
            $rows[0],
            array_flip(
                array('id', 'name', 'nickname', 'sponsor', 'suiter', 'checked_in', 'ticket_id')
            )
        );

        $extras = [];
        foreach ($rows as $row) {
            if (!$row['extra_name']) {
                continue;
            }

            $extras[] = array_intersect_key(
                $row,
                array_flip(array('extra_name', 'extra_quantity', 'extra_type'))
            );
        }
        $extras = array_unique($extras, SORT_REGULAR);

        $flags = [];
        foreach ($rows as $row) {
            if ($row['flag_name']) {
                $flags[] = $row['flag_name'];
            }
        }
        $flags = array_unique($flags);

        $attendee['extras'] = $extras;
        $attendee['flags'] = $flags;

        return $attendee;
    }

    protected function checkIn($attendeeId)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO checkins (attendees_id) VALUES (?)
        ");
        return $stmt->execute(array($attendeeId));
    }


    public function attemptCheckIn($ticketId)
    {
        $attendee = $this->findByTicket($ticketId);
        if ($this->isCheckedIn($attendee)) {
            throw new AlreadyCheckedIn($attendee);
        }

        $this->checkIn($attendee['id']);
        $attendee['checked_in'] = date('Y-m-d H:i:s');

        return $attendee;
    }

    public function getAttendees($checkedIn = null)
    {
        if($checkedIn === true) {
            $stmt = $this->getAttendeeStatement("HAVING checked_in IS NOT NULL");
        } elseif ($checkedIn === false) {
            $stmt = $this->getAttendeeStatement("HAVING checked_in IS NULL");
        } else {
            $stmt = $this->getAttendeeStatement();
        }

        $stmt->execute();

        $result = [];
        foreach ($this->groupByAttendee($stmt->fetchAll(\PDO::FETCH_ASSOC)) as $rows) {
            $result[] = $this->parseDatabaseAttendee($rows);
        }
        return $result;
    }

    public function groupByAttendee($rows)
    {
        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row['id']])) {
                $result[$row['id']] = [];
            }
            $result[$row['id']][] = $row;
        }
        return $result;
    }

    public function search($searchTerm)
    {
        $searchTerm = str_replace(array('%'), '', $searchTerm);
        $stmt = $this->getAttendeeStatement("
            WHERE a.name LIKE ? OR a.nickname LIKE ?
        ");

        $stmt->execute(array("%$searchTerm%", "%$searchTerm%"));
        if($stmt->rowCount() == 0) {
            return null;
        }

        $result = [];
        foreach ($this->groupByAttendee($stmt->fetchAll(\PDO::FETCH_ASSOC)) as $rows) {
            $result[] = $this->parseDatabaseAttendee($rows);
        }
        return $result;
    }

    protected function isCheckedIn($attendee)
    {
        return (bool)$attendee['checked_in'];
    }

    public function emptyDatabase()
    {
        $tables = array(
            "attendees_extras",
            "attendees_flags",
            "flags",
            "extras",
            "checkins",
            "attendees"
        );
        $this->conn->exec("SET foreign_key_checks = 0;");
        array_walk($tables, function($table) {
            $this->conn->exec("TRUNCATE " . $table);
        });
        $this->conn->exec("SET foreign_key_checks = 1;");
    }
}
