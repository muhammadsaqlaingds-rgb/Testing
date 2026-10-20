<?php
// Edit these values for your MongoDB.
return [
    'mongo_uri' => 'mongodb://127.0.0.1:27017',
    'mongo_db' => 'test',                 // database that holds both collections
    'events_coll' => 'attendanceevents',
    'shifts_coll' => 'shiftattendances',
    'timezone' => 'Asia/Karachi',         // +05:00
    'day_start' => '09:00',                // business day starts on the date at 09:00
    'day_end' => '08:00',                // ...and ends next day at 08:00
    'single_checkin_from' => '17:00',        // a single punch at/after this = check-in
];
