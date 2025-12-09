<?php 
    session_start();
    ob_start();
    include '../backend/db.php';
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

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
      #accountsTable td {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
      }
      #accountsTable td:last-child {
        white-space: nowrap !important;
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
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">
              <i class="bx bx-user-circle me-2"></i> User Accounts
            </h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
              <i class="bx bx-plus"></i> Add User
            </button>
          </div>

          <!-- Card: Accounts List -->
          <div class="card">
            <div class="card-header">
              <h5 class="m-0">Accounts List</h5>
              <small class="text-muted">Manage administrators and schedulers</small>
            </div>

            <div class="card-datatable table-responsive p-3">
              <table class="table table-hover align-middle" id="accountsTable">
                <thead>
                  <tr>
                    <th style="width:60px;">#</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>College</th>
                    <th style="width:120px;">Status</th>
                    <th class="text-end" style="width:140px;">Actions</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
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
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="addAccountForm">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" name="username" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" name="password" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" id="add_role" class="form-select" required>
                <option value="">Select Role</option>
                <option value="admin">Administrator</option>
                <option value="scheduler">Scheduler</option>
                <option value="viewer">Viewer</option>
              </select>
            </div>

            <div class="col-md-12" id="add_college_wrapper" style="display:none;">
              <label class="form-label">Assigned College <span class="text-danger">*</span></label>
              <select name="college_id" id="add_college_id" class="form-select">
                <option value="">Select College</option>
                <?php
                  $qc = $conn->query("SELECT college_id, college_code, college_name FROM tbl_college WHERE status='active' ORDER BY college_name");
                  while ($c = $qc->fetch_assoc()) {
                    $label = $c['college_code'] . ' - ' . $c['college_name'];
                    echo "<option value='{$c['college_id']}'>" . htmlspecialchars($label) . "</option>";
                  }
                ?>
              </select>
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
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="editAccountForm">
          <input type="hidden" name="user_id" id="edit_user_id">

          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">New Password</label>
              <input type="password" name="password" id="edit_password" class="form-control" placeholder="Leave blank to keep current">
            </div>

            <div class="col-md-6">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" id="edit_role" class="form-select" required>
                <option value="">Select Role</option>
                <option value="admin">Administrator</option>
                <option value="scheduler">Scheduler</option>
                <option value="viewer">Viewer</option>
              </select>
            </div>

            <div class="col-md-12" id="edit_college_wrapper" style="display:none;">
              <label class="form-label">Assigned College <span class="text-danger">*</span></label>
              <select name="college_id" id="edit_college_id" class="form-select">
                <option value="">Select College</option>
                <?php
                  $qc2 = $conn->query("SELECT college_id, college_code, college_name FROM tbl_college WHERE status='active' ORDER BY college_name");
                  while ($c2 = $qc2->fetch_assoc()) {
                    $label = $c2['college_code'] . ' - ' . $c2['college_name'];
                    echo "<option value='{$c2['college_id']}'>" . htmlspecialchars($label) . "</option>";
                  }
                ?>
              </select>
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
<script src="../assets/js/main.js"></script>

<script>

// INITIAL LOAD
loadAccounts();

// ------------------------------------
// LOAD ACCOUNTS LIST
// ------------------------------------
function loadAccounts() {
  $.post("../backend/query_accounts.php", { load_accounts: 1 }, function(data) {
    $("#accountsTable tbody").html(data);
  });
}

// Show/Hide college field based on role (ADD)
$("#add_role").on("change", function() {
  const role = $(this).val();
  if (role === "scheduler") {
    $("#add_college_wrapper").show();
  } else {
    $("#add_college_wrapper").hide();
    $("#add_college_id").val("");
  }
});

// Show/Hide college field based on role (EDIT)
$("#edit_role").on("change", function() {
  const role = $(this).val();
  if (role === "scheduler") {
    $("#edit_college_wrapper").show();
  } else {
    $("#edit_college_wrapper").hide();
    $("#edit_college_id").val("");
  }
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

      if (res === "missing") {
        Swal.fire("Missing Data", "Please fill all required fields.", "warning");
        return;
      }
      if (res === "need_college") {
        Swal.fire("Missing College", "Scheduler must have an assigned college.", "warning");
        return;
      }
      if (res === "dup_username") {
        Swal.fire("Duplicate Username", "This username is already taken.", "warning");
        return;
      }
      if (res === "dup_email") {
        Swal.fire("Duplicate Email", "This email is already in use.", "warning");
        return;
      }

      Swal.fire({
        icon: "success",
        title: "Saved!",
        text: "User account added successfully.",
        timer: 1200,
        showConfirmButton: false
      });

      $("#addAccountForm")[0].reset();
      $("#add_college_wrapper").hide();
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

  const collegeId = $(this).data("college");
  if ($(this).data("role") === "scheduler") {
    $("#edit_college_wrapper").show();
    $("#edit_college_id").val(collegeId);
  } else {
    $("#edit_college_wrapper").hide();
    $("#edit_college_id").val("");
  }

  $("#edit_password").val(""); // clear password field

  $("#editAccountModal").modal("show");
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

      if (res === "missing") {
        Swal.fire("Missing Data", "Please fill all required fields.", "warning");
        return;
      }
      if (res === "need_college") {
        Swal.fire("Missing College", "Scheduler must have an assigned college.", "warning");
        return;
      }
      if (res === "dup_username") {
        Swal.fire("Duplicate Username", "This username is already taken.", "warning");
        return;
      }
      if (res === "dup_email") {
        Swal.fire("Duplicate Email", "This email is already in use.", "warning");
        return;
      }

      Swal.fire({
        icon: "success",
        title: "Updated!",
        text: "User account updated successfully.",
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
          Swal.fire("Deleted!", "User account removed.", "success");
          loadAccounts();
        }
      );
    }
  });

});

</script>

</body>
</html>
