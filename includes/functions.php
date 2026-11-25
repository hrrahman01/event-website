<?php
/**
 * Shared helpers: database, sessions, CSRF, validation, rate limiting, auth, logging.
 * Requires PHP 8.1 or newer.
 */
declare(strict_types=1);

define('APP_BOOT', true);
require_once __DIR__ . '/config.php';

date_default_timezone_set(APP_TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_DIR . '/php-error.log');

/* =====================================================
   Logging
   ===================================================== */
function logError(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(LOG_DIR . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

function logActivity(?int $adminId, string $action, string $entityType, ?string $entityId, string $description): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (admin_id, action, entity_type, entity_id, description)
             VALUES (:admin, :action, :type, :entity, :description)'
        );
        $stmt->execute([
            ':admin'       => $adminId,
            ':action'      => mb_substr($action, 0, 60),
            ':type'        => mb_substr($entityType, 0, 40),
            ':entity'      => $entityId,
            ':description' => mb_substr($description, 0, 255),
        ]);
    } catch (Throwable $e) {
        logError('Activity log failed: ' . $e->getMessage());
    }
}

/* =====================================================
   Database (PDO, prepared statements only)
   ===================================================== */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            logError('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Service temporarily unavailable. Please try again later.');
        }
    }
    return $pdo;
}

/* =====================================================
   Output helpers
   ===================================================== */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jsonResponse(bool $success, string $message = '', array $extra = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function sendNoCacheHeaders(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function requirePost(bool $asJson = true): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        if ($asJson) {
            jsonResponse(false, 'Method not allowed.', [], 405);
        }
        http_response_code(405);
        exit('Method not allowed.');
    }
}

/* =====================================================
   Sessions and CSRF
   ===================================================== */
function isHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');   // behind AWS load balancer
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isHttps(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    // Expire idle sessions
    if (isset($_SESSION['lastActivity']) && (time() - (int) $_SESSION['lastActivity']) > SESSION_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
        if (!defined('SKIP_ACTIVITY_TOUCH')) {
        $_SESSION['lastActivity'] = time();
    }
}

function csrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrfToken'])) {
        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrfToken'];
}

function verifyCsrf(?string $token): bool
{
    startSecureSession();
    return !empty($_SESSION['csrfToken']) && is_string($token) && hash_equals($_SESSION['csrfToken'], $token);
}

/** Checks the token from POST field "csrf_token" or the X-CSRF-Token header. */
function requireCsrf(bool $asJson = true): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (verifyCsrf(is_string($token) ? $token : null)) {
        return;
    }
    if ($asJson) {
        jsonResponse(false, 'Your session expired. Please refresh the page and try again.', [], 419);
    }
    http_response_code(419);
    exit('Security token invalid. Please go back and refresh the page.');
}

/* =====================================================
   Request, validation and sanitising helpers
   ===================================================== */
function clientIp(): string
{
    // REMOTE_ADDR only: forwarded headers can be spoofed by visitors
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Trims, strips control characters and limits length. */
function cleanText(mixed $value, int $maxLength, bool $multiline = false): string
{
    $text    = is_string($value) ? trim($value) : '';
    $pattern = $multiline ? '/[^\P{C}\n]/u' : '/\p{C}/u';
    $text    = preg_replace($pattern, '', $text) ?? '';
    return mb_substr($text, 0, $maxLength);
}

function isValidEmail(string $email): bool
{
    return mb_strlen($email) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone(string $phone): bool
{
    return preg_match('/^[0-9 ]{6,15}$/', $phone) === 1;
}

function wordCount(string $text): int
{
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    return $words === false ? 0 : count($words);
}

/** Prevents spreadsheet formula injection in CSV exports. */
function csvSafeValue(mixed $value): string
{
    $text = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'" . $text : $text;
}

/** Unique reference such as REG-2027-K7M3QX. */
function generateReference(string $prefix, string $table, string $column): string
{
    $allowed = ['registrations' => 'registration_id', 'abstracts' => 'abstract_id'];
    if (($allowed[$table] ?? null) !== $column) {
        throw new InvalidArgumentException('Invalid reference target.');
    }

    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // no 0/O or 1/I confusion
    $check    = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = :ref");

    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $reference = sprintf('%s-%s-%s', $prefix, date('Y'), $code);
        $check->execute([':ref' => $reference]);
    } while ((int) $check->fetchColumn() > 0);

    return $reference;
}

/* =====================================================
   Rate limiting (stored in MySQL)
   ===================================================== */
function rateLimitKey(string $scope, ?string $identifier = null): string
{
    return $scope . ':' . hash('sha256', $identifier ?? clientIp());
}

function rateLimitExceeded(string $key, int $maxHits, int $windowSeconds): bool
{
    $window = max(1, $windowSeconds);   // integer, safe to inline in the interval
    $stmt   = db()->prepare(
        "SELECT COUNT(*) FROM rate_limits
         WHERE rate_key = :key AND created_at > (NOW() - INTERVAL $window SECOND)"
    );
    $stmt->execute([':key' => $key]);
    return (int) $stmt->fetchColumn() >= $maxHits;
}

function rateLimitHit(string $key): void
{
    db()->prepare('INSERT INTO rate_limits (rate_key) VALUES (:key)')->execute([':key' => $key]);

    // Occasional cleanup of old rows
    if (random_int(1, 50) === 1) {
        db()->exec('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }
}

function rateLimitClear(string $key): void
{
    db()->prepare('DELETE FROM rate_limits WHERE rate_key = :key')->execute([':key' => $key]);
}

/* =====================================================
   Event settings (editable from the admin panel)
   ===================================================== */
function getSettings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = db()->query('SELECT setting_key, setting_value FROM event_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache;
}

function getSetting(string $key, string $default = ''): string
{
    $settings = getSettings();
    return isset($settings[$key]) && $settings[$key] !== null ? (string) $settings[$key] : $default;
}

function saveSetting(string $key, string $value): void
{
    db()->prepare(
        'INSERT INTO event_settings (setting_key, setting_value) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([':key' => $key, ':value' => $value]);
}

/** $type is 'registration' or 'abstract'. Closed by the admin switch or by a passed deadline. */
function isSubmissionOpen(string $type): bool
{
    $switch   = getSetting($type . '_open', '1');
    $deadline = getSetting($type === 'registration' ? 'registration_deadline' : 'abstract_deadline');

    if ($switch !== '1') {
        return false;
    }
    if ($deadline !== '' && strtotime($deadline) !== false && date('Y-m-d') > $deadline) {
        return false;
    }
    return true;
}

/* =====================================================
   Admin authentication
   ===================================================== */
function isAdminLoggedIn(): bool
{
    startSecureSession();
    return !empty($_SESSION['adminId']);
}

function requireAdmin(bool $asJson = false): void
{
    if (isAdminLoggedIn()) {
        sendNoCacheHeaders();
        return;
    }
    if ($asJson) {
        jsonResponse(false, 'Please log in again.', [], 401);
    }
    header('Location: login.php');
    exit;
}

function currentAdminId(): ?int
{
    startSecureSession();
    return isset($_SESSION['adminId']) ? (int) $_SESSION['adminId'] : null;
}

function loginAdmin(array $admin): void
{
    startSecureSession();
    session_regenerate_id(true);   // prevents session fixation
    $_SESSION['adminId']      = (int) $admin['id'];
    $_SESSION['adminName']    = (string) $admin['full_name'];
    $_SESSION['adminRole']    = (string) $admin['role'];
    $_SESSION['lastActivity'] = time();

    db()->prepare('UPDATE admins SET last_login = NOW() WHERE id = :id')->execute([':id' => $admin['id']]);
    logActivity((int) $admin['id'], 'login', 'admin', (string) $admin['id'], 'Admin logged in');
}

function logoutAdmin(): void
{
    startSecureSession();
    $adminId = currentAdminId();
    if ($adminId !== null) {
        logActivity($adminId, 'logout', 'admin', (string) $adminId, 'Admin logged out');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Strict',
        ]);
    }
    session_destroy();
}
