<?php
/**
 * Страница за телефона (от QR кода): прехвърля към SMS приложението с готовия номер и текст.
 * @var array|null $handoff
 */
$smsLink = $handoff ? sms_app_link((string)$handoff['recipient'], (string)$handoff['message']) : '';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>SMS — <?= e(shop_display_name()) ?></title>
    <style>
        body { font-family: system-ui, "Segoe UI", Arial, sans-serif; margin: 0; padding: 24px 18px; background: #f5f5f5; color: #111; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        h1 { font-size: 20px; margin: 0 0 12px; }
        .to { font-size: 18px; font-weight: 600; margin-bottom: 10px; }
        .msg { white-space: pre-wrap; background: #f1f3f5; border-radius: 10px; padding: 12px; margin-bottom: 18px; font-size: 15px; }
        .btn { display: block; text-align: center; background: #0b6b3a; color: #fff; text-decoration: none; font-size: 18px; font-weight: 600; padding: 16px; border-radius: 12px; }
        .copy { display: block; width: 100%; margin-top: 10px; background: #fff; border: 1px solid #ccc; border-radius: 12px; padding: 12px; font-size: 15px; }
        .muted { color: #666; font-size: 13px; margin-top: 14px; text-align: center; }
    </style>
</head>
<body>
<div class="card">
    <?php if (!$handoff): ?>
    <h1>Линкът е невалиден или е изтекъл</h1>
    <p>Натиснете отново „SMS“ в програмата и сканирайте новия QR код.</p>
    <?php else: ?>
    <h1>SMS до клиента</h1>
    <div class="to"><?= e((string)$handoff['recipient']) ?></div>
    <div class="msg" id="smsText"><?= e((string)$handoff['message']) ?></div>
    <a class="btn" id="smsOpen" href="<?= e($smsLink) ?>">Отвори SMS</a>
    <button type="button" class="copy" id="smsCopy">Копирай текста</button>
    <div class="muted">След като се отвори SMS приложението, натиснете „Изпрати“.</div>
    <script>
        // Опит за автоматично отваряне; ако браузърът го спре, остава бутонът.
        setTimeout(() => { window.location.href = document.getElementById('smsOpen').href; }, 300);
        document.getElementById('smsCopy').addEventListener('click', async (e) => {
            try {
                await navigator.clipboard.writeText(document.getElementById('smsText').textContent);
                e.target.textContent = 'Копирано';
            } catch (err) {
                e.target.textContent = 'Задръжте върху текста, за да го копирате';
            }
        });
    </script>
    <?php endif; ?>
</div>
</body>
</html>
