<?php
require __DIR__ . '/auth_middleware.php';
require __DIR__ . '/db.php';

require_guest();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter a valid email and your password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                try {
                    $pdo->beginTransaction();
                    $rehash = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                    $rehash->execute([
                        ':password' => password_hash($password, PASSWORD_DEFAULT),
                        ':id'       => $user['id'],
                    ]);
                    $pdo->commit();
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }

            session_regenerate_id(true); // prevent session fixation
            $_SESSION['user_id']  = (int) $user['id'];
            $_SESSION['username'] = $user['username'];

            flash('success', 'Welcome back, ' . $user['username'] . '!');
            redirect('index.php');
        }

        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/partials/header.php';
?>
<section class="card auth-card">
    <h1>Log in</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= escape($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= escape($email) ?>"
               required autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               required autocomplete="current-password">

        <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <p class="muted center">No account yet? <a href="register.php">Register</a></p>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
