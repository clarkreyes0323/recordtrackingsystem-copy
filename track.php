<?php
include("./connection/config.php");
include("./helpers/SystemOperators.php");

$con = connection();$so = new SystemOperators();

$request_data = null;
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnTrack'])) {$search_ref = trim(filter_input(INPUT_POST, 'file_no', FILTER_SANITIZE_SPECIAL_CHARS));

    if (!empty($search_ref)) {$select_docs = "SELECT * FROM `document_requests` ORDER BY `id` DESC";
        $stmt =$con->prepare($select_docs);$stmt->execute();
        $result =$stmt->get_result();

        while ($row = $result->fetch_assoc()) {$decrypted_file_no = $so->decrypt($row['file_no']);
            
            if (strtoupper($decrypted_file_no) === strtoupper($search_ref)) {$request_data = [
                    'file_no'       => $decrypted_file_no,
                    'student_no'    => $so->decrypt($row['student_no']),
                    'name'          => $so->decrypt($row['firstname']) . ' ' . $so->decrypt($row['lastname']),
                    'program'       => $so->decrypt($row['program']),
                    'doc_type'      => $so->decrypt($row['doc_type']),
                    'purpose'       => $so->decrypt($row['purpose']),
                    'claiming_area' => $so->decrypt($row['claiming_area']),
                    'status'        => $so->decrypt($row['status'])
                ];
                break;
            }
        }
        $stmt->close();

        if (!$request_data) {$error_message = "No record found for Reference/File No.: " . htmlspecialchars($search_ref);
        }
    } else {
        $error_message = "Please enter your Reference/File No.";
    }
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
    <h2>Track Document Request</h2>
    <p><a href="request.php" class="back-link">← Back to Request Form</a></p>

    <form action="track.php" method="post">
        <div class="form-fields">
            <div class="form-items">
                <label>Enter Reference / File No.:</label>
                <input type="text" name="file_no" required placeholder="DOC-YYYYMMDD-XXXXXXXX" value="<?php echo isset($_POST['file_no']) ? htmlspecialchars($_POST['file_no']) : ''; ?>">
            </div>
            <button type="submit" name="btnTrack">Search Status</button>
        </div>
    </form>

    <br><hr><br>

    <?php if (!empty($error_message)): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <?php if ($request_data): ?>
        <h3>Request Details</h3>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Reference No.</th>
                <td><strong><?php echo htmlspecialchars($request_data['file_no']); ?></strong></td>
            </tr>
            <tr>
                <th>Student No.</th>
                <td><?php echo htmlspecialchars($request_data['student_no']); ?></td>
            </tr>
            <tr>
                <th>Student Name</th>
                <td><?php echo htmlspecialchars($request_data['name']); ?></td>
            </tr>
            <tr>
                <th>Program</th>
                <td><?php echo htmlspecialchars($request_data['program']); ?></td>
            </tr>
            <tr>
                <th>Document Requested</th>
                <td><?php echo htmlspecialchars($request_data['doc_type']); ?></td>
            </tr>
            <tr>
                <th>Purpose</th>
                <td><?php echo htmlspecialchars($request_data['purpose']); ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><strong><?php echo htmlspecialchars($request_data['status']); ?></strong></td>
            </tr>
            <tr>
                <th>Claiming Area</th>
                <td><strong><?php echo htmlspecialchars($request_data['claiming_area']); ?></strong></td>
            </tr>
        </table>
    <?php endif; ?>
</body>
</html>