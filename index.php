<?php require_once 'api.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Shortener</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>⚡ URL Shortener</h1>
        <div class="subtitle">Cross-browser • 1-hour expiry</div>

        <div class="warning">
            ⚠️ <strong>Note:</strong> Short URLs work across all browsers and expire after 1 hour.
        </div>

        <form id="form">
            <input type="url" id="url" placeholder="Enter your URL here..." required>
            <button type="submit">Generate Short URL</button>
        </form>

        <div id="result" class="result"></div>

        <div id="clear-btn-container" style="<?php echo count($_SESSION['urls']) > 0 ? 'display: block;' : 'display: none;'; ?>">
            <?php if (count($_SESSION['urls']) > 0): ?>
            <button class="clear-btn" onclick="clearSession()">
                Clear All (<?php echo sanitizeOutput(count($_SESSION['urls'])); ?> URLs in session)
            </button>
            <?php endif; ?>
        </div>

        <input type="hidden" id="csrf-token" value="<?php echo sanitizeOutput(generateCSRFToken()); ?>">
    </div>

    <script src="script.js"></script>
</body>
</html>