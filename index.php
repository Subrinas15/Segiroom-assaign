<?php
session_start();
require_once 'db.php';

/**
 * DEMO CLASS SLOTS
 * lunch time avoid – 13:00–14:00 te kono demo nai
 * Protibar next demo nile same date, different time + subject asbe
 */
function getDemoList()
{
    $today = date('Y-m-d');
    return [
        [
            'date'       => $today,
            'start_time' => '09:00',
            'end_time'   => '11:00',
            'title'      => 'Software Engineering',
        ],
        [
            'date'       => $today,
            'start_time' => '11:00',
            'end_time'   => '13:00',
            'title'      => 'Database Management',
        ],
        [
            'date'       => $today,
            'start_time' => '14:00',
            'end_time'   => '16:00',
            'title'      => 'Web Programming',
        ],
        [
            'date'       => $today,
            'start_time' => '16:00',
            'end_time'   => '18:00',
            'title'      => 'Networking Fundamentals',
        ],
        [
            'date'       => $today,
            'start_time' => '18:00',
            'end_time'   => '20:00',
            'title'      => 'Operating Systems',
        ],
        [
            'date'       => $today,
            'start_time' => '20:00',
            'end_time'   => '22:00',
            'title'      => 'Object Oriented Programming',
        ],
    ];
}

/**
 * Protibar call korle next demo class dibe (1→2→…→6→ abar 1)
 */
function getNextDemo()
{
    $list  = getDemoList();
    $count = count($list);

    $idx = isset($_SESSION['demo_idx']) ? (int)$_SESSION['demo_idx'] : 0;
    if ($idx < 0 || $idx >= $count) {
        $idx = 0;
    }

    $demo = $list[$idx];

    $idx = ($idx + 1) % $count;
    $_SESSION['demo_idx'] = $idx;

    return $demo;
}

/**
 * Buildings list (DB theke)
 */
function getBuildings(PDO $pdo)
{
    $stmt = $pdo->query("SELECT id, name FROM buildings ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * Specific building + time slot e je room gulo free
 * (overlap SQL diye clash check)
 *
 * IMPORTANT:
 * - Same DATE e ekoi room multiple time use korte parbe
 *   jodi time overlap na kore.
 */
function getFreeRooms(PDO $pdo, $buildingId, $date, $startTime, $endTime)
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
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':building_id' => $buildingId,
        ':date'        => $date,
        ':start_time'  => $startTime . ':00',
        ':end_time'    => $endTime . ':00',
    ]);

    return $stmt->fetchAll();
}

/**
 * Ei date-er sob booking (table er jonno)
 */
function getBookingsByDate(PDO $pdo, $date)
{
    $sql = "
        SELECT
            bk.id,
            b.name AS building_name,
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
 * Single booking (edit load er jonno)
 */
function getBookingById(PDO $pdo, $id)
{
    $sql = "
        SELECT
            bk.id,
            bk.room_id,
            bk.date,
            bk.start_time,
            bk.end_time,
            bk.title,
            b.name AS building_name,
            r.level,
            r.room_label
        FROM bookings bk
        INNER JOIN rooms r ON bk.room_id = r.id
        INNER JOIN buildings b ON r.building_id = b.id
        WHERE bk.id = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

/**
 * Booking update (edit save)
 */
function updateBooking(PDO $pdo, $id, $date, $startTime, $endTime, $title)
{
    $sql = "
        UPDATE bookings
        SET date = :date,
            start_time = :start_time,
            end_time   = :end_time,
            title      = :title
        WHERE id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':date'       => $date,
        ':start_time' => $startTime . ':00',
        ':end_time'   => $endTime . ':00',
        ':title'      => $title,
        ':id'         => $id,
    ]);
}

/**
 * Booking delete
 */
function deleteBooking(PDO $pdo, $id)
{
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = :id");
    $stmt->execute([':id' => $id]);
}

/**
 * Booking create
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
        ':start_time' => $startTime . ':00',
        ':end_time'   => $endTime . ':00',
        ':title'      => $title,
    ]);
}

/**
 * Lunch time clash check (13:00–14:00)
 */
function isLunchClash($startTime, $endTime)
{
    $lunchStart = '13:00';
    $lunchEnd   = '14:00';

    // kono overlap ache naki
    return !($endTime <= $lunchStart || $startTime >= $lunchEnd);
}

/**
 * Edit korar somoy room clash ache naki (same room, same date, onno booking sathe)
 * jeno edit kore same time-e abar double booking na korte pare
 */
function hasRoomClashOnEdit(PDO $pdo, $bookingId, $roomId, $date, $startTime, $endTime)
{
    $sql = "
        SELECT 1
        FROM bookings
        WHERE room_id = :room_id
          AND date    = :date
          AND id      <> :id
          AND NOT (
              :end_time <= start_time
              OR
              :start_time >= end_time
          )
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':room_id'    => $roomId,
        ':date'       => $date,
        ':id'         => $bookingId,
        ':start_time' => $startTime . ':00',
        ':end_time'   => $endTime . ':00',
    ]);
    return (bool)$stmt->fetchColumn();
}

/* ---------- INITIAL STATE (GET) ---------- */

$buildings          = getBuildings($pdo);
$demo               = $_SESSION['current_demo'] ?? null;
$selectedBuildingId = $_SESSION['selected_building'] ?? null;
$freeRooms          = [];
$message            = '';
$success            = null;
$editingBooking     = null;

// jodi demo + building thake, rooms calculate
if ($demo && $selectedBuildingId) {
    $freeRooms = getFreeRooms(
        $pdo,
        $selectedBuildingId,
        $demo['date'],
        $demo['start_time'],
        $demo['end_time']
    );
}

/* ---------- POST ACTIONS ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'start') {
        // Home / New Demo button -> new demo class, sob reset
        $demo                         = getNextDemo();
        $_SESSION['current_demo']     = $demo;
        $_SESSION['selected_building'] = null;
        $selectedBuildingId           = null;
        $freeRooms                    = [];
        $editingBooking               = null;

    } else {
        // demo info hidden fields theke nei (jodi thake)
        if (isset($_POST['date'], $_POST['start'], $_POST['end'], $_POST['title'])) {
            $demo = [
                'date'       => $_POST['date'],
                'start_time' => $_POST['start'],
                'end_time'   => $_POST['end'],
                'title'      => $_POST['title'],
            ];
            $_SESSION['current_demo'] = $demo;
        }

        if ($action === 'choose_building') {
            // Building change -> auto submit -> rooms load
            $selectedBuildingId = (int)($_POST['building_id'] ?? 0);
            if ($selectedBuildingId > 0 && $demo) {
                $_SESSION['selected_building'] = $selectedBuildingId;
                $freeRooms = getFreeRooms(
                    $pdo,
                    $selectedBuildingId,
                    $demo['date'],
                    $demo['start_time'],
                    $demo['end_time']
                );
            } else {
                $_SESSION['selected_building'] = null;
                $freeRooms = [];
            }

        } elseif ($action === 'assign_room') {
            $selectedBuildingId = (int)($_POST['building_id'] ?? 0);
            $roomId             = (int)($_POST['room_id'] ?? 0);

            if (!$demo) {
                $success = false;
                $message = 'Demo class information missing.';
            } elseif ($selectedBuildingId <= 0 || $roomId <= 0) {
                $success = false;
                $message = 'Building & room select korte hobe.';
            } elseif (isLunchClash($demo['start_time'], $demo['end_time'])) {
                $success = false;
                $message = 'Lunch time (13:00–14:00) er moddhe class allowed na.';
            } else {
                // safety: ekhono free kina check (overlap wise)
                $avail = getFreeRooms(
                    $pdo,
                    $selectedBuildingId,
                    $demo['date'],
                    $demo['start_time'],
                    $demo['end_time']
                );
                $roomStillFree = false;

                foreach ($avail as $r) {
                    if ((int)$r['id'] === $roomId) {
                        $roomStillFree = true;
                        break;
                    }
                }

                if ($roomStillFree) {
                    createBooking(
                        $pdo,
                        $roomId,
                        $demo['date'],
                        $demo['start_time'],
                        $demo['end_time'],
                        $demo['title']
                    );
                    $success = true;
                    $message = 'Room assigned successfully.';

                    // assign howar pore updated free rooms & bookings
                    $freeRooms = getFreeRooms(
                        $pdo,
                        $selectedBuildingId,
                        $demo['date'],
                        $demo['start_time'],
                        $demo['end_time']
                    );

                } else {
                    $success = false;
                    $message = 'Ei room ei time-e ar free nai, abar onno room try koro.';
                }
            }

        } elseif ($action === 'edit_load') {
            // Edit button click -> edit form open
            $bookingId = (int)($_POST['booking_id'] ?? 0);
            if ($bookingId > 0) {
                $editingBooking = getBookingById($pdo, $bookingId);
                if (!$editingBooking) {
                    $success = false;
                    $message = 'Booking pawa jayni.';
                }
            }

        } elseif ($action === 'edit_save') {
            // Edit form submit -> save changes with clash/lunch check
            $bookingId = (int)($_POST['booking_id'] ?? 0);
            $date      = $_POST['edit_date']  ?? '';
            $startTime = $_POST['edit_start'] ?? '';
            $endTime   = $_POST['edit_end']   ?? '';
            $title     = $_POST['edit_title'] ?? '';

            if ($bookingId <= 0 || $date === '' || $startTime === '' || $endTime === '' || $title === '') {
                $success = false;
                $message = 'Sob field fill korte hobe edit er jonno.';
            } else {
                $old = getBookingById($pdo, $bookingId);
                if (!$old) {
                    $success = false;
                    $message = 'Booking pawa jai nai.';
                } elseif (isLunchClash($startTime, $endTime)) {
                    $success = false;
                    $message = 'Lunch time (13:00–14:00) er moddhe class allowed na.';
                } elseif (hasRoomClashOnEdit($pdo, $bookingId, $old['room_id'], $date, $startTime, $endTime)) {
                    $success = false;
                    $message = 'Ei time-e oi room e already onno class ase (clash).';
                } else {
                    updateBooking($pdo, $bookingId, $date, $startTime, $endTime, $title);
                    $success = true;
                    $message = 'Booking successfully updated.';
                    $editingBooking = getBookingById($pdo, $bookingId);
                }
            }

        } elseif ($action === 'delete_booking') {
            // Delete with confirm (JS confirm front-end e)
            $bookingId = (int)($_POST['booking_id'] ?? 0);
            if ($bookingId > 0) {
                deleteBooking($pdo, $bookingId);
                $success = true;
                $message = 'Booking deleted.';
            } else {
                $success = false;
                $message = 'Invalid booking selected for delete.';
            }
        }
    }
}

/* ---------- BOOKINGS (only jokhon demo on) ---------- */

$bookingsDate  = $demo['date'] ?? null;
$bookingsToday = $bookingsDate ? getBookingsByDate($pdo, $bookingsDate) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SEGi · Demo Auto Room Assign</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        :root {
            --primary: #2596be;
            --primary-soft: #35a8d4;
            --primary-dark: #0f435c;
            --bg-main: #021018;
            --bg-card: #031926;
            --text-main: #e5f3fb;
            --text-muted: #8ba0af;
            --border-soft: rgba(148, 192, 214, 0.55);
            --accent-green: #22c55e;
        }
        * {
            box-sizing: border-box;
        }
        body {
            min-height: 100vh;
            background:
              radial-gradient(circle at top left,
                rgba(37,150,190,0.55) 0,
                #021018 40%,
                #00070c 100%);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .shell {
            max-width: 1020px;
            width: 100%;
        }
        .card-main {
            position: relative;
            border-radius: 26px;
            background: radial-gradient(circle at top left,#031926,#021018);
            box-shadow:
              0 30px 80px rgba(1, 10, 18, 0.95),
              0 0 0 1px rgba(9, 30, 44, 0.9);
            border: 1px solid var(--border-soft);
            overflow: hidden;
        }
        .card-main::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 999px;
            background: radial-gradient(circle,rgba(37,150,190,0.55),transparent 60%);
            top: -120px;
            right: -90px;
            opacity: .5;
            filter: blur(1px);
        }
        .subtitle {
            color: var(--text-muted);
        }
        .section-card {
            border-radius: 18px;
            background: linear-gradient(135deg,#031926,#021018);
            border: 1px solid rgba(110, 170, 200, 0.6);
            box-shadow: 0 10px 30px rgba(0,0,0,0.45);
        }
        .section-header {
            font-size: .8rem;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #6b7f90;
        }
        .pill {
            border-radius: 999px;
            padding: 0.18rem 0.85rem;
            font-size: .78rem;
            background: rgba(3,25,38,0.95);
            color: #e5f3fb;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border: 1px solid rgba(148, 192, 214, 0.55);
        }
        .pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--accent-green);
            box-shadow: 0 0 10px rgba(34,197,94,0.7);
        }
        .btn-main {
            border-radius: 999px;
            padding: 1rem 3.4rem;
            font-size: 1.06rem;
            letter-spacing: .06em;
            background: linear-gradient(135deg,var(--primary),var(--primary-soft));
            border: none;
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            box-shadow:
              0 18px 40px rgba(37,150,190,0.55),
              0 0 0 1px rgba(13, 80, 109, 0.9);
            transform: translateY(0);
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }
        .btn-main:hover {
            filter: brightness(1.08);
            transform: translateY(-2px);
            box-shadow:
              0 22px 55px rgba(37,150,190,0.7),
              0 0 0 1px rgba(25, 105, 138, 0.95);
        }
        .btn-main-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: #021018;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
        }
        .btn-assign {
            border-radius: 999px;
            padding-inline: 1.9rem;
            background: linear-gradient(135deg, var(--accent-green), #4ade80);
            border: none;
            box-shadow: 0 12px 25px rgba(34,197,94,0.45);
            transform: translateY(0);
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }
        .btn-assign:hover {
            filter: brightness(1.06);
            transform: translateY(-1px);
        }
        .btn-assign[disabled] {
            opacity: .4;
            box-shadow: none;
            transform: none;
        }
        .btn-sm-soft {
            border-radius: 999px;
            padding: 0.2rem 0.7rem;
            font-size: .78rem;
        }
        .btn-edit {
            border-color: rgba(59,130,246,0.6);
            color: #bfdbfe;
        }
        .btn-delete {
            border-color: rgba(248,113,113,0.6);
            color: #fecaca;
        }
        .table-wrap {
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid rgba(110, 170, 200, 0.7);
            background: #021018;
        }
        .table thead {
            background: #021018;
            color: #dbeafe;
        }
        .table tbody tr {
            color: #cbd5f5;
            background: #021018;
            transition: background .18s ease, transform .15s ease;
        }
        .table tbody tr:nth-child(even) {
            background: #041723;
        }
        .table tbody tr:hover {
            background: #062233;
            transform: translateY(-1px);
        }
        .badge-venue {
            background: linear-gradient(135deg, var(--primary), var(--primary-soft));
        }
        .fade-in-up {
            opacity: 0;
            transform: translateY(10px);
            animation: fadeInUp .45s ease-out forwards;
        }
        .fade-in-up.delay-1 { animation-delay: .06s; }
        .fade-in-up.delay-2 { animation-delay: .12s; }
        .fade-in-up.delay-3 { animation-delay: .18s; }
        .fade-in-up.delay-4 { animation-delay: .24s; }

        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }
    </style>
</head>
<body>
<div class="shell px-3 position-relative">
    <div class="card card-main p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-4 fade-in-up">
            <span class="pill">
                <span class="pill-dot"></span>
                SEGi Timetable · Room Engine
            </span>
            <small class="subtitle text-end d-none d-md-block">
                No clash • No lunch-time classes • Same room allowed in different slots
            </small>
        </div>

        <!-- HOME: shudhu 1ta button (demo na thakle) -->
        <?php if (!$demo): ?>
            <div class="mb-4 text-center fade-in-up delay-1">
                <form method="post">
                    <input type="hidden" name="action" value="start">
                    <button type="submit" class="btn btn-main">
                        <span class="btn-main-icon">➕</span>
                        Select Venue
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- demo on thakle hint + New Demo button -->
            <div class="mb-3 fade-in-up delay-1">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                    <span class="subtitle">
                        Demo running – ei class er jonno room assign & bookings manage korte parba niche theke.
                    </span>

                    <form method="post">
                        <input type="hidden" name="action" value="start">
                        <button type="submit" class="btn btn-outline-light btn-sm-soft">
                            🔁 New Demo Class
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> py-2 fade-in-up delay-1">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($demo): ?>
            <!-- Step 1: class details -->
            <div class="section-card p-3 mb-4 fade-in-up delay-1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="section-header">Step 1 · Class details</div>
                    <small class="subtitle">Demo only – pore main timetable theke data asbe.</small>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-2 mb-md-0">
                        <small style="color:#7fb8d1;font-weight:600;" class="d-block">Date</small>
                        <div style="color:#d9f3ff;font-size:1.05rem;" class="fw-bold">
                            <?php echo htmlspecialchars($demo['date']); ?>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2 mb-md-0">
                        <small style="color:#7fb8d1;font-weight:600;" class="d-block">Time</small>
                        <div style="color:#d9f3ff;font-size:1.05rem;" class="fw-bold">
                            <?php echo htmlspecialchars($demo['start_time'] . ' – ' . $demo['end_time']); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <small style="color:#7fb8d1;font-weight:600;" class="d-block">Subject</small>
                        <div style="color:#d9f3ff;font-size:1.05rem;" class="fw-bold">
                            <?php echo htmlspecialchars($demo['title']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: building select -->
            <div class="section-card p-3 mb-3 fade-in-up delay-2">
                <div class="section-header mb-2">Step 2 · Choose building</div>
                <form method="post" class="row g-2 align-items-end" id="buildingForm">
                    <input type="hidden" name="action" value="choose_building">
                    <input type="hidden" name="date"  value="<?php echo htmlspecialchars($demo['date']); ?>">
                    <input type="hidden" name="start" value="<?php echo htmlspecialchars($demo['start_time']); ?>">
                    <input type="hidden" name="end"   value="<?php echo htmlspecialchars($demo['end_time']); ?>">
                    <input type="hidden" name="title" value="<?php echo htmlspecialchars($demo['title']); ?>">

                    <div class="col-md-12">
                        <label class="form-label mb-1">Building / Venue</label>
                        <select name="building_id" class="form-select" required id="buildingSelect">
                            <option value="">Choose a building</option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?php echo $b['id']; ?>"
                                    <?php if ($selectedBuildingId && (int)$selectedBuildingId === (int)$b['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="subtitle">
                            Building select korlei niche ei class slot-er jonno free room list auto asbe.
                        </small>
                    </div>
                </form>
            </div>

            <!-- Step 3: rooms + assign -->
            <?php if ($selectedBuildingId !== null): ?>
                <div class="section-card p-3 mb-4 fade-in-up delay-3">
                    <div class="section-header mb-2">Step 3 · Select room &amp; assign</div>

                    <?php if (count($freeRooms) === 0): ?>
                        <p class="subtitle mb-0">
                            Ei building-e ei time slot-er jonno kono room free nai (ba sob already clash hocche).
                        </p>
                    <?php else: ?>
                        <form method="post" class="row g-2 align-items-end" id="assignForm">
                            <input type="hidden" name="action" value="assign_room">
                            <input type="hidden" name="date"  value="<?php echo htmlspecialchars($demo['date']); ?>">
                            <input type="hidden" name="start" value="<?php echo htmlspecialchars($demo['start_time']); ?>">
                            <input type="hidden" name="end"   value="<?php echo htmlspecialchars($demo['end_time']); ?>">
                            <input type="hidden" name="title" value="<?php echo htmlspecialchars($demo['title']); ?>">
                            <input type="hidden" name="building_id" value="<?php echo (int)$selectedBuildingId; ?>">

                            <div class="col-md-8">
                                <label class="form-label mb-1">Available rooms</label>
                                <select name="room_id" class="form-select" required id="roomSelect">
                                    <option value="">Choose a room</option>
                                    <?php foreach ($freeRooms as $r): ?>
                                        <option value="<?php echo $r['id']; ?>">
                                            <?php echo htmlspecialchars($r['room_label'] . ' (Level ' . $r['level'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="subtitle">
                                    Ei list-e sudhu oi date & oi time slot-e je room gulo te **ekdom kono overlap nai** sudhu oigulai dekhacche.
                                </small>
                            </div>
                            <div class="col-md-4 text-md-end" id="assignButtonWrapper" style="display:none;">
                                <button type="submit" class="btn btn-assign mt-2 mt-md-0" id="assignButton" disabled>
                                    Assign Room
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Edit panel (jodi kono booking edit korte chai) -->
            <?php if ($editingBooking): ?>
                <div class="section-card p-3 mb-4 fade-in-up delay-3">
                    <div class="section-header mb-2">Edit booking</div>
                    <form method="post" class="row g-2 align-items-end">
                        <input type="hidden" name="action" value="edit_save">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$editingBooking['id']; ?>">

                        <div class="col-md-3">
                            <label class="form-label mb-1">Date</label>
                            <input type="date" name="edit_date" class="form-control"
                                   value="<?php echo htmlspecialchars($editingBooking['date']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Start time</label>
                            <input type="time" name="edit_start" class="form-control"
                                   value="<?php echo htmlspecialchars(substr($editingBooking['start_time'],0,5)); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">End time</label>
                            <input type="time" name="edit_end" class="form-control"
                                   value="<?php echo htmlspecialchars(substr($editingBooking['end_time'],0,5)); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Subject</label>
                            <input type="text" name="edit_title" class="form-control"
                                   value="<?php echo htmlspecialchars($editingBooking['title']); ?>" required>
                        </div>
                        <div class="col-12 mt-2">
                            <small class="subtitle">
                                Building: <?php echo htmlspecialchars($editingBooking['building_name']); ?> ·
                                Room: <?php echo htmlspecialchars($editingBooking['room_label']); ?> (Level <?php echo htmlspecialchars($editingBooking['level']); ?>)
                            </small>
                        </div>
                        <div class="col-12 mt-2 text-end">
                            <button type="submit" class="btn btn-assign btn-sm-soft">
                                Save changes
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Booked rooms table (same date) -->
            <?php if ($bookingsDate): ?>
                <div class="mb-2 d-flex justify-content-between align-items-center fade-in-up delay-4">
                    <div class="section-header mb-0">Daily view · Booked rooms</div>
                    <small class="subtitle">
                        Date: <?php echo htmlspecialchars($bookingsDate); ?>
                    </small>
                </div>

                <div class="table-wrap fade-in-up delay-4">
                    <?php if (count($bookingsToday) === 0): ?>
                        <div class="p-3">
                            <span class="subtitle">Ei date-e ekhono kono booking nai. Room assign korle ekhane dekhabe.</span>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle">
                                <thead>
                                <tr>
                                    <th style="width: 140px;">Time</th>
                                    <th style="width: 180px;">Venue</th>
                                    <th>Room</th>
                                    <th style="width: 80px;">Level</th>
                                    <th>Subject</th>
                                    <th style="width: 140px;">Actions</th>
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
                                        <td>
                                            <!-- Edit -->
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="edit_load">
                                                <input type="hidden" name="booking_id" value="<?php echo (int)$bk['id']; ?>">
                                                <button type="submit" class="btn btn-outline-info btn-sm-soft btn-edit">
                                                    Edit
                                                </button>
                                            </form>
                                            <!-- Delete -->
                                            <form method="post" class="d-inline"
                                                  onsubmit="return confirm('Are you sure want to delete this booking?');">
                                                <input type="hidden" name="action" value="delete_booking">
                                                <input type="hidden" name="booking_id" value="<?php echo (int)$bk['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm-soft btn-delete">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<script>
// Building select korlei auto submit (Load Rooms button dorkar nai)
const buildingSelect = document.getElementById('buildingSelect');
if (buildingSelect) {
    buildingSelect.addEventListener('change', function () {
        this.form.submit();
    });
}

// Room select howar age Assign button hide & disabled
const roomSelect = document.getElementById('roomSelect');
const assignWrapper = document.getElementById('assignButtonWrapper');
const assignButton = document.getElementById('assignButton');

if (roomSelect && assignWrapper && assignButton) {
    roomSelect.addEventListener('change', function () {
        if (this.value) {
            assignWrapper.style.display = 'block';
            assignButton.disabled = false;
        } else {
            assignWrapper.style.display = 'none';
            assignButton.disabled = true;
        }
    });
}
</script>
</body>
</html>
