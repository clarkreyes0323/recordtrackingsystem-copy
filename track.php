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
    <style>
        .tracker-shell { max-width: 1000px; margin: 0 auto; }
        .tracker-header { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 30px; }
        .tracker-header h1 { color: var(--feu-green); font-size: 1.8rem; margin: 0 0 5px; }
        .tracker-header p { color: #52635b; }
        .tracker-nav { display: flex; flex-wrap: wrap; gap: 10px; }
        .tracker-nav a { display: inline-block; padding: 10px 14px; border-radius: 5px; background: var(--feu-green); color: white; text-decoration: none; font-weight: 600; }
        .tracker-nav a:hover { background: var(--feu-green-dark); }
        .lookup-panel { margin-bottom: 28px; }
        .lookup-panel label { display: block; margin-bottom: 8px; font-weight: 600; }
        .lookup-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; }
        .lookup-row input { min-width: 0; width: 100%; padding: 12px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; }
        .lookup-row button { border: 0; border-radius: 5px; padding: 12px 18px; background: var(--feu-green); color: white; font-size: 1rem; font-weight: 700; cursor: pointer; }
        .lookup-row button:hover { background: var(--feu-green-dark); }
        .error-message { margin: -12px 0 24px; color: #b42318; font-weight: 600; }
        .result-panel { margin: 28px 0; padding: 22px; background: white; border-left: 5px solid var(--feu-gold); border-radius: 6px; box-shadow: 0 3px 10px rgba(18, 33, 25, .08); }
        .result-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px; }
        .result-heading h2 { margin: 0; border: 0; padding: 0; }
        .status-pill { display: inline-block; padding: 7px 11px; border-radius: 20px; background: #fff2cc; color: #594100; font-size: .9rem; font-weight: 700; }
        .detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .detail-item { padding-top: 12px; border-top: 1px solid #e5ebe7; overflow-wrap: anywhere; }
        .detail-item dt { margin-bottom: 4px; color: #52635b; font-size: .85rem; }
        .detail-item dd { margin: 0; font-weight: 600; }
        .history-section { margin-top: 36px; }
        .history-section h2 { margin-bottom: 16px; }
        .table-scroll { overflow-x: auto; background: white; border-radius: 6px; box-shadow: 0 3px 10px rgba(18, 33, 25, .06); }
        .request-table { width: 100%; border-collapse: collapse; text-align: left; }
        .request-table th, .request-table td { padding: 13px 15px; border-bottom: 1px solid #e5ebe7; vertical-align: middle; }
        .request-table th { background: #edf3ef; color: var(--feu-green-dark); font-size: .85rem; }
        .request-table tr:last-child td { border-bottom: 0; }
        .reference-button { border: 0; padding: 0; background: none; color: #005b9a; font: inherit; font-weight: 700; text-decoration: underline; cursor: pointer; overflow-wrap: anywhere; text-align: left; }
        .empty-state { padding: 20px; background: white; color: #52635b; border-radius: 6px; }
        @media (max-width: 640px) {
            .tracker-header { align-items: flex-start; flex-direction: column; }
            .lookup-row { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; gap: 12px; }
            .result-heading { flex-direction: column; }
            .request-table th, .request-table td { padding: 10px; }
        }
    </style>
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