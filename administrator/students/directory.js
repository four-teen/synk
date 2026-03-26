(function () {
  const config = window.studentDirectoryConfig || {};
  const apiUrl = config.apiUrl || "directory_api.php";

  const filterForm = document.getElementById("directoryFilterForm");
  const listBody = document.getElementById("studentListBody");
  const resultCountLabel = document.getElementById("resultCountLabel");
  const sentinel = document.getElementById("listSentinel");
  const sentinelText = document.getElementById("listSentinelText");
  const feedbackContainer = document.getElementById("directoryFeedback");
  const addStudentBtn = document.getElementById("openAddStudentBtn");
  const resetFiltersBtn = document.getElementById("resetDirectoryFilters");
  const studentModalElement = document.getElementById("studentModal");
  const studentModal = studentModalElement ? new bootstrap.Modal(studentModalElement) : null;
  const studentForm = document.getElementById("studentForm");
  const studentModalTitle = document.getElementById("studentModalTitle");
  const generateEmailBtn = document.getElementById("generateEmailBtn");
  const studentIdField = document.getElementById("student_id");
  const programIdField = document.getElementById("program_id");
  const sourceProgramNameField = document.getElementById("source_program_name_hidden");
  const programSelectField = document.getElementById("program_select_modal");
  const yearLevelField = document.getElementById("year_level_modal");
  const studentNumberField = document.getElementById("student_number_modal");
  const lastNameField = document.getElementById("last_name_modal");
  const firstNameField = document.getElementById("first_name_modal");
  const middleNameField = document.getElementById("middle_name_modal");
  const suffixNameField = document.getElementById("suffix_name_modal");
  const emailField = document.getElementById("email_address_modal");
  const hiddenAcademicYear = document.getElementById("academic_year_label_hidden");
  const hiddenSemester = document.getElementById("semester_label_hidden");
  const hiddenCollege = document.getElementById("college_name_hidden");
  const hiddenCampus = document.getElementById("campus_name_hidden");
  const academicYearFilterField = document.getElementById("academic_year_label");
  const semesterFilterField = document.getElementById("semester_label");

  if (!filterForm || !listBody) {
    return;
  }

  const state = {
    page: 1,
    hasMore: true,
    loading: false,
    observer: null
  };
  let autoEmailEnabled = true;

  function escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function showFeedback(status, message) {
    if (!feedbackContainer) {
      return;
    }

    feedbackContainer.innerHTML =
      '<div class="alert alert-' + status + ' alert-dismissible mb-4" role="alert">' +
        '<div class="fw-semibold">' + escapeHtml(message) + "</div>" +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
      "</div>";

    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function normalizeLookupValue(value) {
    return String(value ?? "").trim().toLowerCase();
  }

  function serializeFilters() {
    const formData = new FormData(filterForm);
    const params = new URLSearchParams();

    formData.forEach(function (value, key) {
      const normalized = String(value).trim();
      if (normalized !== "" && normalized !== "0") {
        params.set(key, normalized);
      }
    });

    return params;
  }

  function syncFiltersToUrl() {
    const params = serializeFilters();
    const nextUrl = "directory.php" + (params.toString() ? "?" + params.toString() : "");
    window.history.replaceState({}, "", nextUrl);
  }

  function formatUpper(value) {
    return String(value ?? "").trim().toUpperCase();
  }

  function formatLower(value) {
    return String(value ?? "").trim().toLowerCase();
  }

  function buildFullNameHtml(item) {
    const lastName = formatUpper(item.last_name);
    const firstName = formatUpper(item.first_name);
    const middleName = formatLower(item.middle_name);
    const suffixName = formatUpper(item.suffix_name);

    let html = "";
    if (lastName) {
      html += '<span class="student-name-last">' + escapeHtml(lastName) + "</span>";
    }
    if (firstName) {
      html += (html ? ", " : "") + '<span class="student-name-first">' + escapeHtml(firstName) + "</span>";
    }
    if (middleName) {
      html += ' <span class="student-name-middle">' + escapeHtml(middleName) + "</span>";
    }
    if (suffixName) {
      html += ' <span class="student-name-suffix">' + escapeHtml(suffixName) + "</span>";
    }

    return html || '<span class="text-muted">NO NAME</span>';
  }

  function renderRows(items, resetList) {
    if (resetList) {
      listBody.innerHTML = "";
    }

    if (!items.length && resetList) {
      listBody.innerHTML =
        '<tr><td colspan="4" class="empty-state">' +
          '<i class="bx bx-spreadsheet fs-1 d-block mb-2 text-primary"></i>' +
          "No student records matched the current filters." +
        "</td></tr>";
      return;
    }

    const html = items.map(function (item) {
      return (
        "<tr>" +
          '<td class="student-id-cell">' + escapeHtml(String(item.student_number ?? "")) + "</td>" +
          '<td class="student-name-cell">' + buildFullNameHtml(item) + "</td>" +
          '<td class="email-cell">' + escapeHtml(item.email_address) + "</td>" +
          '<td class="text-end">' +
            '<div class="d-inline-flex gap-1">' +
              '<button type="button" class="btn btn-sm btn-outline-primary btn-edit-student" data-student-id="' + Number(item.student_id || 0) + '">' +
                '<i class="bx bx-edit-alt"></i>' +
              "</button>" +
              '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-student" data-student-id="' + Number(item.student_id || 0) + '">' +
                '<i class="bx bx-trash"></i>' +
              "</button>" +
            "</div>" +
          "</td>" +
        "</tr>"
      );
    }).join("");

    if (resetList) {
      listBody.innerHTML = html;
    } else {
      listBody.insertAdjacentHTML("beforeend", html);
    }
  }

  async function fetchList(resetList) {
    if (state.loading || (!state.hasMore && !resetList)) {
      return;
    }

    state.loading = true;
    if (resetList) {
      state.page = 1;
      state.hasMore = true;
      listBody.innerHTML = '<tr><td colspan="4" class="empty-state">Loading student records...</td></tr>';
    }

    if (sentinelText) {
      sentinelText.textContent = "Loading more students...";
    }

    try {
      const params = serializeFilters();
      params.set("action", "list");
      params.set("page", state.page);

      const response = await fetch(apiUrl + "?" + params.toString(), {
        headers: { "X-Requested-With": "XMLHttpRequest" },
        cache: "no-store"
      });
      const result = await response.json();

      if (!response.ok || result.status !== "success") {
        throw new Error(result.message || "Unable to load student records.");
      }

      renderRows(result.items || [], resetList);
      if (resultCountLabel) {
        resultCountLabel.textContent = Number(result.total || 0).toLocaleString() + " matching records";
      }
      state.hasMore = Boolean(result.has_more);
      state.page += 1;
      if (sentinelText) {
        sentinelText.textContent = state.hasMore ? "Scroll to load more students." : "All matching students are loaded.";
      }
    } catch (error) {
      if (resetList) {
        listBody.innerHTML = '<tr><td colspan="4" class="empty-state">' + escapeHtml(error.message || "Unable to load student records.") + "</td></tr>";
      }
      if (sentinelText) {
        sentinelText.textContent = "Unable to load more students.";
      }
      showFeedback("danger", error.message || "Unable to load student records.");
    } finally {
      state.loading = false;
    }
  }

  function resolveAcademicYearContext() {
    const filterValue = academicYearFilterField ? String(academicYearFilterField.value || "").trim() : "";
    return filterValue || String(config.defaultAcademicYearLabel || "").trim();
  }

  function resolveSemesterContext() {
    const filterValue = semesterFilterField ? String(semesterFilterField.value || "").trim() : "";
    return filterValue || String(config.defaultSemesterLabel || "").trim();
  }

  function setAcademicContext(record) {
    if (record) {
      hiddenAcademicYear.value = String(record.academic_year_label || "");
      hiddenSemester.value = String(record.semester_label || "");
      hiddenCollege.value = String(record.college_name || "");
      hiddenCampus.value = String(record.campus_name || "");
      return;
    }

    hiddenAcademicYear.value = resolveAcademicYearContext();
    hiddenSemester.value = resolveSemesterContext();
    hiddenCollege.value = "";
    hiddenCampus.value = "";
  }

  function findProgramOptionById(programId) {
    if (!programSelectField || Number(programId || 0) <= 0) {
      return null;
    }

    return Array.from(programSelectField.options).find(function (option) {
      return Number(option.dataset.programId || option.value || 0) === Number(programId || 0);
    }) || null;
  }

  function findProgramOptionBySourceName(sourceName, collegeName, campusName) {
    if (!programSelectField) {
      return null;
    }

    const normalizedSourceName = normalizeLookupValue(sourceName);
    if (!normalizedSourceName) {
      return null;
    }

    const normalizedCollegeName = normalizeLookupValue(collegeName);
    const normalizedCampusName = normalizeLookupValue(campusName);
    const matches = Array.from(programSelectField.options).filter(function (option) {
      return normalizeLookupValue(option.dataset.sourceName) === normalizedSourceName;
    });

    if (!matches.length) {
      return null;
    }

    const locationMatch = matches.find(function (option) {
      const sameCollege = !normalizedCollegeName || normalizeLookupValue(option.dataset.college) === normalizedCollegeName;
      const sameCampus = !normalizedCampusName || normalizeLookupValue(option.dataset.campus) === normalizedCampusName;
      return sameCollege && sameCampus;
    });

    return locationMatch || matches[0] || null;
  }

  function removeLegacyProgramOption() {
    if (!programSelectField) {
      return;
    }

    const legacyOption = document.getElementById("studentProgramLegacyOption");
    if (legacyOption) {
      legacyOption.remove();
    }
  }

  function createLegacyProgramOption(record) {
    if (!programSelectField || !record || !record.source_program_name) {
      return;
    }

    removeLegacyProgramOption();

    const option = document.createElement("option");
    option.id = "studentProgramLegacyOption";
    option.value = "legacy-" + String(record.student_id || "record");
    option.dataset.programId = String(record.program_id || 0);
    option.dataset.sourceName = record.source_program_name || "";
    option.dataset.college = record.college_name || "";
    option.dataset.campus = record.campus_name || "";
    option.textContent = "Current record | " + String(record.source_program_name || "");
    programSelectField.appendChild(option);
    programSelectField.value = option.value;
  }

  function syncProgramSelection() {
    if (!programSelectField) {
      return;
    }

    const option = programSelectField.options[programSelectField.selectedIndex];
    programIdField.value = option ? String(option.dataset.programId || option.value || 0) : "0";
    sourceProgramNameField.value = option ? String(option.dataset.sourceName || "") : "";

    const selectedCollege = option ? String(option.dataset.college || "") : "";
    const selectedCampus = option ? String(option.dataset.campus || "") : "";

    if (selectedCollege || selectedCampus) {
      hiddenCollege.value = selectedCollege;
      hiddenCampus.value = selectedCampus;
    }
  }

  function setProgramSelection(record, allowLegacy) {
    if (!programSelectField) {
      return;
    }

    removeLegacyProgramOption();

    const recordProgramId = Number(record && record.program_id ? record.program_id : 0);
    const sourceProgramName = record ? String(record.source_program_name || "") : "";
    const collegeName = record ? String(record.college_name || "") : "";
    const campusName = record ? String(record.campus_name || "") : "";

    const option =
      findProgramOptionById(recordProgramId) ||
      findProgramOptionBySourceName(sourceProgramName, collegeName, campusName);

    if (option) {
      programSelectField.value = option.value;
      return;
    }

    if (allowLegacy && sourceProgramName) {
      createLegacyProgramOption(record);
      return;
    }

    programSelectField.value = "";
  }

  function buildInstitutionalEmail() {
    const sanitize = function (value) {
      return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "");
    };

    const firstToken = sanitize(firstNameField.value);
    const lastToken = sanitize(lastNameField.value);
    if (!firstToken || !lastToken) {
      return "";
    }

    const token = firstToken + lastToken;
    return token ? token + "@sksu.edu.ph" : "";
  }

  function generateInstitutionalEmail(force) {
    const nextEmail = buildInstitutionalEmail();
    if (force || autoEmailEnabled || !emailField.value.trim()) {
      emailField.value = nextEmail;
    }

    if (force) {
      autoEmailEnabled = true;
    }
  }

  function syncAutoEmailState() {
    const generatedEmail = buildInstitutionalEmail();
    const currentEmail = String(emailField.value || "").trim().toLowerCase();
    autoEmailEnabled = currentEmail === "" || (generatedEmail !== "" && currentEmail === generatedEmail.toLowerCase());
  }

  function openStudentModal(record) {
    if (!studentModal) {
      return;
    }

    studentForm.reset();
    removeLegacyProgramOption();
    programIdField.value = "0";
    sourceProgramNameField.value = "";
    autoEmailEnabled = true;

    if (record) {
      studentModalTitle.textContent = "Edit Student";
      studentIdField.value = record.student_id || 0;
      yearLevelField.value = record.year_level || "";
      studentNumberField.value = record.student_number || "";
      lastNameField.value = record.last_name || "";
      firstNameField.value = record.first_name || "";
      middleNameField.value = record.middle_name || "";
      suffixNameField.value = record.suffix_name || "";
      emailField.value = record.email_address || "";
      setAcademicContext(record);
      setProgramSelection(record, true);
    } else {
      studentModalTitle.textContent = "Add Student";
      studentIdField.value = "0";

      const currentProgramFilter = document.getElementById("source_program_name");
      const currentYearFilter = document.getElementById("year_level");
      setAcademicContext(null);
      if (currentProgramFilter && currentProgramFilter.value) {
        setProgramSelection({ source_program_name: currentProgramFilter.value }, false);
      } else if (programSelectField) {
        programSelectField.value = "";
      }
      if (currentYearFilter && currentYearFilter.value !== "0") {
        yearLevelField.value = currentYearFilter.value;
      }
    }

    syncProgramSelection();
    if (!emailField.value.trim()) {
      generateInstitutionalEmail(true);
    }
    syncAutoEmailState();
    studentModal.show();
  }

  function loadStudentForEdit(studentId) {
    return (async function () {
      try {
        const response = await fetch(apiUrl + "?action=get&student_id=" + encodeURIComponent(studentId), {
          headers: { "X-Requested-With": "XMLHttpRequest" },
          cache: "no-store"
        });
        const result = await response.json();

        if (!response.ok || result.status !== "success") {
          throw new Error(result.message || "Unable to load the student record.");
        }

        openStudentModal(result.record || null);
      } catch (error) {
        showFeedback("danger", error.message || "Unable to load the student record.");
      }
    })();
  }

  async function deleteStudent(studentId) {
    if (!window.confirm("Delete this student record? This action cannot be undone.")) {
      return;
    }

    try {
      const formData = new FormData();
      formData.append("student_id", studentId);

      const response = await fetch(apiUrl + "?action=delete", {
        method: "POST",
        body: formData,
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });
      const result = await response.json();

      if (!response.ok || result.status !== "success") {
        throw new Error(result.message || "Unable to delete the student record.");
      }

      showFeedback("success", result.message || "Student record deleted.");
      fetchList(true);
    } catch (error) {
      showFeedback("danger", error.message || "Unable to delete the student record.");
    }
  }

  if (addStudentBtn) {
    addStudentBtn.addEventListener("click", function () {
      openStudentModal(null);
    });
  }

  if (programSelectField) {
    programSelectField.addEventListener("change", syncProgramSelection);
  }

  if (generateEmailBtn) {
    generateEmailBtn.addEventListener("click", generateInstitutionalEmail);
  }

  [firstNameField, lastNameField].forEach(function (field) {
    if (!field) {
      return;
    }

    field.addEventListener("input", function () {
      generateInstitutionalEmail(false);
    });
  });

  if (emailField) {
    emailField.addEventListener("input", syncAutoEmailState);
  }

  filterForm.addEventListener("submit", function (event) {
    event.preventDefault();
    syncFiltersToUrl();
    fetchList(true);
  });

  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener("click", function () {
      const searchField = document.getElementById("search");
      const yearFilter = document.getElementById("year_level");
      const academicYearFilter = document.getElementById("academic_year_label");
      const semesterFilter = document.getElementById("semester_label");
      const programFilter = document.getElementById("source_program_name");

      if (searchField) searchField.value = "";
      if (yearFilter) yearFilter.value = "0";
      if (academicYearFilter) academicYearFilter.value = "";
      if (semesterFilter) semesterFilter.value = "";
      if (programFilter) programFilter.value = "";

      syncFiltersToUrl();
      fetchList(true);
    });
  }

  if (studentForm) {
    studentForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      if (!hiddenAcademicYear.value.trim() || !hiddenSemester.value.trim()) {
        setAcademicContext(null);
      }
      syncProgramSelection();

      try {
        const formData = new FormData(studentForm);
        const response = await fetch(apiUrl + "?action=save", {
          method: "POST",
          body: formData,
          headers: { "X-Requested-With": "XMLHttpRequest" }
        });
        const result = await response.json();

        if (!response.ok || result.status !== "success") {
          throw new Error(result.message || "Unable to save the student record.");
        }

        studentModal.hide();
        showFeedback("success", result.message || "Student record saved.");
        fetchList(true);
      } catch (error) {
        showFeedback("danger", error.message || "Unable to save the student record.");
      }
    });
  }

  listBody.addEventListener("click", function (event) {
    const editButton = event.target.closest(".btn-edit-student");
    if (editButton) {
      loadStudentForEdit(editButton.dataset.studentId);
      return;
    }

    const deleteButton = event.target.closest(".btn-delete-student");
    if (deleteButton) {
      deleteStudent(deleteButton.dataset.studentId);
    }
  });

  if (sentinel && "IntersectionObserver" in window) {
    state.observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          fetchList(false);
        }
      });
    }, {
      rootMargin: "200px 0px"
    });

    state.observer.observe(sentinel);
  }

  setAcademicContext(null);
  fetchList(true);
})();
