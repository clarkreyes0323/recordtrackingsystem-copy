
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Administrators only.");
}

include("./connection/config.php");
include("./helpers/SystemOperators.php");
require_once("./helpers/NotificationService.php");

$con = connection();
$so = new SystemOperators();

$requests = [];
$search = trim($_GET['search'] ?? $_POST['search'] ?? '');
$date_filter = trim($_GET['date_filter'] ?? $_POST['date_filter'] ?? '');

/* =========================
   STUDENT CLASSIFICATION
   ========================= */

function getStudentClassification($year_level) {
    $year_level = strtolower(trim($year_level));

    if (in_array($year_level, [
        'nursery',
        'kindergarten 1',
        'kindergarten 2',
        'kindergarten 2 (preparatory)'
    ], true)) {
        return [
            'name' => 'PRE',
            'bg_color' => '#e0f7fa',
            'border_color' => '#f2fd5a',
            'dot_color' => '#f5dc00',
            'text_color' => '#a37000',
            'full' => 'Pre-school'
        ];

    } elseif (in_array($year_level, [
        'grade 1', 'grade 2', 'grade 3',
        'grade 4', 'grade 5', 'grade 6'
    ], true)) {
        return [
            'name' => 'GS',
            'bg_color' => '#f1f8e9',
            'border_color' => '#33691e',
            'dot_color' => '#0f3600',
            'text_color' => '#33691e',
            'full' => 'Grade School'
        ];

    } elseif (in_array($year_level, [
        'grade 7', 'grade 8', 'grade 9', 'grade 10'
    ], true)) {
        return [
            'name' => 'JHS',
            'bg_color' => '#fff3e0',
            'border_color' => '#e65100',
            'dot_color' => '#702700',
            'text_color' => '#e65100',
            'full' => 'Junior High School'
        ];

    } elseif (in_array($year_level, [
        'grade 11', 'grade 12'
    ], true)) {
        return [
            'name' => 'SHS',
            'bg_color' => '#fce4ec',
            'border_color' => '#e9007c',
            'dot_color' => '#880e4f',
            'text_color' => '#880e4f',
            'full' => 'Senior High School'
        ];

    } elseif (in_array($year_level, [
        '1st year',
        '2nd year',
        '3rd year',
        '4th year',
        '1st year (college)',
        '2nd year (college)',
        '3rd year (college)',
        '4th year (college)'
    ], true)) {
        return [
            'name' => 'TED',
            'bg_color' => '#e8f5e9',
            'border_color' => '#a5a5a5',
            'dot_color' => '#3d3d3d',
            'text_color' => '#2e7d32',
            'full' => 'Tertiary Education'
        ];

    } elseif (in_array($year_level, ['alumni'], true)) {
        return [
            'name' => 'AM',
            'bg_color' => '#f5f5f5',
            'border_color' => '#b135e2',
            'dot_color' => '#420034',
            'text_color' => '#420034',
            'full' => 'Alumni'
        ];

    } else {
        return [
            'name' => 'N/A',
            'bg_color' => '#ffffff',
            'border_color' => '#030303',
            'dot_color' => '#0c0c0c',
            'text_color' => '#0c0c0c',
            'full' => 'Unknown'
        ];
    }
}

/* =========================
   GET ARCHIVED REQUESTS
   COMPLETED AND REJECTED
   ========================= */

$query = "SELECT * FROM document_requests ORDER BY id DESC";

if ($result = $con->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $status = $so->decrypt($row['status']) ?: 'Pending';

        if ($status === 'Completed' || $status === 'Rejected') {
            $requests[] = $row;
        }
    }

    $result->free();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Archive Dashboard | Document Requests</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">

    <style>
        /* =================================
           ARCHIVE SIDEBAR
           ================================= */

        body.admin-dashboard-page.archive-page {
            overflow-x: hidden;
        }

        body.admin-dashboard-page.archive-page .dashboard-sidebar {
            display: block !important;
            position: fixed !important;
            top: 80px !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 180px !important;
            height: calc(100vh - 80px) !important;
            z-index: 1000 !important;
            overflow-y: auto;
            background: #f8f8f8;
            border-right: 1px solid #e0e0e0;
        }

        body.admin-dashboard-page.archive-page .dashboard-sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 12px;
            min-height: 100px;
            border-bottom: 1px solid #e0e0e0;
        }

        body.admin-dashboard-page.archive-page .dashboard-sidebar-content {
            display: block !important;
            padding: 15px 10px;
        }

        body.admin-dashboard-page.archive-page .sidebar-search {
            display: block;
            margin-bottom: 18px;
        }

        body.admin-dashboard-page.archive-page .sidebar-search input {
            width: 100%;
            max-width: 100%;
        }

        body.admin-dashboard-page.archive-page .dashboard-sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        body.admin-dashboard-page.archive-page .sidebar-link {
            display: flex !important;
            align-items: center;
            gap: 12px;
            padding: 12px 10px;
            color: #668b82;
            text-decoration: none;
            border-radius: 8px;
        }

        body.admin-dashboard-page.archive-page .sidebar-link:hover,
        body.admin-dashboard-page.archive-page .sidebar-link[aria-current="page"] {
            background: #e5efeb;
            color: #245d4b;
        }

        body.admin-dashboard-page.archive-page .sidebar-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
        }

        body.admin-dashboard-page.archive-page .sidebar-icon svg {
            width: 25px;
            height: 25px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* Move archive content beside sidebar */

        body.admin-dashboard-page.archive-page .admin-main {
            margin-left: 180px !important;
            width: calc(100% - 180px) !important;
            max-width: none !important;
        }

        /* =================================
           COLLAPSED SIDEBAR
           ================================= */

        body.admin-dashboard-page.archive-page.sidebar-collapsed .dashboard-sidebar {
            width: 76px !important;
        }

        body.admin-dashboard-page.archive-page.sidebar-collapsed .dashboard-sidebar-brand .admin-brand-copy,
        body.admin-dashboard-page.archive-page.sidebar-collapsed .sidebar-label,
        body.admin-dashboard-page.archive-page.sidebar-collapsed .sidebar-search,
        body.admin-dashboard-page.archive-page.sidebar-collapsed .sidebar-link-text {
            display: none !important;
        }

        body.admin-dashboard-page.archive-page.sidebar-collapsed .dashboard-sidebar-brand {
            justify-content: center;
            padding: 15px 5px;
        }

        body.admin-dashboard-page.archive-page.sidebar-collapsed .sidebar-link {
            justify-content: center;
        }

        body.admin-dashboard-page.archive-page.sidebar-collapsed .admin-main {
            margin-left: 76px !important;
            width: calc(100% - 76px) !important;
        }

        /* =================================
           SMALL SCREENS
           ================================= */

        @media (max-width: 768px) {
            body.admin-dashboard-page.archive-page .dashboard-sidebar {
                width: 76px !important;
            }

            body.admin-dashboard-page.archive-page .dashboard-sidebar .admin-brand-copy,
            body.admin-dashboard-page.archive-page .sidebar-label,
            body.admin-dashboard-page.archive-page .sidebar-search,
            body.admin-dashboard-page.archive-page .sidebar-link-text {
                display: none !important;
            }

            body.admin-dashboard-page.archive-page .admin-main {
                margin-left: 76px !important;
                width: calc(100% - 76px) !important;
            }
        }
    </style>
</head>

<body class="admin-dashboard-page archive-page">

    <!-- TOP HEADER -->
    <header class="admin-topbar">

        <div class="admin-topbar-inner">

            <a class="admin-brand"
               href="admin_dashboard.php"
               aria-label="FEU Roosevelt records dashboard">

                <span class="admin-brand-mark" aria-hidden="true">
                    FR
                </span>

                <span class="admin-brand-copy">
                    <small>FEU ROOSEVELT</small>
                    <strong>Records Archive</strong>
                </span>

            </a>

            <div class="admin-topbar-actions">

                <nav class="admin-topbar-nav" aria-label="Admin navigation">

                    <a class="admin-nav-link" href="admin_dashboard.php">
                        Dashboard
                    </a>

                    <a class="admin-nav-link"
                       href="archive.php"
                       aria-current="page">
                        Archive
                    </a>

                </nav>

                <?php include("./helpers/notification_center.php"); ?>

                <a href="logout.php" class="admin-logout">
                    Sign out
                </a>

            </div>
        </div>
    </header>


    <!-- =================================
         LEFT SIDEBAR
         ================================= -->

    <aside class="dashboard-sidebar admin-sidebar"
           id="dashboardSidebar"
           aria-label="Admin navigation">

        <div class="dashboard-sidebar-brand">

            <a class="admin-brand"
               href="admin_dashboard.php"
               aria-label="FEU Roosevelt records dashboard">

                <span class="admin-brand-mark" aria-hidden="true">
                    FR
                </span>

                <span class="admin-brand-copy">
                    <small>FEU ROOSEVELT</small>
                    <strong>Records Office</strong>
                </span>

            </a>

            <button class="sidebar-toggle"
                    id="sidebarToggle"
                    type="button"
                    aria-controls="dashboardSidebar"
                    aria-expanded="true"
                    aria-label="Collapse sidebar">

                &lsaquo;

            </button>

        </div>


        <div class="dashboard-sidebar-content">

            <label class="sidebar-search">

                <span class="sidebar-search-icon" aria-hidden="true"></span>

                <input id="sidebarSearch"
                       type="search"
                       aria-label="Search navigation"
                       placeholder="Search">

            </label>


            <p class="sidebar-label">
                Main menu
            </p>


            <nav>

                <!-- DASHBOARD -->
                <a class="sidebar-link"
                   href="admin_dashboard.php"
                   title="Dashboard">

                    <span class="sidebar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M3 13a9 9 0 1 1 18 0"/>
                            <path d="M12 13l4-4M5 18h14M7 13h.01M17 13h.01"/>
                        </svg>
                    </span>

                    <span class="sidebar-link-text">
                        Dashboard
                    </span>

                </a>


                <!-- ARCHIVE -->
                <a class="sidebar-link"
                   href="archive.php"
                   title="Archive"
                   aria-current="page">

                    <span class="sidebar-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 5h16v14H4z"/>
                            <path d="M4 10h5l2 3h2l2-3h5"/>
                            <path d="M8 8h.01M11 8h.01"/>
                        </svg>
                    </span>

                    <span class="sidebar-link-text">
                        Archive
                    </span>

                </a>

            </nav>

        </div>

    </aside>


    <!-- =================================
         ARCHIVE MAIN CONTENT
         ================================= -->

    <main class="admin-main container-fluid">

        <section class="admin-overview"
                 aria-labelledby="admin-page-title">

            <div>

                <p class="admin-eyebrow">
                    Archived records
                </p>

                <h1 id="admin-page-title">
                    Completed & Rejected Queue
                </h1>

                <p>
                    Review historical records of finished or rejected document requests.
                </p>

            </div>

            <time datetime="<?= date('Y-m-d') ?>">
                <?= date('l, F j, Y') ?>
            </time>

        </section>


        <!-- ARCHIVE LIST -->

        <section class="admin-queue"
                 aria-labelledby="request-queue-title">

            <div class="admin-queue-heading">

                <div>

                    <p class="admin-eyebrow">
                        Historical Submissions
                    </p>

                    <h2 id="request-queue-title">
                        Archive list
                    </h2>

                </div>

                <span>
                    <?= count($requests) ?> total archived
                </span>

            </div>


            <!-- SEARCH AND DATE FILTER -->

            <div class="admin-search">

                <form class="lookup-panel"
                      method="GET"
                      action="archive.php">

                    <div class="lookup-row">

                        <input type="text"
                               name="search"
                               placeholder="Search archive..."
                               value="<?= htmlspecialchars($search) ?>">

                        <input type="date"
                               name="date_filter"
                               value="<?= htmlspecialchars($date_filter) ?>"
                               aria-label="Filter by date">

                        <button type="submit">
                            SEARCH
                        </button>

                    </div>

                    <?php if ($search !== '' || $date_filter !== ''): ?>

                        <a href="archive.php" class="clear-search">
                            Clear Search & Filters
                        </a>

                    <?php endif; ?>

                </form>

            </div>


            <!-- ARCHIVED RECORDS TABLE -->

            <div class="table-wrap">

                <table class="table request-table admin-request-table">

                    <thead>

                        <tr>
                            <th>Ref No.</th>
                            <th>Student No.</th>
                            <th>Student Name</th>
                            <th>Year Level</th>
                            <th>Program</th>
                            <th>File Type</th>
                            <th>Purpose</th>
                            <th>Claiming Area</th>
                            <th>Status</th>
                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($requests)): ?>

                            <tr>
                                <td class="empty-requests" colspan="9">
                                    No archived records found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php
                            $displayed_count = 0;

                            foreach ($requests as $request):

                                $file_no = $so->decrypt($request['file_no']);
                                $student_no = $so->decrypt($request['student_no']);
                                $first = $so->decrypt($request['firstname']);
                                $middle = $so->decrypt($request['middlename'] ?? '') ?: '';
                                $last = $so->decrypt($request['lastname']);

                                $full_name = trim("$first $middle $last");

                                $year_level = $so->decrypt($request['year_level']);
                                $program = $so->decrypt($request['program']);
                                $doc_type = $so->decrypt($request['doc_type']);
                                $purpose = $so->decrypt($request['purpose']);

                                $claiming_area = $so->decrypt(
                                    $request['claiming_area'] ?? ''
                                ) ?: 'N/A';

                                $status = $so->decrypt($request['status']);

                                /* SEARCH FILTER */

                                if ($search !== '') {

                                    $search_text = "$file_no $student_no $full_name $year_level $program $doc_type $purpose $claiming_area $status";

                                    if (stripos($search_text, $search) === false) {
                                        continue;
                                    }
                                }


                                /* DATE FILTER */

                                if ($date_filter !== '') {

                                    $request_date = date(
                                        'Y-m-d',
                                        strtotime($request['created_at'])
                                    );

                                    if ($request_date !== $date_filter) {
                                        continue;
                                    }
                                }


                                $displayed_count++;

                                $classif = getStudentClassification($year_level);

                            ?>

                                <tr>

                                    <!-- REFERENCE NUMBER -->

                                    <td>

                                        <strong>
                                            <?= htmlspecialchars($file_no) ?>
                                        </strong>

                                        <time class="request-date"
                                              datetime="<?= htmlspecialchars($request['created_at']) ?>">

                                            <?= htmlspecialchars(
                                                date(
                                                    'Y/m/d',
                                                    strtotime($request['created_at'])
                                                )
                                            ) ?>

                                        </time>

                                    </td>


                                    <!-- STUDENT NUMBER -->

                                    <td>
                                        <?= htmlspecialchars($student_no) ?>
                                    </td>


                                    <!-- STUDENT NAME -->

                                    <td>

                                        <div class="d-flex align-items-center gap-2">

                                            <span>
                                                <?= htmlspecialchars($full_name) ?>
                                            </span>

                                            <?php if (!empty($classif)): ?>

                                                <span class="d-inline-flex align-items-center px-2 py-1 rounded-pill text-xs font-bold"
                                                      style="
                                                        background-color: <?= $classif['bg_color'] ?>;
                                                        border: 1px solid <?= $classif['border_color'] ?>;
                                                        border-radius: 4px;
                                                        font-size: 0.85rem;
                                                      "
                                                      title="<?= htmlspecialchars($classif['full']) ?>">

                                                    <span class="rounded-circle me-1"
                                                          style="
                                                            width: 6px;
                                                            height: 6px;
                                                            background-color: <?= $classif['dot_color'] ?>;
                                                            display: inline-block;
                                                          ">
                                                    </span>

                                                    <?= htmlspecialchars($classif['name']) ?>

                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </td>


                                    <!-- YEAR LEVEL -->

                                    <td>
                                        <?= htmlspecialchars($year_level) ?>
                                    </td>


                                    <!-- PROGRAM -->

                                    <td>
                                        <?= htmlspecialchars($program) ?>
                                    </td>


                                    <!-- FILE TYPE -->

                                    <td>
                                        <?= htmlspecialchars($doc_type) ?>
                                    </td>


                                    <!-- PURPOSE -->

                                    <td>
                                        <?= htmlspecialchars($purpose) ?>
                                    </td>


                                    <!-- CLAIMING AREA -->

                                    <td>
                                        <?= htmlspecialchars($claiming_area) ?>
                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span class="badge <?= $status === 'Completed' ? 'bg-success' : 'bg-danger' ?>"
                                              style="font-size: 0.85rem; padding: 6px 12px;">

                                            <?= htmlspecialchars($status) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                            <?php if ($displayed_count === 0): ?>

                                <tr>
                                    <td class="empty-requests" colspan="9">
                                        No archived requests match your search.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>


    <!-- =================================
         SIDEBAR JAVASCRIPT
         ================================= -->

    <script>

        /* SIDEBAR COLLAPSE AND EXPAND */

        const sidebarToggle = document.getElementById('sidebarToggle');

        sidebarToggle.addEventListener('click', () => {

            document.body.classList.toggle('sidebar-collapsed');

            const isCollapsed =
                document.body.classList.contains('sidebar-collapsed');

            sidebarToggle.setAttribute(
                'aria-expanded',
                String(!isCollapsed)
            );

            sidebarToggle.setAttribute(
                'aria-label',
                isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'
            );

            sidebarToggle.innerHTML =
                isCollapsed ? '&rsaquo;' : '&lsaquo;';

        });


        /* SIDEBAR SEARCH */

        document.getElementById('sidebarSearch').addEventListener(
            'input',
            (event) => {

                const searchText = event.target.value.toLowerCase();

                document.querySelectorAll(
                    '#dashboardSidebar .sidebar-link'
                ).forEach((link) => {

                    link.hidden = !link.textContent
                        .toLowerCase()
                        .includes(searchText);

                });

            }
        );

    </script>

</body>
</html>

<?php
$con->close();
?>
