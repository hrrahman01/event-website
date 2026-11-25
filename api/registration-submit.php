<?php
/**
 * Registration endpoint.
 *   GET  -> returns a CSRF token and whether registration is open
 *   POST -> validates and saves a registration
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

/* ---------- GET: token + open/closed status ---------- */
if ($method === 'GET') {
    $open = isSubmissionOpen('registration');
    jsonResponse(true, $open ? '' : 'Registration is currently closed.', [
        'token' => csrfToken(),
        'open'  => $open,
    ]);
}

/* ---------- POST ---------- */
requirePost();
requireCsrf();

// Honeypot: bots fill the hidden field. Pretend success and save nothing.
if (cleanText($_POST['website'] ?? '', 100) !== '') {
    jsonResponse(true, 'Thank you.', ['reference' => 'REG-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)))]);
}

// Closed on the server too, so direct API calls cannot bypass the admin switch
if (!isSubmissionOpen('registration')) {
    jsonResponse(false, 'Registration is currently closed.', [], 403);
}

$rateKey = rateLimitKey('registration');
if (rateLimitExceeded($rateKey, FORM_MAX_PER_HOUR, 3600)) {
    jsonResponse(false, 'Too many submissions. Please try again later.', [], 429);
}

/* ----- Collect input ----- */
$allowedTitles     = ['Dr.', 'Prof.', 'Mr.', 'Ms.', 'Other'];
$allowedCategories = ['Delegate', 'Speaker', 'Student', 'Researcher', 'Academic', 'Industry Professional', 'Business', 'Other'];
$rateCategories   = ['Academic', 'Business', 'Student'];
$rateTiers        = ['Early Bird', 'Regular', 'Final'];
$allowedTypes      = ['In-person', 'Virtual'];

$title        = cleanText($_POST['title'] ?? '', 20);
$firstName    = cleanText($_POST['first_name'] ?? '', 60);
$lastName     = cleanText($_POST['last_name'] ?? '', 60);
$email        = strtolower(cleanText($_POST['email'] ?? '', 120));
$phoneCode    = cleanText($_POST['phone_code'] ?? '', 6);
$phoneNumber  = cleanText($_POST['phone'] ?? '', 15);
$country      = cleanText($_POST['country'] ?? '', 80);
$organization = cleanText($_POST['organization'] ?? '', 150);
$designation  = cleanText($_POST['designation'] ?? '', 100);
$category     = cleanText($_POST['registration_category'] ?? '', 40);
$tier         = null;
if (array_key_exists('registration_rate', $_POST)) {
    [$tier, $category] = array_pad(explode('|', cleanText($_POST['registration_rate'], 40), 2), 2, '');
}
$participantCount = filter_var($_POST['participant_count'] ?? '1', FILTER_VALIDATE_FLOAT);
$type         = cleanText($_POST['participation_type'] ?? '', 20);
$diet         = cleanText($_POST['dietary_requirements'] ?? '', 150);
$message      = cleanText($_POST['message'] ?? '', 1000, true);
$consent      = ($_POST['consent'] ?? '') === '1';

/* ----- Validate (first error is returned) ----- */
$error = null;
if (!in_array($title, $allowedTitles, true))                        $error = 'Please select a valid title.';
elseif ($firstName === '' || $lastName === '')                      $error = 'First name and last name are required.';
elseif (!isValidEmail($email))                                      $error = 'Please enter a valid email address.';
elseif (preg_match('/^\+\d{1,4}$/', $phoneCode) !== 1)              $error = 'Please select a valid country code.';
elseif (!isValidPhone($phoneNumber))                                $error = 'Please enter a valid phone number.';
elseif ($country === '')                                            $error = 'Please select your country.';
elseif ($organization === '')                                       $error = 'Organization is required.';
elseif ($designation === '')                                        $error = 'Designation is required.';
elseif (!in_array($category, $allowedCategories, true))             $error = 'Please select a valid category.';
elseif ($tier !== null && (!in_array($tier, $rateTiers, true)
        || !in_array($category, $rateCategories, true)))             $error = 'Please select a valid registration rate.';
elseif ($participantCount === false || $participantCount < 1 || $participantCount > 999
        || floor($participantCount) !== $participantCount)           $error = 'Enter a whole number from 1 to 999.';
elseif (!in_array($type, $allowedTypes, true))                      $error = 'Please select a participation type.';
elseif (!$consent)                                                  $error = 'You must accept the privacy policy to continue.';

if ($error !== null) {
    jsonResponse(false, $error, [], 422);
}

/* ----- Save ----- */
try {
    $pdo = db();

    // Duplicate protection: one registration per email address
    $exists = $pdo->prepare('SELECT id FROM registrations WHERE email = :email LIMIT 1');
    $exists->execute([':email' => $email]);
    if ($exists->fetchColumn() !== false) {
        jsonResponse(false, 'This email address is already registered.', [], 409);
    }

    $reference = generateReference('REG', 'registrations', 'registration_id');

    $insert = $pdo->prepare(
        'INSERT INTO registrations
            (registration_id, title, first_name, last_name, email, phone, country, organization, designation,
             registration_category, registration_tier, participant_count, participation_type, dietary_requirements, message, consent_at, status)
         VALUES
            (:ref, :title, :first, :last, :email, :phone, :country, :org, :designation,
             :category, :tier, :participants, :type, :diet, :message, NOW(), \'Pending\')'
    );
    $insert->execute([
        ':ref'         => $reference,
        ':title'       => $title,
        ':first'       => $firstName,
        ':last'        => $lastName,
        ':email'       => $email,
        ':phone'       => $phoneCode . ' ' . $phoneNumber,
        ':country'     => $country,
        ':org'         => $organization,
        ':designation' => $designation,
        ':category'    => $category,
        ':tier'        => $tier,
        ':participants' => (int) $participantCount,
        ':type'        => $type,
        ':diet'        => $diet !== '' ? $diet : null,
        ':message'     => $message !== '' ? $message : null,
    ]);

    rateLimitHit($rateKey);
    jsonResponse(true, 'Registration received.', ['reference' => $reference]);

} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) {
        jsonResponse(false, 'This email address is already registered.', [], 409);
    }
    logError('Registration failed: ' . $e->getMessage());
    jsonResponse(false, 'Something went wrong. Please try again later.', [], 500);
} catch (Throwable $e) {
    logError('Registration error: ' . $e->getMessage());
    jsonResponse(false, 'Something went wrong. Please try again later.', [], 500);
}
