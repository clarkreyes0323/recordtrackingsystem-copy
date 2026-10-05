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

$con = connection();
$so = new SystemOperators();

$success_message = '';
$requests = [];


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

    $request_id = $_POST['request_id'] ?? '';

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
        in_array($status, $allowed_statuses) &&
        $claiming_area !== ''
    ) {

        $enc_status = $so->encrypt($status);
        $enc_area = $so->encrypt($claiming_area);


        $stmt = $con->prepare(
            "UPDATE document_requests
             SET status = ?, claiming_area = ?
             WHERE id = ?"
        );


        $stmt->bind_param(
            "ssi",
            $enc_status,
            $enc_area,
            $request_id
        );


        if ($stmt->execute()) {

            header(
                "Location: admin_dashboard.php?success=1"
            );

            exit;
        }


        $stmt->close();
    }
}


/* =========================
   SUCCESS MESSAGE
   ========================= */

if (isset($_GET['success'])) {

    $success_message =
        'Request updated successfully.';
}


/* =========================
   GET REQUESTS
   ========================= */

$query =
    "SELECT *
     FROM document_requests
     ORDER BY id DESC";


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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Dashboard | Document Requests
    </title>


    <link
        rel="stylesheet"
        href="style.css"
    >


    <style>

        /* =========================
           SEARCH FILTER
           ========================= */

        .admin-search {

            background: #ffffff;

            border-top: 6px solid #00664f;

            border-radius: 10px;

            padding: 30px;

            margin: 25px 0 40px;

            box-shadow:
                0 4px 12px
                rgba(0, 0, 0, 0.08);
        }


        /* =========================
           REMOVE INNER FORM BOX
           ========================= */

        .admin-search form {

            background: transparent !important;

            border: none !important;

            border-top: none !important;

            border-radius: 0 !important;

            padding: 0 !important;

            margin: 0 !important;

            box-shadow: none !important;
        }


        /* =========================
           SEARCH INPUT
           ========================= */

        .admin-search input {

            width: 100%;

            height: 45px;

            box-sizing: border-box;

            padding: 10px 12px;

            margin-bottom: 15px;

            font-size: 15px;

            border: 1px solid #ccc;

            border-radius: 5px;

            background: #ffffff;
        }


        /* =========================
           SEARCH BUTTON
           ========================= */

        .admin-search button {

            width: 100%;

            height: 42px;

            border: none;

            border-radius: 5px;

            background: #00664f;

            color: #ffffff;

            font-weight: bold;

            cursor: pointer;
        }


        .admin-search button:hover {

            background: #00533f;
        }


        /* =========================
           CLEAR SEARCH
           ========================= */

        .clear-search {

            display: inline-block;

            margin-top: 10px;

            color: #00664f;

            text-decoration: none;
        }


        .clear-search:hover {

            text-decoration: underline;
        }

    </style>

</head>


<body>


    <!-- =========================
         ADMIN DASHBOARD
         ========================= -->

    <h2>
        Admin Dashboard
    </h2>


    <p>
        Review submitted document requests
        and update their status and claiming area.
    </p>


    <!-- =========================
         SEARCH FILTER
         ========================= -->

    <div class="admin-search">

        <form
            method="GET"
            action="admin_dashboard.php"
        >

            <input
                type="text"
                name="search"
                placeholder="Search requests..."
                value="<?= htmlspecialchars($search) ?>"
            >


            <button type="submit">
                SEARCH
            </button>


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


    <!-- =========================
         REQUESTS
         ========================= -->

    <h3>
        Submitted Requests
    </h3>


    <div class="table-wrap">

        <table
            class="request-table"
            border="1"
            cellpadding="8"
            cellspacing="0"
        >

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


                    <?php foreach ($requests as $request): ?>


                        <?php

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
                            )
                            ?: 'Pending';


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


                        /* =========================
                           FORM ID
                           ========================= */

                        $form_id =
                            'request-update-' .
                            (int)$request['id'];

                        ?>


                        <tr>


                            <!-- =====================
                                 REF NO.
                                 ===================== -->

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $file_no
                                    ) ?>

                                </strong>

                            </td>


                            <!-- =====================
                                 STUDENT NO.
                                 ===================== -->

                            <td>

                                <?= htmlspecialchars(
                                    $student_no
                                ) ?>

                            </td>


                            <!-- =====================
                                 STUDENT NAME
                                 ===================== -->

                            <td>

                                <?= htmlspecialchars(
                                    $full_name
                                ) ?>

                            </td>


                            <!-- =====================
                                 PROGRAM
                                 ===================== -->

                            <td>

                                <?= htmlspecialchars(
                                    $program
                                ) ?>

                            </td>


                            <!-- =====================
                                 FILE TYPE
                                 ===================== -->

                            <td>

                                <?= htmlspecialchars(
                                    $doc_type
                                ) ?>

                            </td>


                            <!-- =====================
                                 PURPOSE
                                 ===================== -->

                            <td>

                                <?= htmlspecialchars(
                                    $purpose
                                ) ?>

                            </td>


                            <!-- =====================
                                 CLAIMING AREA
                                 ===================== -->

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


                            <!-- =====================
                                 STATUS
                                 ===================== -->

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


                <?php endif; ?>


            </tbody>

        </table>

    </div>


</body>

</html>
