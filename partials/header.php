<?php
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'Blog Site';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> | Blog Site</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="index.php">Blog Site</a>
        <nav>
            <?php if (is_logged_in()): ?>
                <span class="nav-user">Hi, <?= escape($_SESSION['username']) ?></span>
                <form action="logout.php" method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-link">Log out</button>
                </form>
            <?php else: ?>
                <a href="login.php">Log in</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= escape($flash['message']) ?></div>
<?php endif; ?>
