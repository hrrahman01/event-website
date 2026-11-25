<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireAdmin();                     // not logged in -> redirected to login.php
header('X-Frame-Options: DENY');

$adminName    = (string) ($_SESSION['adminName'] ?? 'Admin');
$adminInitial = mb_strtoupper(mb_substr($adminName, 0, 1));

/**
 * Renders one list section (filters, bulk bar, sortable table, pager).
 * $columns is a list of [label, sortColumn|null].
 */
function renderListSection(string $key, string $title, array $columns, array $statuses, string $categoryLabel): void
{
    ?>
    <section id="view-<?= e($key) ?>" class="adminView d-none" data-entity="<?= e($key) ?>">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-bold mb-0"><?= e($title) ?></h1>
        <button type="button" class="btn btn-outline-primary" data-role="export">
          <i class="fa-solid fa-file-csv me-2"></i>Export CSV
        </button>
      </div>

      <div class="card border-0 shadow-sm"><div class="card-body">
        <div class="row g-2 mb-3">
          <div class="col-lg-4">
            <input type="search" class="form-control" data-role="search" placeholder="Search name, email or reference ID" aria-label="Search">
          </div>
          <div class="col-6 col-lg-2">
            <select class="form-select" data-role="status" aria-label="Filter by status">
              <option value="">All statuses</option>
              <?php foreach ($statuses as $status): ?><option><?= e($status) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-lg-3">
            <select class="form-select" data-role="category" aria-label="Filter by <?= e($categoryLabel) ?>">
              <option value="">All <?= e($categoryLabel) ?></option>
            </select>
          </div>
          <div class="col-6 col-lg-3">
            <select class="form-select" data-role="country" aria-label="Filter by country">
              <option value="">All countries</option>
            </select>
          </div>
          <div class="col-6 col-lg-3">
            <label class="form-label small mb-0">From</label>
            <input type="date" class="form-control" data-role="from">
          </div>
          <div class="col-6 col-lg-3">
            <label class="form-label small mb-0">To</label>
            <input type="date" class="form-control" data-role="to">
          </div>
          <div class="col-lg-3 d-flex align-items-end">
    <button type="button" class="form-control text-center fw-semibold bg-primary text-white" data-role="reset">
        Reset filters
    </button>
</div>
        </div>

        <!-- Bulk actions (visible when rows are selected) -->
        <div class="bulkBar d-none align-items-center flex-wrap gap-2 mb-3" data-role="bulkbar">
          <span><strong data-role="bulkcount">0</strong> selected</span>
          <select class="form-select form-select-sm w-auto" data-role="bulkstatus" aria-label="Bulk status">
            <?php foreach ($statuses as $status): ?><option><?= e($status) ?></option><?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-sm btn-primary" data-role="bulkapply">Apply status</button>
          <button type="button" class="btn btn-sm btn-outline-primary" data-role="bulkexport">Export selected</button>
          <button type="button" class="btn btn-sm btn-outline-danger" data-role="bulkdelete">Delete selected</button>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th><input type="checkbox" class="form-check-input" data-role="selectall" aria-label="Select all rows on this page"></th>
                <th>#</th>
                <?php foreach ($columns as [$label, $sort]): ?>
                  <th>
                    <?php if ($sort !== null): ?>
                      <button type="button" class="sortBtn" data-sort="<?= e($sort) ?>"><?= e($label) ?> <i class="fa-solid fa-sort"></i></button>
                    <?php else: ?><?= e($label) ?><?php endif; ?>
                  </th>
                <?php endforeach; ?>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody data-role="body"></tbody>
          </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <small class="text-body-secondary" data-role="info"></small>
          <div class="btn-group" data-role="pager"></div>
        </div>
      </div></div>
    </section>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
  <title>Admin Panel</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
   <link rel="icon"
          href="../assets/images/favicon/logo_white.png?v=2"
          type="image/png">

  <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body class="adminBody">

  <!-- ================= SIDEBAR ================= -->
  <aside id="adminSidebar" class="adminSidebar" aria-label="Admin navigation">
    <div class="sidebarBrand d-flex align-items-center gap-2 px-4">
      <span class="brandMark d-inline-flex align-items-center justify-content-center rounded-3 bg-warning text-primary fs-5">
        <i class="fa-solid fa-atom" aria-hidden="true"></i>
      </span>
      <span class="text-white fw-bold lh-sm">Conference<small class="d-block fw-normal opacity-75">Admin Panel</small></span>
    </div>

    <nav class="sidebarNav nav flex-column px-3 py-3">
      <a class="sidebarLink active" href="#dashboard" data-view="dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <a class="sidebarLink" href="#registrations" data-view="registrations"><i class="fa-solid fa-users"></i> Registrations</a>
      <a class="sidebarLink" href="#abstracts" data-view="abstracts"><i class="fa-solid fa-file-lines"></i> Abstract Submissions</a>
      <a class="sidebarLink" href="#settings" data-view="settings"><i class="fa-solid fa-sliders"></i> Event Settings</a>
      <a class="sidebarLink" href="#password" data-view="password"><i class="fa-solid fa-key"></i> Change Password</a>

      <form method="post" action="login.php" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="sidebarLink w-100 border-0 bg-transparent text-start"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
      </form>
    </nav>
  </aside>
  <div id="sidebarBackdrop" class="sidebarBackdrop"></div>

  <!-- ================= MAIN ================= -->
  <div class="adminMain">
    <header class="adminTopbar d-flex align-items-center gap-3 px-3 px-lg-4">
      <button type="button" id="sidebarToggle" class="btn btn-light border" aria-label="Toggle sidebar" aria-controls="adminSidebar">
        <i class="fa-solid fa-bars"></i>
      </button>

      <nav aria-label="breadcrumb" class="d-none d-sm-block">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="#dashboard" data-view-link="dashboard" class="text-decoration-none">Admin</a></li>
          <li class="breadcrumb-item active" id="breadcrumbCurrent" aria-current="page">Dashboard</li>
        </ol>
      </nav>

      <div class="ms-auto d-flex align-items-center gap-2">
        <div class="dropdown">
          <button class="btn btn-light border position-relative" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
            <i class="fa-regular fa-bell"></i>
            <span id="notifyCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0 notifyMenu">
            <li><h6 class="dropdown-header">Notifications</h6></li>
            <li><a class="dropdown-item small" href="#registrations" data-view-link="registrations" id="notifyRegs">0 pending registrations</a></li>
            <li><a class="dropdown-item small" href="#abstracts" data-view-link="abstracts" id="notifyAbs">0 abstracts awaiting review</a></li>
          </ul>
        </div>

        <div class="dropdown">
          <button class="btn btn-light border d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatarCircle"><?= e($adminInitial) ?></span>
            <span class="d-none d-md-inline fw-semibold small"><?= e($adminName) ?></span>
            <i class="fa-solid fa-chevron-down small"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0">
            <li><a class="dropdown-item" href="#password" data-view-link="password"><i class="fa-solid fa-key me-2"></i>Change Password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="p-3 p-lg-4">

      <!-- ========== DASHBOARD ========== -->
      <section id="view-dashboard" class="adminView">
        <h1 class="h3 fw-bold mb-4">Dashboard</h1>
        <div class="row g-3 mb-4" id="statCards"></div>

        <div class="row g-3 mb-4">
          <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 fw-bold">Registrations (last 30 days)</h2>
            <div class="chartBox"><canvas id="chartRegTrend" role="img" aria-label="Registration trend"></canvas></div>
          </div></div></div>
          <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 fw-bold">Abstract submissions (last 30 days)</h2>
            <div class="chartBox"><canvas id="chartAbsTrend" role="img" aria-label="Abstract trend"></canvas></div>
          </div></div></div>
          <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 fw-bold">Country-wise participants</h2>
            <div class="chartBox"><canvas id="chartCountry" role="img" aria-label="Participants by country"></canvas></div>
          </div></div></div>
          <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 fw-bold">Registration categories</h2>
            <div class="chartBox"><canvas id="chartCategory" role="img" aria-label="Registrations by category"></canvas></div>
          </div></div></div>
        </div>

        <div class="row g-3">
          <div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Recent registrations</h2>
            <div class="table-responsive"><table class="table align-middle mb-0">
              <thead><tr><th>ID</th><th>Name</th><th>Status</th></tr></thead><tbody id="recentRegs"></tbody>
            </table></div>
          </div></div></div>
          <div class="col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Recent abstract submissions</h2>
            <div class="table-responsive"><table class="table align-middle mb-0">
              <thead><tr><th>ID</th><th>Title</th><th>Status</th></tr></thead><tbody id="recentAbs"></tbody>
            </table></div>
          </div></div></div>
        </div>
      </section>

      <!-- ========== REGISTRATIONS / ABSTRACTS ========== -->
      <?php
      renderListSection('registrations', 'Registrations', [
          ['Reg. ID', 'registration_id'], ['Name', 'first_name'], ['Email', 'email'], ['Phone', null],
          ['Country', 'country'], ['Organization', 'organization'], ['Category', 'registration_category'],
          ['Participation', 'participation_type'], ['Date', 'created_at'], ['Status', 'status'],
      ], ['Pending', 'Confirmed', 'Rejected'], 'categories');

      renderListSection('abstracts', 'Abstract Submissions', [
          ['Abstract ID', 'abstract_id'], ['Author', 'first_name'], ['Email', 'email'], ['Title', 'abstract_title'],
          ['Track', 'research_track'], ['Type', 'presentation_type'], ['Country', 'country'],
          ['Date', 'created_at'], ['Status', 'status'],
      ], ['Pending', 'Under Review', 'Accepted', 'Rejected', 'Revision Required'], 'tracks');
      ?>

      <!-- ========== EVENT SETTINGS ========== -->
      <section id="view-settings" class="adminView d-none">
        <h1 class="h3 fw-bold mb-3">Event Settings</h1>
        <form id="settingsForm" enctype="multipart/form-data" novalidate>
          <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">General</h2>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label fw-semibold" for="s_event_name">Event name</label>
                <input type="text" id="s_event_name" name="event_name" class="form-control" maxlength="150" required></div>
              <div class="col-md-6"><label class="form-label fw-semibold" for="s_event_theme">Conference theme</label>
                <input type="text" id="s_event_theme" name="event_theme" class="form-control" maxlength="200"></div>
              <div class="col-md-6"><label class="form-label fw-semibold" for="s_event_logo">Event logo (JPG, PNG, WebP, max 3 MB)</label>
                <input type="file" id="s_event_logo" name="event_logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                <div class="form-text" id="logoCurrent"></div></div>
              <div class="col-md-3"><label class="form-label fw-semibold" for="s_event_start_date">Start date</label>
                <input type="date" id="s_event_start_date" name="event_start_date" class="form-control" required></div>
              <div class="col-md-3"><label class="form-label fw-semibold" for="s_event_end_date">End date</label>
                <input type="date" id="s_event_end_date" name="event_end_date" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label fw-semibold" for="s_venue">Venue</label>
                <input type="text" id="s_venue" name="venue" class="form-control" maxlength="150"></div>
              <div class="col-md-6"><label class="form-label fw-semibold" for="s_city_country">City and country</label>
                <input type="text" id="s_city_country" name="city_country" class="form-control" maxlength="150"></div>
              <div class="col-md-6"><label class="form-label fw-semibold" for="s_registration_deadline">Registration deadline</label>
                <input type="date" id="s_registration_deadline" name="registration_deadline" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label fw-semibold" for="s_abstract_deadline">Abstract submission deadline</label>
                <input type="date" id="s_abstract_deadline" name="abstract_deadline" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label fw-semibold" for="s_contact_email">Contact email</label>
                <input type="email" id="s_contact_email" name="contact_email" class="form-control" maxlength="120"></div>
              <div class="col-md-6"><label class="form-label fw-semibold" for="s_contact_phone">Contact number</label>
                <input type="tel" id="s_contact_phone" name="contact_phone" class="form-control" maxlength="30"></div>
            </div>
          </div></div>

          <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Hero slides</h2>
            <div class="accordion" id="slideAccordion"></div>
          </div></div>

          <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Submission controls</h2>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="s_registration_open" name="registration_open">
              <label class="form-check-label" for="s_registration_open">Registration is open</label>
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" id="s_abstract_open" name="abstract_open">
              <label class="form-check-label" for="s_abstract_open">Abstract submission is open</label>
            </div>
            <div class="col-md-4 px-0">
              <label class="form-label fw-semibold" for="s_abstract_word_limit">Abstract word limit</label>
              <input type="number" id="s_abstract_word_limit" name="abstract_word_limit" class="form-control" min="50" max="2000">
            </div>
          </div></div>

          <button type="submit" id="settingsSave" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold">Save Settings</button>
        </form>
      </section>

      <!-- ========== CHANGE PASSWORD ========== -->
      <section id="view-password" class="adminView d-none">
        <h1 class="h3 fw-bold mb-3">Change Password</h1>
        <div class="card border-0 shadow-sm passwordCard"><div class="card-body p-4">
          <form id="passwordForm" novalidate>
            <div class="mb-3">
              <label class="form-label fw-semibold" for="pwCurrent">Current password</label>
              <input type="password" id="pwCurrent" name="current_password" class="form-control" autocomplete="current-password" required>
              <div class="invalid-feedback">Enter your current password.</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" for="pwNew">New password</label>
              <input type="password" id="pwNew" name="new_password" class="form-control" autocomplete="new-password" minlength="12" required>
              <div class="form-text">At least 12 characters.</div>
              <div class="invalid-feedback">Password must be at least 12 characters.</div>
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold" for="pwConfirm">Confirm new password</label>
              <input type="password" id="pwConfirm" name="confirm_password" class="form-control" autocomplete="new-password" required>
              <div class="invalid-feedback">Passwords do not match.</div>
            </div>
            <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">Update Password</button>
          </form>
        </div></div>
      </section>

    </main>
  </div>

  <!-- ================= DETAIL / EDIT MODAL ================= -->
  <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
      <div class="modal-content border-0">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold" id="detailTitle">Details</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="detailBody"></div>
        <div class="modal-footer" id="detailFooter"></div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js" defer></script>
  <script src="../assets/js/admin.js" defer></script>
</body>
</html>
