<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    http_response_code(403);
    exit("Access denied. Administrators only.");
}

include("./connection/config.php");
include("./helpers/SystemOperators.php");
require_once("./helpers/NotificationService.php");

$con = connection();
$so = new SystemOperators();

$success_message = '';
$requests = [];
$follow_up_messages = [];

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

$follow_up_query = $con->query(
    "SELECT follow_up_messages.message, follow_up_messages.created_at,
            document_requests.file_no, users.name
     FROM follow_up_messages
     INNER JOIN document_requests ON follow_up_messages.request_id = document_requests.id
     INNER JOIN users ON follow_up_messages.sender_id = users.id
     ORDER BY follow_up_messages.created_at DESC, follow_up_messages.id DESC"
);
while ($message = $follow_up_query->fetch_assoc()) {
    $message['file_no'] = $so->decrypt($message['file_no']) ?: '';
    $follow_up_messages[] = $message;
}
$follow_up_query->free();

$tabs = [
    'all' => 'All Requests',
    'pending' => 'Pending',
    'processing' => 'Processing',
    'approved' => 'Approved'
];
$current_tab = $_GET['tab'] ?? $_POST['tab'] ?? 'all';
if (!array_key_exists($current_tab, $tabs)) {
    $current_tab = 'all';
}
$tab_statuses = [
    'pending' => 'Pending',
    'processing' => 'Processing',
    'approved' => 'Approved'
];

/* =========================
   STUDENT CLASSIFICATION FUNCTIONS
   ========================= */

function getStudentClassification ($year_level) {
    $year_level = strtolower(trim($year_level));
    if (in_array($year_level, ['nursery', 'kindergarten 1', 'kindergarten 2'], true)) {
        return ['Pre-school', 'name' => 'PRE', 'bg_color' => '#e0f7fa', 'border_color' => '#f2fd5a', 'dot_color' => '#f5dc00', 'text_color' => '#a37000'];
    } elseif (in_array($year_level, ['grade 1', 'grade 2', 'grade 3', 'grade 4', 'grade 5', 'grade 6'], true)) {
        return ['Grade School', 'name' => 'GS', 'bg_color' => '#f1f8e9', 'border_color' => '#33691e', 'dot_color' => '#0f3600', 'text_color' => '#33691e'];
    } elseif (in_array($year_level, ['grade 7', 'grade 8', 'grade 9', 'grade 10'], true)) {
        return ['Junior High School', 'name' => 'JHS', 'bg_color' => '#fff3e0', 'border_color' => '#e65100', 'dot_color' => '#702700', 'text_color' => '#e65100'];
    } elseif (in_array($year_level, ['grade 11', 'grade 12'], true)) {
        return ['Senior High School', 'name' => 'SHS', 'bg_color' => '#fce4ec', 'border_color' => '#e9007c', 'dot_color' => '#880e4f', 'text_color' => '#880e4f'];
    } elseif (in_array($year_level, ['1st year', '2nd year', '3rd year', '4th year'], true)) {
        return ['Tertiary Education', 'name' => 'TED', 'bg_color' => '#e8f5e9', 'border_color' => '#a5a5a5', 'dot_color' => '#3d3d3d', 'text_color' => '#2e7d32'];
    } elseif (in_array($year_level, ['Alumni'], true)) {
        return ['Alumni', 'name' => 'AM', 'bg_color' => '#f5f5f5', 'border_color' => '#b135e2', 'dot_color' => '#420034', 'text_color' => '#f1f1f1'];
    } else {
        return ['Unknown', 'name' => 'N/A', 'bg_color' => '#ffffff', 'border_color' => '#030303', 'dot_color' => '#ffffff', 'text_color' => '#0c0c0c'];
    }
}

/* =========================
   SEARCH FILTER
   ========================= */

$search = trim($_GET['search'] ?? $_POST['search'] ?? '');

/* =========================
   UPDATE REQUEST
   ========================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['btnUpdate'])
) {

    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);

    $status = trim(
        filter_input(
            INPUT_POST,
            'status',
            FILTER_UNSAFE_RAW
        ) ?? ''
    );

    $claiming_area = trim(
        filter_input(
            INPUT_POST,
            'claiming_area',
            FILTER_UNSAFE_RAW
        ) ?? ''
    );

    $allowed_statuses = [
        'Pending',
        'Processing',
        'Approved',
        'Ready for Claiming',
        'Completed',
        'Rejected'
    ];

    if (
        $request_id &&
        $request_id > 0 &&
        in_array($status, $allowed_statuses, true) &&
        $claiming_area !== ''
    ) {

        $lookup = $con->prepare(
            "SELECT id, email, file_no, status, claiming_area
             FROM document_requests
             WHERE id = ?"
        );
        $lookup->bind_param("i", $request_id);
        $lookup->execute();
        $existing_request = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if ($existing_request) {
            $old_status = $so->decrypt($existing_request['status']) ?: 'Pending';
            $old_area = $so->decrypt($existing_request['claiming_area'] ?? '') ?: '';
            $changes = [];
            $status_changed = $old_status !== $status;

            if ($status_changed) {
                $changes[] = "status changed from $old_status to $status";
            }
            if ($old_area !== $claiming_area) {
                $old_area_text = $old_area !== '' ? $old_area : 'not assigned';
                $changes[] = "claiming area changed from $old_area_text to $claiming_area";
            }

            $recipient_id = $existing_request['user_id'] !== null
                ? (int)$existing_request['user_id']
                : null;

            if ($recipient_id === null) {
                $student_lookup = $con->prepare(
                    "SELECT id FROM users WHERE email = ? AND role = 'student'"
                );
                $student_lookup->bind_param("s", $existing_request['email']);
                $student_lookup->execute();
                $student = $student_lookup->get_result()->fetch_assoc();
                $student_lookup->close();
                $recipient_id = $student ? (int)$student['id'] : null;
            }

            $enc_status = $so->encrypt($status);
            $enc_area = $so->encrypt($claiming_area);

            try {
                $con->begin_transaction();
                $update = $con->prepare(
                    "UPDATE document_requests
                     SET status = ?, claiming_area = ?
                     WHERE id = ?"
                );
                $update->bind_param("ssi", $enc_status, $enc_area, $request_id);
                $update->execute();
                $update->close();

                if ($status_changed) {
                    $delete_follow_ups = $con->prepare(
                        "DELETE FROM follow_up_messages WHERE request_id = ?"
                    );
                    $delete_follow_ups->bind_param("i", $request_id);
                    $delete_follow_ups->execute();
                    $delete_follow_ups->close();
                }

                if ($changes && $recipient_id !== null) {
                    $reference = $so->decrypt($existing_request['file_no']);
                    $message = "Your request $reference was updated: " . implode('; ', $changes) . '.';
                    addNotification($con, $recipient_id, $message, $request_id);
                }

                $con->commit();
                $redirect_params = [
                    'success' => 1,
                    'tab' => $current_tab
                ];
                if ($search !== '') {
                    $redirect_params['search'] = $search;
                }
                header('Location: admin_dashboard.php?' . http_build_query($redirect_params));
                exit;
            } catch (Throwable $error) {
                $con->rollback();
                error_log($error->getMessage());
            }
        }
    }
}


/* =========================
   SUCCESS MESSAGE
   ========================= */

if (isset($_GET['success'])) {
    $success_message =
        'Request updated successfully.';
}

/* =========================
   GET REQUESTS
   ========================= */

$query =
    "SELECT *
     FROM document_requests
     ORDER BY id DESC";

if ($result = $con->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    $result->free();
}

$tab_counts = array_fill_keys(array_keys($tabs), 0);
$status_counts = array_fill_keys([
    'Pending',
    'Processing',
    'Approved',
    'Ready for Claiming',
    'Completed',
    'Rejected'
], 0);
foreach ($requests as $request) {
    $request_status = $so->decrypt($request['status']) ?: 'Pending';
    $tab_counts['all']++;
    if (isset($status_counts[$request_status])) {
        $status_counts[$request_status]++;
    }

    foreach ($tab_statuses as $tab_key => $tab_status) {
        if ($request_status === $tab_status) {
            $tab_counts[$tab_key]++;
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Dashboard | Document Requests
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body class="admin-dashboard-page sidebar-collapsed">
    <header class="admin-topbar">
        <div class="admin-topbar-inner">
            <a class="admin-brand" href="admin_dashboard.php" aria-label="FEU Roosevelt records dashboard">
                <span class="admin-brand-mark" aria-hidden="true">FR</span>
                <span class="admin-brand-copy">
                    <small>FEU ROOSEVELT</small>
                    <strong>Records Office</strong>
                </span>
            </a>
            <div class="admin-topbar-actions">
                <?php include("./helpers/notification_center.php"); ?>
                <a href="logout.php" class="admin-logout">Sign out</a>
            </div>
        </div>
    </header>

    <aside class="dashboard-sidebar admin-sidebar" id="dashboardSidebar" aria-label="Admin navigation">
        <div class="dashboard-sidebar-brand">
            <a class="admin-brand" href="admin_dashboard.php" aria-label="FEU Roosevelt records dashboard">
                <span class="admin-brand-mark" aria-hidden="true">FR</span>
                <span class="admin-brand-copy">
                    <small>FEU ROOSEVELT</small>
                    <strong>Records Office</strong>
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
                <a class="sidebar-link" href="#admin-page-title" title="Dashboard"><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 13a9 9 0 1 1 18 0"/><path d="M12 13l4-4M5 18h14M7 13h.01M17 13h.01"/></svg></span><span class="sidebar-link-text">Dashboard</span></a>
                <a class="sidebar-link" href="#request-queue-title" title="Request queue"><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM4 10h5l2 3h2l2-3h5M8 8h.01M11 8h.01"/></svg></span><span class="sidebar-link-text">Request queue</span></a>
                <a class="sidebar-link" href="#follow-ups-title" title="Follow-up messages"><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v12H9l-5 4zM8 9h8M8 13h5"/></svg></span><span class="sidebar-link-text">Follow-up messages</span></a>
            </nav>
        </div>
    </aside>

    <main class="admin-main container-fluid">
        <section class="admin-overview" aria-labelledby="admin-page-title">
            <div>
                <p class="admin-eyebrow">Records operations</p>
                <h1 id="admin-page-title">Request overview</h1>
                <p>Review requests, update their status, and coordinate claiming details.</p>
            </div>
            <time datetime="<?= date('Y-m-d') ?>"><?= date('l, F j, Y') ?></time>
        </section>

        <section class="admin-metrics" aria-label="Request totals">
            <div class="admin-metric">
                <span>Total requests</span>
                <strong><?= $tab_counts['all'] ?></strong>
            </div>
            <div class="admin-metric metric-pending">
                <span>Pending</span>
                <strong><?= $status_counts['Pending'] ?></strong>
            </div>
            <div class="admin-metric metric-processing">
                <span>In progress</span>
                <strong><?= $status_counts['Processing'] + $status_counts['Approved'] ?></strong>
            </div>
            <div class="admin-metric metric-ready">
                <span>Approved</span>
                <strong><?= $status_counts['Approved'] ?></strong>
            </div>
        </section>

        <section class="admin-follow-ups" aria-labelledby="follow-ups-title">
            <div class="admin-queue-heading">
                <div>
                    <p class="admin-eyebrow">Student messages</p>
                    <h2 id="follow-ups-title">Follow-up Messages</h2>
                </div>
                <span><?= count($follow_up_messages) ?> total</span>
            </div>
            <?php if ($follow_up_messages): ?>
                <div class="admin-follow-ups-table">
                    <table class="table admin-request-table">
                        <thead>
                            <tr>
                                <th>Received</th>
                                <th>Student</th>
                                <th>Reference No.</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($follow_up_messages as $follow_up): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('M j, Y g:i a', strtotime($follow_up['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($follow_up['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($follow_up['file_no'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="follow-up-message"><?= htmlspecialchars($follow_up['message'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="admin-follow-ups-empty">No follow-up messages have been sent yet.</p>
            <?php endif; ?>
        </section>

        <section class="admin-queue" aria-labelledby="request-queue-title">
            <div class="admin-queue-heading">
                <div>
                    <p class="admin-eyebrow">Manage submissions</p>
                    <h2 id="request-queue-title">Request queue</h2>
                </div>
                <span><?= $tab_counts['all'] ?> total</span>
            </div>

    <!-- =========================
         SEARCH FILTER
         ========================= -->

    <div class="admin-search">
        <form
            class="lookup-panel"
            method="GET"
            action="admin_dashboard.php"
        >
            <div class="lookup-row">
                <input
                    type="text"
                    name="search"
                    placeholder="Search requests..."
                    value="<?= htmlspecialchars($search) ?>"
                >
                <input type="hidden" name="tab" value="<?= htmlspecialchars($current_tab) ?>">

                <button type="submit">
                    SEARCH
                </button>
            </div>

            <?php if ($search !== ''): ?>
                <a
                    href="admin_dashboard.php?<?= http_build_query(['tab' => $current_tab]) ?>"
                    class="clear-search"
                >
                    Clear Search
                </a>
            <?php endif; ?>
        </form>
    </div>

    <nav class="request-tabs" aria-label="Filter document requests">
        <?php foreach ($tabs as $tab_key => $tab_label): ?>
            <?php
            $tab_url = ['tab' => $tab_key];
            if ($search !== '') {
                $tab_url['search'] = $search;
            }
            ?>
            <a
                class="request-tab<?= $current_tab === $tab_key ? ' active' : '' ?>"
                href="admin_dashboard.php?<?= htmlspecialchars(http_build_query($tab_url), ENT_QUOTES, 'UTF-8') ?>"
                <?= $current_tab === $tab_key ? 'aria-current="page"' : '' ?>
            ><?= htmlspecialchars($tab_label, ENT_QUOTES, 'UTF-8') ?> (<?= $tab_counts[$tab_key] ?>)</a>
        <?php endforeach; ?>
    </nav>


    <!-- =========================
         SUCCESS MESSAGE
         ========================= -->

    <?php if ($success_message): ?>

        <div class="success-alert">

            <?= htmlspecialchars($success_message) ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         REQUESTS
         ========================= -->

    <div class="table-wrap">
        <table class="table request-table admin-request-table">
            <thead>
                <tr>
                    <th>
                        Ref No.
                    </th>

                    <th>
                        Student No.
                    </th>

                    <th>
                        Student Name
                    </th>

                    <th>
                        Year Level
                    </th>

                    <th>
                        Program
                    </th>

                    <th>
                        File Type
                    </th>

                    <th>
                        Purpose
                    </th>

                    <th>
                        Claiming Area
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Print
                    </th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td
                            class="empty-requests"
                            colspan="10"
                        >
                            No document requests
                            submitted yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php

                    $displayed_requests = 0;
                    $tab_filtered_count = 0;

                    foreach ($requests as $request):
                        /* =========================
                           DECRYPT DATA
                           ========================= */

                        $file_no =
                            $so->decrypt(
                                $request['file_no']
                            );


                        $student_no =
                            $so->decrypt(
                                $request['student_no']
                            );


                        $first =
                            $so->decrypt(
                                $request['firstname']
                            );


                        $middle =
                            $so->decrypt(
                                $request['middlename']
                            ) ?: '';


                        $last =
                            $so->decrypt(
                                $request['lastname']
                            );


                        $full_name =
                            trim(
                                "$first $middle $last"
                            );


                        $year_level =
                            $so->decrypt(
                                $request['year_level']
                            );


                        $program =
                            $so->decrypt(
                                $request['program']
                            );


                        $doc_type =
                            $so->decrypt(
                                $request['doc_type']
                            );


                        $purpose =
                            $so->decrypt(
                                $request['purpose']
                            );


                        $claiming_area =
                            $so->decrypt(
                                $request['claiming_area'] ?? ''
                            ) ?: '';


                        $status =
                            $so->decrypt(
                                $request['status']
                            ) ?: 'Pending';

                        if (
                            $current_tab !== 'all' &&
                            $status !== $tab_statuses[$current_tab]
                        ) {
                            continue;
                        }
                        $tab_filtered_count++;


                        /* =========================
                           SEARCH FILTER
                           ========================= */

                        if ($search !== '') {

                            $search_text =
                                "$file_no
                                $student_no
                                $full_name
                                $year_level
                                $program
                                $doc_type
                                $purpose
                                $claiming_area
                                $status";


                            if (
                                stripos(
                                    $search_text,
                                    $search
                                ) === false
                            ) {

                                continue;
                            }
                        }


                        $displayed_requests++;

                        /* =========================
                           FORM ID
                           ========================= */

                        $form_id =
                            'request-update-' .
                            (int)$request['id'];

                    ?>

                        <tr>
                            <!-- REF NO. -->
                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $file_no
                                    ) ?>
                                </strong>
                                <time class="request-date" datetime="<?= htmlspecialchars($request['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(date('Y/m/d', strtotime($request['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                </time>
                            </td>

                            <!-- STUDENT NO. -->
                            <td>
                                <?= htmlspecialchars(
                                    $student_no
                                ) ?>
                            </td>

                            <!-- STUDENT NAME -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span><?= htmlspecialchars(
                                        $full_name
                                    ) ?></span>

                                    <?php
                                    // Get the student classification based on the year level
                                    $classif = getStudentClassification($year_level);

                                    if (!empty($classif)):
                                    ?>
                                        <span class="d-inline-flex align-items-center px-2 py-1 rounded-pill text-xs font-bold" 
                                        style="background-color: <?= $classif['bg_color'] ?>; border: 1px solid <?= $classif['border_color'] ?>; border-radius: 4px; font-size: 0.85rem;">
                                        <span class="rounded-circle me-1" style="width: 6px; height: 6px; background-color: <?= $classif['dot_color'] ?>; display: inline-block;"></span>
                                        <?= htmlspecialchars($classif['name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- YEAR LEVEL -->
                            <td>
                                <?= htmlspecialchars(
                                    $year_level
                                ) ?>
                            </td>

                            <!-- PROGRAM -->
                            <td>
                                <?= htmlspecialchars(
                                    $program
                                ) ?>
                            </td>


                            <!-- FILE TYPE -->
                            <td>
                                <?= htmlspecialchars(
                                    $doc_type
                                ) ?>
                            </td>

                            <!-- PURPOSE -->
                            <td>
                                <?= htmlspecialchars(
                                    $purpose
                                ) ?>
                            </td>

                            <!-- CLAIMING AREA -->
                            <td class="claiming-area-cell">
                                <input
                                    class="request-area"
                                    type="text"
                                    name="claiming_area"
                                    form="<?= $form_id ?>"
                                    placeholder="Claiming area"
                                    value="<?= htmlspecialchars(
                                        $claiming_area
                                    ) ?>"
                                    required
                                >
                            </td>

                            <!-- STATUS -->
                            <td>
                                <form
                                    class="request-form"
                                    id="<?= $form_id ?>"
                                    method="POST"
                                    action="admin_dashboard.php"
                                >
                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= (int)$request['id'] ?>"
                                    >
                                    <input type="hidden" name="tab" value="<?= htmlspecialchars($current_tab) ?>">
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                                    <select
                                        name="status"
                                        required
                                    >

                                        <?php

                                        $options = [
                                            'Pending',
                                            'Processing',
                                            'Approved',
                                            'Ready for Claiming',
                                            'Completed',
                                            'Rejected'
                                        ];


                                        foreach (
                                            $options
                                            as $opt
                                        ):
                                        ?>

                                            <option
                                                value="<?= htmlspecialchars($opt) ?>"
                                                <?= $status === $opt
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= htmlspecialchars(
                                                    $opt
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>


                                    <button
                                        type="submit"
                                        name="btnUpdate"
                                    >
                                        Save Update
                                    </button>
                                </form>
                            </td>

                            <td>
                                <button
                                    class="print-button admin-print-button"
                                    type="button"
                                    data-print-reference="<?= htmlspecialchars($file_no, ENT_QUOTES, 'UTF-8') ?>"
                                    data-print-student-no="<?= htmlspecialchars($student_no, ENT_QUOTES, 'UTF-8') ?>"
                                    data-print-name="<?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>"
                                    data-print-year-level="<?= htmlspecialchars($year_level, ENT_QUOTES, 'UTF-8') ?>"
                                    data-print-program="<?= htmlspecialchars($program, ENT_QUOTES, 'UTF-8') ?>"
                                    data-print-document="<?= htmlspecialchars($doc_type, ENT_QUOTES, 'UTF-8') ?>"
                                    data-print-purpose="<?= htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') ?>"
                                    data-print-claiming-area="<?= htmlspecialchars($claiming_area, ENT_QUOTES, 'UTF-8') ?>"
                                    data-print-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                    data-print-submitted="<?= htmlspecialchars(date('M j, Y', strtotime($request['created_at'])), ENT_QUOTES, 'UTF-8') ?>"
                                >Print</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($displayed_requests === 0): ?>
                        <tr>
                            <td
                                class="empty-requests"
                                colspan="10"
                            >
                                <?= $search !== ''
                                    ? 'No requests match your search.'
                                    : 'No requests in this tab.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
        </section>
    </main>
    <section id="request-print-sheet" class="print-sheet" aria-label="Printable request summary">
        <header class="print-document-heading">
            <p>FEU ROOSEVELT</p>
            <h1>Document Request Summary</h1>
            <p>Records Office</p>
        </header>
        <h2 id="print-document-title"></h2>
        <dl class="print-detail-grid">
            <div><dt>Reference No.</dt><dd id="print-reference"></dd></div>
            <div><dt>Student Name</dt><dd id="print-name"></dd></div>
            <div><dt>Student No.</dt><dd id="print-student-no"></dd></div>
            <div><dt>Year Level</dt><dd id="print-year-level"></dd></div>
            <div><dt>Program</dt><dd id="print-program"></dd></div>
            <div><dt>Purpose</dt><dd id="print-purpose"></dd></div>
            <div><dt>Submitted</dt><dd id="print-submitted"></dd></div>
            <div><dt>Status</dt><dd id="print-status"></dd></div>
            <div><dt>Claiming Area</dt><dd id="print-claiming-area"></dd></div>
        </dl>
        <p class="print-disclaimer">This printout summarizes the request; it is not a copy of the requested record.</p>
    </section>
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

        const printFields = {
            reference: 'print-reference',
            studentNo: 'print-student-no',
            name: 'print-name',
            yearLevel: 'print-year-level',
            program: 'print-program',
            purpose: 'print-purpose',
            claimingArea: 'print-claiming-area',
            status: 'print-status',
            submitted: 'print-submitted'
        };

        document.querySelectorAll('[data-print-reference]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('print-document-title').textContent = button.dataset.printDocument;
                Object.entries(printFields).forEach(([dataKey, elementId]) => {
                    const value = button.dataset[`print${dataKey.charAt(0).toUpperCase()}${dataKey.slice(1)}`];
                    document.getElementById(elementId).textContent = value || 'Not provided';
                });
                document.body.classList.add('printing-request');
                window.print();
            });
        });

        window.addEventListener('afterprint', () => {
            document.body.classList.remove('printing-request');
        });
    </script>
</body>
</html>

<?php $con->close(); ?>