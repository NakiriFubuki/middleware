<?php require __DIR__ . '/copyright-footer.php'; ?>
    <div id="toast-container" class="toast-container"></div>
    <div id="loading-overlay" class="loading-overlay hidden">
        <div class="spinner"></div>
    </div>

    <script src="<?= assetUrl('js/ajax.js') ?>"></script>
    <script src="<?= assetUrl('js/notifications.js') ?>"></script>
    <script src="<?= assetUrl('js/form-validator.js') ?>"></script>
    <script src="<?= assetUrl('js/main.js') ?>"></script>
    <?php foreach ($extraJs as $js): ?>
        <script src="<?= assetUrl('js/' . $js) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
