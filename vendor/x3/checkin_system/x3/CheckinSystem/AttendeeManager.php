<?php
namespace x3\CheckinSystem;

use x3\Functional\Functional as F;
use x3\CheckinSystem\Exception\AlreadyCheckedIn;

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
        return $this->parseDatabaseAttendee($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function parseDatabaseAttendee($rows)
    {
        $attendee = array_intersect_key(
            $rows[0],
            array_flip(
                array('id', 'name', 'nickname', 'sponsor', 'suiter', 'checked_in')
            )
        );
        $extras = array_unique(array_filter(array_map(function($row) {
            return array_intersect_key(
                $row,
                array_flip(
                    array('extra_name', 'extra_quantity', 'extra_type')
                )
            );
        }, $rows), F::mapKey('extra_name')), SORT_REGULAR);

        $flags = array_unique(array_filter(
            array_map(F::mapKey('flag_name'), $rows)
        ));

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
        return call_user_func(F::compose(
            F::curry('array_map', array($this, 'parseDatabaseAttendee')),
            array($this, 'groupByAttendee')
        ), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function groupByAttendee($rows)
    {
        return F::arrayMapKeys(function($row) {
            return array($row['id'], $row);
        }, $rows, true);
    }

    function isCheckedIn($attendee)
    {
        return (bool)$attendee['checked_in'];
    }
}
