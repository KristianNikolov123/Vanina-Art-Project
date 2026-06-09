<div class="container">
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
