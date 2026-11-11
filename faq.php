<?php
session_start();

// Only authenticated students can access this page.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    header("Location: index.php");
    exit();
}

$name = $_SESSION['user_name'] ?? 'Student';
$userName = trim($name);
$firstName = preg_split('/\s+/', $userName)[0] ?? 'Student';

// Load the database connection.
require_once __DIR__ . '/connection/config.php';

// Load the notification functions.
require_once __DIR__ . '/helpers/NotificationService.php';

// Establish the database connection.
$con = connection();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Frequently Asked Questions - FEU Roosevelt</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="style.css?v=<?= file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : '1' ?>"
    >

    <style>
        .faq-main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 24px 60px;
        }

        .faq-hero {
            background: linear-gradient(135deg, #064e3b, #087443);
            color: #fff;
            padding: 40px;
            border-radius: 18px;
            margin-bottom: 32px;
        }

        .faq-eyebrow {
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 0.78rem;
            font-weight: 700;
            opacity: 0.8;
            margin-bottom: 10px;
        }

        .faq-hero h1 {
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            font-weight: 800;
            margin-bottom: 14px;
        }

        .faq-hero p {
            max-width: 700px;
            margin-bottom: 0;
            line-height: 1.7;
            color: #e4f5eb;
        }

        .faq-content {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.04);
        }

        .faq-section-title {
            font-size: 1.2rem;
            font-weight: 750;
            color: #14532d;
            margin-bottom: 18px;
        }

        .faq-accordion .accordion-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px !important;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .faq-accordion .accordion-button {
            font-weight: 650;
            color: #1f2937;
            padding: 18px 20px;
            background: #fff;
            box-shadow: none;
        }

        .faq-accordion .accordion-button:not(.collapsed) {
            color: #166534;
            background: #f0fdf4;
        }

        .faq-accordion .accordion-button:focus {
            box-shadow: 0 0 0 3px rgba(22, 101, 52, 0.15);
            border-color: transparent;
        }

        .faq-accordion .accordion-body {
            color: #4b5563;
            line-height: 1.8;
            padding: 18px 20px;
        }

        .faq-accordion .accordion-body a {
            color: #166534;
            font-weight: 650;
        }

        .faq-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .faq-status.pending {
            color: #92400e;
            background: #fef3c7;
        }

        .faq-status.processing {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .faq-status.approved {
            color: #166534;
            background: #dcfce7;
        }

        .faq-status.ready {
            color: #6b21a8;
            background: #f3e8ff;
        }

        .faq-status.completed {
            color: #374151;
            background: #e5e7eb;
        }

        .faq-status.rejected {
            color: #991b1b;
            background: #fee2e2;
        }

        .faq-help-card {
            margin-top: 26px;
            padding: 24px;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            border-radius: 12px;
        }

        .faq-help-card h2 {
            font-size: 1.15rem;
            font-weight: 750;
            color: #14532d;
        }

        .faq-help-card p {
            color: #4b5563;
            line-height: 1.7;
        }

        .faq-help-card .btn {
            font-weight: 650;
        }

        @media (max-width: 576px) {
            .faq-main {
                padding: 24px 14px 40px;
            }

            .faq-hero {
                padding: 26px 22px;
            }

            .faq-content {
                padding: 18px 14px;
            }

            .faq-accordion .accordion-button {
                padding: 15px;
                font-size: 0.95rem;
            }

            .faq-accordion .accordion-body {
                padding: 15px;
            }
        }
    </style>
</head>

<body class="student-dashboard-page student-sidebar-page sidebar-collapsed">

    <!-- Student Portal Header -->
    <header class="student-topbar">
        <div class="student-topbar-inner">

            <a
                class="student-brand"
                href="student_dashboard.php"
                aria-label="FEU Roosevelt student portal home"
            >
                <span class="brand-mark" aria-hidden="true">FR</span>

                <span class="brand-copy">
                    <span>FEU ROOSEVELT</span>
                    <strong>Student Portal</strong>
                </span>
            </a>

            <nav class="student-topbar-actions" aria-label="Student navigation">

    <a class="student-nav-link" href="student_dashboard.php">
        Dashboard
    </a>

    <a class="student-nav-link" href="track.php">
        Track Requests
    </a>

    <a class="student-nav-link" href="request.php">
        New Request
    </a>

    <a class="student-nav-link" href="faq.php" aria-current="page">
        FAQs
    </a>

    <!-- Notification bell -->
    <?php include __DIR__ . '/helpers/notification_center.php'; ?>

    <a href="logout.php" class="logout">
        Sign out
    </a>

</nav>
        </div>
    </header>

    <!-- FAQ Main Content -->
    <main class="faq-main">

        <section class="faq-hero" aria-labelledby="faq-title">

            <p class="faq-eyebrow">Student support center</p>

            <h1 id="faq-title">
                Frequently Asked Questions
            </h1>

            <p>
                Have questions about your document requests?
                Find answers about submitting requests, tracking their
                progress, understanding request statuses, and claiming
                your documents through the FEU Roosevelt Student Portal.
            </p>

        </section>

        <!-- Document Request Questions -->
        <section class="faq-content mb-4"
                 aria-labelledby="request-faq-title">

            <h2 class="faq-section-title" id="request-faq-title">
                Document Requests
            </h2>

            <div class="accordion faq-accordion" id="requestFaq">

                <div class="accordion-item">
                    <h3 class="accordion-header" id="requestQ1">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#requestA1"
                                aria-expanded="false"
                                aria-controls="requestA1">
                            1. How do I request a document?
                        </button>
                    </h3>

                    <div id="requestA1"
                         class="accordion-collapse collapse"
                         aria-labelledby="requestQ1"
                         data-bs-parent="#requestFaq">

                        <div class="accordion-body">
                            Sign in to your student account and select
                            <strong>New Request</strong> in the top
                            navigation bar. Complete the required fields
                            and submit your document request.

                            You can also start a request from the
                            dashboard by selecting
                            <a href="request.php">Request a document</a>.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header" id="requestQ2">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#requestA2"
                                aria-expanded="false"
                                aria-controls="requestA2">
                            2. How can I check my submitted requests?
                        </button>
                    </h3>

                    <div id="requestA2"
                         class="accordion-collapse collapse"
                         aria-labelledby="requestQ2"
                         data-bs-parent="#requestFaq">

                        <div class="accordion-body">
                            Open the <strong>Track Requests</strong> page
                            from the top navigation bar. It is intended
                            to let you review your document requests and
                            their current statuses.

                            Your dashboard also displays your six most
                            recent requests, along with their reference
                            numbers, document types, submission dates,
                            and statuses.

                            <p class="mt-2 mb-0">
                                <a href="track.php">Go to Track Requests</a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header" id="requestQ3">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#requestA3"
                                aria-expanded="false"
                                aria-controls="requestA3">
                            3. Where can I find my request reference number?
                        </button>
                    </h3>

                    <div id="requestA3"
                         class="accordion-collapse collapse"
                         aria-labelledby="requestQ3"
                         data-bs-parent="#requestFaq">

                        <div class="accordion-body">
                            Look at the <strong>Reference</strong> column
                            in your dashboard's Recent Requests table or
                            open the tracking page if it displays your
                            request details.

                            Keep your reference number available when
                            following up on a request.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header" id="requestQ4">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#requestA4"
                                aria-expanded="false"
                                aria-controls="requestA4">
                            4. Can I submit more than one document request?
                        </button>
                    </h3>

                    <div id="requestA4"
                         class="accordion-collapse collapse"
                         aria-labelledby="requestQ4"
                         data-bs-parent="#requestFaq">

                        <div class="accordion-body">
                            You can use the New Request page to start
                            another request. Whether multiple requests
                            may be processed simultaneously, or whether
                            particular limits apply, depends on the
                            school's document-request procedures.
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Request Status Questions -->
        <section class="faq-content mb-4"
                 aria-labelledby="status-faq-title">

            <h2 class="faq-section-title" id="status-faq-title">
                Understanding Request Statuses
            </h2>

            <div class="accordion faq-accordion" id="statusFaq">

                <div class="accordion-item">
                    <h3 class="accordion-header" id="statusQ1">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#statusA1"
                                aria-expanded="false"
                                aria-controls="statusA1">
                            5. What does Pending mean?
                        </button>
                    </h3>

                    <div id="statusA1"
                         class="accordion-collapse collapse"
                         aria-labelledby="statusQ1"
                         data-bs-parent="#statusFaq">

                        <div class="accordion-body">
                            <span class="faq-status pending">Pending</span>
                            <p class="mb-0">
                                Your request has been recorded but has
                                not yet progressed to the next processing
                                stage. Check the tracking page for any
                                available updates.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header" id="statusQ2">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#statusA2"
                                aria-expanded="false"
                                aria-controls="statusA2">
                            6. What do Processing and Approved mean?
                        </button>
                    </h3>

                    <div id="statusA2"
                         class="accordion-collapse collapse"
                         aria-labelledby="statusQ2"
                         data-bs-parent="#statusFaq">

                        <div class="accordion-body">
                            <p>
                                <span class="faq-status processing">
                                    Processing
                                </span>
                            </p>
                            Your request is being processed by the
                            responsible office.

                            <p class="mt-3">
                                <span class="faq-status approved">
                                    Approved
                                </span>
                            </p>
                            Your request has been approved. This does
                            not necessarily mean the document is already
                            available for collection. Wait for the
                            status to change to Ready for Claiming or
                            follow the instructions provided by the
                            responsible office.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header" id="statusQ3">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#statusA3"
                                aria-expanded="false"
                                aria-controls="statusA3">
                            7. What does Ready for Claiming mean?
                        </button>
                    </h3>

                    <div id="statusA3"
                         class="accordion-collapse collapse"
                         aria-labelledby="statusQ3"
                         data-bs-parent="#statusFaq">

                        <div class="accordion-body">
                            <span class="faq-status ready">
                                Ready for Claiming
                            </span>

                            <p class="mt-2 mb-0">
                                Your requested document is marked as
                                ready for collection. Check the tracking
                                page for the designated claiming area
                                and any available instructions before
                                visiting the school.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header" id="statusQ4">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#statusA4"
                                aria-expanded="false"
                                aria-controls="statusA4">
                            8. What do Completed and Rejected mean?
                        </button>
                    </h3>

                    <div id="statusA4"
                         class="accordion-collapse collapse"
                         aria-labelledby="statusQ4"
                         data-bs-parent="#statusFaq">

                        <div class="accordion-body">
                            <p>
                                <span class="faq-status completed">
                                    Completed
                                </span>
                            </p>
                            Your request has been marked as completed
                            in the system.

                            <p class="mt-3">
                                <span class="faq-status rejected">
                                    Rejected
                                </span>
                            </p>
                            Your request has been declined. Check for
                            any explanation or instructions available
                            through the tracking page, or contact the
                            responsible school office for clarification.
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Tracking and Notifications -->
        <section class="faq-content mb-4"
                 aria-labelledby="tracking-faq-title">

            <h2 class="faq-section-title" id="tracking-faq-title">
                Tracking and Notifications
            </h2>

            <div class="accordion faq-accordion" id="trackingFaq">

                <div class="accordion-item">
                    <h3 class="accordion-header" id="trackingQ1">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#trackingA1"
                                aria-expanded="false"
                                aria-controls="trackingA1">
                            9. Will I receive a notification when my status changes?
                        </button>
                    </h3>

                    <div id="trackingA1"
                         class="accordion-collapse collapse"
                         aria-labelledby="trackingQ1"
                         data-bs-parent="#trackingFaq">

                        <div class="accordion-body">
                            The student portal includes a notification
                            bell intended to display notices about
                            document-request status changes and claiming
                            area updates.

                            Open the bell menu to review available
                            notifications. Notifications depend on the
                            notification system being configured and
                            the relevant updates being recorded.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header" id="trackingQ2">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#trackingA2"
                                aria-expanded="false"
                                aria-controls="trackingA2">
                            10. What should I do if my request has not been updated?
                        </button>
                    </h3>

                    <div id="trackingA2"
                         class="accordion-collapse collapse"
                         aria-labelledby="trackingQ2"
                         data-bs-parent="#trackingFaq">

                        <div class="accordion-body">
                            Check the tracking page and notification
                            bell first. If the status remains unchanged,
                            contact the office responsible for processing
                            your document request.

                            Provide your request reference number and
                            document type so the office can identify
                            the request more easily.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h3 class="accordion-header" id="trackingQ3">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#trackingA3"
                                aria-expanded="false"
                                aria-controls="trackingA3">
                            11. Where can I find the claiming area?
                        </button>
                    </h3>

                    <div id="trackingA3"
                         class="accordion-collapse collapse"
                         aria-labelledby="trackingQ3"
                         data-bs-parent="#trackingFaq">

                        <div class="accordion-body">
                            Open Track Requests and review the details
                            of your request. If the claiming area has
                            been provided by the administrator, it
                            should appear with the request information.

                            If no location is displayed, contact the
                            responsible school office before attempting
                            to collect your document.
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Help Section -->
        <section class="faq-help-card"
                 aria-labelledby="faq-help-title">

            <h2 id="faq-help-title">
                Still have questions?
            </h2>

            <p>
                If your question is not answered here, review your
                request details or contact the appropriate FEU Roosevelt
                office for assistance with your document request.
            </p>

            <div class="d-flex flex-wrap gap-2">
                <a href="track.php" class="btn btn-success">
                    Track My Requests
                </a>

                <a href="request.php"
                   class="btn btn-outline-success">
                    Submit a Request
                </a>

                <a href="student_dashboard.php"
                   class="btn btn-outline-secondary">
                    Back to Dashboard
                </a>
            </div>

        </section>

    </main>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js">
    </script>

</body>
</html>
