<?php
session_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

$college_id = $_SESSION['college_id'];
$college_name = $_SESSION['college_name'];
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8"/>
    <title>Room Management | Synk Scheduler</title>

    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
       <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <style>
        #roomTable td {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
    </style>
</head>

<body>
<div class="layout-wrapper layout-content-navbar">
<div class="layout-container">

<?php include 'sidebar.php'; ?>
<div class="layout-page">
<?php include 'navbar.php'; ?>

<div class="content-wrapper">
<div class="container-xxl flex-grow-1 container-p-y">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold">
            <i class="bx bx-building-house me-2"></i>
            Room Management <small class="text-muted">(<?= $college_name ?>)</small>
        </h4>

        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
            <i class="bx bx-plus"></i> Add Room
        </button>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="m-0">Room List</h5>
            <small class="text-muted">All rooms under <?= $college_name ?>.</small>
        </div>

        <div class="table-responsive p-3">
            <table class="table table-hover" id="roomTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Room Code</th>
                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</div>
<?php include '../footer.php'; ?>
</div>
</div>

</div>

<!-- ADD ROOM MODAL -->
<div class="modal fade" id="addRoomModal">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Add Room</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="addRoomForm">
          
          <div class="mb-3">
            <label class="form-label">Room Code <span class="text-danger">*</span></label>
            <input type="text" name="room_code" class="form-control" required placeholder="e.g., CCS-101">
          </div>

          <div class="mb-3">
            <label class="form-label">Room Name</label>
            <input type="text" name="room_name" class="form-control" placeholder="e.g., Computer Laboratory 1">
          </div>

          <div class="mb-3">
            <label class="form-label">Room Type</label>
            <select name="room_type" class="form-select" required>
                <option value="lecture">Lecture Room</option>
                <option value="laboratory">Laboratory Room</option>
                <option value="lec_lab">Lecture-Laboratory Room</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Capacity</label>
            <input type="number" name="capacity" class="form-control" value="0">
          </div>

        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnSaveRoom">Save</button>
      </div>

    </div>
  </div>
</div>

<!-- EDIT ROOM MODAL -->
<div class="modal fade" id="editRoomModal">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Edit Room</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="editRoomForm">

          <input type="hidden" id="edit_room_id" name="room_id">

          <div class="mb-3">
            <label class="form-label">Room Code <span class="text-danger">*</span></label>
            <input type="text" id="edit_room_code" name="room_code" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Room Name</label>
            <input type="text" id="edit_room_name" name="room_name" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label">Room Type</label>
            <select id="edit_room_type" name="room_type" class="form-select" required>
                <option value="lecture">Lecture Room</option>
                <option value="laboratory">Laboratory Room</option>
                <option value="lec_lab">Lecture-Laboratory Room</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Capacity</label>
            <input type="number" id="edit_room_capacity" name="capacity" class="form-control">
          </div>

        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnUpdateRoom">Update Room</button>
      </div>

    </div>
  </div>
</div>


<!-- SCRIPTS -->
<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
loadRooms();

// LOAD ROOMS
function loadRooms() {
    $.post('../backend/query_rooms.php', { load_rooms: 1 }, function(data){
        $("#roomTable tbody").html(data);
    });
}

// SAVE ROOM
$("#btnSaveRoom").click(function () {
    $.post('../backend/query_rooms.php', $("#addRoomForm").serialize() + "&save_room=1", function(res){
        Swal.fire("Success", "Room added successfully", "success");
        // $("#addRoomModal").modal("hide");
        loadRooms();
    });
});

// 🔹 OPEN EDIT MODAL
$(document).on("click", ".btnEdit", function () {

    let id       = $(this).data("id");
    let code     = $(this).data("code");
    let name     = $(this).data("name");
    let type     = $(this).data("type");
    let capacity = $(this).data("capacity");

    $("#edit_room_id").val(id);
    $("#edit_room_code").val(code);
    $("#edit_room_name").val(name);
    $("#edit_room_type").val(type);
    $("#edit_room_capacity").val(capacity);

    $("#editRoomModal").modal("show");
});


// 🔹 UPDATE ROOM
$("#btnUpdateRoom").click(function () {

    $.post('../backend/query_rooms.php',
        $("#editRoomForm").serialize() + "&update_room=1",
        function(res) {

            if (res === "duplicate") {
                Swal.fire("Duplicate!", "Room code already exists.", "warning");
                return;
            }

            if (res === "success") {
                Swal.fire("Updated!", "Room updated successfully.", "success");
                $("#editRoomModal").modal("hide");
                loadRooms();
            }
        }
    );
});


// 🔹 DELETE ROOM
$(document).on("click", ".btnDelete", function () {

    let id = $(this).data("id");

    Swal.fire({
        title: "Are you sure?",
        text: "This room will be permanently deleted.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#aaa",
        confirmButtonText: "Delete"
    }).then((result) => {

        if (result.isConfirmed) {

            $.post('../backend/query_rooms.php',
                { delete_room: 1, room_id: id },
                function(res) {

                    if (res === "success") {
                        Swal.fire("Deleted!", "Room removed successfully.", "success");
                        loadRooms();
                    }
                }
            );
        }
    });
});

</script>

</body>
</html>
