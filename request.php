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

// Retrieve the one-time success message after redirecting from a submission.
$submitted_file_no = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['request_success_reference'])) {
    $submitted_file_no = $_SESSION['request_success_reference'];
    unset($_SESSION['request_success_reference']);
}

$year_levels = [
    'Nursery',
    'Kindergarten 1',
    'Kindergarten 2 (Preparatory)',
    'Grade 1',
    'Grade 2',
    'Grade 3',
    'Grade 4',
    'Grade 5',
    'Grade 6',
    'Grade 7',
    'Grade 8',
    'Grade 9',
    'Grade 10',
    'Grade 11',
    'Grade 12',
    '1st year (College)',
    '2nd year (College)',
    '3rd year (College)',
    '4th year (College)',
    'Alumni',
    'N/A'
];
$college_year_levels = [
    '1st year (College)',
    '2nd year (College)',
    '3rd year (College)',
    '4th year (College)',
    'Alumni'
];
$degree_programs = [
    'Bachelor of Science in Information Technology (BSIT)',
    'Bachelor of Multimedia Arts (BMMA)',
    'Bachelor of Science in Business Administration (BSBA)',
    'Bachelor of Science in Tourism Management (BSTM)',
    'Bachelor of Science in Hospitality Management (BSHM)'
];

// Process the form only when it was submitted using its registration button.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnRegister'])) {
    // Accept only a program valid for the selected year level.
    $year_level_value = trim(filter_input(INPUT_POST, 'year_level', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $program_value = trim(filter_input(INPUT_POST, 'program', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $allowed_programs = in_array($year_level_value, $college_year_levels, true)
        ? $degree_programs
        : ['N/A'];

    if (
        !in_array($year_level_value, $year_levels, true) ||
        !in_array($program_value, $allowed_programs, true)
    ) {
        http_response_code(400);
        exit('Please select a valid year level and its matching program or course.');
    }

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
    $year_level = $so->encrypt($year_level_value);
    $program = $so->encrypt($program_value);
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
            // Redirect after saving so refreshing the confirmation cannot resubmit the form.
            $_SESSION['request_success_reference'] = $raw_file_no;
            $con->close();
            header('Location: request.php');
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
<body class="student-dashboard-page student-sidebar-page student-request-page sidebar-collapsed">
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
            <nav class="student-topbar-actions" aria-label="Student navigation">
                <a class="student-nav-link" href="student_dashboard.php">Dashboard</a>
                <a class="student-nav-link" href="track.php">Track Requests</a>
                <a class="student-nav-link" href="request.php" aria-current="page">New Request</a>
                <a class="student-nav-link" href="faq.php">FAQs</a>
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
                    <select name="year_level" id="yearLevel" required>
                        <option value="" selected disabled>Select year level</option>
                        <?php foreach ($year_levels as $level): ?>
                            <option value="<?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-items">
                    <!-- Let the student select their degree program or course. -->
                    <label>Degree Program / Course:</label>
                    <select name="program" id="program" required disabled>
                        <option value="" selected>Select a year level first</option>
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

    <?php if ($submitted_file_no !== null): ?>
        <div class="request-success-overlay">
            <section
                class="request-success-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="request-success-title"
                aria-describedby="request-success-description"
            >
                <div class="request-success-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="m5 12 4 4L19 6"></path>
                    </svg>
                </div>
                <p class="request-success-eyebrow">Request received</p>
                <h2 id="request-success-title">Successfully submitted</h2>
                <p id="request-success-description">
                    Your document request has been submitted. Save your reference number to track its status.
                </p>
                <label class="request-reference-label" for="requestReference">Reference/File No.</label>
                <div class="request-reference-row">
                    <input
                        id="requestReference"
                        type="text"
                        value="<?= htmlspecialchars($submitted_file_no, ENT_QUOTES, 'UTF-8') ?>"
                        readonly
                    >
                    <button class="request-copy-button" id="copyRequestReference" type="button">Copy</button>
                </div>
                <p class="request-copy-feedback" id="copyRequestFeedback" aria-live="polite"></p>
                <div class="request-success-actions">
                    <a class="request-dashboard-button" href="student_dashboard.php">Back to dashboard</a>
                    <a class="request-track-button" href="track.php">Go to track request</a>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <!-- Enable sidebar collapse and filter navigation links from the search box. -->
    <script>
        const copyReferenceButton = document.getElementById('copyRequestReference');
        if (copyReferenceButton) {
            copyReferenceButton.addEventListener('click', async () => {
                const referenceInput = document.getElementById('requestReference');
                const feedback = document.getElementById('copyRequestFeedback');
                try {
                    await navigator.clipboard.writeText(referenceInput.value);
                    feedback.textContent = 'Reference number copied.';
                } catch (error) {
                    referenceInput.select();
                    const copied = document.execCommand('copy');
                    feedback.textContent = copied
                        ? 'Reference number copied.'
                        : 'Copy failed. Select and copy the reference number manually.';
                }
            });
        }

        const yearLevelSelect = document.getElementById('yearLevel');
        const programSelect = document.getElementById('program');
        const collegeYearLevels = <?= json_encode($college_year_levels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const degreePrograms = <?= json_encode($degree_programs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Keep course selection locked until a year level determines its choices.
        yearLevelSelect.addEventListener('change', () => {
            const isCollege = collegeYearLevels.includes(yearLevelSelect.value);
            const choices = isCollege ? degreePrograms : ['N/A'];

            programSelect.replaceChildren();
            if (isCollege) {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select degree program / course';
                placeholder.disabled = true;
                placeholder.selected = true;
                programSelect.appendChild(placeholder);
            }

            choices.forEach((program) => {
                const option = document.createElement('option');
                option.value = program;
                option.textContent = program;
                programSelect.appendChild(option);
            });

            programSelect.disabled = false;
        });

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