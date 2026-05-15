<?php include __DIR__ . '/../partials/header.view.php' ?>

<article>
    <header>
        <h1>Users</h1>
        <p>Manage local application users and roles.</p>
    </header>

    <?php $success = message(null, true); ?>
    <?php if (!empty($success)): ?>
        <p><mark><?= esc($success) ?></mark></p>
    <?php endif; ?>

    <p>
        <a href="<?= ROOT ?>/users/create"
            role="button"
            hx-get="<?= ROOT ?>/users/create"
            hx-target="#page-content"
            hx-select="#page-content > *"
            hx-select-oob="#site-nav"
            hx-swap="innerHTML"
            hx-push-url="true">Create user</a>
    </p>

    <?php if (empty($users)): ?>
        <p>No users found.</p>
    <?php else: ?>
        <figure>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Auth</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?= esc($row->name) ?></td>
                            <td><?= esc($row->username) ?></td>
                            <td><?= esc($row->email ?: '-') ?></td>
                            <td><?= esc($row->role) ?></td>
                            <td><?= esc($row->auth_provider) ?></td>
                            <td><?= (int)$row->is_active === 1 ? 'Active' : 'Inactive' ?></td>
                            <td><?= esc($row->last_login_at ?: '-') ?></td>
                            <td>
                                <a href="<?= ROOT ?>/users/edit/<?= (int)$row->id ?>"
                                    hx-get="<?= ROOT ?>/users/edit/<?= (int)$row->id ?>"
                                    hx-target="#page-content"
                                    hx-select="#page-content > *"
                                    hx-select-oob="#site-nav"
                                    hx-swap="innerHTML"
                                    hx-push-url="true">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </figure>
    <?php endif; ?>
</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
