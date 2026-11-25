<?php
/**
 * CLI script: creates the first admin account.
 * Run:  C:\xampp\php\php.exe scripts\create-admin.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../includes/functions.php';

const IS_WINDOWS = DIRECTORY_SEPARATOR === '\\';

/** Reads one line from the terminal. Hides typing on Linux/Mac (Windows CMD cannot hide it from PHP). */
function ask(string $label, bool $secret = false): string
{
    echo $label . ': ';
    if ($secret && !IS_WINDOWS) {
        shell_exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        echo PHP_EOL;
        return $value;
    }
    return trim((string) fgets(STDIN));
}

function fail(string $message): never
{
    fwrite(STDERR, PHP_EOL . 'Error: ' . $message . PHP_EOL);
    exit(1);
}

echo PHP_EOL . "=== Create admin account ===" . PHP_EOL;

$fullName = ask('Full name');
$username = ask('Username (letters, numbers, . _ -)');
$email    = ask('Email');

if (IS_WINDOWS) {
    echo "Note: on Windows the password is visible while typing. Run 'cls' afterwards." . PHP_EOL;
}
$password = ask('Password (min 12 characters)', true);
$confirm  = ask('Confirm password', true);

if ($fullName === '' || mb_strlen($fullName) > 100)                     fail('Full name is required (max 100 characters).');
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username))                  fail('Username must be 3-50 characters: letters, numbers, . _ -');
if (!isValidEmail($email))                                              fail('Email address is not valid.');
if (strlen($password) < 12)                                             fail('Password must be at least 12 characters.');
if (!hash_equals($password, $confirm))                                  fail('Passwords do not match.');

try {
    $stmt = db()->prepare(
        'INSERT INTO admins (full_name, username, email, password_hash, role)
         VALUES (:name, :username, :email, :hash, :role)'
    );
    $stmt->execute([
        ':name'     => $fullName,
        ':username' => $username,
        ':email'    => $email,
        ':hash'     => password_hash($password, PASSWORD_DEFAULT),
        ':role'     => 'super_admin',
    ]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) {
        fail('That username or email already exists.');
    }
    logError('create-admin failed: ' . $e->getMessage());
    fail('Could not create the admin. See storage/logs/app.log');
}

echo PHP_EOL . "Admin '$username' created successfully." . PHP_EOL;
