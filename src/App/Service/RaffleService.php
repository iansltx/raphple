<?php

namespace App\Service;

use Aura\Sql\ExtendedPdoInterface;

class RaffleService {
    protected $db;
    protected $sms;
    protected $phoneNumber;

    public function __construct(ExtendedPdoInterface $db, SMS $sms, $phone_number) {
        $this->db = $db;
        $this->sms = $sms;
        $this->phoneNumber = $phone_number;
    }

    public function create($name, array $items) {
        $sid = uniqid();
        $this->db->perform('INSERT INTO raffle (raffle_name, sid) VALUES(?, ?)', [$name, $sid]);
        $id = $this->db->lastInsertId();
        foreach ($items as $item)
            $this->db->perform('INSERT INTO raffle_item (raffle_id, item) VALUES(?, ?)', [$id, $item]);
        return $id;
    }

    public function getEntrantCount($id) {
        return $this->db->fetchValue('SELECT COUNT(*) FROM entrant WHERE raffle_id = ?', [$id]);
    }

    public function getPhoneNumber($id) {
        return $this->phoneNumber;
    }

    public function getCode($id) {
        return $id;
    }

    public function isComplete($id) {
        return $this->db->fetchValue('SELECT COUNT(*) FROM raffle WHERE is_complete = 1 && id = ?', [$id]);
    }

    public function getName($id) {
        return $this->db->fetchValue('SELECT raffle_name FROM raffle WHERE id = ?', [$id]);
    }

    public function getNameByCode($code) {
        return $this->getName($code);
    }

    public function getSid($id) {
        return $this->db->fetchValue('SELECT sid FROM raffle WHERE id = ?', [$id]);
    }

    public function getEntrantPhoneNumbers($id) {
        return $this->db->fetchCol('SELECT phone_number FROM entrant WHERE raffle_id = ?', [$id]);
    }

    public function complete($id) {
        $this->db->perform('UPDATE raffle SET is_complete = 1 WHERE id = ?', [$id]);

        $items = $this->db->fetchCol('SELECT item FROM raffle_item WHERE raffle_id = ? ORDER BY id ASC', [$id]);
        $entrants = $this->db->fetchCol('SELECT phone_number FROM entrant WHERE raffle_id = ?', [$id]);

        shuffle($entrants);

        $winnerNumbers = [];

        foreach ($entrants as $k => $phone_number) {
            if (isset($items[$k])) {
                $message = 'You won! Your prize: ' . $items[$k];
                $winnerNumbers[] = $phone_number;
            } else {
                $message = 'Sorry, you didn\'t win this time. Maybe next time!';
            }

            $this->sms->send($phone_number, $message);
        }

        return $winnerNumbers;
    }

    public function recordEntry($code, $phone_number) {
        if (!$this->raffleExists($code))
            return false;
        if ($this->db->fetchValue('SELECT COUNT(*) FROM entrant WHERE raffle_id = ? && phone_number = ?',
            [$code, $phone_number]))
            return false;

        $this->db->perform('INSERT INTO entrant (raffle_id, phone_number) VALUES (?, ?)', [$code, $phone_number]);
        return true;
    }

    public function raffleExists($id) {
        return $this->db->fetchValue('SELECT COUNT(*) FROM raffle WHERE id = ?', [$id]) > 0;
    }
}
