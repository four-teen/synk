<?php 
    session_start();
    ob_start();
    include '../backend/db.php';

    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../index.php');
        exit;
    }

    $collegeOptionsHtml = '';
    $collegeQuery = $conn->query("
        SELECT
            c.college_id,
            c.college_code,
            c.college_name,
            cp.campus_name
        FROM tbl_college c
        LEFT JOIN tbl_campus cp ON cp.campus_id = c.campus_id
        WHERE c.status = 'active'
        ORDER BY cp.campus_name ASC, c.college_name ASC, c.college_code ASC
    ");

    while ($collegeRow = $collegeQuery->fetch_assoc()) {
        $label = trim((string)$collegeRow['college_code']);
        $collegeName = trim((string)$collegeRow['college_name']);
        $campusName = trim((string)($collegeRow['campus_name'] ?? ''));

        if ($label !== '' && $collegeName !== '') {
            $label .= ' - ' . $collegeName;
        } elseif ($collegeName !== '') {
            $label = $collegeName;
        }

        if ($campusName !== '') {
            $label .= ' (' . $campusName . ')';
        }

        $collegeOptionsHtml .= "<option value='" . (int)$collegeRow['college_id'] . "'>" .
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
            "</option>";
    }
?>
<!DOCTYPE html>

<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free"
>
<head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>User Accounts | Synk</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
      .accounts-header {
        gap: 1rem;
      }

      .accounts-header .btn {
        white-space: nowrap;
      }

      .accounts-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        width: 100%;
        max-width: 32rem;
        margin-left: auto;
        min-width: 0;
      }

      .accounts-card-top {
        gap: 1rem;
      }

      .accounts-card-copy {
        min-width: 0;
      }

      .accounts-search-group {
        flex: 1 1 18rem;
        min-width: 0;
      }

      .accounts-search-group .input-group-text {
        border-right: 0;
        background: #fff;
      }

      .accounts-search-group .form-control {
        border-left: 0;
      }

      .accounts-search-group .form-control:focus {
        box-shadow: none;
      }

      #btnClearAccountSearch {
        flex: 0 0 auto;
        white-space: nowrap;
      }

      .accounts-table-wrap {
        border: 1px solid rgba(105, 108, 255, 0.08);
        border-radius: 1rem;
        overflow: hidden;
      }

      #accountsTable td {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
      }

      #accountsTable tbody tr:last-child td {
        border-bottom: 0;
      }

      #accountsTable td:last-child {
        white-space: nowrap !important;
      }

      .account-mobile-list {
        display: grid;
        gap: 1rem;
      }

      .account-mobile-card {
        border: 1px solid rgba(105, 108, 255, 0.12);
        border-radius: 1rem;
        box-shadow: 0 10px 24px rgba(67, 89, 113, 0.08);
      }

      .account-mobile-card .card-body {
        padding: 1rem;
      }

      .account-mobile-top {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
      }

      .account-mobile-index {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        height: 2rem;
        padding: 0 0.65rem;
        border-radius: 999px;
        background: rgba(105, 108, 255, 0.12);
        color: #696cff;
        font-size: 0.8rem;
        font-weight: 700;
      }

      .account-mobile-name {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #435971;
      }

      .account-mobile-email {
        color: #566a7f;
        font-size: 0.9rem;
        word-break: break-word;
      }

      .account-mobile-badges {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.4rem;
      }

      .account-mobile-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(67, 89, 113, 0.08);
      }

      .account-mobile-meta-label {
        display: block;
        margin-bottom: 0.25rem;
        color: #8592a3;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
      }

      .account-mobile-meta-value {
        color: #435971;
        font-size: 0.92rem;
        font-weight: 600;
        word-break: break-word;
      }

      .account-mobile-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
      }

      .account-mobile-actions .btn {
        flex: 1 1 0;
      }

      .accounts-empty-state {
        border: 1px dashed rgba(67, 89, 113, 0.2);
        border-radius: 1rem;
        padding: 1.25rem;
        text-align: center;
        color: #8592a3;
        background: rgba(245, 246, 255, 0.7);
      }

      .college-access-select {
        min-height: 11rem;
      }

      .select2-container {
        width: 100% !important;
      }

      .select2-container--default .select2-selection--multiple {
        min-height: 3rem;
        border: 1px solid #d9dee3;
        border-radius: 0.5rem;
        padding: 0.35rem 0.5rem;
      }

      .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #696cff;
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.16);
      }

      .select2-container--default .select2-selection--multiple .select2-selection__choice {
        margin-top: 0.3rem;
        background: rgba(105, 108, 255, 0.1);
        border: 0;
        border-radius: 999px;
        color: #435971;
        padding: 0.2rem 0.65rem;
      }

      .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #696cff;
        margin-right: 0.35rem;
      }

      .select2-dropdown {
        border: 1px solid #d9dee3;
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 0.5rem 1.2rem rgba(67, 89, 113, 0.16);
      }

      .college-access-dropdown {
        min-width: 0 !important;
      }

      .college-access-dropdown .select2-results {
        max-height: 18rem;
        overflow-y: auto;
      }

      .select2-search--dropdown .select2-search__field {
        border: 1px solid #d9dee3;
        border-radius: 0.5rem;
        padding: 0.45rem 0.65rem;
      }

      .select2-results__option {
        padding: 0.55rem 0.75rem;
      }

      @media (max-width: 767.98px) {
        .accounts-header {
          align-items: stretch !important;
        }

        .accounts-header .btn {
          width: 100%;
        }

        .accounts-toolbar {
          width: 100%;
          max-width: none;
        }

        .accounts-search-group {
          flex-basis: 100%;
        }

        .account-mobile-top {
          flex-direction: column;
        }

        .account-mobile-badges {
          justify-content: flex-start;
        }

        .account-mobile-grid {
          grid-template-columns: 1fr;
        }

        .account-mobile-actions {
          flex-direction: column;
        }
      }
    </style>
</head>

<body>
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>
    <!-- /Sidebar -->

    <!-- Layout page -->
    <div class="layout-page">

      <!-- Navbar -->
      <?php include 'navbar.php'; ?>
      <!-- /Navbar -->

      <!-- Content wrapper -->
      <div class="content-wrapper">

        <div class="container-xxl flex-grow-1 container-p-y">

          <!-- Title + Add Button -->
          <div class="accounts-header d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
            <h4 class="fw-bold mb-0">
              <i class="bx bx-user-circle me-2"></i> Access Accounts
            </h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
              <i class="bx bx-plus"></i> Add Account
            </button>
          </div>

          <!-- Card: Accounts List -->
          <div class="card">
            <div class="card-header">
              <div class="accounts-card-top d-flex flex-column flex-xl-row justify-content-between align-items-xl-center">
                <div class="accounts-card-copy">
                  <h5 class="m-0">Accounts List</h5>
                  <small class="text-muted">Search and manage administrator and scheduler access accounts</small>
                </div>
                <div class="accounts-toolbar align-items-stretch">
                  <div class="input-group accounts-search-group">
                    <span class="input-group-text">
                      <i class="bx bx-search"></i>
                    </span>
                    <input
                      type="search"
                      id="accountSearch"
                      class="form-control"
                      placeholder="Search name, email, role, college access"
                      autocomplete="off"
                    >
                  </div>
                  <button type="button" class="btn btn-outline-secondary" id="btnClearAccountSearch">
                    Clear
                  </button>
                </div>
              </div>
              <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mt-3">
                <small class="text-muted" id="accountsMeta">Loading accounts...</small>
                <small class="text-muted" id="accountsScrollHint">Scroll to load more</small>
              </div>
            </div>

            <div class="card-body p-3">
              <div class="accounts-table-wrap d-none d-lg-block">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0" id="accountsTable">
                    <thead>
                      <tr>
                        <th style="width:60px;">#</th>
                        <th>Display Name</th>
                        <th>Email</th>
                        <th>Access</th>
                        <th>Role</th>
                        <th>College Access</th>
                        <th style="width:120px;">Status</th>
                        <th class="text-end" style="width:140px;">Actions</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>

              <div class="account-mobile-list d-lg-none" id="accountsMobileList"></div>
            </div>
          </div>

        </div>

        <!-- Footer -->
        <?php include '../footer.php'; ?>
        <!-- /Footer -->

        <div class="content-backdrop fade"></div>
      </div>
      <!-- /Content wrapper -->

    </div>
    <!-- /Layout page -->

  </div>

  <div class="layout-overlay layout-menu-toggle"></div>
</div>

<!-- ========================= -->
<!-- ADD ACCOUNT MODAL         -->
<!-- ========================= -->
<div class="modal fade" id="addAccountModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Add Google Access Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="addAccountForm">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Display Name <span class="text-danger">*</span></label>
              <input type="text" name="username" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">SKSU Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" placeholder="name@sksu.edu.ph" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" id="add_role" class="form-select" required>
                <option value="">Select Role</option>
                <option value="admin">Administrator</option>
                <option value="scheduler">Scheduler</option>
              </select>
            </div>

            <div class="col-md-12" id="add_college_wrapper" style="display:none;">
              <label class="form-label">Managed Colleges <span class="text-danger">*</span></label>
              <select name="college_ids[]" id="add_college_ids" class="form-select college-access-select" multiple>
                <?= $collegeOptionsHtml ?>
              </select>
              <small class="text-muted">Select every college this scheduler is allowed to manage.</small>
            </div>

            <div class="col-md-12" id="add_default_college_wrapper" style="display:none;">
              <label class="form-label">Default College on Login <span class="text-danger">*</span></label>
              <select name="default_college_id" id="add_default_college_id" class="form-select">
                <option value="">Select Default College</option>
              </select>
              <small class="text-muted">The scheduler can switch later, but this college opens first after sign in.</small>
            </div>

            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnSaveAccount">Save</button>
      </div>

    </div>
  </div>
</div>

<!-- ========================= -->
<!-- EDIT ACCOUNT MODAL        -->
<!-- ========================= -->
<div class="modal fade" id="editAccountModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit Google Access Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="editAccountForm">
          <input type="hidden" name="user_id" id="edit_user_id">

          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Display Name <span class="text-danger">*</span></label>
              <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">SKSU Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" id="edit_role" class="form-select" required>
                <option value="">Select Role</option>
                <option value="admin">Administrator</option>
                <option value="scheduler">Scheduler</option>
              </select>
            </div>

            <div class="col-md-12" id="edit_college_wrapper" style="display:none;">
              <label class="form-label">Managed Colleges <span class="text-danger">*</span></label>
              <select name="college_ids[]" id="edit_college_ids" class="form-select college-access-select" multiple>
                <?= $collegeOptionsHtml ?>
              </select>
              <small class="text-muted">Select every college this scheduler is allowed to manage.</small>
            </div>

            <div class="col-md-12" id="edit_default_college_wrapper" style="display:none;">
              <label class="form-label">Default College on Login <span class="text-danger">*</span></label>
              <select name="default_college_id" id="edit_default_college_id" class="form-select">
                <option value="">Select Default College</option>
              </select>
              <small class="text-muted">This becomes the first workspace after sign in.</small>
            </div>

            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" id="edit_status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnUpdateAccount">Update</button>
      </div>

    </div>
  </div>
</div>


<!-- Core JS -->
<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../assets/vendor/js/menu.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../assets/js/main.js"></script>

<script>

const accountState = {
  allAccounts: [],
  filteredAccounts: [],
  batchSize: 24,
  renderedCount: 0
};

let accountSearchTimer = null;

function initializeCollegeAccessSelect2(mode) {
  const $select = $("#" + mode + "_college_ids");
  const $modal = $select.closest(".modal");

  if ($select.data("select2")) {
    $select.select2("destroy");
  }

  $select.select2({
    width: "100%",
    placeholder: "Search and select colleges",
    closeOnSelect: false,
    dropdownParent: $modal,
    dropdownAutoWidth: false,
    dropdownCssClass: "college-access-dropdown"
  });

  $select.off("select2:open.collegeWidth").on("select2:open.collegeWidth", function() {
    const dropdownWidth = $select.next(".select2-container").outerWidth();
    const $dropdown = $(".select2-container--open .select2-dropdown");

    if (dropdownWidth && $dropdown.length) {
      $dropdown.css({
        width: dropdownWidth + "px",
        minWidth: dropdownWidth + "px",
        maxWidth: dropdownWidth + "px"
      });
    }
  });
}

// INITIAL LOAD
initializeCollegeAccessSelect2("add");
initializeCollegeAccessSelect2("edit");
loadAccounts();

$(window).on("scroll", function() {
  maybeLoadMoreAccounts();
});

$("#accountSearch").on("input", function() {
  window.clearTimeout(accountSearchTimer);
  accountSearchTimer = window.setTimeout(function() {
    applyAccountSearch();
  }, 120);
});

$("#btnClearAccountSearch").on("click", function() {
  $("#accountSearch").val("");
  applyAccountSearch();
  $("#accountSearch").trigger("focus");
});

// ------------------------------------
// LOAD ACCOUNTS LIST
// ------------------------------------
function loadAccounts() {
  $("#accountsMeta").text("Loading accounts...");
  $("#accountsScrollHint").text("");

  $.ajax({
    url: "../backend/query_accounts.php",
    type: "POST",
    data: { load_accounts: 1 },
    success: function(data) {
      const trimmed = $.trim(data);

      if (trimmed === "unauthorized") {
        window.location = "../index.php";
        return;
      }

      if (
        trimmed.indexOf("Fatal error") !== -1 ||
        trimmed.indexOf("Parse error") !== -1 ||
        trimmed.indexOf("Warning:") !== -1 ||
        (trimmed !== "" && trimmed.indexOf("<tr") === -1)
      ) {
        renderAccountsLoadError();
        return;
      }

      accountState.allAccounts = parseAccountRows(trimmed);
      applyAccountSearch();
    },
    error: function() {
      renderAccountsLoadError();
    }
  });
}

function parseAccountRows(html) {
  const $tbody = $("<tbody>").html(html);
  const accounts = [];

  $tbody.find("tr").each(function() {
    const $row = $(this);
    const $cells = $row.children("td");
    const $editButton = $row.find(".btnEditAccount").first();
    const $deleteButton = $row.find(".btnDeleteAccount").first();

    if ($cells.length < 8 || $editButton.length === 0 || $deleteButton.length === 0) {
      return;
    }

    const username = $cells.eq(1).text().trim();
    const email = $cells.eq(2).text().trim();
    const accessText = $cells.eq(3).text().trim();
    const roleText = $cells.eq(4).text().trim();
    const collegeText = $cells.eq(5).text().trim();
    const statusText = $cells.eq(6).text().trim();
    const collegeIdsAttr = String($editButton.attr("data-college-ids") || "[]");
    let collegeIds = [];

    try {
      collegeIds = JSON.parse(collegeIdsAttr);
    } catch (error) {
      collegeIds = [];
    }

    accounts.push({
      id: String($editButton.data("id") || $deleteButton.data("id") || ""),
      username: username,
      email: email,
      accessHtml: $.trim($cells.eq(3).html()),
      accessText: accessText,
      roleText: roleText,
      roleValue: String($editButton.data("role") || ""),
      collegeHtml: $.trim($cells.eq(5).html()),
      collegeText: collegeText === "" ? "N/A" : collegeText,
      collegeIds: Array.isArray(collegeIds) ? collegeIds.map(String) : [],
      collegeIdsAttr: collegeIdsAttr,
      defaultCollegeValue: String($editButton.attr("data-default-college") || ""),
      statusHtml: $.trim($cells.eq(6).html()),
      statusText: statusText,
      statusValue: String($editButton.data("status") || ""),
      searchText: [
        username,
        email,
        accessText,
        roleText,
        collegeText,
        statusText
      ].join(" ").toLowerCase()
    });
  });

  return accounts;
}

function applyAccountSearch() {
  const query = $.trim($("#accountSearch").val()).toLowerCase();

  if (query === "") {
    accountState.filteredAccounts = accountState.allAccounts.slice();
  } else {
    accountState.filteredAccounts = accountState.allAccounts.filter(function(account) {
      return account.searchText.indexOf(query) !== -1;
    });
  }

  accountState.renderedCount = 0;
  renderNextAccountBatch(true);
}

function renderNextAccountBatch(reset) {
  const $tableBody = $("#accountsTable tbody");
  const $mobileList = $("#accountsMobileList");
  const filteredTotal = accountState.filteredAccounts.length;

  if (reset) {
    $tableBody.empty();
    $mobileList.empty();
  }

  if (filteredTotal === 0) {
    renderAccountsEmptyState();
    updateAccountsMeta();
    return;
  }

  const startIndex = accountState.renderedCount;
  const nextItems = accountState.filteredAccounts.slice(
    startIndex,
    startIndex + accountState.batchSize
  );

  if (nextItems.length === 0) {
    updateAccountsMeta();
    return;
  }

  let desktopHtml = "";
  let mobileHtml = "";

  $.each(nextItems, function(offset, account) {
    const rowNumber = startIndex + offset + 1;
    desktopHtml += buildDesktopRowHtml(account, rowNumber);
    mobileHtml += buildMobileCardHtml(account, rowNumber);
  });

  $tableBody.append(desktopHtml);
  $mobileList.append(mobileHtml);
  accountState.renderedCount += nextItems.length;

  updateAccountsMeta();
  window.setTimeout(fillViewportAccounts, 0);
}

function buildDesktopRowHtml(account, rowNumber) {
  return `
    <tr>
      <td>${rowNumber}</td>
      <td>${escapeHtml(account.username)}</td>
      <td>${escapeHtml(account.email)}</td>
      <td>${account.accessHtml}</td>
      <td>${escapeHtml(account.roleText)}</td>
      <td>${account.collegeHtml || "<span class='text-muted'>N/A</span>"}</td>
      <td>${account.statusHtml}</td>
      <td class="text-end text-nowrap">${buildActionButtonsHtml(account, false)}</td>
    </tr>
  `;
}

function buildMobileCardHtml(account, rowNumber) {
  return `
    <div class="card account-mobile-card">
      <div class="card-body">
        <div class="account-mobile-top">
          <div>
            <span class="account-mobile-index">#${rowNumber}</span>
            <h6 class="account-mobile-name mt-3">${escapeHtml(account.username)}</h6>
            <div class="account-mobile-email mt-1">${escapeHtml(account.email)}</div>
          </div>
          <div class="account-mobile-badges">
            ${account.accessHtml}
            ${account.statusHtml}
          </div>
        </div>
        <div class="account-mobile-grid">
          <div>
            <span class="account-mobile-meta-label">Role</span>
            <span class="account-mobile-meta-value">${escapeHtml(account.roleText)}</span>
          </div>
          <div>
            <span class="account-mobile-meta-label">College Access</span>
            <span class="account-mobile-meta-value">${escapeHtml(account.collegeText)}</span>
          </div>
        </div>
        <div class="account-mobile-actions">
          ${buildActionButtonsHtml(account, true)}
        </div>
      </div>
    </div>
  `;
}

function buildActionButtonsHtml(account, mobileView) {
  const editClasses = mobileView
    ? "btn btn-outline-warning btnEditAccount"
    : "btn btn-sm btn-warning btnEditAccount";
  const deleteClasses = mobileView
    ? "btn btn-outline-danger btnDeleteAccount"
    : "btn btn-sm btn-danger btnDeleteAccount";
  const editLabel = mobileView
    ? '<i class="bx bx-edit-alt me-1"></i>Edit'
    : '<i class="bx bx-edit-alt"></i>';
  const deleteLabel = mobileView
    ? '<i class="bx bx-trash me-1"></i>Delete'
    : '<i class="bx bx-trash"></i>';

  return `
    <button
      class="${editClasses}"
      data-id="${escapeHtml(account.id)}"
      data-username="${escapeHtml(account.username)}"
      data-email="${escapeHtml(account.email)}"
      data-role="${escapeHtml(account.roleValue)}"
      data-college-ids="${escapeHtml(account.collegeIdsAttr)}"
      data-default-college="${escapeHtml(account.defaultCollegeValue)}"
      data-status="${escapeHtml(account.statusValue)}"
    >
      ${editLabel}
    </button>
    <button
      class="${deleteClasses}"
      data-id="${escapeHtml(account.id)}"
    >
      ${deleteLabel}
    </button>
  `;
}

function fillViewportAccounts() {
  let safetyCounter = 0;

  while (
    accountState.renderedCount < accountState.filteredAccounts.length &&
    $(document).height() <= $(window).height() + 120 &&
    safetyCounter < 8
  ) {
    renderNextAccountBatch(false);
    safetyCounter++;
  }
}

function maybeLoadMoreAccounts() {
  if (accountState.renderedCount >= accountState.filteredAccounts.length) {
    return;
  }

  if ($(window).scrollTop() + $(window).height() >= $(document).height() - 220) {
    renderNextAccountBatch(false);
  }
}

function updateAccountsMeta() {
  const total = accountState.allAccounts.length;
  const filtered = accountState.filteredAccounts.length;
  const shown = Math.min(accountState.renderedCount, filtered);
  const hasQuery = $.trim($("#accountSearch").val()) !== "";

  if (filtered === 0) {
    $("#accountsMeta").text(hasQuery ? "No matching accounts found." : "No accounts found.");
    $("#accountsScrollHint").text(hasQuery ? "Try a different search term." : "");
    return;
  }

  if (hasQuery) {
    $("#accountsMeta").text("Showing " + shown + " of " + filtered + " matching accounts (" + total + " total)");
  } else {
    $("#accountsMeta").text("Showing " + shown + " of " + filtered + " accounts");
  }

  if (shown < filtered) {
    $("#accountsScrollHint").text("Scroll to load more");
  } else {
    $("#accountsScrollHint").text("All matching accounts loaded");
  }
}

function renderAccountsLoadError() {
  accountState.allAccounts = [];
  accountState.filteredAccounts = [];
  accountState.renderedCount = 0;

  $("#accountsTable tbody").html(`
    <tr>
      <td colspan="8" class="text-center py-4 text-danger">
        Unable to load the account list right now.
      </td>
    </tr>
  `);

  $("#accountsMobileList").html(`
    <div class="accounts-empty-state text-danger">
      Unable to load the account list right now.
    </div>
  `);

  $("#accountsMeta").text("Account loading failed.");
  $("#accountsScrollHint").text("");
}

function renderAccountsEmptyState() {
  $("#accountsTable tbody").html(`
    <tr>
      <td colspan="8" class="text-center py-4 text-muted">
        No accounts found.
      </td>
    </tr>
  `);

  $("#accountsMobileList").html(`
    <div class="accounts-empty-state">
      No accounts found.
    </div>
  `);
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function buildDefaultCollegeOptions(mode, preferredValue) {
  const $collegeSelect = $("#" + mode + "_college_ids");
  const $defaultSelect = $("#" + mode + "_default_college_id");
  const selectedValues = ($collegeSelect.val() || []).map(String);
  const placeholder = mode === "add" ? "Select Default College" : "Select Default College";

  $defaultSelect.empty().append(`<option value="">${placeholder}</option>`);

  selectedValues.forEach(function(value) {
    const option = $collegeSelect.find(`option[value="${value}"]`).first();
    if (option.length) {
      $defaultSelect.append(
        $("<option>", {
          value: value,
          text: option.text()
        })
      );
    }
  });

  if (selectedValues.length === 0) {
    $defaultSelect.val("");
    return;
  }

  const desiredValue = selectedValues.indexOf(String(preferredValue || "")) !== -1
    ? String(preferredValue)
    : selectedValues[0];

  $defaultSelect.val(desiredValue);
}

function resetCollegeAccessFields(mode) {
  $("#" + mode + "_college_ids").val(null).trigger("change");
  $("#" + mode + "_default_college_id")
    .empty()
    .append('<option value="">Select Default College</option>')
    .val("");
}

function toggleCollegeAccessFields(mode, role, preferredDefaultValue) {
  const isScheduler = role === "scheduler";
  $("#" + mode + "_college_wrapper").toggle(isScheduler);
  $("#" + mode + "_default_college_wrapper").toggle(isScheduler);

  if (!isScheduler) {
    resetCollegeAccessFields(mode);
    return;
  }

  buildDefaultCollegeOptions(mode, preferredDefaultValue);
  $("#" + mode + "_college_ids").trigger("change.select2");
}

$("#add_role").on("change", function() {
  toggleCollegeAccessFields("add", $(this).val(), $("#add_default_college_id").val());
});

$("#edit_role").on("change", function() {
  toggleCollegeAccessFields("edit", $(this).val(), $("#edit_default_college_id").val());
});

$("#add_college_ids").on("change", function() {
  buildDefaultCollegeOptions("add", $("#add_default_college_id").val());
});

$("#edit_college_ids").on("change", function() {
  buildDefaultCollegeOptions("edit", $("#edit_default_college_id").val());
});

// ------------------------------------
// SAVE ACCOUNT
// ------------------------------------
$("#btnSaveAccount").click(function () {

  $.post(
    "../backend/query_accounts.php",
    $("#addAccountForm").serialize() + "&save_account=1",
    function(res) {

      res = res.trim();

      if (res === "unauthorized") {
        Swal.fire("Session Expired", "Please sign in again.", "warning").then(() => {
          window.location = "../index.php";
        });
        return;
      }
      if (res === "missing") {
        Swal.fire("Missing Data", "Please fill all required fields.", "warning");
        return;
      }
      if (res === "invalid_domain") {
        Swal.fire("Invalid Email", "Only @sksu.edu.ph email addresses are allowed.", "warning");
        return;
      }
      if (res === "invalid_role") {
        Swal.fire("Invalid Role", "Only Administrator and Scheduler roles are supported in Synk.", "warning");
        return;
      }
      if (res === "invalid_status") {
        Swal.fire("Invalid Status", "Please choose a valid account status.", "warning");
        return;
      }
      if (res === "need_college") {
        Swal.fire("Missing College Access", "Select at least one college for the scheduler.", "warning");
        return;
      }
      if (res === "dup_email") {
        Swal.fire("Duplicate Email", "This email is already in use.", "warning");
        return;
      }
      if (res !== "success") {
        Swal.fire("Save Failed", "Please try again later.", "error");
        return;
      }

      Swal.fire({
        icon: "success",
        title: "Saved!",
        text: "Access account added successfully.",
        timer: 1200,
        showConfirmButton: false
      });

      $("#addAccountForm")[0].reset();
      resetCollegeAccessFields("add");
      $("#add_college_wrapper").hide();
      $("#add_default_college_wrapper").hide();
      $("#addAccountModal").modal("hide");
      loadAccounts();
    }
  );

});

// ------------------------------------
// OPEN EDIT MODAL
// ------------------------------------
$(document).on("click", ".btnEditAccount", function () {

  $("#edit_user_id").val($(this).data("id"));
  $("#edit_username").val($(this).data("username"));
  $("#edit_email").val($(this).data("email"));
  $("#edit_role").val($(this).data("role"));
  $("#edit_status").val($(this).data("status"));

  const role = String($(this).data("role") || "");
  const collegeIdsAttr = String($(this).attr("data-college-ids") || "[]");
  const defaultCollegeId = String($(this).attr("data-default-college") || "");
  let collegeIds = [];

  try {
    collegeIds = JSON.parse(collegeIdsAttr);
  } catch (error) {
    collegeIds = [];
  }

  if (role === "scheduler") {
    $("#edit_college_ids").val((Array.isArray(collegeIds) ? collegeIds : []).map(String)).trigger("change");
    toggleCollegeAccessFields("edit", role, defaultCollegeId);
  } else {
    toggleCollegeAccessFields("edit", role, "");
  }

  $("#editAccountModal").modal("show");
});

$("#addAccountModal").on("shown.bs.modal", function() {
  $("#add_college_ids").trigger("focus");
});

$("#editAccountModal").on("shown.bs.modal", function() {
  $("#edit_college_ids").trigger("focus");
});

// ------------------------------------
// UPDATE ACCOUNT
// ------------------------------------
$("#btnUpdateAccount").click(function () {

  $.post(
    "../backend/query_accounts.php",
    $("#editAccountForm").serialize() + "&update_account=1",
    function(res) {

      res = res.trim();

      if (res === "unauthorized") {
        Swal.fire("Session Expired", "Please sign in again.", "warning").then(() => {
          window.location = "../index.php";
        });
        return;
      }
      if (res === "missing") {
        Swal.fire("Missing Data", "Please fill all required fields.", "warning");
        return;
      }
      if (res === "invalid_domain") {
        Swal.fire("Invalid Email", "Only @sksu.edu.ph email addresses are allowed.", "warning");
        return;
      }
      if (res === "invalid_role") {
        Swal.fire("Invalid Role", "Only Administrator and Scheduler roles are supported in Synk.", "warning");
        return;
      }
      if (res === "invalid_status") {
        Swal.fire("Invalid Status", "Please choose a valid account status.", "warning");
        return;
      }
      if (res === "need_college") {
        Swal.fire("Missing College Access", "Select at least one college for the scheduler.", "warning");
        return;
      }
      if (res === "dup_email") {
        Swal.fire("Duplicate Email", "This email is already in use.", "warning");
        return;
      }
      if (res !== "success") {
        Swal.fire("Update Failed", "Please try again later.", "error");
        return;
      }

      Swal.fire({
        icon: "success",
        title: "Updated!",
        text: "Access account updated successfully.",
        timer: 1200,
        showConfirmButton: false
      });

      $("#editAccountModal").modal("hide");
      loadAccounts();
    }
  );

});

// ------------------------------------
// DELETE ACCOUNT
// ------------------------------------
$(document).on("click", ".btnDeleteAccount", function () {
  let id = $(this).data("id");

  Swal.fire({
    title: "Are you sure?",
    text: "This user account will be permanently deleted.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#aaa",
    confirmButtonText: "Delete"
  }).then((result) => {
    if (result.isConfirmed) {
      $.post(
        "../backend/query_accounts.php",
        { delete_account: 1, user_id: id },
        function(res) {
          res = res.trim();

          if (res === "unauthorized") {
            Swal.fire("Session Expired", "Please sign in again.", "warning").then(() => {
              window.location = "../index.php";
            });
            return;
          }
          if (res !== "deleted") {
            Swal.fire("Delete Failed", "Please try again later.", "error");
            return;
          }

          Swal.fire("Deleted!", "Access account removed.", "success");
          loadAccounts();
        }
      );
    }
  });

});

</script>

</body>
</html>
