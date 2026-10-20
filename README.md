# Attendance Admin (PHP + MongoDB, XAMPP)

Daily attendance viewer/editor for `shiftattendances` and `attendanceevents`.
Every save writes `manuallyEdited: false`.

## Step 1 - Enable the MongoDB PHP extension (once)
1. Check PHP version / x64 / thread safety in http://localhost/dashboard/phpinfo.php
2. Download the matching `php_mongodb.dll` from https://pecl.php.net/package/mongodb
3. Copy it to `C:\xampp\php\ext\`
4. In `C:\xampp\php\php.ini` add: `extension=mongodb`
5. Restart Apache in the XAMPP Control Panel.

No Composer is needed: the app uses the native extension directly.

## Step 2 - Install
1. Unzip to `C:\xampp\htdocs\attendance-admin`
2. Edit `config/config.php` (Mongo URI, database name; default db is `test`)
3. Open http://localhost/attendance-admin/

## Structure
```
attendance-admin/
|- config/config.php             settings
|- src/Database.php              Mongo wrapper
|- src/Time.php                  UTC <-> Pakistan time, business-day window
|- src/AttendanceRepository.php  all queries and edits
`- public/
   |- index.php                  UI
   |- api.php                    JSON endpoints (CSRF protected)
   |- bootstrap.php
   `- assets/{css,js}
```

## Usage
Pick a date and click **Edit** on a row:
- Change Check In / Check Out, then **Save shift** (shiftattendances).
- Edit, add or delete punches (attendanceevents), then **Recalculate from punches**.
Business day = date 09:00 to next day 08:00 (+05:00).

## Note
Your main app's Fetch recomputes rows with `manuallyEdited: false` from punches, so a later Fetch
can overwrite a shift edit. To keep a time, edit the punch in attendanceevents as well.
