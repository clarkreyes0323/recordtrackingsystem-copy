<?php
ob_start(); // Prevents header redirection output blocks
session_start();

include("./connection/config.php");
include("./helpers/SystemOperators.php");

$con = connection();
$so  = new SystemOperators();

$error   = "";
$success = "";

// -------------------------------------------------------------
// 1. HANDLE LOGIN
// -------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['btnLogin'])) {
    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter both email and password.";
    } else {
        $query = "SELECT id, name, email, password, role FROM users";
        $stmt  = $con->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();

        $matched_user = null;

        while ($row = $result->fetch_assoc()) {
            $db_email = $so->decrypt($row['email'] ?? '') ?: $row['email'];

            if (strtolower(trim($db_email)) === strtolower($email)) {
                $matched_user = $row;
                break;
            }
        }
        $stmt->close();

        if ($matched_user && password_verify($password, $matched_user["password"])) {
            session_regenerate_id(true);

            // Decrypt & normalize role string
            $raw_role = $matched_user["role"] ?? 'student';
            $role     = strtolower(trim($so->decrypt($raw_role) ?: $raw_role));

            $_SESSION["user_id"]   = $matched_user["id"];
            $_SESSION["user_name"] = $matched_user["name"];
            $_SESSION["role"]      = $role;

            // Route based on role
            if ($role === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: request.php"); // Redirects student to request.php
            }
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    }
}

// -------------------------------------------------------------
// 2. HANDLE SIGNUP / REGISTRATION
// -------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['btnSignup'])) {
    $name     = trim(filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $error = "Please fill in all fields to sign up.";
    } else {
        // Check if email exists
        $stmt = $con->prepare("SELECT id, email FROM users");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $already_exists = false;
        while ($row = $result->fetch_assoc()) {
            $db_email = $so->decrypt($row['email'] ?? '') ?: $row['email'];
            if (strtolower(trim($db_email)) === strtolower($email)) {
                $already_exists = true;
                break;
            }
        }
        $stmt->close();

        if ($already_exists) {
            $error = "Email address is already registered.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $enc_email      = $so->encrypt($email);
            $role           = 'student';

            $insertStmt = $con->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("ssss", $name, $enc_email, $hashedPassword, $role);

            if ($insertStmt->execute()) {
                $success = "Account created successfully! You can now log in.";
            } else {
                $error = "Registration failed. Please try again.";
            }
            $insertStmt->close();
        }
    }
}

$con->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FEU Roosevelt - Login & Registration</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>

<body>

  <div class="container">

    <div class="form-container">

      <!-- LOGIN FORM -->
      <div class="form-box login">
        <div class="title">Login</div>

        <?php if (!empty($error)): ?>
            <p style="color: #dc2626; background: #fee2e2; padding: 8px; border-radius: 4px; font-size: 0.85rem; margin-bottom: 10px;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <p style="color: #166534; background: #dcfce7; padding: 8px; border-radius: 4px; font-size: 0.85rem; margin-bottom: 10px;"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <form action="index.php" method="POST">
          <div class="input-box">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>

          <div class="input-box">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" placeholder="Enter your password" required>
          </div>

          <button type="submit" name="btnLogin">Login</button>

          <p class="signup-text">
            Don't have an account?
            <a href="#" id="showSignup">Signup now</a>
          </p>
        </form>
      </div>

      <!-- SIGNUP FORM -->
      <div class="form-box signup" style="display: none;">
        <div class="title">Signup</div>

        <!-- Updated action to index.php -->
        <form action="index.php" method="POST">
          <div class="input-box">
            <i class="fa-solid fa-user"></i>
            <input type="text" name="fullname" placeholder="Enter your full name" required>
          </div>

          <div class="input-box">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" name="email" placeholder="Enter your email" required>
          </div>

          <div class="input-box">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" placeholder="Create a password" required>
          </div>

          <button type="submit" name="btnSignup">Signup</button>

          <p class="signup-text">
            Already have an account?
            <a href="#" id="showLogin">Login now</a>
          </p>
        </form>
      </div>

    </div>

    <div class="image-container">
      <div class="image-content"></div>
    </div>

  </div>

  <script>
    const loginForm = document.querySelector(".login");
    const signupForm = document.querySelector(".signup");

    document.getElementById("showSignup").addEventListener("click", function(e) {
      e.preventDefault();
      loginForm.style.display = "none";
      signupForm.style.display = "block";
    });

    document.getElementById("showLogin").addEventListener("click", function(e) {
      e.preventDefault();
      signupForm.style.display = "none";
      loginForm.style.display = "block";
    });
  </script>

</body>
</html>