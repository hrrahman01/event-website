<?php
/**
 * Abstract submission endpoint.
 *   GET  -> returns a CSRF token, open/closed status and the word limit
 *   POST -> validates the form and the uploaded file, then saves both
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

/* ---------- GET: token + status ---------- */
if ($method === 'GET') {
    $open = isSubmissionOpen('abstract');
    jsonResponse(true, $open ? '' : 'Abstract submission is currently closed.', [
        'token'     => csrfToken(),
        'open'      => $open,
        'wordLimit' => (int) getSetting('abstract_word_limit', '300'),
    ]);
}

/* ---------- POST ---------- */
requirePost();
requireCsrf();

// Honeypot
if (cleanText($_POST['website'] ?? '', 100) !== '') {
    jsonResponse(true, 'Thank you.', ['reference' => 'ABS-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)))]);
}

// Enforced on the server so direct API calls cannot bypass the admin switch or deadline
if (!isSubmissionOpen('abstract')) {
    jsonResponse(false, 'Abstract submission is currently closed.', [], 403);
}

$rateKey = rateLimitKey('abstract');
if (rateLimitExceeded($rateKey, FORM_MAX_PER_HOUR, 3600)) {
    jsonResponse(false, 'Too many submissions. Please try again later.', [], 429);
}

/* =====================================================
   File validation helpers
   ===================================================== */
const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx'];

/** Real content type per extension (detected from file bytes, not from the browser). */
const ALLOWED_MIME_TYPES = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
];

function uploadErrorMessage(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is too large. Maximum size is 5 MB.',
        UPLOAD_ERR_PARTIAL                        => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE                        => 'Please upload your abstract file.',
        default                                   => 'The file could not be uploaded. Please try again.',
    };
}

/** Checks the first bytes of the file (magic numbers). */
function hasValidSignature(string $path, string $extension): bool
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $head = (string) fread($handle, 8);
    fclose($handle);

    return match ($extension) {
        'pdf'  => str_starts_with($head, '%PDF-'),
        'doc'  => str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),   // legacy Office container
        'docx' => str_starts_with($head, "PK\x03\x04"),                          // zip container
        default => false,
    };
}

/** A real .docx is a zip that contains word/document.xml. */
function isRealDocx(string $path): bool
{
    if (!class_exists('ZipArchive')) {
        return true;   // signature check already passed; enable php_zip for the stricter check
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return false;
    }
    $valid = $zip->locateName('word/document.xml') !== false;
    $zip->close();
    return $valid;
}

/** Display-only name: no paths, no control characters. The stored name is always random. */
function safeDisplayName(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[^\p{L}\p{N}._ \-()]/u', '', $name) ?? '';
    return mb_substr(trim($name), 0, 150) ?: 'abstract';
}

/* =====================================================
   Collect and validate form fields
   ===================================================== */
$allowedTitles = ['Dr.', 'Prof.', 'Mr.', 'Ms.', 'Other'];
$allowedTracks = [
    'Life Sciences', 'Physical Sciences', 'Engineering and Technology',
    'Medical and Health Sciences', 'Environmental Sciences', 'Computer Science and AI', 'Other',
];
$allowedPresentations = ['Oral Presentation', 'Poster Presentation', 'Virtual Presentation'];

$title        = cleanText($_POST['title'] ?? '', 20);
$firstName    = cleanText($_POST['first_name'] ?? '', 60);
$lastName     = cleanText($_POST['last_name'] ?? '', 60);
$email        = strtolower(cleanText($_POST['email'] ?? '', 120));
$phoneCode    = cleanText($_POST['phone_code'] ?? '', 6);
$phoneNumber  = cleanText($_POST['phone'] ?? '', 15);
$country      = cleanText($_POST['country'] ?? '', 80);
$organization = cleanText($_POST['organization'] ?? '', 150);
$designation  = cleanText($_POST['designation'] ?? '', 100);
$abstractName = cleanText($_POST['abstract_title'] ?? '', 200);
$track        = cleanText($_POST['research_track'] ?? '', 80);
$presentation = cleanText($_POST['presentation_type'] ?? '', 40);
$coAuthors    = cleanText($_POST['co_authors'] ?? '', 500, true);
$description  = cleanText($_POST['abstract_description'] ?? '', 20000, true);
$keywords     = cleanText($_POST['keywords'] ?? '', 200);
$consent      = ($_POST['consent'] ?? '') === '1';
$wordLimit    = max(50, (int) getSetting('abstract_word_limit', '300'));

$error = null;
if (!in_array($title, $allowedTitles, true))                        $error = 'Please select a valid title.';
elseif ($firstName === '' || $lastName === '')                      $error = 'First name and last name are required.';
elseif (!isValidEmail($email))                                      $error = 'Please enter a valid email address.';
elseif (preg_match('/^\+\d{1,4}$/', $phoneCode) !== 1)              $error = 'Please select a valid country code.';
elseif (!isValidPhone($phoneNumber))                                $error = 'Please enter a valid phone number.';
elseif ($country === '')                                            $error = 'Please select your country.';
elseif ($organization === '')                                       $error = 'Organization is required.';
elseif ($designation === '')                                        $error = 'Designation is required.';
elseif ($abstractName === '')                                       $error = 'Abstract title is required.';
elseif (!in_array($track, $allowedTracks, true))                    $error = 'Please select a valid research track.';
elseif (!in_array($presentation, $allowedPresentations, true))      $error = 'Please select a presentation type.';
elseif ($description === '')                                        $error = 'Abstract description is required.';
elseif (wordCount($description) > $wordLimit)                       $error = "Abstract description must not exceed $wordLimit words.";
elseif ($keywords === '')                                           $error = 'Please enter at least one keyword.';
elseif (!$consent)                                                  $error = 'You must accept the declaration to continue.';

if ($error !== null) {
    jsonResponse(false, $error, [], 422);
}

/* =====================================================
   Validate the uploaded file
   ===================================================== */
$upload = $_FILES['abstract_file'] ?? null;
if (!is_array($upload) || is_array($upload['error'] ?? null)) {
    jsonResponse(false, 'Please upload your abstract file.', [], 422);
}
if ($upload['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, uploadErrorMessage((int) $upload['error']), [], 422);
}
if (!is_uploaded_file($upload['tmp_name'])) {
    jsonResponse(false, 'Invalid upload.', [], 400);
}

$fileSize = (int) $upload['size'];
if ($fileSize <= 0 || $fileSize > MAX_UPLOAD_BYTES) {
    jsonResponse(false, 'The file must be between 1 byte and 5 MB.', [], 422);
}

$originalName = safeDisplayName((string) $upload['name']);
$extension    = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
    jsonResponse(false, 'Only PDF, DOC and DOCX files are allowed.', [], 422);
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string) $finfo->file($upload['tmp_name']);

if (!in_array($mimeType, ALLOWED_MIME_TYPES[$extension], true)
    || !hasValidSignature($upload['tmp_name'], $extension)
    || ($extension === 'docx' && !isRealDocx($upload['tmp_name']))) {
    jsonResponse(false, 'The file content does not match its type. Please upload a valid PDF, DOC or DOCX.', [], 422);
}

/* =====================================================
   Save file (private folder) and database row
   ===================================================== */
$storedName  = bin2hex(random_bytes(16)) . '.' . $extension;   // random, never the user's name
$destination = ABSTRACT_DIR . '/' . $storedName;

try {
    $pdo = db();

    // Duplicate protection: same email + same abstract title
    $exists = $pdo->prepare('SELECT id FROM abstracts WHERE email = :email AND abstract_title = :title LIMIT 1');
    $exists->execute([':email' => $email, ':title' => $abstractName]);
    if ($exists->fetchColumn() !== false) {
        jsonResponse(false, 'You have already submitted an abstract with this title.', [], 409);
    }

    if (!is_dir(ABSTRACT_DIR) || !is_writable(ABSTRACT_DIR)) {
        logError('Abstract storage folder is missing or not writable: ' . ABSTRACT_DIR);
        jsonResponse(false, 'Something went wrong. Please try again later.', [], 500);
    }

    if (!move_uploaded_file($upload['tmp_name'], $destination)) {
        logError('move_uploaded_file failed for ' . $destination);
        jsonResponse(false, 'The file could not be saved. Please try again.', [], 500);
    }
    @chmod($destination, 0640);

    try {
        $reference = generateReference('ABS', 'abstracts', 'abstract_id');

        $insert = $pdo->prepare(
            'INSERT INTO abstracts
                (abstract_id, title, first_name, last_name, email, phone, country, organization, designation,
                 abstract_title, research_track, presentation_type, co_authors, abstract_description, keywords,
                 original_filename, stored_filename, file_mime_type, file_size, consent_at, status)
             VALUES
                (:ref, :title, :first, :last, :email, :phone, :country, :org, :designation,
                 :abstractTitle, :track, :presentation, :coAuthors, :description, :keywords,
                 :originalName, :storedName, :mime, :size, NOW(), \'Pending\')'
        );
        $insert->execute([
            ':ref'           => $reference,
            ':title'         => $title,
            ':first'         => $firstName,
            ':last'          => $lastName,
            ':email'         => $email,
            ':phone'         => $phoneCode . ' ' . $phoneNumber,
            ':country'       => $country,
            ':org'           => $organization,
            ':designation'   => $designation,
            ':abstractTitle' => $abstractName,
            ':track'         => $track,
            ':presentation'  => $presentation,
            ':coAuthors'     => $coAuthors !== '' ? $coAuthors : null,
            ':description'   => $description,
            ':keywords'      => $keywords,
            ':originalName'  => $originalName,
            ':storedName'    => $storedName,
            ':mime'          => $mimeType,
            ':size'          => $fileSize,
        ]);
    } catch (Throwable $inner) {
        @unlink($destination);   // do not leave an orphan file if the database insert fails
        throw $inner;
    }

    rateLimitHit($rateKey);
    jsonResponse(true, 'Abstract submitted.', ['reference' => $reference]);

} catch (Throwable $e) {
    logError('Abstract submission failed: ' . $e->getMessage());
    jsonResponse(false, 'Something went wrong. Please try again later.', [], 500);
}
