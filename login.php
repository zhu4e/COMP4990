<?php
require_once "includes/config.php";
session_start();

$pageTitle = "Login";
include "includes/header.php";

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Example account: admin/admin123
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['user'] = $username;
        header('Location: index.php');
        exit();
    } else {
        $error = "Invalid username or password!";
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
