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
            "SELECT user_id, email, file_no, status, claiming_area
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

            if ($old_status !== $status) {
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

<body class="admin-dashboard-page">
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
                </tr>
            </thead>

            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td
                            class="empty-requests"
                            colspan="8"
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
                                <?= htmlspecialchars(
                                    $full_name
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
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($displayed_requests === 0): ?>
                        <tr>
                            <td
                                class="empty-requests"
                                colspan="8"
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
</body>
</html>

<?php $con->close(); ?>