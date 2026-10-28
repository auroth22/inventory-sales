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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Inventory & Sales</title>
    <link rel="stylesheet" href="style.css">
    <script>
        // Apply the saved theme before the page paints (no flash). Default = Fredbear.
        try { if (localStorage.getItem('landing-theme') === 'plain') document.documentElement.dataset.theme = 'plain'; } catch (e) {}
    </script>
</head>
<body class="landing">

    <!-- Top bar with login -->
    <header class="fb-header">
        <div class="fb-header-inner">
            <span class="fb-logo">inventory & sales</span>

            <form method="POST" class="fb-login">
                <input type="hidden" name="form" value="login">
                <div class="fb-field">
                    <label for="login-username">Username</label>
                    <input id="login-username" type="text" name="username" required
                           value="<?= $tab === 'login' ? htmlspecialchars($old_username) : '' ?>">
                </div>
                <div class="fb-field">
                    <label for="login-pass">Password</label>
                    <input id="login-pass" type="password" name="password" required>
                </div>
                <button type="submit" class="fb-login-btn">Log In</button>
            </form>
        </div>
    </header>

    <!-- Messages for login errors / "account created" -->
    <?php if ($error && $tab === 'login'): ?>
        <div class="flash error landing-flash"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="flash success landing-flash"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <main class="landing-body">
        <!-- Left: tagline -->
        <div class="hero">
            <h1>Track your stock and sales in one place.</h1>
            <p>It's free real estate.</p>
            <div class="eyes" aria-hidden="true"><i></i><i></i></div>
        </div>

        <!-- Right: sign-up window -->
        <section class="signup-card">
            <h2>Sign Up</h2>
            <p class="signup-sub">It's quick and easy as 9/11.</p>

            <?php if ($error && $tab === 'register'): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" class="panel">
                <input type="hidden" name="form" value="register">
                <input type="text" name="username" placeholder="Choose a username" required
                       <?= $tab === 'register' ? 'autofocus' : '' ?>
                       value="<?= $tab === 'register' ? htmlspecialchars($old_username) : '' ?>">
                <input type="password" name="password" placeholder="Password (min 6 characters)" required>
                <input type="password" name="confirm" placeholder="Confirm password" required>
                <button type="submit">Sign Up</button>
            </form>
        </section>
    </main>

    <button type="button" class="theme-toggle" id="theme-toggle"></button>

    <script>
        const root = document.documentElement;
        const btn  = document.getElementById('theme-toggle');

        function paint() {
            const plain = root.dataset.theme === 'plain';
            btn.textContent = plain ? 'Theme: Plain \u00b7 switch to Fredbear' : 'Theme: Fredbear \u00b7 switch to Plain';
            btn.setAttribute('aria-label', btn.textContent);
        }
        btn.addEventListener('click', () => {
            const next = root.dataset.theme === 'plain' ? 'fredbear' : 'plain';
            root.dataset.theme = next;
            try { localStorage.setItem('landing-theme', next); } catch (e) {}
            paint();
        });
        paint();
    </script>
</body>
</html>
