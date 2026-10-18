<?php
ob_start();
session_start();

// Student authentication
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    header("Location: index.php");
    exit();
}

include("./connection/config.php");
include("./helpers/SystemOperators.php");
require_once("./helpers/NotificationService.php");

$con = connection();
$so = new SystemOperators();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnRegister'])) {$raw_file_no = "DOC-" . date("Ymd") . "-" . strtoupper($so->randomStringGenerator(8));$file_no     = $so->encrypt($raw_file_no);

    $user_id = (int)$_SESSION['user_id'];

    $student_no  =$so->encrypt(trim(filter_input(INPUT_POST, 'student_no', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $fname       =$so->encrypt(trim(filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $lname       =$so->encrypt(trim(filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $mname       =$so->encrypt(trim(filter_input(INPUT_POST, 'middlename', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $year_level  =$so->encrypt(trim(filter_input(INPUT_POST, 'year_level', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $program     =$so->encrypt(trim(filter_input(INPUT_POST, 'program', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $email       =$so->encrypt(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? ''));
    $doc_type    =$so->encrypt(trim(filter_input(INPUT_POST, 'doc_type', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $purpose     =$so->encrypt(trim(filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    
    $status        =$so->encrypt('Pending');
    $claiming_area =$so->encrypt('Pending Registrar Assignment');

    $insert_query = "INSERT INTO `document_requests`
                    (`user_id`, `file_no`, `student_no`, `firstname`, `lastname`, `middlename`, `year_level`, `program`, `email`, `doc_type`, `purpose`, `claiming_area`, `status`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $insert_stmt = $con->prepare($insert_query);
    $insert_stmt->bind_param('issssssssssss', $user_id, $file_no, $student_no, $fname, $lname, $mname, $year_level, $program, $email, $doc_type, $purpose, $claiming_area, $status);
    
    try {
        $con->begin_transaction();
        if ($insert_stmt->execute()) {
            $request_id = (int)$con->insert_id;
            $admin_stmt = $con->prepare("SELECT id FROM users WHERE role = 'admin'");
            $admin_stmt->execute();
            $admin_result = $admin_stmt->get_result();
            while ($admin = $admin_result->fetch_assoc()) {
                addNotification(
                    $con,
                    (int)$admin['id'],
                    "New document request $raw_file_no was submitted.",
                    $request_id
                );
            }
            $admin_stmt->close();
            $con->commit();
            $insert_stmt->close();
            echo "<script> 
                    alert('Request submitted successfully!\\nYour Reference/File No. is: " . $raw_file_no . "\\nPlease save this number for tracking.');
                    window.location='track.php';
                  </script>";
            exit();
        }
        $con->rollback();
    } catch(mysqli_sql_exception $e) {
        $con->rollback();
        echo "<script>alert('Database Error: " . addslashes($e->getMessage()) . "');</script>";
    }
    
    $insert_stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Document Request Form</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="student-dashboard-page student-request-page sidebar-collapsed">
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
                <a class="sidebar-link" href="student_dashboard.php" title="Dashboard"><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 13a9 9 0 1 1 18 0"/><path d="M12 13l4-4M5 18h14M7 13h.01M17 13h.01"/></svg></span><span class="sidebar-link-text">Dashboard</span></a>
                <a class="sidebar-link" href="track.php" title="Track Requests"><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h4M10 12h6M10 16h6"/></svg></span><span class="sidebar-link-text">Track Requests</span></a>
                <a class="sidebar-link" href="request.php" title="New Request"><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 4h12v16H6zM9 8h6M9 12h3"/><path d="M17 15v6M14 18h6"/></svg></span><span class="sidebar-link-text">New Request</span></a>
            </nav>
        </div>
    </aside>

    <main class="request-page-main">
        <div class="nav-header">
            <h2>Student Document Request Form</h2>
        </div>

        <form action="request.php" method="post">
            <div class="form-fields">
                <div class="form-items">
                    <label>Student No.:</label>
                    <input type="text" name="student_no" required placeholder="2026-0001">
                </div>
                <div class="form-items">
                    <label>Last Name:</label>
                    <input type="text" name="lastname" required placeholder="Dela Cruz">
                </div>
                <div class="form-items">
                    <label>First Name:</label>
                    <input type="text" name="firstname" required placeholder="Juan">
                </div>
                <div class="form-items">
                    <label>Middle Name:</label>
                    <input type="text" name="middlename" placeholder="Santos / Leave blank if none">
                </div>
                <div class="form-items">
                    <label>Year Level:</label>
                    <input type="text" name="year_level" placeholder="3rd Year, 4th Year, etc., N/A if not applicable">
                </div>
                <div class="form-items">
                    <label>Degree Program / Course:</label>
                    <input type="text" name="program" required placeholder="BSIT, BSN, BSBA, etc., N/A if not applicable">
                </div>
                <div class="form-items">
                    <label>Email:</label>
                    <input type="email" name="email" required placeholder="example@school.edu">
                </div>

                <hr>

                <div class="form-items">
                    <label>Record Requested Type:</label>
                    <select name="doc_type" required>
                        <option value="" disabled selected>Select document type</option>
                        <option value="Cert. of Enrollment">Cert. of Enrollment</option>
                        <option value="Cert. of Registration">Cert. of Registration</option>
                        <option value="Cert. of Grades">Cert. of Grades</option>
                        <option value="Cert. of Graduation">Cert. of Graduation</option>
                        <option value="Form 137 / Transcript">Form 137 / Transcript</option>
                        <option value="Diploma">Diploma</option>
                        <option value="Health Record">Health Record</option>
                    </select>
                </div>
                <div class="form-items">
                    <label>Purpose:</label>
                    <input type="text" name="purpose" required placeholder="Evaluation / Transfer">
                </div>

                <button type="submit" name="btnRegister">Submit Request</button>
            </div>
        </form>
    </main>

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
    </script>
    <?php $con->close(); ?>
</body>
</html>