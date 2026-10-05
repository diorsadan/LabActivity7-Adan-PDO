# LabActivity7-Adan-PDO — PHP Blog with PDO

A modular PHP blog with session authentication, posts, comments, and PDO (MySQL) persistence.

## Features

- Registration (`password_hash`, email uniqueness check) and login (`password_verify`)
- Session-based access guards (`require_auth()`, `require_guest()`)
- Newsfeed of all posts (newest first) with author names via `INNER JOIN`
- Create posts and comment on any post
- Edit or delete your own posts/comments only (ownership enforced in SQL), with an "(Edited)" marker on edits
- Prepared statements everywhere, `htmlspecialchars` output escaping, CSRF tokens on all forms, session ID regeneration on login

## Project structure

```
LabActivity7-Adan-PDO/
├── assets/style.css       # Styles
├── partials/header.php    # Shared page header + flash messages
├── partials/footer.php    # Shared page footer
├── auth_middleware.php    # Session, guards, escape(), CSRF, flash helpers
├── db.php                 # PDO connection
├── schema.sql             # Database + tables
├── register.php           # Guest only
├── login.php              # Guest only
├── index.php              # Newsfeed (auth)
├── edit_post.php          # Edit own post (auth)
├── edit_comment.php       # Edit own comment (auth)
├── delete_post.php        # Delete own post + its comments (auth, POST)
├── delete_comment.php     # Delete own comment (auth, POST)
└── logout.php             # Ends session (POST)
```

## Setup (XAMPP)

1. Copy this folder into `C:\xampp\htdocs\` (or your web root).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Create the database by importing `schema.sql`:
   - phpMyAdmin: open http://localhost/phpmyadmin → **Import** → choose `schema.sql` → **Go**, or
   - CLI: `mysql -u root -p < schema.sql`
4. If your MySQL user/password are not the XAMPP defaults (`root` / empty), edit `DB_USER` and `DB_PASS` in `db.php`.
5. Open http://localhost/LabActivity7-Adan-PDO/register.php (adjust the path to where you placed the folder), create an account, and log in.

Requirements: PHP 7.4+ with the `pdo_mysql` extension, MySQL 5.6.5+ or MariaDB 10.0+.

## Publish to GitHub

> **Reminder:** push this project to a **public** GitHub repository named **`LabActivity7-Adan-PDO`**.

```bash
git init
git add .
git commit -m "LabActivity7: PHP blog with PDO"
git branch -M main
git remote add origin https://github.com/<your-username>/LabActivity7-Adan-PDO.git
git push -u origin main
```

Make sure the repository visibility is set to **Public** when creating it on GitHub.
