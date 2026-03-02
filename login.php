<?php
require_once "includes/config.php";
session_start();

$pageTitle = "Login";
include "includes/header.php";

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // take user input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $conn_app->prepare("SELECT id, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $db_pass = $row['password'];
            $user_id = $row['id'];
            $isValid = false;

            // verify the secure hash
            if (password_verify($password, $db_pass)) {
                $isValid = true;
            } 
            // If it's plain text, log them in and hash (for default admin123 password)
            elseif ($password === $db_pass) {
                $isValid = true;
                
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $upgrade = $conn_app->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upgrade->bind_param("si", $new_hash, $user_id);
                $upgrade->execute();
                $upgrade->close();
            }

            if ($isValid) {
                $_SESSION['user'] = $username;
                $_SESSION['role'] = $row['role'];
                header('Location: index.php');
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

  <p class="muted">Demo account: admin / admin123</p>
</div>

<?php include "includes/footer.php"; ?>
