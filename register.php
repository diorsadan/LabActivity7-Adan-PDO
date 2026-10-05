<?php
require __DIR__ . '/auth_middleware.php';
require __DIR__ . '/db.php';

require_guest();

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 50) {
        $errors[] = 'Username must be between 3 and 50 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors[] = 'Please enter a valid email address (max 100 characters).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Uniqueness check and insert run atomically; FOR UPDATE locks the email key.
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1 FOR UPDATE');
            $stmt->execute([':email' => $email]);

            if ($stmt->fetch()) {
                $pdo->rollBack();
                $errors[] = 'That email address is already registered.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO users (username, email, password) VALUES (:username, :email, :password)'
                );
                $insert->execute([
                    ':username' => $username,
                    ':email'    => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $pdo->commit();

                flash('success', 'Registration successful. You can now log in.');
                redirect('login.php');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // 23000 = integrity constraint violation (UNIQUE email as a final safeguard).
            if ($e->getCode() === '23000') {
                $errors[] = 'That email address is already registered.';
            } else {
                throw $e;
            }
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/partials/header.php';
?>
<section class="card auth-card">
    <h1>Create an account</h1>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="register.php">
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= escape($username) ?>"
               required minlength="3" maxlength="50" autocomplete="username">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= escape($email) ?>"
               required maxlength="100" autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               required minlength="8" autocomplete="new-password">

        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password"
               required minlength="8" autocomplete="new-password">

        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>
    <p class="muted center">Already have an account? <a href="login.php">Log in</a></p>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
