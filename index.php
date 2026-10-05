<?php
require __DIR__ . '/auth_middleware.php';
require __DIR__ . '/db.php';

require_auth();

$userId = (int) $_SESSION['user_id'];
$postErrors = [];
$title = '';
$content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_post') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title === '' || mb_strlen($title) > 255) {
            $postErrors[] = 'Title is required and must be at most 255 characters.';
        }
        if ($content === '' || mb_strlen($content) > POST_CONTENT_MAX) {
            $postErrors[] = 'Post content is required and must be at most ' . POST_CONTENT_MAX . ' characters.';
        }

        if (!$postErrors) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO posts (user_id, title, content) VALUES (:user_id, :title, :content)'
                );
                $stmt->execute([':user_id' => $userId, ':title' => $title, ':content' => $content]);
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            flash('success', 'Post published.');
            redirect('index.php');
        }
    } elseif ($action === 'add_comment') {
        $postId  = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
        $comment = trim($_POST['comment'] ?? '');

        if (!$postId || $comment === '' || mb_strlen($comment) > COMMENT_MAX) {
            flash('error', 'Comment is required and must be at most ' . COMMENT_MAX . ' characters.');
            redirect('index.php');
        }

        try {
            $pdo->beginTransaction();

            // Lock the post so it cannot be deleted while the comment is being added.
            $exists = $pdo->prepare('SELECT id FROM posts WHERE id = :id FOR UPDATE');
            $exists->execute([':id' => $postId]);
            if (!$exists->fetch()) {
                $pdo->rollBack();
                flash('error', 'That post no longer exists.');
                redirect('index.php');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO comments (user_id, post_id, comment) VALUES (:user_id, :post_id, :comment)'
            );
            $stmt->execute([':user_id' => $userId, ':post_id' => $postId, ':comment' => $comment]);
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        redirect('index.php#post-' . $postId);
    }
}

// Newsfeed: all posts with their author, newest first.
$postsStmt = $pdo->prepare(
    'SELECT p.id, p.user_id, p.title, p.content, p.is_edited, p.created_at, u.username
     FROM posts p
     INNER JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC, p.id DESC'
);
$postsStmt->execute();
$posts = $postsStmt->fetchAll();

// All comments with their author, grouped by post in PHP (avoids N+1 queries).
$commentsByPost = [];
$commentsStmt = $pdo->prepare(
    'SELECT c.id, c.user_id, c.post_id, c.comment, c.is_edited, c.created_at, u.username
     FROM comments c
     INNER JOIN users u ON u.id = c.user_id
     ORDER BY c.created_at ASC, c.id ASC'
);
$commentsStmt->execute();
foreach ($commentsStmt->fetchAll() as $row) {
    $commentsByPost[$row['post_id']][] = $row;
}

function format_date(string $timestamp): string
{
    return date('M j, Y \a\t g:i A', strtotime($timestamp));
}

$pageTitle = 'Newsfeed';
require __DIR__ . '/partials/header.php';
?>
<section class="card">
    <h2>Create a post</h2>

    <?php if ($postErrors): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($postErrors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_post">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= escape($title) ?>" required maxlength="255">

        <label for="content">Content</label>
        <textarea id="content" name="content" rows="4" required maxlength="<?= POST_CONTENT_MAX ?>"><?= escape($content) ?></textarea>

        <button type="submit" class="btn btn-primary">Publish</button>
    </form>
</section>

<h2 class="feed-heading">Newsfeed</h2>

<?php if (!$posts): ?>
    <p class="card muted center">No posts yet. Be the first to write one!</p>
<?php endif; ?>

<?php foreach ($posts as $post): ?>
    <article class="card post" id="post-<?= (int) $post['id'] ?>">
        <header class="post-header">
            <h3><?= escape($post['title']) ?></h3>
            <div class="meta">
                by <strong><?= escape($post['username']) ?></strong>
                &middot; <?= escape(format_date($post['created_at'])) ?>
                <?php if ((int) $post['is_edited'] === 1): ?>
                    <span class="edited">(Edited)</span>
                <?php endif; ?>
                <?php if ((int) $post['user_id'] === $userId): ?>
                    &middot; <a href="edit_post.php?id=<?= (int) $post['id'] ?>">Edit</a>
                    &middot;
                    <form method="post" action="delete_post.php" class="inline-form"
                          onsubmit="return confirm('Delete this post and all its comments?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                        <button type="submit" class="btn-link danger">Delete</button>
                    </form>
                <?php endif; ?>
            </div>
        </header>

        <div class="post-content"><?= nl2br(escape($post['content'])) ?></div>

        <section class="comments">
            <h4>Comments (<?= count($commentsByPost[$post['id']] ?? []) ?>)</h4>

            <?php foreach ($commentsByPost[$post['id']] ?? [] as $comment): ?>
                <div class="comment">
                    <div class="meta">
                        <strong><?= escape($comment['username']) ?></strong>
                        &middot; <?= escape(format_date($comment['created_at'])) ?>
                        <?php if ((int) $comment['is_edited'] === 1): ?>
                            <span class="edited">(Edited)</span>
                        <?php endif; ?>
                        <?php if ((int) $comment['user_id'] === $userId): ?>
                            &middot; <a href="edit_comment.php?id=<?= (int) $comment['id'] ?>">Edit</a>
                            &middot;
                            <form method="post" action="delete_comment.php" class="inline-form"
                                  onsubmit="return confirm('Delete this comment?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $comment['id'] ?>">
                                <button type="submit" class="btn-link danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <p><?= nl2br(escape($comment['comment'])) ?></p>
                </div>
            <?php endforeach; ?>

            <form method="post" action="index.php" class="comment-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_comment">
                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                <textarea name="comment" rows="2" placeholder="Write a comment..." required maxlength="<?= COMMENT_MAX ?>"
                          aria-label="Write a comment"></textarea>
                <button type="submit" class="btn btn-secondary">Comment</button>
            </form>
        </section>
    </article>
<?php endforeach; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
