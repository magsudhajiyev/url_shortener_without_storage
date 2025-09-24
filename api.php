<?php
// URL Shortener with 1-hour cross-browser temporary storage
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_start();

if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
    session_regenerate_id(true);
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

$baseUrl = 'http://localhost:8000/';

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeOutput($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function isValidUrl($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $parsed = parse_url($url);

    if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
        return false;
    }

    if (isset($parsed['host'])) {
        $host = strtolower($parsed['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0']) ||
            preg_match('/^(10|172\.(1[6-9]|2[0-9]|3[01])|192\.168)\./', $host)) {
            return false;
        }
    }

    if (strlen($url) > 2048) {
        return false;
    }

    return true;
}

function logError($message) {
    error_log('[URL_SHORTENER] ' . date('Y-m-d H:i:s') . ' - ' . $message);
}

if (!isset($_SESSION['urls'])) {
    $_SESSION['urls'] = [];
}

// Cross-browser temporary storage (1-hour expiry)
function getTempDir() {
    $tempDir = sys_get_temp_dir() . '/url_shortener';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    return $tempDir;
}

function saveUrlToTemp($code, $url) {
    $tempDir = getTempDir();
    $data = [
        'url' => $url,
        'created' => time(),
        'expires' => time() + 3600
    ];
    file_put_contents($tempDir . '/' . $code . '.json', json_encode($data));
}

function getUrlFromTemp($code) {
    $tempDir = getTempDir();
    $file = $tempDir . '/' . $code . '.json';

    if (!file_exists($file)) {
        return false;
    }

    $data = json_decode(file_get_contents($file), true);
    if (!$data || $data['expires'] < time()) {
        @unlink($file);
        return false;
    }

    return $data['url'];
}

function cleanupExpiredUrls() {
    $tempDir = getTempDir();
    $files = glob($tempDir . '/*.json');
    $now = time();

    foreach ($files as $file) {
        $data = @json_decode(file_get_contents($file), true);
        if (!$data || $data['expires'] < $now) {
            @unlink($file);
        }
    }
}

if (rand(1, 100) === 1) {
    cleanupExpiredUrls();
}

// Rate limiting
if (!isset($_SESSION['last_creation'])) {
    $_SESSION['last_creation'] = 0;
    $_SESSION['creation_count'] = 0;
}

if (time() - $_SESSION['last_creation'] > 60) {
    $_SESSION['creation_count'] = 0;
    $_SESSION['last_creation'] = time();
}

function generateShortCode($length = 6) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    $max = strlen($chars) - 1;

    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}

// Handle redirects
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = trim(parse_url($requestUri, PHP_URL_PATH), '/');

if (!empty($path) && $path !== 'index.php' && $path !== 'api.php' && !strpos($path, '.') && !strpos($path, '/') && !strpos($path, '\\')) {
    $path = preg_replace('/[^a-zA-Z0-9]/', '', $path);

    // Check both session and temp storage for URL
    $url = null;
    if (isset($_SESSION['urls'][$path])) {
        $url = $_SESSION['urls'][$path];
    } else {
        $url = getUrlFromTemp($path);
    }

    if ($url) {
        if (isValidUrl($url)) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header("Location: " . $url);
            exit;
        } else {
            logError("Invalid URL attempted redirect: " . $url);
        }
    }

    http_response_code(404);
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>404 - Not Found</title>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='font-family: sans-serif; text-align: center; padding: 50px;'>
        <h2>⚠️ Short URL not found or expired</h2>
        <p>This link expires after 1 hour.</p>
        <a href='/'>Create new short URL</a>
    </body>
    </html>
    ");
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');

    try {
        if ($_SESSION['creation_count'] >= 10) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded. Please wait a minute.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if ($input === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }

        $url = $input['url'] ?? '';
        $csrf_token = $input['csrf_token'] ?? '';

        if (!validateCSRFToken($csrf_token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid request token']);
            exit;
        }

        if (empty($url)) {
            http_response_code(400);
            echo json_encode(['error' => 'URL is required']);
            exit;
        }

        if (!isValidUrl($url)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or unsafe URL']);
            logError("Invalid URL submitted: " . $url);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error occurred']);
        logError("Exception in POST handler: " . $e->getMessage());
        exit;
    }

    $_SESSION['creation_count']++;
    $existingCode = array_search($url, $_SESSION['urls']);

    if ($existingCode !== false) {
        $code = $existingCode;
    } else {
        $attempts = 0;
        do {
            $code = generateShortCode(6);
            $attempts++;
            if ($attempts > 100) {
                http_response_code(500);
                echo json_encode(['error' => 'Unable to generate unique code']);
                logError("Failed to generate unique code after 100 attempts");
                exit;
            }
        } while (isset($_SESSION['urls'][$code]));

        if (count($_SESSION['urls']) >= 100) {
            $_SESSION['urls'] = array_slice($_SESSION['urls'], -50, null, true);
        }

        $_SESSION['urls'][$code] = $url;
        saveUrlToTemp($code, $url);
    }

    $shortUrl = $baseUrl . $code;
    echo json_encode([
        'success' => true,
        'short_url' => sanitizeOutput($shortUrl),
        'original_url' => sanitizeOutput($url),
        'saved' => strlen($url) - strlen($shortUrl),
        'total_in_session' => count($_SESSION['urls'])
    ]);
    exit;
}

// Handle clear session request
if (isset($_GET['clear'])) {
    $token = $_GET['csrf_token'] ?? '';
    if (validateCSRFToken($token)) {
        $_SESSION['urls'] = [];
        $_SESSION['creation_count'] = 0;
        header("Location: /");
        exit;
    } else {
        http_response_code(403);
        die('Invalid request token');
    }
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; connect-src \'self\'; frame-ancestors \'none\';');
?>