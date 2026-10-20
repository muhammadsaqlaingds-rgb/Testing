<?php
declare(strict_types=1);

use MongoDB\BSON\{ObjectId, UTCDateTime};

final class AttendanceRepository
{
    private string $events;
    private string $shifts;

    public function __construct()
    {
        $this->events = Database::cfg('events_coll');
        $this->shifts = Database::cfg('shifts_coll');
    }

    /* ---------- READ ---------- */

    public function dailyShifts(string $date, string $search = ''): array
    {
        $filter = ['date' => $date];
        if ($search !== '') {
            $filter['empCode'] = ['$regex' => preg_quote($search), '$options' => 'i'];
        }
        $rows = Database::find($this->shifts, $filter, ['sort' => ['empCode' => 1, 'shift' => 1], 'limit' => 500]);
        return array_map([$this, 'shiftToArray'], $rows);
    }

    public function punches(string $empCode, string $date): array
    {
        [$from, $to] = Time::window($date);
        $rows = Database::find($this->events, [
            'empCode'   => $empCode,
            'eventTime' => ['$gte' => $from, '$lt' => $to],
        ], ['sort' => ['eventTime' => 1]]);

        return array_map(fn($r) => [
            'id'      => (string)$r['_id'],
            'time'    => Time::toLocal($r['eventTime'] ?? null),
            'deviceIp'=> $r['deviceIp'] ?? '',
            'serialNo'=> $r['serialNo'] ?? '',
            'manual'  => ($r['deviceIp'] ?? '') === 'manual',
        ], $rows);
    }

    private function shiftToArray(array $r): array
    {
        return [
            'id'       => (string)$r['_id'],
            'empCode'  => $r['empCode'] ?? '',
            'name'     => $r['name'] ?? $r['employeeName'] ?? '',
            'date'     => $r['date'] ?? '',
            'shift'    => $r['shift'] ?? '',
            'checkIn'  => Time::toLocal($r['checkIn'] ?? null),
            'checkOut' => Time::toLocal($r['checkOut'] ?? null),
            'totalPunches' => $r['totalPunches'] ?? 0,
            'status'   => $r['attendanceStatus'] ?? '',
            'manuallyEdited' => (bool)($r['manuallyEdited'] ?? false),
        ];
    }

    /* ---------- WRITE: shiftattendances ---------- */

    /** Always saves manuallyEdited = false (as requested). */
    public function updateShift(string $id, ?string $in, ?string $out, ?string $status): void
    {
        $checkIn  = Time::parse($in);
        $checkOut = Time::parse($out);
        if ($checkIn && $checkOut && $checkOut->toDateTime() < $checkIn->toDateTime()) {
            throw new InvalidArgumentException('Check-out cannot be earlier than check-in.');
        }
        $set = [
            'checkIn'        => $checkIn,
            'checkOut'       => $checkOut,
            'totalPunches'   => (int)($checkIn !== null) + (int)($checkOut !== null),
            'manuallyEdited' => false,
            'updatedAt'      => Time::now(),
        ];
        if ($status !== null && $status !== '') {
            $set['attendanceStatus'] = $status;
        } elseif ($checkIn || $checkOut) {
            $set['attendanceStatus'] = 'Present';
        }
        Database::update($this->shifts, ['_id' => new ObjectId($id)], ['$set' => $set]);
    }

    /** Rebuild checkIn/checkOut of a shift row from its punches (same rule as the app's Fetch). */
    public function recalcShift(string $id): void
    {
        $row = Database::findOne($this->shifts, ['_id' => new ObjectId($id)]);
        if (!$row) throw new RuntimeException('Shift record not found.');

        $p = $this->punches((string)$row['empCode'], (string)$row['date']);
        $in = $out = null;
        if (count($p) >= 2) {
            $in = $p[0]['time'];
            $out = $p[count($p) - 1]['time'];
        } elseif (count($p) === 1) {
            $t = new DateTimeImmutable($p[0]['time'], Time::tz());
            $limit = new DateTimeImmutable($t->format('Y-m-d') . ' ' . Database::cfg('single_checkin_from'), Time::tz());
            if ($t >= $limit) $in = $p[0]['time']; else $out = $p[0]['time'];
        }
        $this->updateShift($id, $in, $out, ($in || $out) ? 'Present' : null);
    }

    /* ---------- WRITE: attendanceevents ---------- */

    public function updatePunch(string $id, string $local): void
    {
        $dt = Time::parse($local);
        if (!$dt) throw new InvalidArgumentException('Punch time is required.');
        Database::update($this->events, ['_id' => new ObjectId($id)], ['$set' => [
            'eventTime'  => $dt,
            'raw.time'   => (new DateTimeImmutable($local, Time::tz()))->format('c'),
            'updatedAt'  => Time::now(),
        ]]);
    }

    public function addPunch(string $empCode, string $local): void
    {
        $dt = Time::parse($local);
        if (!$dt) throw new InvalidArgumentException('Punch time is required.');
        $ms = $dt->toDateTime()->getTimestamp();
        Database::insert($this->events, [
            'empCode'    => $empCode,
            'eventTime'  => $dt,
            'deviceIp'   => 'manual',
            'serialNo'   => $ms,
            'punchKey'   => "manual|$empCode|$ms",
            'major'      => 5,
            'minor'      => 38,
            'doorNo'     => 1,
            'verifyMode' => 'manual',
            'attendanceStatus' => null,
            'cardNo'     => null,
            'raw'        => ['employeeNoString' => $empCode, 'time' => (new DateTimeImmutable($local, Time::tz()))->format('c')],
            'createdAt'  => Time::now(),
            'updatedAt'  => Time::now(),
        ]);
    }

    public function deletePunch(string $id): void
    {
        Database::delete($this->events, ['_id' => new ObjectId($id)]);
    }
}
