<div class="container">
    <?php if (!empty($pending_users)): ?>
    <div class="card border-warning mb-4">
        <div class="card-header bg-warning-subtle"><strong>Чакащи регистрации</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr><th>Потребител</th><th>Имейл</th><th>Заявка</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $pending): ?>
                    <tr>
                        <td><?= e($pending['username']) ?></td>
                        <td><?= e($pending['email']) ?></td>
                        <td><?= e((string)$pending['created_at']) ?></td>
                        <td class="text-end text-nowrap">
                            <form method="post" action="<?= BASE_PATH ?>/users/<?= (int)$pending['id'] ?>/approve" class="d-inline">
                                <button type="submit" class="btn btn-sm btn-success">Одобри</button>
                            </form>
                            <form method="post" action="<?= BASE_PATH ?>/users/<?= (int)$pending['id'] ?>/reject" class="d-inline"
                                onsubmit="return confirm('Да отхвърля ли заявката и да изтрия потребителя?');">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Отхвърли</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <h1 class="mb-4">Архив на влизанията</h1>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Потребител</th>
                    <th>Имейл</th>
                    <th>Време на влизане</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e($log['username']) ?></td>
                    <td><?= e($log['email']) ?></td>
                    <td><?= e($log['login_time']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
