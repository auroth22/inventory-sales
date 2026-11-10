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

// Open the popup right away after an error, a new account, or a ?tab=register link
$autoOpen = ($error !== '' || $success !== '' || isset($_GET['tab']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Inventory & Sales</title>
    <link rel="stylesheet" href="style.css">
    <script>
        // Apply the saved theme before the page paints (no flash). Default = dark.
        try { if (localStorage.getItem('theme') === 'light') document.documentElement.dataset.theme = 'light'; } catch (e) {}
    </script>
</head>
<body class="landing">

    <main class="hero">
        <h1>Track your stock and sales in one place.</h1>
        <p>It's free real estate.</p>
        <button type="button" id="open-auth">Let&rsquo;s get started</button>
    </main>

    <!-- Login / register popup -->
    <dialog id="auth-modal" class="auth-modal">
        <div class="auth-inner">
            <button type="button" class="close-x" id="close-auth" aria-label="Close">&times;</button>

            <div class="tabs">
                <button type="button" class="tab <?= $tab === 'login' ? 'active' : '' ?>" data-tab="login">Log In</button>
                <button type="button" class="tab <?= $tab === 'register' ? 'active' : '' ?>" data-tab="register">Register</button>
            </div>

            <!-- Log in -->
            <form method="POST" class="panel <?= $tab === 'login' ? 'active' : '' ?>" id="panel-login">
                <input type="hidden" name="form" value="login">
                <?php if ($error && $tab === 'login'): ?>
                    <p class="error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <?php if ($success): ?>
                    <p class="success"><?= htmlspecialchars($success) ?></p>
                <?php endif; ?>
                <input type="text" name="username" placeholder="Username" aria-label="Username" required
                       autocomplete="username"
                       value="<?= $tab === 'login' ? htmlspecialchars($old_username) : '' ?>">
                <input type="password" name="password" placeholder="Password" aria-label="Password" required
                       autocomplete="current-password">
                <button type="submit">Log In</button>
            </form>

            <!-- Register -->
            <form method="POST" class="panel <?= $tab === 'register' ? 'active' : '' ?>" id="panel-register">
                <input type="hidden" name="form" value="register">
                <?php if ($error && $tab === 'register'): ?>
                    <p class="error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <input type="text" name="username" placeholder="Choose a username" aria-label="Choose a username" required
                       autocomplete="username"
                       value="<?= $tab === 'register' ? htmlspecialchars($old_username) : '' ?>">
                <input type="password" name="password" placeholder="Password (min 6 characters)" aria-label="Password" required
                       autocomplete="new-password">
                <input type="password" name="confirm" placeholder="Confirm password" aria-label="Confirm password" required
                       autocomplete="new-password">
                <button type="submit">Create account</button>
            </form>
        </div>
    </dialog>

    <button type="button" class="theme-toggle" id="theme-toggle"></button>

    <script>
        const modal  = document.getElementById('auth-modal');
        const tabs   = document.querySelectorAll('.tab');
        const panels = { login: document.getElementById('panel-login'), register: document.getElementById('panel-register') };

        function showTab(name) {
            tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
            Object.keys(panels).forEach(k => panels[k].classList.toggle('active', k === name));
            const first = panels[name].querySelector('input[type="text"]');
            if (first) first.focus();
        }
        tabs.forEach(t => t.addEventListener('click', () => showTab(t.dataset.tab)));

        document.getElementById('open-auth').addEventListener('click', () => { modal.showModal(); showTab('login'); });
        document.getElementById('close-auth').addEventListener('click', () => modal.close());
        // Clicking the dark area outside the box closes it
        modal.addEventListener('click', e => { if (e.target === modal) modal.close(); });

        <?php if ($autoOpen): ?>
        modal.showModal();
        <?php endif; ?>

        // Light / dark switch (remembered)
        const root = document.documentElement;
        const btn  = document.getElementById('theme-toggle');
        function paint() {
            btn.textContent = root.dataset.theme === 'light' ? '\u{1F319} Dark mode' : '\u2600\uFE0F Light mode';
        }
        btn.addEventListener('click', () => {
            const next = root.dataset.theme === 'light' ? 'dark' : 'light';
            root.dataset.theme = next;
            try { localStorage.setItem('theme', next); } catch (e) {}
            paint();
        });
        paint();
    </script>
</body>
</html>