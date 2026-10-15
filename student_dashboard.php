

<?php
session_start();

// Only authenticated students can access this page.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    header("Location: index.php");
    exit();
}

$name = $_SESSION['user_name'] ?? 'Student';

require_once __DIR__ . '/connection/config.php';
require_once __DIR__ . '/helpers/SystemOperators.php';
require_once __DIR__ . '/helpers/NotificationService.php';
$con = connection();
$so = new SystemOperators();
$userId = (int)$_SESSION['user_id'];
$userName = trim($name);
$firstName = preg_split('/\s+/', $userName)[0] ?? 'Student';

$userStmt = $con->prepare("SELECT email FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
$userEmail = $userRow ? strtolower(trim($so->decrypt($userRow['email']) ?: '')) : '';

$requestStmt = $con->prepare(
    "SELECT id, user_id, email, file_no, doc_type, status, created_at
     FROM document_requests
     WHERE user_id = ? OR user_id IS NULL
     ORDER BY created_at DESC, id DESC"
);
$requestStmt->bind_param("i", $userId);
$requestStmt->execute();
$requestResult = $requestStmt->get_result();
$requests = [];

while ($row = $requestResult->fetch_assoc()) {
    if ($row['user_id'] === null) {
        $requestEmail = strtolower(trim($so->decrypt($row['email']) ?: ''));
        if ($userEmail === '' || $requestEmail !== $userEmail) {
            continue;
        }
    }

    $row['file_no'] = $so->decrypt($row['file_no']) ?: '';
    $row['doc_type'] = $so->decrypt($row['doc_type']) ?: 'Document request';
    $row['status'] = $so->decrypt($row['status']) ?: 'Pending';
    $requests[] = $row;
}
$requestStmt->close();

$requestCounts = [
    'total' => count($requests),
    'in_progress' => 0,
    'ready' => 0,
    'completed' => 0
];
foreach ($requests as $request) {
    if (in_array($request['status'], ['Processing', 'Approved'], true)) {
        $requestCounts['in_progress']++;
    } elseif ($request['status'] === 'Ready for Claiming') {
        $requestCounts['ready']++;
    } elseif ($request['status'] === 'Completed') {
        $requestCounts['completed']++;
    }
}

$statusClasses = [
    'Pending' => 'status-pending',
    'Processing' => 'status-processing',
    'Approved' => 'status-approved',
    'Ready for Claiming' => 'status-ready',
    'Completed' => 'status-completed',
    'Rejected' => 'status-rejected'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - FEU Roosevelt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body class="student-dashboard-page">
    <header class="student-topbar">
        <div class="student-topbar-inner">
            <a class="student-brand" href="student_dashboard.php" aria-label="FEU Roosevelt student portal home">
                <span class="brand-mark" aria-hidden="true">FR</span>
                <span class="brand-copy">
                    <span>FEU ROOSEVELT</span>
                    <strong>Student Portal</strong>
                </span>
            </a>
            <nav class="student-topbar-actions" aria-label="Student navigation">
                <a class="student-nav-link" href="track.php">Track Requests</a>
                <a class="student-nav-link student-new-request" href="request.php">New Request</a>
                <?php include "./helpers/notification_center.php"; ?>
                <a href="logout.php" class="logout">Sign out</a>
            </nav>
        </div>
    </header>

    <main class="student-main">
        <section class="student-welcome" aria-labelledby="welcome-title">
            <div>
                <p class="student-eyebrow">Document services</p>
                <h1 id="welcome-title">Welcome, <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Review your document requests or start a new one.</p>
            </div>
            <time class="welcome-date" datetime="<?= date('Y-m-d') ?>"><?= date('l, F j, Y') ?></time>
        </section>

        <section class="row g-3 mb-4" aria-label="Request summary">
            <div class="col-6 col-xl-3">
                <div class="student-metric">
                    <span class="student-metric-label">Total requests</span>
                    <span class="student-metric-value"><?= $requestCounts['total'] ?></span>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="student-metric">
                    <span class="student-metric-label">In progress</span>
                    <span class="student-metric-value"><?= $requestCounts['in_progress'] ?></span>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="student-metric metric-ready">
                    <span class="student-metric-label">Ready for claiming</span>
                    <span class="student-metric-value"><?= $requestCounts['ready'] ?></span>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="student-metric metric-complete">
                    <span class="student-metric-label">Completed</span>
                    <span class="student-metric-value"><?= $requestCounts['completed'] ?></span>
                </div>
            </div>
        </section>

        <div class="row g-4">
            <section class="col-lg-8" aria-labelledby="recent-requests-title">
                <div class="student-section">
                    <div class="student-section-header">
                        <h2 id="recent-requests-title">Recent requests</h2>
                        <a href="track.php">View all requests</a>
                    </div>
                    <?php if ($requests): ?>
                        <div class="student-request-scroll">
                            <table class="table student-requests-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Reference</th>
                                        <th scope="col">Document</th>
                                        <th scope="col">Submitted</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($requests, 0, 6) as $request): ?>
                                        <?php $statusClass = $statusClasses[$request['status']] ?? 'status-pending'; ?>
                                        <tr>
                                            <td><span class="student-reference"><?= htmlspecialchars($request['file_no'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td><span class="student-document-name"><?= htmlspecialchars($request['doc_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td class="student-date"><?= htmlspecialchars(date('M j, Y', strtotime($request['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="request-status <?= $statusClass ?>"><?= htmlspecialchars($request['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="student-empty-state">
                            <h3>No document requests yet</h3>
                            <p>Your submitted requests and their latest status will appear here.</p>
                            <a class="btn btn-success" href="request.php">Start a request</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="col-lg-4" aria-labelledby="student-actions-title">
                <section class="student-section">
                    <div class="student-section-header">
                        <h2 id="student-actions-title">Quick actions</h2>
                    </div>
                    <div class="student-quick-actions">
                        <a class="student-action-link" href="request.php">
                            <span><strong>Request a document</strong><small>Submit a new records request</small></span>
                            <span class="student-action-arrow" aria-hidden="true">&rsaquo;</span>
                        </a>
                        <a class="student-action-link" href="track.php">
                            <span><strong>Track a request</strong><small>Check status and claiming details</small></span>
                            <span class="student-action-arrow" aria-hidden="true">&rsaquo;</span>
                        </a>
                    </div>
                    <p class="student-help-note">When a request status or claiming area changes, a notice will appear in your bell menu.</p>
                </section>
            </aside>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

</body>
</html>

<?php $con->close(); ?>