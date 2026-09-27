<?php
include("./connection/config.php");
include("./helpers/SystemOperators.php");

$con = connection();$so = new SystemOperators();
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
            header("Location: admin_dashboard.php?success=1");
            exit;
        }
        $stmt->close();
	}
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success_message = 'Request updated successfully.';
}

// Fetch all document requests
$query = "SELECT * FROM document_requests ORDER BY id DESC";
if ($result = $con->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}

$con->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Document Requests</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .table-wrap { overflow-x: auto; }
        .request-table { min-width: 950px; }
        .request-form { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
        .request-form select, .request-form input, .request-form button, .request-area { width: 100%; padding: 6px; }
        .success-alert { color: #155724; background-color: #d4edda; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>

    <h2>Admin Dashboard</h2>
    <p>Review submitted document requests and update their status and claiming area.</p>

    <?php if ($success_message): ?>
        <div class="success-alert"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <h3>Submitted Requests (<?= count($requests) ?>)</h3>
    
    <div class="table-wrap">
        <table class="request-table" border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Ref No.</th>
                    <th>Student No.</th>
                    <th>Student Name</th>
                    <th>Program</th>
                    <th>File Type</th>
                    <th>Purpose</th>
                    <th>Claiming Area</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="8" style="text-align: center;">No document requests submitted yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
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
                        <tr>
                            <td><strong><?= htmlspecialchars($so->decrypt($request['file_no'])) ?></strong></td>
                            <td><?= htmlspecialchars($so->decrypt($request['student_no'])) ?></td>
                            <td><?= htmlspecialchars($full_name) ?></td>
                            <td><?= htmlspecialchars($so->decrypt($request['program'])) ?></td>
                            <td><?= htmlspecialchars($so->decrypt($request['doc_type'])) ?></td>
                            <td><?= htmlspecialchars($so->decrypt($request['purpose'])) ?></td>
                            <td>
                                <input class="request-area" type="text" name="claiming_area" form="<?= $form_id ?>" placeholder="Claiming area" value="<?= htmlspecialchars($claiming_area) ?>" required>
                            </td>
                            <td>
                                <form class="request-form" id="<?= $form_id ?>" method="POST" action="admin_dashboard.php">
                                    <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                    <select name="status" required>
                                        <?php 
                                        $options = ['Pending', 'Processing', 'Approved', 'Ready for Claiming', 'Completed', 'Rejected'];
                                        foreach ($options as $opt): 
                                        ?>
                                            <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <button type="submit" name="btnUpdate">Save Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>