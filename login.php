<?php
require 'includes/auth.php';
require 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = isset($_GET['registered']) ? "Account created! You can log in now." : "";
$tab = (($_GET['tab'] ?? '') === 'register') ? 'register' : 'login';  // which tab opens first
$old_username = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form     = $_POST['form'] ?? 'login';      // hidden field tells us which form was sent
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $old_username = $username;                  // keep what they typed if there's an error

    if ($form === 'login') {
        $tab = 'login';
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['role']     = $user['role'];
            header("Location: index.php");
            exit;
        }
        $error = "Invalid username or password.";

    } else {
        $tab = 'register';
        $confirm = $_POST['confirm'] ?? '';

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $error = "Username: 3-30 characters, letters, numbers, or underscore only.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // Role is always 'staff'. Never take the role from the form.
                $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'staff')");
                $stmt->bind_param("ss", $username, $hash);
                $stmt->execute();
                header("Location: login.php?registered=1");
                exit;
            } catch (mysqli_sql_exception $e) {
                $error = ($e->getCode() == 1062) ? "That username is already taken." : "Something went wrong. Try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory and Sales</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="landing">

    <!-- The page behind the popup -->
    <div class="hero">
        <h1>Inventory &amp; Sales</h1>
        <p>Track your stock and sales in one place.</p>
        <button type="button" id="open-auth">Sign in</button>
    </div>

    <!-- The popup. <dialog> is a built-in HTML element. -->
    <dialog id="auth-modal" class="auth-modal">
        <div class="auth-inner">
            <button type="button" class="close-x" id="close-auth" aria-label="Close">&times;</button>

            <div class="tabs">
                <button type="button" class="tab" data-tab="login">Log in</button>
                <button type="button" class="tab" data-tab="register">Register</button>
            </div>

            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <?php if ($success): ?><p class="success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

            <form method="POST" id="form-login" class="panel">
                <input type="hidden" name="form" value="login">
                <input type="text" name="username" placeholder="Username" required
                       value="<?= $tab === 'login' ? htmlspecialchars($old_username) : '' ?>">
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Log in</button>
            </form>

            <form method="POST" id="form-register" class="panel">
                <input type="hidden" name="form" value="register">
                <input type="text" name="username" placeholder="Choose a username" required
                       value="<?= $tab === 'register' ? htmlspecialchars($old_username) : '' ?>">
                <input type="password" name="password" placeholder="Password (min 6 characters)" required>
                <input type="password" name="confirm" placeholder="Confirm password" required>
                <button type="submit">Create account</button>
            </form>
        </div>
    </dialog>

    <script>
        const modal  = document.getElementById('auth-modal');
        const tabs   = document.querySelectorAll('.tab');
        const panels = document.querySelectorAll('.panel');

        // Show one tab and hide the other
        function showTab(name) {
            tabs.forEach(t   => t.classList.toggle('active', t.dataset.tab === name));
            panels.forEach(p => p.classList.toggle('active', p.id === 'form-' + name));
        }

        tabs.forEach(t => t.addEventListener('click', () => showTab(t.dataset.tab)));

        // Open and close buttons
        document.getElementById('open-auth').addEventListener('click', () => modal.showModal());
        document.getElementById('close-auth').addEventListener('click', () => modal.close());

        // Click on the dark area outside the box to close
        modal.addEventListener('click', e => { if (e.target === modal) modal.close(); });

        // Open on page load, on the right tab (PHP fills in the value)
        showTab('<?= $tab ?>');
        modal.showModal();
    </script>
</body>
</html>
