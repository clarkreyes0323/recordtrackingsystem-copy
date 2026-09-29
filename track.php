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

$con = connection();$so = new SystemOperators();

$request_data = null;
$error_message = "";
$search_ref = '';
$my_requests = [];

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

if ($user && !empty($user['email'])) {
    $select_docs = "SELECT * FROM `document_requests` WHERE `email` = ? ORDER BY `id` DESC";
    $stmt = $con->prepare($select_docs);
    $stmt->bind_param("s", $user['email']);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $decrypted_file_no = $so->decrypt($row['file_no']);
        $decrypted_status = $so->decrypt($row['status']);
        $decrypted_doc_type = $so->decrypt($row['doc_type']);

        $my_requests[] = [
            'file_no' => $decrypted_file_no,
            'doc_type' => $decrypted_doc_type,
            'status' => $decrypted_status,
            'created_at' => $row['created_at']
        ];

        if ($search_ref !== '' && strtoupper($decrypted_file_no) === strtoupper($search_ref)) {
            $request_data = [
                'file_no'       => $decrypted_file_no,
                'student_no'    => $so->decrypt($row['student_no']),
                'name'          => $so->decrypt($row['firstname']) . ' ' . $so->decrypt($row['lastname']),
                'program'       => $so->decrypt($row['program']),
                'doc_type'      => $decrypted_doc_type,
                'purpose'       => $so->decrypt($row['purpose']),
                'claiming_area' => $so->decrypt($row['claiming_area']),
                'status'        => $decrypted_status
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
<body>
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

        <?php if ($request_data): ?>
            <section class="result-panel" aria-labelledby="result-title">
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
                    <div class="detail-item"><dt>Purpose</dt><dd><?php echo htmlspecialchars($request_data['purpose']); ?></dd></div>
                    <div class="detail-item"><dt>Claiming Area</dt><dd><?php echo htmlspecialchars($request_data['claiming_area']); ?></dd></div>
                </dl>
            </section>
        <?php endif; ?>

        <section class="history-section" aria-labelledby="history-title">
            <h2 id="history-title">Your Recent Requests</h2>
            <?php if ($my_requests): ?>
                <div class="table-scroll">
                    <table class="request-table">
                        <thead>
                            <tr><th>Reference No.</th><th>Document</th><th>Submitted</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_requests as $request): ?>
                                <tr>
                                    <td><button class="reference-button" type="button" data-reference="<?php echo htmlspecialchars($request['file_no']); ?>"><?php echo htmlspecialchars($request['file_no']); ?></button></td>
                                    <td><?php echo htmlspecialchars($request['doc_type']); ?></td>
                                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($request['created_at']))); ?></td>
                                    <td><?php echo htmlspecialchars($request['status']); ?></td>
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
    <script>
        document.querySelectorAll('[data-reference]').forEach((button) => {
            button.addEventListener('click', () => {
                const referenceInput = document.getElementById('file_no');
                referenceInput.value = button.dataset.reference;
                document.getElementById('lookup-form').requestSubmit();
            });
        });
    </script>
</body>
</html>