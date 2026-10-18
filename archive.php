<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Administrators only.");
}

include("./connection/config.php");
include("./helpers/SystemOperators.php");
require_once("./helpers/NotificationService.php");

$con = connection();
$so = new SystemOperators();

$requests = [];
$search = trim($_GET['search'] ?? $_POST['search'] ?? '');

/* =========================
   STUDENT CLASSIFICATION FUNCTION
   ========================= */
function getStudentClassification($year_level) {
    $year_level = strtolower(trim($year_level));
    if (in_array($year_level, ['nursery', 'kindergarten 1', 'kindergarten 2'], true)) {
        return ['name' => 'PRE', 'bg_color' => '#e0f7fa', 'border_color' => '#f2fd5a', 'dot_color' => '#f5dc00', 'text_color' => '#a37000', 'full' => 'Pre-school'];
    } elseif (in_array($year_level, ['grade 1', 'grade 2', 'grade 3', 'grade 4', 'grade 5', 'grade 6'], true)) {
        return ['name' => 'GS', 'bg_color' => '#f1f8e9', 'border_color' => '#33691e', 'dot_color' => '#0f3600', 'text_color' => '#33691e', 'full' => 'Grade School'];
    } elseif (in_array($year_level, ['grade 7', 'grade 8', 'grade 9', 'grade 10'], true)) {
        return ['name' => 'JHS', 'bg_color' => '#fff3e0', 'border_color' => '#e65100', 'dot_color' => '#702700', 'text_color' => '#e65100', 'full' => 'Junior High School'];
    } elseif (in_array($year_level, ['grade 11', 'grade 12'], true)) {
        return ['name' => 'SHS', 'bg_color' => '#fce4ec', 'border_color' => '#e9007c', 'dot_color' => '#880e4f', 'text_color' => '#880e4f', 'full' => 'Senior High School'];
    } elseif (in_array($year_level, ['1st year', '2nd year', '3rd year', '4th year'], true)) {
        return ['name' => 'TED', 'bg_color' => '#e8f5e9', 'border_color' => '#a5a5a5', 'dot_color' => '#3d3d3d', 'text_color' => '#2e7d32', 'full' => 'Tertiary Education'];
    } elseif (in_array($year_level, ['alumni'], true)) {
        return ['name' => 'AM', 'bg_color' => '#f5f5f5', 'border_color' => '#b135e2', 'dot_color' => '#420034', 'text_color' => '#420034', 'full' => 'Alumni'];
    } else {
        return ['name' => 'N/A', 'bg_color' => '#ffffff', 'border_color' => '#030303', 'dot_color' => '#0c0c0c', 'text_color' => '#0c0c0c', 'full' => 'Unknown'];
    }
}

/* =========================
   ARCHIVED REQUESTS (COMPLETED & REJECTED)
   ========================= */
$query = "SELECT * FROM document_requests ORDER BY id DESC";

if ($result = $con->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $status = $so->decrypt($row['status']) ?: 'Pending';
        
        if ($status === 'Completed' || $status === 'Rejected') {
            $requests[] = $row;
        }

    }

    $result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Dashboard | Document Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body class="admin-dashboard-page">
    <header class="admin-topbar">
        <div class="admin-topbar-inner">
            <a class="admin-brand" href="admin_dashboard.php" aria-label="FEU Roosevelt records dashboard">
                <span class="admin-brand-mark" aria-hidden="true">FR</span>
                <span class="admin-brand-copy">
                    <small>FEU ROOSEVELT</small>
                    <strong>Records Archive</strong>
                </span>
            </a>
            <div class="admin-topbar-actions">
                <?php include("./helpers/notification_center.php"); ?>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm me-2 text-decoration-none fw-semibold">
                    ← Back to Dashboard
                </a>
                <a href="logout.php" class="admin-logout">Sign out</a>
            </div>
        </div>
    </header>

    <main class="admin-main container-fluid">
        <section class="admin-overview" aria-labelledby="admin-page-title">
            <div>
                <p class="admin-eyebrow">Archived records</p>
                <h1 id="admin-page-title">Completed & Rejected Queue</h1>
                <p>Review historical records of finished or rejected document requests.</p>
            </div>
            <time datetime="<?= date('Y-m-d') ?>"><?= date('l, F j, Y') ?></time>
        </section>

        <section class="admin-queue" aria-labelledby="request-queue-title">
            <div class="admin-queue-heading">
                <div>
                    <p class="admin-eyebrow">Historical Submissions</p>
                    <h2 id="request-queue-title">Archive list</h2>
                </div>
                <span><?= count($requests) ?> total archived</span>
            </div>

            <!-- SEARCH FILTER -->
            <div class="admin-search">
                <form class="lookup-panel" method="GET" action="archive.php">
                    <div class="lookup-row">
                        <input type="text" name="search" placeholder="Search archive..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit">SEARCH</button>
                    </div>
                    <?php if ($search !== ''): ?>
                        <a href="archive.php" class="clear-search">Clear Search</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- REQUESTS TABLE -->
            <div class="table-wrap">
                <table class="table request-table admin-request-table">
                    <thead>
                        <tr>
                            <th>Ref No.</th>
                            <th>Student No.</th>
                            <th>Student Name</th>
                            <th>Year Level</th>
                            <th>Program</th>
                            <th>File Type</th>
                            <th>Purpose</th>
                            <th>Claiming Area</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td class="empty-requests" colspan="9">No archived records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $displayed_count = 0;
                            foreach ($requests as $request):
                                $file_no = $so->decrypt($request['file_no']);
                                $student_no = $so->decrypt($request['student_no']);
                                $first = $so->decrypt($request['firstname']);
                                $middle = $so->decrypt($request['middlename'] ?? '') ?: '';
                                $last = $so->decrypt($request['lastname']);
                                $full_name = trim("$first $middle $last");
                                $year_level = $so->decrypt($request['year_level']);
                                $program = $so->decrypt($request['program']);
                                $doc_type = $so->decrypt($request['doc_type']);
                                $purpose = $so->decrypt($request['purpose']);
                                $claiming_area = $so->decrypt($request['claiming_area'] ?? '') ?: 'N/A';
                                $status = $so->decrypt($request['status']);

                                if ($search !== '') {
                                    $search_text = "$file_no $student_no $full_name $year_level $program $doc_type $purpose $claiming_area $status";
                                    if (stripos($search_text, $search) === false) {
                                        continue;
                                    }
                                }

                                $displayed_count++;
                                $classif = getStudentClassification($year_level);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($file_no) ?></strong>
                                        <time class="request-date" datetime="<?= htmlspecialchars($request['created_at']) ?>">
                                            <?= htmlspecialchars(date('Y/m/d', strtotime($request['created_at']))) ?>
                                        </time>
                                    </td>
                                    <td><?= htmlspecialchars($student_no) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span><?= htmlspecialchars($full_name) ?></span>
                                            <?php if (!empty($classif)): ?>
                                                <span class="d-inline-flex align-items-center px-2 py-1 rounded-pill text-xs font-bold" 
                                                style="background-color: <?= $classif['bg_color'] ?>; border: 1px solid <?= $classif['border_color'] ?>; border-radius: 4px; font-size: 0.85rem;"
                                                title="<?= htmlspecialchars($classif['full']) ?>">
                                                    <span class="rounded-circle me-1" style="width: 6px; height: 6px; background-color: <?= $classif['dot_color'] ?>; display: inline-block;"></span>
                                                    <?= htmlspecialchars($classif['name']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($year_level) ?></td>
                                    <td><?= htmlspecialchars($program) ?></td>
                                    <td><?= htmlspecialchars($doc_type) ?></td>
                                    <td><?= htmlspecialchars($purpose) ?></td>
                                    <td><?= htmlspecialchars($claiming_area) ?></td>
                                    <td>
                                        <span class="badge <?= $status === 'Completed' ? 'bg-success' : 'bg-danger' ?>" style="font-size: 0.85rem; padding: 6px 12px;">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($displayed_count === 0): ?>
                                <tr>
                                    <td class="empty-requests" colspan="9">No archived requests match your search.</td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
<?php $con->close(); ?>