<?php
require_once 'db.php';

/**
 * Buildings list (dropdown-er jonno)
 * Data asche tomar buildings table theke
 */
function getBuildings(PDO $pdo)
{
    $stmt = $pdo->query("SELECT id, name FROM buildings ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * Specific building + time slot e je room-gulo free, shegula tule ana
 * Data asche tomar rooms & bookings table theke
 */
function getFreeRoomsInBuilding(PDO $pdo, $buildingId, $date, $startTime, $endTime)
{
    $sql = "
        SELECT r.id, r.level, r.room_label
        FROM rooms r
        WHERE r.building_id = :building_id
          AND NOT EXISTS (
              SELECT 1
              FROM bookings b
              WHERE b.room_id = r.id
                AND b.date = :date
                AND NOT (
                    :end_time <= b.start_time
                    OR
                    :start_time >= b.end_time
                )
          )
        ORDER BY r.level ASC, r.room_label ASC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':building_id' => $buildingId,
        ':date'        => $date,
        ':start_time'  => $startTime,
        ':end_time'    => $endTime,
    ]);

    return $stmt->fetch(); // 1ta row or false
}

/**
 * Ei date-er sob booking list (nicher table-er jonno)
 */
function getBookingsByDate(PDO $pdo, $date)
{
    $sql = "
        SELECT b.name AS building_name,
               r.level,
               r.room_label,
               bk.date,
               bk.start_time,
               bk.end_time,
               bk.title
        FROM bookings bk
        INNER JOIN rooms r ON bk.room_id = r.id
        INNER JOIN buildings b ON r.building_id = b.id
        WHERE bk.date = :date
        ORDER BY bk.start_time ASC, b.name ASC, r.room_label ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':date' => $date]);
    return $stmt->fetchAll();
}

/**
 * Booking create (auto assign result save korar jonno)
 */
function createBooking(PDO $pdo, $roomId, $date, $startTime, $endTime, $title)
{
    $insert = $pdo->prepare("
        INSERT INTO bookings (room_id, date, start_time, end_time, title)
        VALUES (:room_id, :date, :start_time, :end_time, :title)
    ");

    $insert->execute([
        ':room_id'    => $roomId,
        ':date'       => $date,
        ':start_time' => $startTime,
        ':end_time'   => $endTime,
        ':title'      => $title,
    ]);
}

/* ---------- INPUT: ager project theke asha data ---------- */
/*
   URL example (ager project theke call korbe):
   assign_room.php?date=2025-11-20&start=09:00&end=11:00&title=Database+Management
*/

$date      = $_POST['date']      ?? ($_GET['date']      ?? null);
$startTime = $_POST['start']     ?? ($_GET['start']     ?? null);
$endTime   = $_POST['end']       ?? ($_GET['end']       ?? null);
$title     = $_POST['title']     ?? ($_GET['title']     ?? null);

if ($date === null || $startTime === null || $endTime === null || $title === null) {
    $missingData = true;
} else {
    $missingData = false;
}

// time normalise (09:00 -> 09:00:00)
if (!$missingData) {
    if (strlen($startTime) === 5) $startTime .= ':00';
    if (strlen($endTime)   === 5) $endTime   .= ':00';
}

/* ---------- PAGE STATE ---------- */

$buildings      = getBuildings($pdo);
$bookingsToday  = !$missingData ? getBookingsByDate($pdo, $date) : [];
$message        = '';
$success        = null;
$assignedRoom   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$missingData) {
    // building select hoye "Auto Assign Room" button press korle
    $buildingId = (int)($_POST['building_id'] ?? 0);

    if ($buildingId <= 0) {
        $success = false;
        $message = 'Please select a building.';
    } else {
        // oi building + oi time slot e 1ta free room khuja
        $room = getFreeRoomsInBuilding($pdo, $buildingId, $date, $startTime, $endTime);

        if (!$room) {
            $success = false;
            $message = 'Ei building-e ei time slot-er jonno kono free room nai.';
        } else {
            // free room paile booking create
            createBooking($pdo, $room['id'], $date, $startTime, $endTime, $title);
            $success = true;
            $assignedRoom = $room['room_label'] . ' (Level ' . $room['level'] . ')';
            $message = 'Room auto assigned: ' . $assignedRoom;

            // nicher table update korar jonno abar booking list niye ashi
            $bookingsToday = getBookingsByDate($pdo, $date);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Room · SEGi Timetable</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at top left, #2c6688 0, #102331 45%, #060b10 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .shell {
            max-width: 960px;
            width: 100%;
        }
        .card-main {
            border-radius: 24px;
            background: rgba(15, 23, 42, 0.95);
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }
        .pill {
            border-radius: 999px;
            padding: 0.15rem 0.75rem;
            font-size: .75rem;
            background: rgba(148, 163, 184, 0.18);
            color: #e5e7eb;
        }
        .subtitle {
            color: #9ca3af;
        }
        .section-card {
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.98);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }
        .table-wrap {
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.25);
            background: rgba(15, 23, 42, 0.98);
        }
        .table thead {
            background: rgba(15, 23, 42, 1);
            color: #e5e7eb;
        }
        .table tbody tr {
            color: #cbd5f5;
        }
        .badge-venue {
            background: #2c6688;
        }
        .btn-assign {
            border-radius: 999px;
            padding-inline: 1.8rem;
        }
    </style>
</head>
<body>
<div class="shell px-3">
    <div class="card card-main p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="pill">SEGi Timetable · Room Assignment</span>
            <small class="subtitle">Sob data asche main project theke – ekhane sudhu building & auto room.</small>
        </div>

        <?php if ($missingData): ?>
            <div class="alert alert-warning">
                Ei page direct open koro na. Timetable page theke ashle URL-e
                <code>?date=YYYY-MM-DD&amp;start=HH:MM&amp;end=HH:MM&amp;title=Subject+Name</code>
                pathate hobe.
            </div>
        <?php else: ?>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> py-2">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($assignedRoom): ?>
                <div class="section-card p-3 mb-3">
                    <small class="subtitle d-block mb-1">Auto assigned room</small>
                    <div class="fw-semibold">
                        <?php echo htmlspecialchars($assignedRoom); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Class info (read-only) -->
            <div class="section-card p-3 mb-4">
                <div class="row">
                    <div class="col-md-3 mb-2 mb-md-0">
                        <small class="subtitle d-block">Date</small>
                        <div class="fw-semibold">
                            <?php echo htmlspecialchars($date); ?>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2 mb-md-0">
                        <small class="subtitle d-block">Time</small>
                        <div class="fw-semibold">
                            <?php echo htmlspecialchars(substr($startTime,0,5) . ' – ' . substr($endTime,0,5)); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <small class="subtitle d-block">Subject</small>
                        <div class="fw-semibold">
                            <?php echo htmlspecialchars($title); ?>
                        </div>
                    </div>
                </div>
                <small class="subtitle d-block mt-2">
                    Ei value gula ekhane change kora jabe na – main project thekei asche.
                </small>
            </div>

            <!-- Building select + auto assign -->
            <div class="section-card p-3 mb-4">
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="date"  value="<?php echo htmlspecialchars($date); ?>">
                    <input type="hidden" name="start" value="<?php echo htmlspecialchars(substr($startTime,0,5)); ?>">
                    <input type="hidden" name="end"   value="<?php echo htmlspecialchars(substr($endTime,0,5)); ?>">
                    <input type="hidden" name="title" value="<?php echo htmlspecialchars($title); ?>">

                    <div class="col-md-8">
                        <label class="form-label mb-1">Select Building / Venue</label>
                        <select name="building_id" class="form-select" required>
                            <option value="">Choose a building</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?php echo $b['id']; ?>">
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="subtitle">
                            Building select korle system oi building-er moddhe free room ber kore auto assign korbe.
                        </small>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button type="submit" class="btn btn-success btn-assign mt-2 mt-md-0">
                            Auto Assign Room
                        </button>
                    </div>
                </form>
            </div>

            <!-- Booked rooms list for this date -->
            <div class="mb-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Bookings for <?php echo htmlspecialchars($date); ?></h6>
                <small class="subtitle">Ei date-er sob booked room niche dekhano hocche.</small>
            </div>

            <div class="table-wrap">
                <?php if (count($bookingsToday) === 0): ?>
                    <div class="p-3">
                        <span class="subtitle">Ei date-e ekhono kono booking nai.</span>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead>
                            <tr>
                                <th style="width: 140px;">Time</th>
                                <th style="width: 180px;">Venue</th>
                                <th>Room</th>
                                <th style="width: 100px;">Level</th>
                                <th>Subject</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bookingsToday as $bk): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars(substr($bk['start_time'], 0, 5) . ' – ' . substr($bk['end_time'], 0, 5)); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-venue rounded-pill">
                                            <?php echo htmlspecialchars($bk['building_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($bk['room_label']); ?></td>
                                    <td><?php echo htmlspecialchars($bk['level']); ?></td>
                                    <td><?php echo htmlspecialchars($bk['title']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>
</div>
</body>
</html>
