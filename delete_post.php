<?php
require __DIR__ . '/auth_middleware.php';
require __DIR__ . '/db.php';

require_auth();

// Deleting is POST-only (with CSRF) so a link alone cannot delete anything.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();

$userId = (int) $_SESSION['user_id'];
$postId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

try {
    $pdo->beginTransaction();

    // Ownership is enforced in SQL; lock the row until the delete finishes.
    $lock = $pdo->prepare('SELECT id FROM posts WHERE id = :id AND user_id = :user_id FOR UPDATE');
    $lock->execute([':id' => $postId ?: 0, ':user_id' => $userId]);
    if (!$lock->fetch()) {
        $pdo->rollBack();
        flash('error', 'Post not found or you are not allowed to delete it.');
        redirect('index.php');
    }

    // Comments on this post are removed automatically by ON DELETE CASCADE.
    $delete = $pdo->prepare('DELETE FROM posts WHERE id = :id AND user_id = :user_id');
    $delete->execute([':id' => $postId, ':user_id' => $userId]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

flash('success', 'Post deleted.');
redirect('index.php');
