<?php include __DIR__ . '/../partials/header.view.php' ?>

<article class="auth-panel profile-panel">
    <header>
        <h1><?= !empty($isOwnProfile) ? 'My Profile' : esc($user->name) ?></h1>
        <p><?= esc($user->username) ?></p>
    </header>

    <?php $success = message(null, true); ?>
    <?php if (!empty($success)): ?>
        <p><mark><?= esc($success) ?></mark></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <p><mark><?= esc(implode(' | ', $errors)); ?></mark></p>
    <?php endif; ?>

    <dl class="profile-summary">
        <dt>Name</dt>
        <dd><?= esc($user->name) ?></dd>

        <dt>Email</dt>
        <dd><?= esc($user->email ?: '-') ?></dd>

        <dt>Role</dt>
        <dd><?= esc($user->role) ?></dd>
    </dl>

    <?php if (!empty($isOwnProfile) && !empty($canEditEmail)): ?>
        <form method="post" action="<?= ROOT ?>/profile"
            hx-post="<?= ROOT ?>/profile"
            hx-target="#page-content"
            hx-select="#page-content > *"
            hx-select-oob="#site-nav"
            hx-swap="innerHTML">
            <?= csrf_field() ?>

            <label for="email">Email address</label>
            <input type="email" name="email" id="email" value="<?= esc(old_value('email', $user->email)); ?>" maxlength="190" required>

            <div class="form-actions">
                <button type="submit">Update email</button>
            </div>
        </form>
    <?php else: ?>
        <?php if (!empty($isOwnProfile) && ($user->auth_provider ?? 'local') === 'ldap'): ?>
            <p><small>Your email address is managed by Active Directory.</small></p>
        <?php endif; ?>
    <?php endif; ?>
</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
