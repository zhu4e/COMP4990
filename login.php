<?php
// Show errors for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "includes/config.php";
session_start();

$pageTitle = "Login";

include "includes/header.php";

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // get user input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        // ✅ FIXED: removed is_active
        $stmt = $conn_app->prepare("SELECT id, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $db_pass = $row['password'];
            $user_id = $row['id'];
            $role = $row['role'];
            $isValid = false;

            // verify hashed password
            if (password_verify($password, $db_pass)) {
                $isValid = true;
            }
            // fallback for plaintext password (demo account)
            elseif ($password === $db_pass) {
                $isValid = true;

                // upgrade to hashed password
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $upgrade = $conn_app->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upgrade->bind_param("si", $new_hash, $user_id);
                $upgrade->execute();
                $upgrade->close();
            }

            if ($isValid) {
                // Save session
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;

                // Redirect based on role
                if ($role === 'admin') { header("Location: admin_dashboard.php"); } 
                elseif ($role === 'analyst') { header("Location: analyst_dashboard.php"); } 
                else { header("Location: user_dashboard.php"); }
                exit();
            } else {
                $error = "Invalid username or password!";
            }
        } else {
            $error = "Invalid username or password!";
        }

        $stmt->close();
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<div class="center-card login-card">

  <h1>Welcome to the E-Commerce Data Warehouse</h1>
  <p class="muted">Please sign in to access your dashboard.</p>

  <?php if (!empty($error)): ?>
    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <button class="btn" type="submit">Login</button>
  </form>

  <p class="muted">Demo accounts:</p>
  <ul class="muted">
    <li>Admin: admin / admin123</li>
    <li>Analyst: analyst / analyst123</li>
    <li>User: user / user123</li>
  </ul>
</div>

<?php include "includes/footer.php"; ?>
