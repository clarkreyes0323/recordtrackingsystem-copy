

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
$follow_up_success = '';

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

$con->query("CREATE TABLE IF NOT EXISTS follow_up_messages (
    id INT NOT NULL AUTO_INCREMENT,
    request_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_follow_up_request (request_id),
    KEY idx_follow_up_sender (sender_id),
    CONSTRAINT fk_follow_up_request FOREIGN KEY (request_id) REFERENCES document_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_up_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnSubmitFollowUp']) && $userEmail !== '') {
    $follow_request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $follow_up_message = trim(filter_input(INPUT_POST, 'follow_up_message', FILTER_UNSAFE_RAW) ?? '');

    if ($follow_request_id && $follow_request_id > 0 && $follow_up_message !== '') {
        $request_lookup = $con->prepare("SELECT id, user_id, email, file_no FROM document_requests WHERE id = ?");
        $request_lookup->bind_param("i", $follow_request_id);
        $request_lookup->execute();
        $request_record = $request_lookup->get_result()->fetch_assoc();
        $request_lookup->close();

        if ($request_record) {
            $is_owner = (int)($request_record['user_id'] ?? 0) === $userId;
            $request_email = strtolower(trim($so->decrypt($request_record['email']) ?: ''));

            if ($is_owner || $request_email === $userEmail) {
                $insert_follow_up = $con->prepare("INSERT INTO follow_up_messages (request_id, sender_id, message) VALUES (?, ?, ?)");
                $insert_follow_up->bind_param("iis", $follow_request_id, $userId, $follow_up_message);
                $insert_follow_up->execute();
                $insert_follow_up->close();

                $admin_stmt = $con->prepare("SELECT id FROM users WHERE role = 'admin'");
                $admin_stmt->execute();
                $admin_result = $admin_stmt->get_result();
                while ($admin = $admin_result->fetch_assoc()) {
                    addNotification(
                        $con,
                        (int)$admin['id'],
                        "A student sent a follow-up message for request " . ($so->decrypt($request_record['file_no']) ?: 'request #'.$follow_request_id) . ".",
                        $follow_request_id
                    );
                }
                $admin_stmt->close();
                $follow_up_success = 'Your follow-up message has been sent to the records office.';
            }
        }
    }
}

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

<body class="student-dashboard-page sidebar-collapsed">
    <header class="student-topbar">
        <div class="student-topbar-inner">
            <a class="student-brand" href="student_dashboard.php" aria-label="FEU Roosevelt student portal home">
                <span class="brand-mark" aria-hidden="true">FR</span>
                <span class="brand-copy">
                    <span>FEU ROOSEVELT</span>
                    <strong>Student Portal</strong>
                </span>
            </a>
            <nav class="student-topbar-actions" aria-label="Student actions">
                <a class="student-nav-link" href="track.php">Track Requests</a>
                <a class="student-nav-link student-new-request" href="request.php">New Request</a>
                <?php include "./helpers/notification_center.php"; ?>
                <a href="logout.php" class="logout">Sign out</a>
            </nav>
        </div>
    </header>

    <aside class="dashboard-sidebar student-sidebar" id="dashboardSidebar" aria-label="Student navigation">
        <div class="dashboard-sidebar-brand">
            <a class="student-brand" href="student_dashboard.php" aria-label="FEU Roosevelt student portal home">
                <span class="brand-mark" aria-hidden="true">FR</span>
                <span class="brand-copy">
                    <span>FEU ROOSEVELT</span>
                    <strong>Student Portal</strong>
                </span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-controls="dashboardSidebar" aria-expanded="false" aria-label="Expand sidebar">&rsaquo;</button>
        </div>
        <div class="dashboard-sidebar-content">
            <label class="sidebar-search">
                <span class="sidebar-search-icon" aria-hidden="true"></span>
                <input id="sidebarSearch" type="search" aria-label="Search navigation" placeholder="Search">
            </label>
            <p class="sidebar-label">Main menu</p>
            <nav>
                <a class="sidebar-link" href="student_dashboard.php" title="Dashboard"><span class="sidebar-icon" aria-hidden="true">D</span><span class="sidebar-link-text">Dashboard</span></a>
                <a class="sidebar-link" href="track.php" title="Track Requests"><span class="sidebar-icon" aria-hidden="true">TR</span><span class="sidebar-link-text">Track Requests</span></a>
                <a class="sidebar-link" href="request.php" title="New Request"><span class="sidebar-icon" aria-hidden="true">NR</span><span class="sidebar-link-text">New Request</span></a>
            </nav>
        </div>
    </aside>

    <main class="student-main">
        <section class="student-welcome" aria-labelledby="welcome-title">
            <div>
                <p class="student-eyebrow">Document services</p>
                <h1 id="welcome-title">Welcome, <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Review your document requests or start a new one.</p>
            </div>
            <time class="welcome-date" datetime="<?= date('Y-m-d') ?>"><?= date('l, F j, Y') ?></time>
        </section>

        <?php if ($follow_up_success !== ''): ?>
            <p class="success-message" role="status"><?= htmlspecialchars($follow_up_success, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

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
                                        <th scope="col">Action</th>
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
                                            <td><button class="follow-up-button compact" type="button" data-request-id="<?= (int)$request['id'] ?>">Follow Up</button></td>
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

    <div class="follow-up-modal" id="followUpModal" hidden>
        <div class="follow-up-modal-content" role="dialog" aria-modal="true" aria-labelledby="followUpModalTitle">
            <div class="follow-up-modal-header">
                <h3 id="followUpModalTitle">Send a follow-up message</h3>
                <button class="follow-up-close" type="button" data-close-follow-up aria-label="Close follow-up dialog">&times;</button>
            </div>
            <form method="post" action="student_dashboard.php">
                <input type="hidden" id="followUpRequestId" name="request_id" value="">
                <label for="followUpMessage">Write a message for the records office</label>
                <textarea id="followUpMessage" name="follow_up_message" rows="5" maxlength="500" placeholder="I would like to follow up on my request because..." required></textarea>
                <div class="modal-actions">
                    <button type="button" class="secondary-button" data-close-follow-up>Cancel</button>
                    <button type="submit" name="btnSubmitFollowUp" class="primary-button">Send Follow Up</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');

        sidebarToggle.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
            sidebarToggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
            sidebarToggle.innerHTML = isCollapsed ? '&rsaquo;' : '&lsaquo;';
        });

        document.getElementById('sidebarSearch').addEventListener('input', (event) => {
            const searchText = event.target.value.toLowerCase();
            document.querySelectorAll('#dashboardSidebar .sidebar-link').forEach((link) => {
                link.hidden = !link.textContent.toLowerCase().includes(searchText);
            });
        });

        const followUpModal = document.getElementById('followUpModal');
        const followUpRequestId = document.getElementById('followUpRequestId');

        const openFollowUpModal = (requestId) => {
            if (!followUpModal || !followUpRequestId) {
                return;
            }
            followUpRequestId.value = requestId;
            followUpModal.hidden = false;
        };

        const closeFollowUpModal = () => {
            if (!followUpModal || !followUpRequestId) {
                return;
            }
            followUpModal.hidden = true;
            followUpRequestId.value = '';
            const textArea = document.getElementById('followUpMessage');
            if (textArea) {
                textArea.value = '';
            }
        };

        document.querySelectorAll('[data-request-id]').forEach((button) => {
            button.addEventListener('click', () => {
                openFollowUpModal(button.dataset.requestId);
            });
        });

        document.querySelectorAll('[data-close-follow-up]').forEach((button) => {
            button.addEventListener('click', closeFollowUpModal);
        });

        if (followUpModal) {
            followUpModal.addEventListener('click', (event) => {
                if (event.target === followUpModal) {
                    closeFollowUpModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !followUpModal.hidden) {
                    closeFollowUpModal();
                }
            });
        }
    </script>

</body>
</html>

<?php $con->close(); ?>