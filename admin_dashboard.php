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

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnUpdate'])) {
    $request_id = $_POST['request_id'] ?? '';
	$status = trim(filter_input(INPUT_POST, 'status', FILTER_UNSAFE_RAW) ?? '');
	$claiming_area = trim(filter_input(INPUT_POST, 'claiming_area', FILTER_UNSAFE_RAW) ?? '');

	$allowed_statuses = ['Pending', 'Processing', 'Approved', 'Ready for Claiming', 'Completed', 'Rejected'];

    if ($request_id && in_array($status, $allowed_statuses) && $claiming_area !== '') {
		$enc_status = $so->encrypt($status);
		$enc_area = $so->encrypt($claiming_area);

		$stmt = $con->prepare("UPDATE document_requests SET status = ?, claiming_area = ? WHERE id = ?");
        $stmt->bind_param("ssi", $enc_status, $enc_area, $request_id);
        
        if ($stmt->execute()) {
            // Post-Redirect-Get pattern to prevent form resubmission on refresh
            $active_tab = $_GET['tab'] ?? 'all';
            header("Location: admin_dashboard.php?success=1");
            exit;
/* =========================
   SEARCH FILTER
   ========================= */

$search = trim($_GET['search'] ?? '');

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
                $redirect_url = 'admin_dashboard.php?success=1';
                if ($search !== '') {
                    $redirect_url .= '&search=' . urlencode($search);
                }
                header("Location: $redirect_url");
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

$current_tab = $_GET['tab'] ?? 'all';

// Fetch all document requests
$query = "SELECT * FROM document_requests ORDER BY id DESC";
/* =========================
   GET REQUESTS
   ========================= */

$query =
    "SELECT *
     FROM document_requests
     ORDER BY id DESC";

if ($result = $con->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $row['status'] = $so->decrypt($row['status']) ?: 'Pending';
        $requests[] = $row;
    }

    $result->free();
}

$con->close();

$filtered_requests = [];
foreach ($requests as $request) {
    if ($current_tab === 'all') {
        $filtered_requests[] = $request;
    } elseif ($current_tab === 'pending' && $request['status'] === 'Pending') {
        $filtered_requests[] = $request;
    } elseif ($current_tab === 'processing' && $request['status'] === 'Processing') {
        $filtered_requests[] = $request;
    } elseif ($current_tab === 'approved' && $request['status'] === 'Approved') {
        $filtered_requests[] = $request;
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


    <!-- Main stylesheet -->
    <link rel="stylesheet" href="style.css">

    <!-- Admin dashboard stylesheet -->
    <link
        rel="stylesheet"
        href="admin_dashboard.css"
    >

</head>

<body>
    <!-- =========================
         ADMIN DASHBOARD
         ========================= -->

    <div class="dashboard-header">
        <div>
            <h2>Admin Dashboard</h2>
            <p>Review submitted document requests and update their status and claiming area.</p>
        </div>
        <div class="dashboard-actions">
            <?php include("./helpers/notification_center.php"); ?>
            <a href="logout.php" class="logout">Log out</a>
        </div>
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

                <button type="submit">
                    SEARCH
                </button>
            </div>

            <?php if ($search !== ''): ?>
                <a
                    href="admin_dashboard.php"
                    class="clear-search"
                >
                    Clear Search
                </a>
            <?php endif; ?>
        </form>
    </div>


    <!-- =========================
         SUCCESS MESSAGE
         ========================= -->

    <?php if ($success_message): ?>

        <div class="success-alert">

            <?= htmlspecialchars($success_message) ?>

        </div>

    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="tab-container">
        <a href="admin_dashboard.php?tab=all" class="tab-btn <?= $current_tab === 'all' ? 'active' : '' ?>">All Requests</a>
        <a href="admin_dashboard.php?tab=pending" class="tab-btn <?= $current_tab === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="admin_dashboard.php?tab=processing" class="tab-btn <?= $current_tab === 'processing' ? 'active' : '' ?>">Processing</a>
        <a href="admin_dashboard.php?tab=approved" class="tab-btn <?= $current_tab === 'approved' ? 'active' : '' ?>">Approved</a>
    </div>

    <h3>Submitted Requests (<?= count($filtered_requests) ?>)</h3>
    

    <!-- =========================
         REQUESTS
         ========================= -->

    <h3>
        Submitted Requests
    </h3>

    <div class="table-wrap">
        <table class="request-table">
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
                <?php if (empty($filtered_requests)): ?>
                    <tr><td class="empty-requests" colspan="8">No document requests submitted yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($filtered_requests as $request): ?>
                        <?php
                            // Decrypt fields up front for cleaner HTML rendering
                            $status = $so->decrypt($request['status']) ?: 'Pending';
                            $claiming_area = $so->decrypt($request['claiming_area'] ?? '') ?: '';
                            $form_id = 'request-update-' . (int)$request['id'];
                            $first = $so->decrypt($request['firstname']);
                            $middle = $so->decrypt($request['middlename']) ?: '';
                            $last = $so->decrypt($request['lastname']);
                            $full_name = trim("$first $middle $last");
                        ?>
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
                            <td>
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
                                No requests match your search.
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