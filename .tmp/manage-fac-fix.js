
let facultyDT; // global variable

function escapeHtml(value) {
  return $("<div>").text(value).html();
}

function isSmallScreen() {
  return window.matchMedia("(max-width: 991.98px)").matches;
}

function loadDesignationOptions($selectElement, selectedId = "") {
  $.post(
    "../backend/query_designation.php",
    { load_designation_options: 1 },
    function(optionsHtml) {
      $selectElement.html(optionsHtml);

      if (selectedId !== "") {
        $selectElement.val(selectedId);
      } else {
        $selectElement.val("");
      }
    }
  );
}

function buildDesignationPillHtml(label, style) {
  const styleAttr = style && style.trim() !== "" ? `style="${style}"` : "";
  return `<span class="faculty-designation-pill" ${styleAttr}>${escapeHtml(label)}</span>`;
}

function renderFacultyMobileList() {
  const $mobileList = $("#facultyMobileList");
  const cards = [];

  $("#facultyTable tbody tr").each(function() {
    const $row = $(this);
    const $editBtn = $row.find(".btnEditFaculty").first();

    const facultyId = String($editBtn.data("id") || "").trim();
    const designationName = String($editBtn.data("designationName") || $row.find("td").eq(2).text().trim()).trim();
    const designationStyle = String($editBtn.data("designationStyle") || "").trim();
    const statusHtml = $row.find("td").eq(3).html();
    const statusText = $.trim($row.find("td").eq(3).text());
    const rowData = {
      id: facultyId,
      fullName: $.trim($row.find("td").eq(1).text()),
      designationName,
      designationStyle,
      statusHtml,
      statusText,
      lname: String($editBtn.data("lname") || ""),
      fname: String($editBtn.data("fname") || ""),
      mname: String($editBtn.data("mname") || ""),
      ext: String($editBtn.data("ext") || ""),
      designationId: String($editBtn.data("designation") || ""),
      status: String($editBtn.data("status") || "")
    };

    cards.push(rowData);
  });

  if (cards.length === 0) {
    $mobileList.html("<div class='faculty-empty-state'>No faculty records found.</div>");
    return;
  }

  const rowsHtml = cards
    .map(function(item, index) {
      return `
        <div class="card faculty-mobile-card">
          <div class="card-body">
            <div class="faculty-mobile-top">
              <div>
                <span class="faculty-mobile-index">#${index + 1}</span>
                <h6 class="faculty-mobile-name mt-3">${escapeHtml(item.fullName)}</h6>
                <div class="faculty-mobile-designation">${buildDesignationPillHtml(item.designationName, item.designationStyle)}</div>
              </div>
              <div>${item.statusHtml || ""}</div>
            </div>

            <div class="faculty-mobile-meta">
              <div>
                <span class="faculty-mobile-meta-label">Name</span>
                <span class="faculty-mobile-meta-value">${escapeHtml(item.fullName)}</span>
              </div>
              <div>
                <span class="faculty-mobile-meta-label">Status</span>
                <span class="faculty-mobile-meta-value">${escapeHtml(item.statusText || "N/A")}</span>
              </div>
            </div>

            <div class="faculty-mobile-actions">
              <button
                class="btn btn-outline-warning btnEditFaculty"
                data-id="${escapeHtml(item.id)}"
                data-lname="${escapeHtml(item.lname)}"
                data-fname="${escapeHtml(item.fname)}"
                data-mname="${escapeHtml(item.mname)}"
                data-ext="${escapeHtml(item.ext)}"
                data-status="${escapeHtml(item.status)}"
                data-designation="${escapeHtml(item.designationId)}"
                data-designation-name="${escapeHtml(item.designationName)}"
                data-designation-style="${escapeHtml(item.designationStyle)}"
                type="button"
              >
                <i class="bx bx-edit me-1"></i>Edit
              </button>
              <button
                class="btn btn-outline-danger btnDeleteFaculty"
                data-id="${escapeHtml(item.id)}"
                type="button"
              >
                <i class="bx bx-trash me-1"></i>Delete
              </button>
            </div>
          </div>
        </div>
      `;
    })
    .join("");

  $mobileList.html(rowsHtml);
}

function renderFacultyListView() {
  if (isSmallScreen()) {
    if (facultyDT !== null && facultyDT !== undefined) {
      facultyDT.destroy();
      facultyDT = null;
    }

    renderFacultyMobileList();
    return;
  }

  $("#facultyMobileList").empty();

  if (facultyDT === null || facultyDT === undefined) {
    if (typeof $.fn.DataTable === "function") {
      facultyDT = $("#facultyTable").DataTable({
        responsive: true,
        autoWidth: false,
        ordering: true,
        pageLength: 10,
        language: {
          search: "Search:",
          lengthMenu: "Show _MENU_ entries",
          info: "Showing _START_ to _END_ of _TOTAL_ faculty",
        },
        columnDefs: [
          { orderable: false, targets: 4 } // disable sorting for action column
        ]
      });
    }
  }
}

function showFacultyLoadMessage(message) {
  const safeMessage = escapeHtml(message || "No faculty records found.");
  if (isSmallScreen()) {
    $("#facultyMobileList").html(`<div class='faculty-empty-state'>${safeMessage}</div>`);
    $("#facultyTable tbody").empty();
    if (facultyDT !== null && facultyDT !== undefined) {
      facultyDT.destroy();
      facultyDT = null;
    }
    return;
  }

  $("#facultyMobileList").empty();
  if (facultyDT !== null && facultyDT !== undefined) {
    facultyDT.destroy();
    facultyDT = null;
  }

  $("#facultyTable tbody").html(
    `<tr><td colspan="5" class="text-center text-muted py-3">${safeMessage}</td></tr>`
  );
}

// INITIAL LOAD
loadDesignationOptions($("#add_designation_id"));
loadFaculty();

// ------------------------------------
// LOAD FACULTY LIST
// ------------------------------------
function loadFaculty() {
  $.post("../backend/query_faculty.php", { load_faculty: 1 }, function(data) {
    const payload = $.trim(data);

    if (!payload || !payload.includes("<tr")) {
      showFacultyLoadMessage("No faculty records found.");
      return;
    }

    $("#facultyTable tbody").html(payload);
    if (facultyDT) {
      facultyDT.destroy();
      facultyDT = null;
    }
    renderFacultyListView();
  }).fail(function() {
    showFacultyLoadMessage("Failed to load faculty list. Please refresh the page.");
  });
}

// ------------------------------------
// SAVE FACULTY (KEEP MODAL OPEN)
// ------------------------------------
$("#btnSaveFaculty").click(function () {

  $.post(
    "../backend/query_faculty.php",
    $("#addFacultyForm").serialize() + "&save_faculty=1",
    function(res) {
      res = $.trim(res);

      if (res === "missing") {
        Swal.fire("Missing Data", "Please fill out required fields.", "warning");
        return;
      }

      if (res === "invalid_designation") {
        Swal.fire("Invalid Designation", "Please choose a valid active designation.", "warning");
        return;
      }

      if (res === "schema_update_required") {
        Swal.fire("Update Needed", "Please run the faculty designation migration first.", "warning");
        return;
      }

      // SUCCESS ALERT
      Swal.fire({
        icon: "success",
        title: "Saved!",
        text: "Faculty added successfully.",
        timer: 1200,
        showConfirmButton: false
      });

      // DO NOT CLOSE MODAL ❌
      // $("#addFacultyModal").modal("hide");  ← remove this

      // RESET FORM FOR NEXT ENTRY
      $("#addFacultyModal").modal("hide");
      $("#addFacultyForm")[0].reset();

      // SET FOCUS BACK TO FIRST NAME OR LAST NAME
      loadDesignationOptions($("#add_designation_id"));
      $("#add_designation_id").val("");
      $("input[name='last_name']").focus();

      // REFRESH TABLE
      loadFaculty();
    }
  );
});


// allow Enter key to trigger save in add modal
$("#addFacultyForm input").on("keypress", function(e) {
  if (e.which === 13) {
    e.preventDefault();
    $("#btnSaveFaculty").click();
  }
});

// ------------------------------------
// OPEN EDIT MODAL
// ------------------------------------
  $(document).on("click", ".btnEditFaculty", function () {

  $("#edit_faculty_id").val($(this).data("id"));
  $("#edit_last_name").val($(this).data("lname"));
  $("#edit_first_name").val($(this).data("fname"));
  $("#edit_middle_name").val($(this).data("mname"));
  $("#edit_ext_name").val($(this).data("ext"));
  $("#edit_status").val($(this).data("status"));
  loadDesignationOptions($("#edit_designation_id"), String($(this).data("designation")));

  $("#editFacultyModal").modal("show");
});

// ------------------------------------
// UPDATE FACULTY
// ------------------------------------
$("#btnUpdateFaculty").click(function () {

  $.post(
    "../backend/query_faculty.php",
    $("#editFacultyForm").serialize() + "&update_faculty=1",
    function(res) {
      res = $.trim(res);

      if (res === "missing") {
        Swal.fire("Missing Data", "Please fill out required fields.", "warning");
        return;
      }

      if (res === "invalid_designation") {
        Swal.fire("Invalid Designation", "Please choose a valid active designation.", "warning");
        return;
      }

      if (res === "schema_update_required") {
        Swal.fire("Update Needed", "Please run the faculty designation migration first.", "warning");
        return;
      }

      Swal.fire({
        icon: "success",
        title: "Updated!",
        text: "Faculty record updated.",
        timer: 1200,
        showConfirmButton: false
      });

      $("#editFacultyModal").modal("hide");

      // REFRESH TABLE
      loadFaculty();
    }
  );

});

// ------------------------------------
// DELETE FACULTY
// ------------------------------------
$(document).on("click", ".btnDeleteFaculty", function () {
  let id = $(this).data("id");

  Swal.fire({
    title: "Are you sure?",
    text: "This faculty will be permanently deleted.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#aaa",
    confirmButtonText: "Delete"
  }).then((result) => {
    if (result.isConfirmed) {
      $.post(
        "../backend/query_faculty.php",
        { delete_faculty: 1, faculty_id: id },
        function(res) {
          Swal.fire("Deleted!", "Faculty removed successfully.", "success");
          loadFaculty();
        }
      );
    }
  });

});

$(window).on("resize", function() {
  renderFacultyListView();
});


