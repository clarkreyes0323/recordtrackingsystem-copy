<?php
session_start();

// Only logged-in students can access the tracker.
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    http_response_code(403);
    exit("Access denied. Students only.");
}
include("./connection/config.php");
include("./helpers/SystemOperators.php");
require_once("./helpers/NotificationService.php");

$con = connection();$so = new SystemOperators();

$request_data = null;
$error_message = "";
$search_ref = '';
$my_requests = [];
$follow_up_success = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnTrack'])) {
    $search_ref = trim(filter_input(INPUT_POST, 'file_no', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    if ($search_ref === '') {
        $error_message = "Please enter your Reference/File No.";
    }
}

$user_stmt = $con->prepare("SELECT `email` FROM `users` WHERE `id` = ?");
$user_stmt->bind_param("i", $_SESSION["user_id"]);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnSubmitFollowUp']) && $user && !empty($user['email'])) {
    $follow_request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $follow_up_message = trim(filter_input(INPUT_POST, 'follow_up_message', FILTER_UNSAFE_RAW) ?? '');

    if ($follow_request_id && $follow_request_id > 0 && $follow_up_message !== '') {
        $request_lookup = $con->prepare("SELECT id, user_id, email, file_no FROM document_requests WHERE id = ?");
        $request_lookup->bind_param("i", $follow_request_id);
        $request_lookup->execute();
        $request_record = $request_lookup->get_result()->fetch_assoc();
        $request_lookup->close();

        if ($request_record) {
            $is_owner = (int)($request_record['user_id'] ?? 0) === (int)$_SESSION['user_id'];
            $request_email = strtolower(trim($so->decrypt($request_record['email']) ?: ''));
            $student_email = strtolower(trim($so->decrypt($user['email']) ?: ''));

            if ($is_owner || $request_email === $student_email) {
                $insert_follow_up = $con->prepare("INSERT INTO follow_up_messages (request_id, sender_id, message) VALUES (?, ?, ?)");
                $insert_follow_up->bind_param("iis", $follow_request_id, $_SESSION['user_id'], $follow_up_message);
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

if ($user && !empty($user['email'])) {
    $current_user_email = strtolower(trim($so->decrypt($user['email']) ?: ''));
    $select_docs = "SELECT * FROM `document_requests` WHERE `user_id` = ? OR `user_id` IS NULL ORDER BY `id` DESC";
    $stmt = $con->prepare($select_docs);
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if ($row['user_id'] === null) {
            $request_email = strtolower(trim($so->decrypt($row['email']) ?: ''));
            if ($current_user_email === '' || $request_email !== $current_user_email) {
                continue;
            }
        }

        $decrypted_file_no = $so->decrypt($row['file_no']) ?: '';
        $decrypted_status = $so->decrypt($row['status']) ?: 'Pending';
        $decrypted_doc_type = $so->decrypt($row['doc_type']) ?: 'Document request';

        $my_requests[] = [
            'id' => (int)$row['id'],
            'file_no' => $decrypted_file_no,
            'doc_type' => $decrypted_doc_type,
            'status' => $decrypted_status,
            'created_at' => $row['created_at']
        ];

        if ($search_ref !== '' && strtoupper(trim($decrypted_file_no)) === strtoupper(trim($search_ref))) {
            $request_data = [
                'id'            => (int)$row['id'],
                'file_no'       => $decrypted_file_no,
                'student_no'    => $so->decrypt($row['student_no']) ?: '',
                'name'          => ($so->decrypt($row['firstname']) ?: '') . ' ' . ($so->decrypt($row['lastname']) ?: ''),
                'program'       => $so->decrypt($row['program']) ?: '',
                'doc_type'      => $decrypted_doc_type,
                'purpose'       => $so->decrypt($row['purpose']) ?: '',
                'claiming_area' => $so->decrypt($row['claiming_area']) ?: '',
                'status'        => $decrypted_status,
                'year_level'    => $so->decrypt($row['year_level']) ?: '',
                'created_at'    => $row['created_at']
            ];
        }
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnTrack']) && $search_ref !== '' && !$request_data) {
    $error_message = "No record found for Reference/File No.: " . htmlspecialchars($search_ref);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Request Status</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
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

    <main class="tracker-shell">
        <header class="tracker-header">
            <div>
                <h1>Track a Document Request</h1>
                <p>FEU Roosevelt Student Portal</p>
            </div>
            <nav class="tracker-nav" aria-label="Student navigation">
                <a href="student_dashboard.php">Dashboard</a>
                <a href="request.php">New Request</a>
            </nav>
        </header>

        <form id="lookup-form" class="lookup-panel" action="track.php" method="post">
            <label for="file_no">Reference / File No.</label>
            <div class="lookup-row">
                <input id="file_no" type="text" name="file_no" list="my-request-refs" required autocomplete="off" placeholder="DOC-YYYYMMDD-XXXXXXXX" value="<?php echo htmlspecialchars($search_ref); ?>">
                <datalist id="my-request-refs">
                    <?php foreach ($my_requests as $request): ?>
                        <option value="<?php echo htmlspecialchars($request['file_no']); ?>" label="<?php echo htmlspecialchars($request['doc_type'] . ' - ' . $request['status']); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <button type="submit" name="btnTrack">Track Status</button>
            </div>
        </form>

        <?php if ($error_message !== ''): ?>
            <p class="error-message" role="alert"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <?php if ($follow_up_success !== ''): ?>
            <p class="success-message" role="status"><?php echo htmlspecialchars($follow_up_success); ?></p>
        <?php endif; ?>
        <!-- Print the request details if found -->
        <?php if ($request_data): ?>
            <section class="result-panel printable-request" aria-labelledby="result-title">
                <div class="print-document-heading">
                    <p>FEU ROOSEVELT</p>
                    <h2>Document Request Summary</h2>
                </div>
        <!-- Display the track search details -->
                <div class="result-heading">
                    <div>
                        <h2 id="result-title"><?php echo htmlspecialchars($request_data['doc_type']); ?></h2>
                        <p><?php echo htmlspecialchars($request_data['file_no']); ?></p>
                    </div>
                    <span class="status-pill"><?php echo htmlspecialchars($request_data['status']); ?></span>
                </div>
                <dl class="detail-grid">
                    <div class="detail-item"><dt>Student Name</dt><dd><?php echo htmlspecialchars($request_data['name']); ?></dd></div>
                    <div class="detail-item"><dt>Student No.</dt><dd><?php echo htmlspecialchars($request_data['student_no']); ?></dd></div>
                    <div class="detail-item"><dt>Program</dt><dd><?php echo htmlspecialchars($request_data['program']); ?></dd></div>
                    <div class="detail-item"><dt>Year Level</dt><dd><?php echo htmlspecialchars($request_data['year_level']); ?></dd></div>
                    <div class="detail-item"><dt>Purpose</dt><dd><?php echo htmlspecialchars($request_data['purpose']); ?></dd></div>
                    <div class="detail-item"><dt>Submitted</dt><dd><?php echo htmlspecialchars(date('M j, Y', strtotime($request_data['created_at']))); ?></dd></div>
                    <div class="detail-item"><dt>Claiming Area</dt><dd><?php echo htmlspecialchars($request_data['claiming_area']); ?></dd></div>
                </dl>
                <p class="print-disclaimer">This printout summarizes the request; it is not a copy of the requested record.</p>
                <div class="request-action-row">
                    <button class="print-button" type="button" id="print-request">Print Request</button>
                    <button class="follow-up-button" type="button" data-request-id="<?php echo (int)$request_data['id']; ?>">Follow Up</button>
                </div>
            </section>
        <?php endif; ?>

        <section class="history-section" aria-labelledby="history-title">
            <h2 id="history-title">Your Recent Requests</h2>
            <?php if ($my_requests): ?>
                <div class="table-scroll">
                    <table class="request-table">
                        <thead>
                            <tr><th>Reference No.</th><th>Document</th><th>Submitted</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_requests as $request): ?>
                                <tr>
                                    <td><button class="reference-button" type="button" data-reference="<?php echo htmlspecialchars($request['file_no']); ?>"><?php echo htmlspecialchars($request['file_no']); ?></button></td>
                                    <td><?php echo htmlspecialchars($request['doc_type']); ?></td>
                                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($request['created_at']))); ?></td>
                                    <td><?php echo htmlspecialchars($request['status']); ?></td>
                                    <td><button class="follow-up-button compact" type="button" data-request-id="<?php echo (int)$request['id']; ?>">Follow Up</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">No requests are linked to this account yet.</p>
            <?php endif; ?>
        </section>
    </main>

    <div class="follow-up-modal" id="followUpModal" hidden>
        <div class="follow-up-modal-content" role="dialog" aria-modal="true" aria-labelledby="followUpModalTitle">
            <div class="follow-up-modal-header">
                <h3 id="followUpModalTitle">Send a follow-up message</h3>
                <button class="follow-up-close" type="button" data-close-follow-up aria-label="Close follow-up dialog">&times;</button>
            </div>
            <form method="post" action="track.php">
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

        const printRequestButton = document.getElementById('print-request');
        if (printRequestButton) {
            printRequestButton.addEventListener('click', () => {
                document.body.classList.add('printing-result');
                window.print();
            });
        }

        window.addEventListener('afterprint', () => {
            document.body.classList.remove('printing-result');
        });

        document.querySelectorAll('[data-reference]').forEach((button) => {
            button.addEventListener('click', () => {
                const referenceInput = document.getElementById('file_no');
                referenceInput.value = button.dataset.reference;
                document.getElementById('lookup-form').requestSubmit();
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
            if (button.classList.contains('reference-button')) {
                return;
            }
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