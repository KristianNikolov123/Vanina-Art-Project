<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 text-center">
            <h1 class="display-5 mb-4">💬 Дъска за обратна връзка</h1>
            <p class="lead mb-4">Ако нещо не ти харесва, имаш идея или искаш промяна – напиши го тук!</p>
            <form method="post" action="<?= url_for('submit_feedback') ?>" class="mb-4">
                <div class="input-group">
                    <textarea class="form-control" name="message" rows="2" maxlength="300" placeholder="Напиши своята критика, идея или предложение..." required></textarea>
                    <button class="btn btn-primary" type="submit">Изпрати</button>
                </div>
            </form>
            <div class="feedback-bubbles mt-4 text-start">
                <?php if (empty($feedbacks)): ?>
                    <div class="text-muted">Все още няма обратна връзка. Бъди първият!</div>
                <?php else: ?>
                    <?php foreach ($feedbacks as $fb): ?>
                    <div class="bubble mb-2 p-3 rounded shadow-sm" style="background: #f1f1f1; display: inline-block; max-width: 80%;">
                        <span style="white-space: pre-line;" id="msg-<?= (int)$fb['id'] ?>"><?= e($fb['message']) ?></span>
                        <div class="text-end" style="font-size: 0.85em; color: #888;"><?= e($fb['formatted_time']) ?></div>
                        <form method="post" action="<?= BASE_PATH ?>/delete_feedback/<?= (int)$fb['id'] ?>" style="display:inline;">
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Сигурни ли сте, че искате да изтриете тази обратна връзка?')">Изтрий</button>
                        </form>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleEdit(<?= (int)$fb['id'] ?>)">Редактирай</button>
                        <form method="post" action="<?= BASE_PATH ?>/edit_feedback/<?= (int)$fb['id'] ?>" id="edit-form-<?= (int)$fb['id'] ?>" style="display:none; margin-top: 8px;">
                            <div class="input-group">
                                <input type="text" class="form-control" name="edit_message" value="<?= e($fb['message']) ?>" maxlength="300" required>
                                <button type="submit" class="btn btn-primary btn-sm">Запази</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEdit(<?= (int)$fb['id'] ?>)">Отказ</button>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>