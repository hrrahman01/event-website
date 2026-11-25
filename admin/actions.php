<?php
/**
 * Admin JSON API. Every action requires a logged-in admin.
 * GET actions: stats, counts, list, get, export, download, settings_get
 * POST actions (CSRF required): set_status, delete, update, settings_save, change_password
 */
declare(strict_types=1);

// Background polling must not keep the session alive forever
if (($_GET['action'] ?? '') === 'counts') {
    define('SKIP_ACTIVITY_TOUCH', true);
}

require_once __DIR__ . '/../includes/functions.php';

requireAdmin(true);   // returns JSON 401 when not logged in or session timed out

$adminId = currentAdminId();
$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action  = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';

/* =====================================================
   Configuration (all column names are fixed constants, never user input)
   ===================================================== */
const TITLES        = ['Dr.', 'Prof.', 'Mr.', 'Ms.', 'Other'];
const REG_CATEGORIES = ['Delegate', 'Speaker', 'Student', 'Researcher', 'Academic', 'Industry Professional', 'Business', 'Other'];
const REG_TYPES     = ['In-person', 'Virtual'];
const TRACKS        = ['Life Sciences', 'Physical Sciences', 'Engineering and Technology', 'Medical and Health Sciences',
                       'Environmental Sciences', 'Computer Science and AI', 'Other'];
const PRESENTATIONS = ['Oral Presentation', 'Poster Presentation', 'Virtual Presentation'];

const ENTITIES = [
    'registrations' => [
        'table'    => 'registrations',
        'idColumn' => 'registration_id',
        'label'    => 'Registration',
        'statuses' => ['Pending', 'Confirmed', 'Rejected'],
        'search'   => ["CONCAT(first_name, ' ', last_name)", 'email', 'registration_id', 'organization'],
        'category' => 'registration_category',
        'sortable' => ['id', 'registration_id', 'first_name', 'email', 'country', 'organization',
                       'registration_category', 'participation_type', 'created_at', 'status'],
        'columns'  => 'id, registration_id, title, first_name, last_name, email, phone, country, organization,
                       registration_category, participation_type, status, created_at',
        'export'   => [
            'registration_id' => 'Reference', 'title' => 'Title', 'first_name' => 'First Name', 'last_name' => 'Last Name',
            'email' => 'Email', 'phone' => 'Phone', 'country' => 'Country', 'organization' => 'Organization',
            'designation' => 'Designation', 'registration_category' => 'Category', 'participation_type' => 'Participation',
            'registration_tier' => 'Registration Tier', 'participant_count' => 'Participants',
            'dietary_requirements' => 'Dietary Requirements', 'message' => 'Message', 'status' => 'Status', 'created_at' => 'Submitted',
        ],
    ],
    'abstracts' => [
        'table'    => 'abstracts',
        'idColumn' => 'abstract_id',
        'label'    => 'Abstract',
        'statuses' => ['Pending', 'Under Review', 'Accepted', 'Rejected', 'Revision Required'],
        'search'   => ["CONCAT(first_name, ' ', last_name)", 'email', 'abstract_id', 'abstract_title'],
        'category' => 'research_track',
        'sortable' => ['id', 'abstract_id', 'first_name', 'email', 'abstract_title', 'research_track',
                       'presentation_type', 'country', 'created_at', 'status'],
        'columns'  => 'id, abstract_id, title, first_name, last_name, email, abstract_title, research_track,
                       presentation_type, country, status, created_at',
        'export'   => [
            'abstract_id' => 'Reference', 'title' => 'Title', 'first_name' => 'First Name', 'last_name' => 'Last Name',
            'email' => 'Email', 'phone' => 'Phone', 'country' => 'Country', 'organization' => 'Organization',
            'designation' => 'Designation', 'abstract_title' => 'Abstract Title', 'research_track' => 'Track',
            'presentation_type' => 'Presentation', 'keywords' => 'Keywords', 'co_authors' => 'Co-authors',
            'abstract_description' => 'Description', 'reviewer_notes' => 'Reviewer Notes', 'status' => 'Status', 'created_at' => 'Submitted',
        ],
    ],
];

const SETTING_KEYS = [
    'event_name', 'event_theme', 'event_logo', 'event_start_date', 'event_end_date', 'venue', 'city_country',
    'registration_deadline', 'abstract_deadline', 'contact_email', 'contact_phone',
    'registration_open', 'abstract_open', 'abstract_word_limit',
];

/* =====================================================
   Helpers
   ===================================================== */
function entityConfig(mixed $name): array
{
    if (!is_string($name) || !isset(ENTITIES[$name])) {
        jsonResponse(false, 'Invalid request.', [], 400);
    }
    return ENTITIES[$name];
}

/** Turns "1,2,3" into [1,2,3] (positive integers only, max 500). */
function parseIds(mixed $raw): array
{
    $parts = explode(',', is_string($raw) ? $raw : '');
    $ids   = array_values(array_unique(array_filter(array_map('intval', $parts), static fn (int $i): bool => $i > 0)));
    return array_slice($ids, 0, 500);
}

function placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

function isValidDate(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

/** Builds a WHERE clause from search and filter inputs. Returns [sql, params]. */
function buildWhere(array $cfg, array $src): array
{
    $where  = [];
    $params = [];

    $search = cleanText($src['search'] ?? '', 100);
    if ($search !== '') {
        $like  = '%' . addcslashes($search, '\\%_') . '%';
        $parts = [];
        foreach ($cfg['search'] as $i => $expression) {
            $parts[]          = "$expression LIKE :s$i";
            $params[":s$i"]   = $like;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $status = cleanText($src['status'] ?? '', 30);
    if ($status !== '' && in_array($status, $cfg['statuses'], true)) {
        $where[]            = 'status = :status';
        $params[':status']  = $status;
    }

    $category = cleanText($src['category'] ?? '', 80);
    if ($category !== '') {
        $where[]              = $cfg['category'] . ' = :category';
        $params[':category']  = $category;
    }

    $country = cleanText($src['country'] ?? '', 80);
    if ($country !== '') {
        $where[]             = 'country = :country';
        $params[':country']  = $country;
    }

    $from = cleanText($src['from'] ?? '', 10);
    if ($from !== '' && isValidDate($from)) {
        $where[]          = 'DATE(created_at) >= :dateFrom';
        $params[':dateFrom'] = $from;
    }
    $to = cleanText($src['to'] ?? '', 10);
    if ($to !== '' && isValidDate($to)) {
        $where[]        = 'DATE(created_at) <= :dateTo';
        $params[':dateTo'] = $to;
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

/** Saves an optional uploaded image. Returns the relative web path, or null when no file was chosen. */
function storeImage(string $field, string $relativeDir, string $prefix): ?string
{
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || is_array($file['error'] ?? null) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Image upload failed. Please try again.');
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new InvalidArgumentException('Images must be 3 MB or smaller.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($file['tmp_name']) === false) {
        throw new InvalidArgumentException('Only JPG, PNG or WebP images are allowed.');
    }

    $directory = ROOT_DIR . '/' . $relativeDir;
    if (!is_dir($directory) || !is_writable($directory)) {
        logError('Image folder is not writable: ' . $directory);
        throw new RuntimeException('Image folder is not writable.');
    }

    $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;   // random name, extension from real content
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
        throw new RuntimeException('Could not save the image.');
    }
    return $relativeDir . '/' . $name;
}

/* =====================================================
   GET actions
   ===================================================== */
function dailySeries(string $table): array
{
    $rows = db()->query(
        "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM $table
         WHERE created_at >= (CURDATE() - INTERVAL 29 DAY) GROUP BY DATE(created_at)"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $labels = [];
    $values = [];
    for ($i = 29; $i >= 0; $i--) {
        $stamp    = strtotime("-$i day");
        $labels[] = date('d M', $stamp);
        $values[] = (int) ($rows[date('Y-m-d', $stamp)] ?? 0);
    }
    return ['labels' => $labels, 'values' => $values];
}

function actionStats(): never
{
    $pdo = db();

    $reg = $pdo->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status='Pending'),0) AS pending,
                COALESCE(SUM(status='Confirmed'),0) AS confirmed,
                COALESCE(SUM(status='Rejected'),0) AS rejected
         FROM registrations"
    )->fetch();

    $abs = $pdo->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status='Pending'),0) AS pending,
                COALESCE(SUM(status='Under Review'),0) AS underReview,
                COALESCE(SUM(status='Accepted'),0) AS accepted,
                COALESCE(SUM(status='Rejected'),0) AS rejected,
                COALESCE(SUM(status='Revision Required'),0) AS revision
         FROM abstracts"
    )->fetch();

    jsonResponse(true, '', [
        'registrations'  => array_map('intval', $reg),
        'abstracts'      => array_map('intval', $abs),
        'regTrend'       => dailySeries('registrations'),
        'absTrend'       => dailySeries('abstracts'),
        'countries'      => $pdo->query('SELECT country AS label, COUNT(*) AS total FROM registrations GROUP BY country ORDER BY total DESC, country LIMIT 7')->fetchAll(),
        'categories'     => $pdo->query('SELECT registration_category AS label, COUNT(*) AS total FROM registrations GROUP BY registration_category ORDER BY total DESC')->fetchAll(),
        'recentRegs'     => $pdo->query('SELECT id, registration_id, title, first_name, last_name, status FROM registrations ORDER BY id DESC LIMIT 5')->fetchAll(),
        'recentAbstracts' => $pdo->query('SELECT id, abstract_id, abstract_title, status FROM abstracts ORDER BY id DESC LIMIT 5')->fetchAll(),
    ]);
}

function actionCounts(): never
{
    $row = db()->query(
        "SELECT (SELECT COUNT(*) FROM registrations WHERE status = 'Pending') AS pendingRegs,
                (SELECT COUNT(*) FROM abstracts WHERE status IN ('Pending','Under Review')) AS pendingAbs,
                (SELECT COALESCE(MAX(id),0) FROM registrations) AS lastReg,
                (SELECT COALESCE(MAX(id),0) FROM abstracts) AS lastAbs"
    )->fetch();
    jsonResponse(true, '', array_map('intval', $row));
}

function actionList(): never
{
    $cfg = entityConfig($_GET['entity'] ?? null);
    [$whereSql, $params] = buildWhere($cfg, $_GET);

    $perPage = min(50, max(5, (int) ($_GET['per_page'] ?? 10)));
    $sort    = in_array($_GET['sort'] ?? '', $cfg['sortable'], true) ? $_GET['sort'] : 'created_at';
    $dir     = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

    $count = db()->prepare("SELECT COUNT(*) FROM {$cfg['table']} $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $pages  = max(1, (int) ceil($total / $perPage));
    $page   = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare(
        "SELECT {$cfg['columns']} FROM {$cfg['table']} $whereSql ORDER BY $sort $dir, id DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    $countries = db()->query("SELECT DISTINCT country FROM {$cfg['table']} ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse(true, '', [
        'rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages,
        'perPage' => $perPage, 'countries' => $countries,
    ]);
}

function actionGet(): never
{
    $cfg = entityConfig($_GET['entity'] ?? null);
    $stmt = db()->prepare("SELECT * FROM {$cfg['table']} WHERE id = :id");
    $stmt->execute([':id' => (int) ($_GET['id'] ?? 0)]);
    $row = $stmt->fetch();
    if ($row === false) {
        jsonResponse(false, 'Record not found.', [], 404);
    }
    unset($row['stored_filename']);   // internal storage name is never sent to the browser
    jsonResponse(true, '', ['row' => $row]);
}

function actionExport(): never
{
    global $adminId;
    $name = $_GET['entity'] ?? '';
    $cfg  = entityConfig($name);
    $cols = $cfg['export'];
    $select = implode(', ', array_keys($cols));

    $ids = parseIds($_GET['ids'] ?? '');
    if ($ids) {
        $stmt = db()->prepare("SELECT $select FROM {$cfg['table']} WHERE id IN (" . placeholders($ids) . ') ORDER BY created_at DESC');
        $stmt->execute($ids);
    } else {
        [$whereSql, $params] = buildWhere($cfg, $_GET);
        $stmt = db()->prepare("SELECT $select FROM {$cfg['table']} $whereSql ORDER BY created_at DESC LIMIT 10000");
        $stmt->execute($params);
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel reads accents correctly
    fputcsv($out, array_values($cols), ',', '"', '');
    while ($row = $stmt->fetch()) {
        fputcsv($out, array_map('csvSafeValue', $row), ',', '"', '');   // blocks formula injection
    }
    fclose($out);

    logActivity($adminId, 'export', $name, null, 'Exported ' . $name . ' to CSV');
    exit;
}

function actionDownload(): never
{
    global $adminId;
    $stmt = db()->prepare('SELECT abstract_id, stored_filename, original_filename FROM abstracts WHERE id = :id');
    $stmt->execute([':id' => (int) ($_GET['id'] ?? 0)]);
    $row = $stmt->fetch();

    // Only our own random filenames are ever read from the private folder
    if ($row === false || preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx)$/', (string) $row['stored_filename'], $m) !== 1) {
        http_response_code(404);
        exit('File not found.');
    }
    $path = ABSTRACT_DIR . '/' . $row['stored_filename'];
    if (!is_file($path)) {
        http_response_code(404);
        exit('File not found.');
    }

    $mimeByExt = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $downloadName = (string) $row['original_filename'];
    $asciiName    = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: 'abstract.' . $m[1];

    header('Content-Type: ' . $mimeByExt[$m[1]]);
    header("Content-Disposition: attachment; filename=\"$asciiName\"; filename*=UTF-8''" . rawurlencode($downloadName));
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');

    logActivity($adminId, 'download', 'abstract', (string) $row['abstract_id'], 'Downloaded abstract file');
    readfile($path);
    exit;
}

function actionSettingsGet(): never
{
    $settings = array_intersect_key(getSettings(true), array_flip(SETTING_KEYS));
    $slides   = db()->query(
        'SELECT id, heading, subheading, description, image_path, primary_button_text, secondary_button_text
         FROM hero_slides ORDER BY display_order, id'
    )->fetchAll();
    jsonResponse(true, '', ['settings' => $settings, 'slides' => $slides]);
}

/* =====================================================
   POST actions
   ===================================================== */
function actionSetStatus(): never
{
    global $adminId;
    $name   = $_POST['entity'] ?? '';
    $cfg    = entityConfig($name);
    $status = cleanText($_POST['status'] ?? '', 30);
    $ids    = parseIds($_POST['ids'] ?? '');

    if (!$ids || !in_array($status, $cfg['statuses'], true)) {
        jsonResponse(false, 'Invalid request.', [], 422);
    }

    $stmt = db()->prepare("UPDATE {$cfg['table']} SET status = ? WHERE id IN (" . placeholders($ids) . ')');
    $stmt->execute(array_merge([$status], $ids));

    logActivity($adminId, 'status_change', $name, implode(',', $ids), 'Set status to ' . $status . ' for ' . count($ids) . ' record(s)');
    jsonResponse(true, count($ids) . ' record(s) updated.');
}

function actionDelete(): never
{
    global $adminId;
    $name = $_POST['entity'] ?? '';
    $cfg  = entityConfig($name);
    $ids  = parseIds($_POST['ids'] ?? '');
    if (!$ids) {
        jsonResponse(false, 'Nothing selected.', [], 422);
    }

    $pdo   = db();
    $files = [];
    if ($name === 'abstracts') {
        $stmt = $pdo->prepare('SELECT stored_filename FROM abstracts WHERE id IN (' . placeholders($ids) . ')');
        $stmt->execute($ids);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $pdo->prepare("DELETE FROM {$cfg['table']} WHERE id IN (" . placeholders($ids) . ')')->execute($ids);

    foreach ($files as $file) {   // remove uploaded files together with their rows
        if (preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx)$/', (string) $file) === 1) {
            @unlink(ABSTRACT_DIR . '/' . $file);
        }
    }

    logActivity($adminId, 'delete', $name, implode(',', $ids), 'Deleted ' . count($ids) . ' record(s)');
    jsonResponse(true, count($ids) . ' record(s) deleted.');
}

function actionUpdate(): never
{
    global $adminId;
    $name   = $_POST['entity'] ?? '';
    $cfg    = entityConfig($name);
    $id     = (int) ($_POST['id'] ?? 0);
    $status = cleanText($_POST['status'] ?? '', 30);

    $exists = db()->prepare("SELECT id FROM {$cfg['table']} WHERE id = :id");
    $exists->execute([':id' => $id]);
    if ($exists->fetchColumn() === false) {
        jsonResponse(false, 'Record not found.', [], 404);
    }
    if (!in_array($status, $cfg['statuses'], true)) {
        jsonResponse(false, 'Invalid status.', [], 422);
    }

    if ($name === 'registrations') {
        $d = [
            'title'                 => cleanText($_POST['title'] ?? '', 20),
            'first_name'            => cleanText($_POST['first_name'] ?? '', 60),
            'last_name'             => cleanText($_POST['last_name'] ?? '', 60),
            'phone'                 => cleanText($_POST['phone'] ?? '', 30),
            'country'               => cleanText($_POST['country'] ?? '', 80),
            'organization'          => cleanText($_POST['organization'] ?? '', 150),
            'designation'           => cleanText($_POST['designation'] ?? '', 100),
            'registration_category' => cleanText($_POST['registration_category'] ?? '', 40),
            'participation_type'    => cleanText($_POST['participation_type'] ?? '', 20),
            'dietary_requirements'  => cleanText($_POST['dietary_requirements'] ?? '', 150),
            'message'               => cleanText($_POST['message'] ?? '', 1000, true),
        ];
        if (!in_array($d['title'], TITLES, true) || !in_array($d['registration_category'], REG_CATEGORIES, true)
            || !in_array($d['participation_type'], REG_TYPES, true)) {
            jsonResponse(false, 'Please choose valid title, category and participation type.', [], 422);
        }
        foreach (['first_name', 'last_name', 'phone', 'country', 'organization', 'designation'] as $required) {
            if ($d[$required] === '') {
                jsonResponse(false, 'Please fill all required fields.', [], 422);
            }
        }
        $d['dietary_requirements'] = $d['dietary_requirements'] !== '' ? $d['dietary_requirements'] : null;
        $d['message']              = $d['message'] !== '' ? $d['message'] : null;
    } else {
        $d = [
            'abstract_title'       => cleanText($_POST['abstract_title'] ?? '', 200),
            'research_track'       => cleanText($_POST['research_track'] ?? '', 80),
            'presentation_type'    => cleanText($_POST['presentation_type'] ?? '', 40),
            'co_authors'           => cleanText($_POST['co_authors'] ?? '', 500, true),
            'abstract_description' => cleanText($_POST['abstract_description'] ?? '', 20000, true),
            'keywords'             => cleanText($_POST['keywords'] ?? '', 200),
            'reviewer_notes'       => cleanText($_POST['reviewer_notes'] ?? '', 2000, true),
        ];
        if (!in_array($d['research_track'], TRACKS, true) || !in_array($d['presentation_type'], PRESENTATIONS, true)) {
            jsonResponse(false, 'Please choose a valid track and presentation type.', [], 422);
        }
        foreach (['abstract_title', 'abstract_description', 'keywords'] as $required) {
            if ($d[$required] === '') {
                jsonResponse(false, 'Please fill all required fields.', [], 422);
            }
        }
        $d['co_authors']     = $d['co_authors'] !== '' ? $d['co_authors'] : null;
        $d['reviewer_notes'] = $d['reviewer_notes'] !== '' ? $d['reviewer_notes'] : null;
    }

    $d['status'] = $status;
    $sets = implode(', ', array_map(static fn (string $col): string => "$col = :$col", array_keys($d)));
    $stmt = db()->prepare("UPDATE {$cfg['table']} SET $sets WHERE id = :id");
    $stmt->execute(array_merge(array_combine(array_map(static fn ($k) => ":$k", array_keys($d)), array_values($d)), [':id' => $id]));

    logActivity($adminId, 'update', $name, (string) $id, $cfg['label'] . ' updated');
    jsonResponse(true, $cfg['label'] . ' updated.');
}

function actionSettingsSave(): never
{
    global $adminId;

    $values = [
        'event_name'            => cleanText($_POST['event_name'] ?? '', 150),
        'event_theme'           => cleanText($_POST['event_theme'] ?? '', 200),
        'venue'                 => cleanText($_POST['venue'] ?? '', 150),
        'city_country'          => cleanText($_POST['city_country'] ?? '', 150),
        'contact_email'         => strtolower(cleanText($_POST['contact_email'] ?? '', 120)),
        'contact_phone'         => cleanText($_POST['contact_phone'] ?? '', 30),
        'event_start_date'      => cleanText($_POST['event_start_date'] ?? '', 10),
        'event_end_date'        => cleanText($_POST['event_end_date'] ?? '', 10),
        'registration_deadline' => cleanText($_POST['registration_deadline'] ?? '', 10),
        'abstract_deadline'     => cleanText($_POST['abstract_deadline'] ?? '', 10),
        'registration_open'     => ($_POST['registration_open'] ?? '0') === '1' ? '1' : '0',
        'abstract_open'         => ($_POST['abstract_open'] ?? '0') === '1' ? '1' : '0',
        'abstract_word_limit'   => (string) (int) ($_POST['abstract_word_limit'] ?? 300),
    ];

    if ($values['event_name'] === '') {
        throw new InvalidArgumentException('Event name is required.');
    }
    if ($values['contact_email'] !== '' && !isValidEmail($values['contact_email'])) {
        throw new InvalidArgumentException('Contact email is not valid.');
    }
    foreach (['event_start_date', 'event_end_date', 'registration_deadline', 'abstract_deadline'] as $dateKey) {
        if (!isValidDate($values[$dateKey])) {
            throw new InvalidArgumentException('Please enter valid dates.');
        }
    }
    if ($values['event_end_date'] < $values['event_start_date']) {
        throw new InvalidArgumentException('The end date cannot be before the start date.');
    }
    if ((int) $values['abstract_word_limit'] < 50 || (int) $values['abstract_word_limit'] > 2000) {
        throw new InvalidArgumentException('Word limit must be between 50 and 2000.');
    }

    // Images are validated and stored first, then the database is updated in one transaction
    $logoPath = storeImage('event_logo', 'assets/images', 'logo');
    if ($logoPath !== null) {
        $values['event_logo'] = $logoPath;
    }

    $slides = db()->query('SELECT id, image_path FROM hero_slides ORDER BY display_order, id')->fetchAll();
    $slideData = [];
    foreach ($slides as $slide) {
        $sid     = (int) $slide['id'];
        $heading = cleanText($_POST["slide_heading_$sid"] ?? '', 150);
        if ($heading === '') {
            throw new InvalidArgumentException("Slide heading is required (slide $sid).");
        }
        $slideData[$sid] = [
            'heading'     => $heading,
            'subheading'  => cleanText($_POST["slide_subheading_$sid"] ?? '', 200),
            'description' => cleanText($_POST["slide_description_$sid"] ?? '', 500, true),
            'primary'     => cleanText($_POST["slide_primary_$sid"] ?? '', 50),
            'secondary'   => cleanText($_POST["slide_secondary_$sid"] ?? '', 50),
            'image'       => storeImage("slide_image_$sid", 'assets/images/hero', "slide-$sid") ?? $slide['image_path'],
        ];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($values as $key => $value) {
            saveSetting($key, $value);
        }
        $update = $pdo->prepare(
            'UPDATE hero_slides SET heading = :heading, subheading = :sub, description = :descr, image_path = :image,
                    primary_button_text = :primary, secondary_button_text = :secondary WHERE id = :id'
        );
        foreach ($slideData as $sid => $s) {
            $update->execute([
                ':heading' => $s['heading'], ':sub' => $s['subheading'], ':descr' => $s['description'],
                ':image' => $s['image'], ':primary' => $s['primary'], ':secondary' => $s['secondary'], ':id' => $sid,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    logActivity($adminId, 'settings_update', 'settings', null, 'Event settings updated');
    jsonResponse(true, 'Settings saved.');
}

function actionChangePassword(): never
{
    global $adminId;
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new     = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    $rateKey = rateLimitKey('password-change', (string) $adminId);
    if (rateLimitExceeded($rateKey, 5, 900)) {
        jsonResponse(false, 'Too many attempts. Please try again in 15 minutes.', [], 429);
    }
    if (strlen($new) < 12 || strlen($new) > 200) {
        jsonResponse(false, 'The new password must be at least 12 characters.', [], 422);
    }
    if (!hash_equals($new, $confirm)) {
        jsonResponse(false, 'The new passwords do not match.', [], 422);
    }

    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = :id');
    $stmt->execute([':id' => $adminId]);
    $hash = $stmt->fetchColumn();

    if (!is_string($hash) || !password_verify($current, $hash)) {
        rateLimitHit($rateKey);
        jsonResponse(false, 'Your current password is incorrect.', [], 422);
    }

    db()->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id')
        ->execute([':hash' => password_hash($new, PASSWORD_DEFAULT), ':id' => $adminId]);

    session_regenerate_id(true);
    rateLimitClear($rateKey);
    logActivity($adminId, 'password_change', 'admin', (string) $adminId, 'Password changed');
    jsonResponse(true, 'Password updated.');
}

/* =====================================================
   Dispatcher
   ===================================================== */
try {
    $getActions  = ['stats', 'counts', 'list', 'get', 'export', 'download', 'settings_get'];
    $postActions = ['set_status', 'delete', 'update', 'settings_save', 'change_password'];

    if (in_array($action, $getActions, true)) {
        if ($method !== 'GET') {
            jsonResponse(false, 'Method not allowed.', [], 405);
        }
    } elseif (in_array($action, $postActions, true)) {
        requirePost();
        requireCsrf();
    } else {
        jsonResponse(false, 'Unknown action.', [], 404);
    }

    match ($action) {
        'stats'           => actionStats(),
        'counts'          => actionCounts(),
        'list'            => actionList(),
        'get'             => actionGet(),
        'export'          => actionExport(),
        'download'        => actionDownload(),
        'settings_get'    => actionSettingsGet(),
        'set_status'      => actionSetStatus(),
        'delete'          => actionDelete(),
        'update'          => actionUpdate(),
        'settings_save'   => actionSettingsSave(),
        'change_password' => actionChangePassword(),
    };
} catch (InvalidArgumentException $e) {
    jsonResponse(false, $e->getMessage(), [], 422);
} catch (Throwable $e) {
    logError('Admin action "' . $action . '" failed: ' . $e->getMessage());
    jsonResponse(false, 'Something went wrong. Please try again.', [], 500);
}
