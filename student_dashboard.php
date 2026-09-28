
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Student Portal - FEU Roosevelt</title>

    <link rel="stylesheet" href="style.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f6f8;
        }

        .header {
            background: #006b3c;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .logout {
            color: white;
            text-decoration: none;
            background: #b91c1c;
            padding: 10px 16px;
            border-radius: 5px;
        }

        .dashboard {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        .cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .card {
            flex: 1;
            min-width: 230px;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .card a {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 18px;
            background: #006b3c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>

    <div class="header">
        <h2>FEU Roosevelt Student Portal</h2>

        <a href="logout.php" class="logout">
            Logout
        </a>
    </div>

    <div class="dashboard">

        <h2>
            Welcome,
            <?php echo htmlspecialchars($name); ?>!
        </h2>

        <p>
            Student Document Request and Tracking System
        </p>

        <div class="cards">

            <div class="card">
                <h3>Request Documents</h3>

                <p>
                    Submit a new request for your
                    academic documents and records.
                </p>

                <a href="request.php">
                    New Request
                </a>
            </div>

            <div class="card">
                <h3>Track Requests</h3>

                <p>
                    Check the current status of
                    your submitted document requests.
                </p>

                <a href="track.php">
                    View My Requests
                </a>
            </div>

        </div>

    </div>

</body>
</html>