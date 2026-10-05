<?php
/** Page 2 - Sign in and create account (two forms, switched with tabs). */
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect('profile.php');
}

// Only allow redirects back to pages on this site
$next = $_GET['next'] ?? $_POST['next'] ?? 'planner.php';
if (!preg_match('#^/?[a-zA-Z0-9_\-/]*\.php(\?[^:]*)?$#', $next) || str_contains($next, '//')) {
    $next = 'planner.php';
}

$mode   = ($_GET['mode'] ?? $_POST['action'] ?? 'login') === 'register' ? 'register' : 'login';
$errors = [];
$old    = ['username' => '', 'email' => '', 'dietary_preference' => 'none'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if ($_POST['action'] === 'login') {
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT user_id, username, password_hash FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $user['user_id'];
            $_SESSION['username'] = $user['username'];
            flash('Signed in. Welcome back, ' . $user['username'] . '.');
            redirect($next);
        }
        $errors[] = 'That username or password is not right. Check both and try again.';
        $old['username'] = $login;
    }

    if ($_POST['action'] === 'register') {
        $old['username']           = trim($_POST['username'] ?? '');
        $old['email']              = trim($_POST['email'] ?? '');
        $old['dietary_preference'] = array_key_exists($_POST['dietary_preference'] ?? '', DIETS) ? $_POST['dietary_preference'] : 'none';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';
        $budget   = (float) ($_POST['budget'] ?? 60);

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $old['username'])) {
            $errors[] = 'Username must be 3 to 30 letters, numbers or underscores.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'The two passwords do not match.';
        }
        if ($budget < 0 || $budget > 1000) {
            $errors[] = 'Weekly budget must be between $0 and $1000.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$old['username'], $old['email']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'That username or email is already registered. Sign in instead.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, dietary_preference, budget) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['dietary_preference'], $budget]);

            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $pdo->lastInsertId();
            $_SESSION['username'] = $old['username'];
            flash('Account created. Start by adding a few meals to your planner.');
            redirect('planner.php');
        }
    }
}

$pageTitle  = $mode === 'register' ? 'Create account' : 'Sign in';
$activePage = 'auth';
require 'includes/header.php';
?>
<section class="wrap narrow section">
    <div class="auth-card" data-tabs data-initial="<?= $mode ?>">
        <div class="tab-list" role="tablist">
            <button role="tab" id="tab-login" aria-controls="panel-login" data-tab="login">Sign in</button>
            <button role="tab" id="tab-register" aria-controls="panel-register" data-tab="register">Create account</button>
        </div>

        <?php if ($errors): ?>
            <div class="form-errors" role="alert">
                <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div role="tabpanel" id="panel-login" aria-labelledby="tab-login" data-panel="login">
            <h1>Sign in</h1>
            <form method="post" class="form" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <label>Username or email
                    <input type="text" name="login" required autocomplete="username" value="<?= e($old['username']) ?>">
                </label>
                <label>Password
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <button class="btn btn-primary btn-block" type="submit">Sign in</button>
                <p class="muted small">Just looking? Use the demo account: <strong>demo</strong> / <strong>password123</strong></p>
            </form>
        </div>

        <div role="tabpanel" id="panel-register" aria-labelledby="tab-register" data-panel="register">
            <h1>Create account</h1>
            <form method="post" class="form" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="register">
                <label>Username
                    <input type="text" name="username" required pattern="[A-Za-z0-9_]{3,30}" autocomplete="username"
                           value="<?= e($old['username']) ?>" data-error="3 to 30 letters, numbers or underscores.">
                </label>
                <label>Email
                    <input type="email" name="email" required autocomplete="email" value="<?= e($old['email']) ?>" data-error="Enter a valid email address.">
                </label>
                <div class="form-row">
                    <label>Password
                        <input type="password" name="password" id="reg-password" required minlength="8" autocomplete="new-password" data-error="At least 8 characters.">
                    </label>
                    <label>Confirm password
                        <input type="password" name="confirm" required data-match="reg-password" autocomplete="new-password" data-error="Passwords must match.">
                    </label>
                </div>
                <div class="form-row">
                    <label>Dietary preference
                        <select name="dietary_preference">
                            <?php foreach (DIETS as $val => $label): ?>
                                <option value="<?= $val ?>"<?= $old['dietary_preference'] === $val ? ' selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Weekly food budget ($)
                        <input type="number" name="budget" min="0" max="1000" step="1" value="60">
                    </label>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Create account</button>
            </form>
        </div>
    </div>
</section>
<?php require 'includes/footer.php'; ?>
