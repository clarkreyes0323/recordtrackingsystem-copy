<?php
// Buffer output so redirects and response scripts can be sent safely.
ob_start();
// Keep the signed-in student's identity available during this request.
session_start();

// Only authenticated student accounts may submit document requests.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    header("Location: index.php");
    exit();
}

// Load database access, encryption helpers, and notification handling.
include("./connection/config.php");
include("./helpers/SystemOperators.php");
require_once("./helpers/NotificationService.php");

// Open the database connection and create the helper used for encrypted data.
$con = connection();
$so = new SystemOperators();

// Process the form only when it was submitted using its registration button.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnRegister'])) {
    // Generate a human-readable tracking number and encrypt it for storage.
    $raw_file_no = "DOC-" . date("Ymd") . "-" . strtoupper($so->randomStringGenerator(8));
    $file_no = $so->encrypt($raw_file_no);

    // Associate this request with the student who is currently signed in.
    $user_id = (int)$_SESSION['user_id'];

    // Sanitize, trim, and encrypt each submitted value before saving it.
    $student_no = $so->encrypt(trim(filter_input(INPUT_POST, 'student_no', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $fname = $so->encrypt(trim(filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $lname = $so->encrypt(trim(filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $mname = $so->encrypt(trim(filter_input(INPUT_POST, 'middlename', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $year_level = $so->encrypt(trim(filter_input(INPUT_POST, 'year_level', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $program = $so->encrypt(trim(filter_input(INPUT_POST, 'program', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $email = $so->encrypt(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? ''));
    $doc_type = $so->encrypt(trim(filter_input(INPUT_POST, 'doc_type', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
    $purpose = $so->encrypt(trim(filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));

    // New requests start pending and await the registrar's claiming-area assignment.
    $status = $so->encrypt('Pending');
    $claiming_area = $so->encrypt('Pending Registrar Assignment');

    // Prepare a parameterized insert so form values are bound as data.
    $insert_query = "INSERT INTO `document_requests`
                    (`user_id`, `file_no`, `student_no`, `firstname`, `lastname`, `middlename`, `year_level`, `program`, `email`, `doc_type`, `purpose`, `claiming_area`, `status`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Bind the request data in the same order as the insert columns.
    $insert_stmt = $con->prepare($insert_query);
    $insert_stmt->bind_param('issssssssssss', $user_id, $file_no, $student_no, $fname, $lname, $mname, $year_level, $program, $email, $doc_type, $purpose, $claiming_area, $status);

    // Save the request and its notifications together as a single transaction.
    try {
        $con->begin_transaction();
        if ($insert_stmt->execute()) {
            // Use the inserted ID to associate notifications with this request.
            $request_id = (int)$con->insert_id;
            // Find every administrator who should be notified of the new request.
            $admin_stmt = $con->prepare("SELECT id FROM users WHERE role = 'admin'");
            $admin_stmt->execute();
            $admin_result = $admin_stmt->get_result();
            while ($admin = $admin_result->fetch_assoc()) {
                // Notify each administrator with the student's tracking number.
                addNotification(
                    $con,
                    (int)$admin['id'],
                    "New document request $raw_file_no was submitted.",
                    $request_id
                );
            }
            // Close the lookup and commit the saved request and notifications.
            $admin_stmt->close();
            $con->commit();
            $insert_stmt->close();
            // Show the tracking number and direct the student to request tracking.
            echo "<script> 
                    alert('Request submitted successfully!\\nYour Reference/File No. is: " . $raw_file_no . "\\nPlease save this number for tracking.');
                    window.location='track.php';
                  </script>";
            exit();
        }
        // Revert the transaction if the request could not be inserted.
        $con->rollback();
    } catch(mysqli_sql_exception $e) {
        // Revert partial work and report the database error to the student.
        $con->rollback();
        echo "<script>alert('Database Error: " . addslashes($e->getMessage()) . "');</script>";
    }

    // Release the prepared statement after processing the submission.
    $insert_stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Set document encoding and responsive viewport for the request page. -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Document Request Form</title>
    <!-- Load the shared styles and refresh the asset URL to avoid stale CSS. -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="student-dashboard-page student-request-page sidebar-collapsed">
    <!-- Provide the portal brand and account actions at the top of the page. -->
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

    <!-- Show navigation links and a control for collapsing the student sidebar. -->
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

    <!-- Main content contains the student document request form. -->
    <main class="request-page-main">
        <div class="nav-header">
            <h2>Student Document Request Form</h2>
        </div>

        <!-- Submit student details and document requirements to this page. -->
        <form action="request.php" method="post">
            <div class="form-fields">
                <div class="form-items">
                    <!-- Collect the student's school identification number. -->
                    <label>Student No.:</label>
                    <input type="text" name="student_no" required placeholder="2026-0001">
                </div>
                <div class="form-items">
                    <!-- Collect the student's legal last name. -->
                    <label>Last Name:</label>
                    <input type="text" name="lastname" required placeholder="Dela Cruz">
                </div>
                <div class="form-items">
                    <!-- Collect the student's first name. -->
                    <label>First Name:</label>
                    <input type="text" name="firstname" required placeholder="Juan">
                </div>
                <div class="form-items">
                    <!-- Collect the middle name when the student has one. -->
                    <label>Middle Name:</label>
                    <input type="text" name="middlename" placeholder="Santos / Leave blank if none">
                </div>
                <div class="form-items">
                    <!-- Let the student select their current year level. -->
                    <label>Year Level:</label>
                    <select name="year_level">
                        <option value="" selected>Select year level</option>
                        <option value="Nursery">Nursery</option>
                        <option value="Kindergarten 1">Kindergarten 1</option>
                        <option value="Kindergarten 2 (Preparatory)">Kindergarten 2 (Preparatory)</option>
                        <option value="Grade 1">Grade 1</option>
                        <option value="Grade 2">Grade 2</option>
                        <option value="Grade 3">Grade 3</option>
                        <option value="Grade 4">Grade 4</option>
                        <option value="Grade 5">Grade 5</option>
                        <option value="Grade 6">Grade 6</option>
                        <option value="Grade 7">Grade 7</option>
                        <option value="Grade 8">Grade 8</option>
                        <option value="Grade 9">Grade 9</option>
                        <option value="Grade 10">Grade 10</option>
                        <option value="Grade 11">Grade 11</option>
                        <option value="Grade 12">Grade 12</option>
                        <option value="1st year (College)">1st year (College)</option>
                        <option value="2nd year (College)">2nd year (College)</option>
                        <option value="3rd year (College)">3rd year (College)</option>
                        <option value="4th year (College)">4th year (College)</option>
                        <option value="Alumni">Alumni</option>
                        <option value="N/A">N/A</option>
                    </select>
                </div>
                <div class="form-items">
                    <!-- Let the student select their degree program or course. -->
                    <label>Degree Program / Course:</label>
                    <select name="program" required>
                        <option value="" disabled selected>Select degree program / course</option>
                        <option value="Bachelor of Science in Information Technology (BSIT)">Bachelor of Science in Information Technology (BSIT)</option>
                        <option value="Bachelor of Multimedia Arts (BMMA)">Bachelor of Multimedia Arts (BMMA)</option>
                        <option value="Bachelor of Science in Business Administration (BSBA)">Bachelor of Science in Business Administration (BSBA)</option>
                        <option value="Bachelor of Science in Tourism Management (BSTM)">Bachelor of Science in Tourism Management (BSTM)</option>
                        <option value="Bachelor of Science in Hospitality Management (BSHM)">Bachelor of Science in Hospitality Management (BSHM)</option>
                    </select>
                </div>
                <div class="form-items">
                    <!-- Collect an email address for request-related communication. -->
                    <label>Email:</label>
                    <input type="email" name="email" required placeholder="example@school.edu">
                </div>

                <hr>

                <div class="form-items">
                    <!-- Let the student choose which record or document to request. -->
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
                        <option value="Good Moral Certificate">Good Moral Certificate</option>
                        <option value="Certificate of Transfer Credentials">Certificate of Transfer Credentials</option>
                        <option value="Certificate of Units Earned">Certificate of Units Earned</option>
                        <option value="Certificate of Honorable Dismissal">Certificate of Honorable Dismissal</option>
                        <option value="Certificate of Candidacy for Graduation">Certificate of Candidacy for Graduation</option>
                        <option value="Certified True Copy">Certified True Copy</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-items">
                    <!-- Record why the student needs the requested document. -->
                    <label>Purpose:</label>
                    <input type="text" name="purpose" required placeholder="Evaluation / Transfer">
                </div>

                <!-- Submit the completed request to the PHP processing block above. -->
                <button type="submit" name="btnRegister">Submit Request</button>
            </div>
        </form>
    </main>

    <!-- Enable sidebar collapse and filter navigation links from the search box. -->
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');

        // Toggle the sidebar and keep its accessible state labels in sync.
        sidebarToggle.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
            sidebarToggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
            sidebarToggle.innerHTML = isCollapsed ? '&rsaquo;' : '&lsaquo;';
        });

        // Hide navigation links that do not match the entered search text.
        document.getElementById('sidebarSearch').addEventListener('input', (event) => {
            const searchText = event.target.value.toLowerCase();
            document.querySelectorAll('#dashboardSidebar .sidebar-link').forEach((link) => {
                link.hidden = !link.textContent.toLowerCase().includes(searchText);
            });
        });
    </script>
    <!-- Close the database connection after the page has been rendered. -->
    <?php $con->close(); ?>
</body>
</html>