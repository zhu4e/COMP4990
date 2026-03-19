<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();

// Check the role 
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied: You must be an administrator to view this page.");
}

$pageTitle = "User Management";
include "includes/header.php";

$message = "";
$error = "";

// Adding users
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_user'])) {
    $new_user = trim($_POST['new_username'] ?? '');
    
    // ✅ FIX: hash password
    $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    
    $new_role = $_POST['new_role'] ?? 'user';

    if (!empty($new_user) && !empty($_POST['new_password'])) {
        $stmt = $conn_app->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $new_user, $new_pass, $new_role);
        
        if ($stmt->execute()) {
            $message = "User '$new_user' added successfully as an $new_role!";
        } else {
            $error = "Error adding user. That username might already exist.";
        }
        $stmt->close();
    } else {
        $error = "Please provide both a username and a password.";
    }
}

// Deleting users
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_user'])) {
    $delete_id = (int)$_POST['delete_id'];
    $delete_username = $_POST['delete_username'];
    
    // ✅ FIX: correct session variable
    if ($delete_username === $_SESSION['username']) { 
        $error = "Action denied: You cannot delete your own account while logged in!";
    } else {
        $stmt = $conn_app->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $message = "User '$delete_username' deleted successfully!";
        } else {
            $error = "Error deleting user.";
        }
        $stmt->close();
    }
}

// Fetch all users
$result = $conn_app->query("SELECT id, username, role FROM users ORDER BY id ASC");
?>

<div class="page database-page">
  <div class="topbar">
    <div class="topbar-left">
      <h1>User Management</h1>
      <p class="muted">Add, view, or remove dashboard access.</p>
    </div>
    <div class="topbar-right">
      <a class="btn secondary" href="index.php">Back to Dashboard</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="grid-2 wide">
    
    <section class="panel">
      <div class="panel-head">
        <h2>Add New User</h2>
      </div>
      <div style="padding: 16px;">
        <?php if ($message): ?>
          <div class="alert" style="background: rgba(22,101,52,.1); color: #166534; border-color: rgba(22,101,52,.2); margin-bottom: 14px;">
            <strong>Success:</strong> <?= htmlspecialchars($message) ?>
          </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
          <div class="alert error" style="margin-bottom: 14px;">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
        
        <form method="POST">
          <div class="form-row">
              <input type="text" name="new_username" placeholder="New Username" required autocomplete="off">
              <input type="password" name="new_password" placeholder="Password" required autocomplete="new-password">
              
              <select name="new_role" class="sql-select" style="width: 100%; margin-top: 6px;">
                <option value="user">Standard User</option>
                <option value="analyst">Data Analyst</option>
                <option value="admin">Administrator</option>
              </select>

              <button type="submit" name="add_user" class="btn" style="margin-top: 10px;">Create User</button>
          </div>
        </form>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2>Current Users</h2>
        <span class="tag">Database: add_users</span>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Role</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['id']) ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              
              <!-- ✅ FIXED ROLE DISPLAY -->
              <td>
                <?php if ($row['role'] === 'admin'): ?>
                  <span class="tag" style="background: rgba(220,38,38,.1); color: #dc2626;">Admin</span>

                <?php elseif ($row['role'] === 'analyst'): ?>
                  <span class="tag" style="background: rgba(37,99,235,.1); color: #2563eb;">Analyst</span>

                <?php else: ?>
                  <span class="tag" style="background: rgba(15,23,42,.1); color: var(--text);">User</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($row['username'] !== $_SESSION['username']): ?>
                  <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to completely delete user: <?= htmlspecialchars($row['username']) ?>?');">
                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                    <input type="hidden" name="delete_username" value="<?= htmlspecialchars($row['username']) ?>">
                    <button type="submit" name="delete_user" class="btn secondary" style="padding: 6px 10px; font-size: 12px; color: #dc2626 !important; border-color: rgba(220,38,38,.25);">Delete</button>
                  </form>
                <?php else: ?>
                  <span class="muted" style="font-size: 12px; font-weight: bold;">(You)</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</div>

<?php include "includes/footer.php"; ?>
