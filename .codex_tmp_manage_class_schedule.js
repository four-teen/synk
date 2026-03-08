
    const CSRF_TOKEN = null;

    $.ajaxPrefilter(function (options) {
      const method = (options.type || options.method || "GET").toUpperCase();
      if (method !== "POST") return;

      if (typeof options.data === "string") {
        const tokenPair = "csrf_token=" + encodeURIComponent(CSRF_TOKEN);
        options.data = options.data ? (options.data + "&" + tokenPair) : tokenPair;
        return;
      }

      if (Array.isArray(options.data)) {
        options.data.push({ name: "csrf_token", value: CSRF_TOKEN });
        return;
      }

      if ($.isPlainObject(options.data)) {
        options.data.csrf_token = CSRF_TOKEN;
        return;
      }

      if (!options.data) {
        options.data = { csrf_token: CSRF_TOKEN };
      }
    });

    function buildDayButtons(containerId, prefix) {
      const days = ['M','T','W','Th','F','S'];
      let html = '';

      days.forEach(d => {
        html += `
          <input type="checkbox" class="btn-check ${prefix}-day" id="${prefix}_${d}" value="${d}">
          <label class="btn btn-outline-secondary btn-sm me-1" for="${prefix}_${d}">
            ${d}
          </label>
        `;
      });

      $("#" + containerId).html(html);
    }

    let termRoomCacheKey = "";
    let termRoomCache = [];
    let scheduleListRequest = null;
    let scheduleAutoLoadTimer = null;
    let singleSuggestionTimer = null;
    let dualSuggestionTimer = null;
    const SCHEDULE_DAY_ORDER = ["M", "T", "W", "Th", "F", "S"];

    function escapeHtml(text) {
        return String(text || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function normalizeRoomType(roomType) {
        const value = String(roomType || "").toLowerCase().trim();
        return ["lecture", "laboratory", "lec_lab"].includes(value) ? value : "lecture";
    }

    function roomMatchesSchedule(room, scheduleType) {
        const roomType = normalizeRoomType(room && room.room_type);
        const type = String(scheduleType || "").toUpperCase();

        if (type === "LAB") {
            return roomType === "laboratory" || roomType === "lec_lab";
        }

        return roomType === "lecture" || roomType === "lec_lab";
    }

    function buildRoomOptionsHtml(rooms, placeholder) {
        const list = Array.isArray(rooms) ? rooms : [];
        const optionsHtml = list.map(r =>
            `<option value="${parseInt(r.room_id, 10)}">${escapeHtml(r.label)}</option>`
        ).join("");

        return `<option value="">${escapeHtml(placeholder)}</option>${optionsHtml}`;
    }

    function setRoomOptions(selector, rooms, placeholder, selectedRoomId = "") {
        $(selector).html(buildRoomOptionsHtml(rooms, placeholder));
        if (selectedRoomId !== "") {
            $(selector).val(String(selectedRoomId));
        }
    }

    function filterRoomsForSchedule(scheduleType) {
        return termRoomCache.filter(room => roomMatchesSchedule(room, scheduleType));
    }

    function applySingleScheduleRoomOptions(selectedRoomId = "") {
        const lectureRooms = filterRoomsForSchedule("LEC");
        setRoomOptions("#sched_room_id", lectureRooms, "Select lecture room...", selectedRoomId);
        return lectureRooms.length > 0;
    }

    function applyDualScheduleRoomOptions(selectedLectureRoomId = "", selectedLabRoomId = "") {
        const lectureRooms = filterRoomsForSchedule("LEC");
        const laboratoryRooms = filterRoomsForSchedule("LAB");

        setRoomOptions("#lec_room_id", lectureRooms, "Select lecture room...", selectedLectureRoomId);
        setRoomOptions("#lab_room_id", laboratoryRooms, "Select laboratory room...", selectedLabRoomId);

        return {
            lectureCount: lectureRooms.length,
            laboratoryCount: laboratoryRooms.length
        };
    }

    function clearTermRoomOptions() {
        setRoomOptions("#sched_room_id", [], "Select lecture room...");
        setRoomOptions("#lec_room_id", [], "Select lecture room...");
        setRoomOptions("#lab_room_id", [], "Select laboratory room...");
        termRoomCache = [];
        termRoomCacheKey = "";
    }

    function abortScheduleListRequest() {
        if (scheduleListRequest && scheduleListRequest.readyState !== 4) {
            scheduleListRequest.abort();
        }
        scheduleListRequest = null;
    }

    function renderScheduleListMessage(message, tone = "muted") {
        $("#scheduleListContainer").html(
            `<div class="text-center text-${escapeHtml(tone)} py-4">${message}</div>`
        );
    }

    function updateScheduleGroupCounts() {
        $("#scheduleListContainer .schedule-group-card").each(function () {
            const card = $(this);
            const visibleRows = card.find("tbody tr.schedule-offering-row[data-search-match='1']").length;
            card.find(".schedule-group-count").text(`${visibleRows} class(es)`);
            card.toggle(visibleRows > 0);
        });
    }

    function renderScheduleSearchEmptyState() {
        $("#scheduleSearchEmptyState").remove();

        const keyword = $("#scheduleSubjectSearch").val().trim();
        if (keyword === "") {
            return;
        }

        const hasVisibleRows = $("#scheduleListContainer tr.schedule-offering-row[data-search-match='1']").length > 0;
        if (hasVisibleRows) {
            return;
        }

        $("#scheduleListContainer").append(`
            <div id="scheduleSearchEmptyState" class="text-center text-muted py-4">
                No subjects matched <strong>${escapeHtml(keyword)}</strong> in the current filtered offerings.
            </div>
        `);
    }

    function applyScheduleSearchFilter() {
        const keyword = $("#scheduleSubjectSearch").val().trim().toLowerCase();
        const rows = $("#scheduleListContainer tr.schedule-offering-row");

        if (rows.length === 0) {
            $("#scheduleSearchEmptyState").remove();
            return;
        }

        rows.each(function () {
            const row = $(this);
            const haystack = String(row.data("searchText") || row.text()).toLowerCase();
            const isMatch = keyword === "" || haystack.includes(keyword);
            row.attr("data-search-match", isMatch ? "1" : "0");
            row.toggle(isMatch);
        });

        updateScheduleGroupCounts();
        renderScheduleSearchEmptyState();
    }

    function normalizeSuggestionDays(days) {
        const list = Array.isArray(days) ? days : [];
        return SCHEDULE_DAY_ORDER.filter(day => list.includes(day));
    }

    function collectCheckedDays(selector) {
        const days = [];
        $(selector).filter(":checked").each(function () {
            days.push($(this).val());
        });
        return normalizeSuggestionDays(days);
    }

    function setSingleScheduleDays(days) {
        $(".sched-day").prop("checked", false);
        normalizeSuggestionDays(days).forEach(function (day) {
            $("#day_" + day).prop("checked", true);
        });
    }

    function setDualScheduleDays(prefix, days) {
        $("." + prefix + "-day").prop("checked", false);
        normalizeSuggestionDays(days).forEach(function (day) {
            $("#" + prefix + "_" + day).prop("checked", true);
        });
    }

    function renderSuggestionBoardState(selector, message) {
        $(selector).html(`
            <div class="suggestion-empty">${escapeHtml(message)}</div>
        `);
    }

    function buildSuggestionCardHtml(item, targetMode) {
        const reasons = Array.isArray(item.reasons) ? item.reasons : [];
        const reasonsHtml = reasons.map(reason =>
            `<span class="suggestion-reason">${escapeHtml(reason)}</span>`
        ).join("");

        return `
            <div class="suggestion-card ${escapeHtml(item.fit_class || "valid")}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="suggestion-fit ${escapeHtml(item.fit_class || "valid")}">${escapeHtml(item.fit_label || "Valid Slot")}</span>
                            <span class="suggestion-chip">${escapeHtml(item.pattern_label || "Suggested Slot")}</span>
                        </div>
                        <div class="suggestion-slot">${escapeHtml(item.days_label || "")} • ${escapeHtml(item.time_label || "")}</div>
                        <div class="suggestion-meta mt-1">${escapeHtml(item.room_label || "")}</div>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary btn-use-suggestion"
                        data-target-mode="${escapeHtml(targetMode)}"
                        data-schedule-type="${escapeHtml(item.schedule_type || "LEC")}"
                        data-room-id="${escapeHtml(item.room_id || "")}"
                        data-time-start="${escapeHtml(item.time_start || "")}"
                        data-time-end="${escapeHtml(item.time_end || "")}"
                        data-days='${escapeHtml(JSON.stringify(item.days || []))}'>
                        Use Slot
                    </button>
                </div>
                <div class="suggestion-reasons mt-3">${reasonsHtml}</div>
            </div>
        `;
    }

    function renderSuggestionBoard(selector, suggestions, targetMode, emptyMessage) {
        if (!Array.isArray(suggestions) || suggestions.length === 0) {
            renderSuggestionBoardState(selector, emptyMessage);
            return;
        }

        $(selector).html(
            suggestions.map(item => buildSuggestionCardHtml(item, targetMode)).join("")
        );
    }

    function setSuggestionToggleButton(buttonSelector, isVisible, showLabel, hideLabel) {
        $(buttonSelector).html(
            `<i class="bx bx-bulb me-1"></i> ${escapeHtml(isVisible ? hideLabel : showLabel)}`
        );
    }

    function setSuggestionPanelVisibility(panelSelector, buttonSelector, isVisible, showLabel, hideLabel) {
        $(panelSelector).toggleClass("d-none", !isVisible);
        setSuggestionToggleButton(buttonSelector, isVisible, showLabel, hideLabel);
    }

    function panelIsVisible(panelSelector) {
        return !$(panelSelector).hasClass("d-none");
    }

    function resetSingleSuggestionPanel() {
        renderSuggestionBoardState("#singleSuggestionBoard", "Click Show Suggested Schedule to load ranked schedule options.");
        setSuggestionPanelVisibility(
            "#singleSuggestionPanel",
            "#btnToggleSingleSuggestions",
            false,
            "Show Suggested Schedule",
            "Hide Suggested Schedule"
        );
    }

    function resetDualSuggestionPanels() {
        renderSuggestionBoardState("#dualLectureSuggestionBoard", "Click Show Suggested Lecture Schedule to load ranked lecture options.");
        renderSuggestionBoardState("#dualLabSuggestionBoard", "Click Show Suggested Lab Schedule to load ranked lab options.");
        setSuggestionPanelVisibility(
            "#dualLectureSuggestionPanel",
            "#btnToggleLectureSuggestions",
            false,
            "Show Suggested Lecture Schedule",
            "Hide Suggested Lecture Schedule"
        );
        setSuggestionPanelVisibility(
            "#dualLabSuggestionPanel",
            "#btnToggleLabSuggestions",
            false,
            "Show Suggested Lab Schedule",
            "Hide Suggested Lab Schedule"
        );
    }

    function buildSuggestionCardHtml(item, targetMode) {
        const reasons = Array.isArray(item.reasons) ? item.reasons : [];
        const reasonsHtml = reasons.map(reason =>
            `<span class="suggestion-reason">${escapeHtml(reason)}</span>`
        ).join("");

        return `
            <div class="suggestion-card ${escapeHtml(item.fit_class || "valid")}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="suggestion-fit ${escapeHtml(item.fit_class || "valid")}">${escapeHtml(item.fit_label || "Valid Slot")}</span>
                            <span class="suggestion-chip">${escapeHtml(item.pattern_label || "Suggested Slot")}</span>
                        </div>
                        <div class="suggestion-slot">${escapeHtml(item.days_label || "")} - ${escapeHtml(item.time_label || "")}</div>
                        <div class="suggestion-meta mt-1">${escapeHtml(item.room_label || "")}</div>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary btn-use-suggestion"
                        data-target-mode="${escapeHtml(targetMode)}"
                        data-schedule-type="${escapeHtml(item.schedule_type || "LEC")}"
                        data-room-id="${escapeHtml(item.room_id || "")}"
                        data-time-start="${escapeHtml(item.time_start || "")}"
                        data-time-end="${escapeHtml(item.time_end || "")}"
                        data-days='${escapeHtml(JSON.stringify(item.days || []))}'>
                        Use Slot
                    </button>
                </div>
                <div class="suggestion-reasons mt-3">${reasonsHtml}</div>
            </div>
        `;
    }

    function collectSingleDrafts() {
        return {
            LEC: {
                room_id: $("#sched_room_id").val() || "",
                time_start: $("#sched_time_start").val(),
                time_end: $("#sched_time_end").val(),
                days: collectCheckedDays(".sched-day")
            }
        };
    }

    function collectDualDrafts() {
        return {
            LEC: {
                room_id: $("#lec_room_id").val() || "",
                time_start: $("#lec_time_start").val(),
                time_end: $("#lec_time_end").val(),
                days: collectCheckedDays(".lec-day")
            },
            LAB: {
                room_id: $("#lab_room_id").val() || "",
                time_start: $("#lab_time_start").val(),
                time_end: $("#lab_time_end").val(),
                days: collectCheckedDays(".lab-day")
            }
        };
    }

    function requestScheduleSuggestions(offeringId, drafts, onSuccess, onError) {
        $.ajax({
            url: "../backend/load_schedule_suggestions.php",
            type: "POST",
            dataType: "json",
            data: {
                offering_id: offeringId,
                drafts_json: JSON.stringify(drafts || {})
            },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    onError((res && res.message) ? res.message : "Unable to load suggestions.");
                    return;
                }
                onSuccess(res);
            },
            error: function () {
                onError("Unable to load suggestions.");
            }
        });
    }

    function loadSingleScheduleSuggestions() {
        const offeringId = $("#sched_offering_id").val();

        if (!offeringId) {
            renderSuggestionBoardState("#singleSuggestionBoard", "Suggestions will appear after selecting a class.");
            return;
        }

        renderSuggestionBoardState("#singleSuggestionBoard", "Loading lecture suggestions...");

        requestScheduleSuggestions(
            offeringId,
            collectSingleDrafts(),
            function (res) {
                renderSuggestionBoard(
                    "#singleSuggestionBoard",
                    (res.suggestions && res.suggestions.LEC) ? res.suggestions.LEC : [],
                    "single",
                    "No conflict-free lecture suggestions found inside the current scheduling window."
                );
            },
            function (message) {
                renderSuggestionBoardState("#singleSuggestionBoard", message);
            }
        );
    }

    function loadDualScheduleSuggestions() {
        const offeringId = $("#dual_offering_id").val();

        if (!offeringId) {
            renderSuggestionBoardState("#dualLectureSuggestionBoard", "Suggestions will appear after selecting a class.");
            renderSuggestionBoardState("#dualLabSuggestionBoard", "Suggestions will appear after selecting a class.");
            return;
        }

        renderSuggestionBoardState("#dualLectureSuggestionBoard", "Loading lecture suggestions...");
        renderSuggestionBoardState("#dualLabSuggestionBoard", "Loading lab suggestions...");

        requestScheduleSuggestions(
            offeringId,
            collectDualDrafts(),
            function (res) {
                renderSuggestionBoard(
                    "#dualLectureSuggestionBoard",
                    (res.suggestions && res.suggestions.LEC) ? res.suggestions.LEC : [],
                    "dual",
                    "No conflict-free lecture suggestions found inside the current scheduling window."
                );
                renderSuggestionBoard(
                    "#dualLabSuggestionBoard",
                    (res.suggestions && res.suggestions.LAB) ? res.suggestions.LAB : [],
                    "dual",
                    "No conflict-free lab suggestions found inside the current scheduling window."
                );
            },
            function (message) {
                renderSuggestionBoardState("#dualLectureSuggestionBoard", message);
                renderSuggestionBoardState("#dualLabSuggestionBoard", message);
            }
        );
    }

    function queueSingleSuggestionRefresh() {
        if (singleSuggestionTimer) {
            clearTimeout(singleSuggestionTimer);
        }

        singleSuggestionTimer = window.setTimeout(function () {
            if ($("#scheduleModal").hasClass("show") && panelIsVisible("#singleSuggestionPanel")) {
                loadSingleScheduleSuggestions();
            }
        }, 220);
    }

    function queueDualSuggestionRefresh() {
        if (dualSuggestionTimer) {
            clearTimeout(dualSuggestionTimer);
        }

        dualSuggestionTimer = window.setTimeout(function () {
            if (
                $("#dualScheduleModal").hasClass("show") &&
                (panelIsVisible("#dualLectureSuggestionPanel") || panelIsVisible("#dualLabSuggestionPanel"))
            ) {
                loadDualScheduleSuggestions();
            }
        }, 220);
    }

    function applySingleSuggestion(item) {
        setSingleScheduleDays(item.days || []);
        $("#sched_time_start").val(item.time_start || "");
        $("#sched_time_end").val(item.time_end || "");
        $("#sched_room_id").val(String(item.room_id || ""));
        setSuggestionPanelVisibility(
            "#singleSuggestionPanel",
            "#btnToggleSingleSuggestions",
            false,
            "Show Suggested Schedule",
            "Hide Suggested Schedule"
        );
    }

    function applyDualSuggestion(scheduleType, item) {
        const prefix = scheduleType === "LAB" ? "lab" : "lec";

        setDualScheduleDays(prefix, item.days || []);
        $("#" + prefix + "_time_start").val(item.time_start || "");
        $("#" + prefix + "_time_end").val(item.time_end || "");
        $("#" + prefix + "_room_id").val(String(item.room_id || ""));

        if (scheduleType === "LAB") {
            setSuggestionPanelVisibility(
                "#dualLabSuggestionPanel",
                "#btnToggleLabSuggestions",
                false,
                "Show Suggested Lab Schedule",
                "Hide Suggested Lab Schedule"
            );
            return;
        }

        setSuggestionPanelVisibility(
            "#dualLectureSuggestionPanel",
            "#btnToggleLectureSuggestions",
            false,
            "Show Suggested Lecture Schedule",
            "Hide Suggested Lecture Schedule"
        );
    }

    function ensureDefaultProspectus() {
        const prospectusSelect = $("#prospectus_id");
        if (prospectusSelect.val()) {
            return;
        }

        const firstProspectus = prospectusSelect.find("option").filter(function () {
            return $(this).val() !== "";
        }).first().val();

        if (firstProspectus) {
            prospectusSelect.val(firstProspectus);
        }
    }

    function loadTermRoomOptions(forceReload = false) {
        const dfd = $.Deferred();
        const ay = $("#ay_id").val();
        const sem = $("#semester").val();

        if (!ay || !sem) {
            clearTermRoomOptions();
            dfd.reject("missing_term");
            return dfd.promise();
        }

        const key = `${ay}-${sem}`;
        if (!forceReload && key === termRoomCacheKey && termRoomCache.length > 0) {
            dfd.resolve(termRoomCache);
            return dfd.promise();
        }

        $.ajax({
            url: "../backend/load_term_room_options.php",
            type: "POST",
            dataType: "json",
            data: {
                ay_id: ay,
                semester: sem
            },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    clearTermRoomOptions();
                    dfd.reject((res && res.message) ? res.message : "Failed to load rooms.");
                    return;
                }

                termRoomCacheKey = key;
                termRoomCache = Array.isArray(res.rooms) ? res.rooms : [];
                if (termRoomCache.length === 0) {
                    clearTermRoomOptions();
                    dfd.reject("No rooms are available for selected AY and Semester.");
                    return;
                }
                dfd.resolve(termRoomCache);
            },
            error: function () {
                clearTermRoomOptions();
                dfd.reject("Failed to load rooms.");
            }
        });

        return dfd.promise();
    }


    function loadScheduleTable(forceRoomReload = true) {

        const pid = $("#prospectus_id").val();
        const ay  = $("#ay_id").val();
        const sem = $("#semester").val();

        if (!pid || !ay || !sem) {
            abortScheduleListRequest();
            renderScheduleListMessage("Select Prospectus, Academic Year, and Semester to view class offerings.");
            return;
        }

        abortScheduleListRequest();
        renderScheduleListMessage("Loading classes...");

        loadTermRoomOptions(forceRoomReload)
            .always(function () {
                scheduleListRequest = $.post(
                    "../backend/load_class_offerings.php",
                    {
                        prospectus_id: pid,
                        ay_id: ay,
                        semester: sem
                    },
                    function (rows) {
                        $("#scheduleListContainer").html(rows);
                        applyScheduleSearchFilter();
                    }
                ).fail(function (xhr) {
                    if (xhr.statusText === "abort") {
                        return;
                    }
                    $("#scheduleListContainer").html(
                        "<div class='text-center text-danger py-4'>Failed to load classes.</div>"
                    );
                    console.error(xhr.responseText);
                });
            });
    }

    function scheduleAutoLoad(forceRoomReload = true) {
        if (scheduleAutoLoadTimer) {
            clearTimeout(scheduleAutoLoadTimer);
        }

        scheduleAutoLoadTimer = window.setTimeout(function () {
            loadScheduleTable(forceRoomReload);
        }, 120);
    }

    function clearScheduleForOffering(offeringId, subjectLabel) {
        if (!offeringId) {
            Swal.fire("Error", "Missing offering reference.", "error");
            return;
        }

        const label = subjectLabel || "this class";

        Swal.fire({
            icon: "warning",
            title: "Clear Schedule?",
            html: `This will remove all saved schedules for <b>${escapeHtml(label)}</b>.`,
            showCancelButton: true,
            confirmButtonText: "Yes, clear it",
            cancelButtonText: "Cancel",
            allowOutsideClick: false,
            customClass: { popup: "swal-top" }
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: "../backend/query_class_schedule.php",
                type: "POST",
                dataType: "json",
                data: {
                    clear_schedule: 1,
                    offering_id: offeringId
                },
                success: function (res) {
                    if (res.status === "ok") {
                        Swal.fire({
                            icon: "success",
                            title: "Schedule Cleared",
                            timer: 1200,
                            showConfirmButton: false
                        });

                        $("#scheduleModal").modal("hide");
                        $("#dualScheduleModal").modal("hide");

                        setTimeout(function () {
                            loadScheduleTable();
                        }, 300);
                        return;
                    }

                    Swal.fire("Error", res.message || "Failed to clear schedule.", "error");
                },
                error: function (xhr) {
                    Swal.fire("Error", xhr.responseText || "Failed to clear schedule.", "error");
                }
            });
        });
    }

    // ===============================================================
$(document).ready(function () {

ensureDefaultProspectus();
resetSingleSuggestionPanel();
resetDualSuggestionPanels();

$("#prospectus_id, #ay_id, #semester").on("change", function () {
  if (this.id === "ay_id" || this.id === "semester") {
    clearTermRoomOptions();
  }
  scheduleAutoLoad(true);
});

$("#scheduleSubjectSearch").on("input", function () {
  applyScheduleSearchFilter();
});

$("#btnRefreshSingleSuggestions").on("click", function () {
  loadSingleScheduleSuggestions();
});

$("#btnRefreshLectureSuggestions, #btnRefreshLabSuggestions").on("click", function () {
  loadDualScheduleSuggestions();
});

$("#btnToggleSingleSuggestions").on("click", function () {
  const shouldShow = !panelIsVisible("#singleSuggestionPanel");
  setSuggestionPanelVisibility(
    "#singleSuggestionPanel",
    "#btnToggleSingleSuggestions",
    shouldShow,
    "Show Suggested Schedule",
    "Hide Suggested Schedule"
  );

  if (shouldShow) {
    loadSingleScheduleSuggestions();
  }
});

$("#btnToggleLectureSuggestions").on("click", function () {
  const shouldShow = !panelIsVisible("#dualLectureSuggestionPanel");
  setSuggestionPanelVisibility(
    "#dualLectureSuggestionPanel",
    "#btnToggleLectureSuggestions",
    shouldShow,
    "Show Suggested Lecture Schedule",
    "Hide Suggested Lecture Schedule"
  );

  if (shouldShow) {
    loadDualScheduleSuggestions();
  }
});

$("#btnToggleLabSuggestions").on("click", function () {
  const shouldShow = !panelIsVisible("#dualLabSuggestionPanel");
  setSuggestionPanelVisibility(
    "#dualLabSuggestionPanel",
    "#btnToggleLabSuggestions",
    shouldShow,
    "Show Suggested Lab Schedule",
    "Hide Suggested Lab Schedule"
  );

  if (shouldShow) {
    loadDualScheduleSuggestions();
  }
});

$("#scheduleModal").on("hidden.bs.modal", function () {
  resetSingleSuggestionPanel();
});

$("#dualScheduleModal").on("hidden.bs.modal", function () {
  resetDualSuggestionPanels();
});

$(document).on("input change", "#scheduleModal .sched-day, #scheduleModal #sched_time_start, #scheduleModal #sched_time_end, #scheduleModal #sched_room_id", function () {
  if ($("#scheduleModal").hasClass("show")) {
    queueSingleSuggestionRefresh();
  }
});

$(document).on("input change", "#dualScheduleModal .lec-day, #dualScheduleModal .lab-day, #dualScheduleModal #lec_time_start, #dualScheduleModal #lec_time_end, #dualScheduleModal #lec_room_id, #dualScheduleModal #lab_time_start, #dualScheduleModal #lab_time_end, #dualScheduleModal #lab_room_id", function () {
  if ($("#dualScheduleModal").hasClass("show")) {
    queueDualSuggestionRefresh();
  }
});

$(document).on("click", ".btn-use-suggestion", function () {
  const button = $(this);
  let days = [];

  try {
    days = JSON.parse(button.attr("data-days") || "[]");
  } catch (error) {
    days = [];
  }

  const item = {
    room_id: button.data("roomId"),
    time_start: button.data("timeStart"),
    time_end: button.data("timeEnd"),
    days: normalizeSuggestionDays(days)
  };

  if (button.data("targetMode") === "single") {
    applySingleSuggestion(item);
    return;
  }

  applyDualSuggestion(String(button.data("scheduleType") || "LEC").toUpperCase(), item);
});

scheduleAutoLoad(true);


$("#btnShowMatrix").on("click", function () {

  const ay  = $("#ay_id").val();
  const sem = $("#semester").val();

  if (!ay || !sem) {
    Swal.fire(
      "Missing Filters",
      "Please select Academic Year and Semester first.",
      "warning"
    );
    return;
  }

  $("#matrixModal").modal("show");

  $("#matrixContainer").html(`
    <div class="text-center text-muted py-5">
      Loading room utilization…
    </div>
  `);

  $.post(
    "../backend/load_room_time_matrix.php",
    {
      ay_id: ay,
      semester: sem
    },
    function (html) {
      $("#matrixContainer").html(html);
    }
  ).fail(function (xhr) {
    $("#matrixContainer").html(
      "<div class='text-danger text-center'>Failed to load matrix.</div>"
    );
    console.error(xhr.responseText);
  });

});




        // ============================
        // CLICK SCHEDULE / EDIT BUTTON
        // ============================
$(document).on("click", ".btn-schedule", function () {

    const btn = $(this);

    const offeringId = btn.data("offering-id");
    const labUnits   = parseFloat(btn.data("lab-units")) || 0;
    const isEditMode = btn.text().trim().toLowerCase() === "edit";

    const subCode = btn.data("sub-code");
    const subDesc = btn.data("sub-desc");
    const section = btn.data("section");

    // Shared labels
    const subjectLabel = subCode + " — " + subDesc;

    // ============================
    // CASE A — LECTURE ONLY
    // ============================
    if (labUnits === 0) {

        // Populate existing modal
        $("#sched_offering_id").val(offeringId);
        $("#sched_subject_label").text(subjectLabel);
        $("#sched_section_label").text("Section: " + section);
        $("#btnClearSchedule")
            .data("offering-id", offeringId)
            .data("subject-label", subjectLabel)
            .toggleClass("d-none", !isEditMode);
        $("#btnClearDualSchedule").addClass("d-none");

        // Reset fields
        $(".sched-day").prop("checked", false);
        $("#sched_time_start").val("");
        $("#sched_time_end").val("");
        $("#sched_room_id").val("").trigger("change");

        // Existing data (edit mode)
        const daysJson = btn.data("days-json");
        if (daysJson) {
            try {
                JSON.parse(daysJson).forEach(d => {
                    $("#day_" + d).prop("checked", true);
                });
            } catch(e){}
        }

        if (btn.data("time-start")) $("#sched_time_start").val(btn.data("time-start"));
        if (btn.data("time-end"))   $("#sched_time_end").val(btn.data("time-end"));

        const selectedRoomId = btn.data("room-id") ? String(btn.data("room-id")) : "";
        resetSingleSuggestionPanel();
        loadTermRoomOptions(false).done(function () {
            if (!applySingleScheduleRoomOptions(selectedRoomId)) {
                Swal.fire("Room Setup Issue", "No lecture-compatible rooms are available for selected AY and Semester.", "warning");
                return;
            }
            $("#scheduleModal").modal("show");
        }).fail(function (message) {
            Swal.fire("Room Setup Issue", message || "No room is available for selected AY and Semester.", "warning");
        });
        return;
    }

// ============================
// CASE B — LECTURE + LAB
// ============================
$("#dual_offering_id").val(offeringId);
$("#dual_subject_label").text(subjectLabel);
$("#dual_section_label").text("Section: " + section);
$("#btnClearDualSchedule")
    .data("offering-id", offeringId)
    .data("subject-label", subjectLabel)
    .toggleClass("d-none", !isEditMode);
$("#btnClearSchedule").addClass("d-none");
resetDualSuggestionPanels();

// Build day buttons first
buildDayButtons("lec_days", "lec");
buildDayButtons("lab_days", "lab");

// CLEAR fields (default)
$("#lec_time_start, #lec_time_end, #lab_time_start, #lab_time_end").val("");
$("#lec_room_id, #lab_room_id").val("");
$(".lec-day, .lab-day").prop("checked", false);

// ============================
// EDIT MODE → LOAD EXISTING
// ============================
if (isEditMode) {

    $.ajax({
        url: "../backend/query_class_schedule.php",
        type: "POST",
        dataType: "json",
        data: {
            load_dual_schedule: 1,
            offering_id: offeringId
        },
        success: function (res) {

            if (res.status !== "ok") {
                Swal.fire("Error", res.message, "error");
                return;
            }

            // -------- LECTURE --------
            if (res.LEC) {
                $("#lec_time_start").val(res.LEC.time_start);
                $("#lec_time_end").val(res.LEC.time_end);

                res.LEC.days.forEach(d => {
                    $("#lec_" + d).prop("checked", true);
                });
            }

            // -------- LAB --------
            if (res.LAB) {
                $("#lab_time_start").val(res.LAB.time_start);
                $("#lab_time_end").val(res.LAB.time_end);

                res.LAB.days.forEach(d => {
                    $("#lab_" + d).prop("checked", true);
                });
            }

            loadTermRoomOptions(false).done(function () {
                const roomCounts = applyDualScheduleRoomOptions(
                    res.LEC && res.LEC.room_id ? String(res.LEC.room_id) : "",
                    res.LAB && res.LAB.room_id ? String(res.LAB.room_id) : ""
                );

                if (roomCounts.lectureCount === 0) {
                    Swal.fire("Room Setup Issue", "No lecture-compatible rooms are available for selected AY and Semester.", "warning");
                    return;
                }

                if (roomCounts.laboratoryCount === 0) {
                    Swal.fire("Room Setup Issue", "No laboratory-compatible rooms are available for selected AY and Semester.", "warning");
                    return;
                }

                $("#dualScheduleModal").modal("show");
            }).fail(function (message) {
                Swal.fire("Room Setup Issue", message || "No room is available for selected AY and Semester.", "warning");
            });
        },
        error: function (xhr) {
            Swal.fire("Error", xhr.responseText, "error");
        }
    });

} else {
    // NEW ENTRY MODE
    loadTermRoomOptions(false).done(function () {
        const roomCounts = applyDualScheduleRoomOptions();
        if (roomCounts.lectureCount === 0) {
            Swal.fire("Room Setup Issue", "No lecture-compatible rooms are available for selected AY and Semester.", "warning");
            return;
        }
        if (roomCounts.laboratoryCount === 0) {
            Swal.fire("Room Setup Issue", "No laboratory-compatible rooms are available for selected AY and Semester.", "warning");
            return;
        }
        $("#dualScheduleModal").modal("show");
    }).fail(function (message) {
        Swal.fire("Room Setup Issue", message || "No room is available for selected AY and Semester.", "warning");
    });
}



});



$("#btnClearSchedule").on("click", function () {
    clearScheduleForOffering(
        $(this).data("offering-id"),
        $(this).data("subject-label")
    );
});

$("#btnClearDualSchedule").on("click", function () {
    clearScheduleForOffering(
        $(this).data("offering-id"),
        $(this).data("subject-label")
    );
});

const SUPPORTED_TIME_START = "07:30";
const SUPPORTED_TIME_END = "17:30";

function isWithinSupportedScheduleWindow(timeStart, timeEnd) {
    return Boolean(timeStart) &&
           Boolean(timeEnd) &&
           timeStart >= SUPPORTED_TIME_START &&
           timeEnd <= SUPPORTED_TIME_END;
}

// ============================
// SAVE CLASS SCHEDULE
// ============================
$(document).off("click", "#btnSaveSchedule").on("click", "#btnSaveSchedule", function (event) {
    event.preventDefault();
    event.stopPropagation();

    const offering_id = $("#sched_offering_id").val();
    const room_id     = $("#sched_room_id").val();
    const time_start  = $("#sched_time_start").val();
    const time_end    = $("#sched_time_end").val();

    let days = [];
    $(".sched-day:checked").each(function () {
        days.push($(this).val());
    });

    // ----------------------------
    // VALIDATION (Improved)
    // ----------------------------
    function showValidation(title, message) {
        // keep modal open but bring alert to front
        Swal.fire({
            icon: "warning",
            title: title,
            html: message,
            allowOutsideClick: false,
            customClass: {
                popup: 'swal-top'
            }
        });
    }

    if (!offering_id) {
        showValidation("Missing Data", "Offering reference is missing. Please reload the page.");
        return;
    }

    if (!room_id) {
        showValidation("Missing Room", "Please select a room.");
        return;
    }

    if (!time_start || !time_end) {
        showValidation("Missing Time", "Please provide both start and end time.");
        return;
    }

    if (time_end <= time_start) {
        showValidation(
            "Invalid Time Range",
            "End time must be later than start time."
        );
        return;
    }

    if (!isWithinSupportedScheduleWindow(time_start, time_end)) {
        showValidation(
            "Unsupported Time Window",
            "Class schedules must stay within 7:30 AM to 5:30 PM."
        );
        return;
    }

    if (days.length === 0) {
        showValidation(
            "Missing Days",
            "Please select at least one day for the class schedule."
        );
        return;
    }


        // ----------------------------
        // AJAX SAVE
        // ----------------------------
        $.ajax({
            url: "../backend/query_class_schedule.php",
            type: "POST",
            dataType: "json",
            data: {
                save_schedule: 1,
                offering_id: offering_id,
                room_id: room_id,
                time_start: time_start,
                time_end: time_end,
                days_json: JSON.stringify(days)
            },
            success: function (res) {

                if (res.status === "conflict") {
                    Swal.fire({
                        icon: "error",
                        title: "Schedule Conflict",
                        html: res.message,
                        allowOutsideClick: false,
                        customClass: { popup: 'swal-top' }
                    });
                    return;
                }

                if (res.status === "ok") {
                    Swal.fire({
                        icon: "success",
                        title: "Schedule Saved",
                        timer: 1200,
                        showConfirmButton: false
                    });

                    $("#scheduleModal").modal("hide");

                    setTimeout(function () {
                        loadScheduleTable();
                    }, 300);
                    return;
                }

                Swal.fire("Error", res.message || "Unknown error.", "error");
            },
            error: function (xhr) {
                Swal.fire("Error", xhr.responseText, "error");
            }
        });
    });

// =======================================================
// SAVE LECTURE + LAB SCHEDULE
// =======================================================
$(document).off("click", "#btnSaveDualSchedule").on("click", "#btnSaveDualSchedule", function (event) {
    event.preventDefault();
    event.stopPropagation();

    const offering_id = $("#dual_offering_id").val();

    if (!offering_id) {
        Swal.fire("Error", "Missing offering reference.", "error");
        return;
    }

    function collectDays(prefix) {
        let days = [];
        $("." + prefix + "-day:checked").each(function () {
            days.push($(this).val());
        });
        return days;
    }

    // -----------------------------
    // LECTURE DATA
    // -----------------------------
    const lec = {
        type: "LEC",
        room_id: $("#lec_room_id").val(),
        time_start: $("#lec_time_start").val(),
        time_end: $("#lec_time_end").val(),
        days: collectDays("lec")
    };

    // -----------------------------
    // LAB DATA
    // -----------------------------
    const lab = {
        type: "LAB",
        room_id: $("#lab_room_id").val(),
        time_start: $("#lab_time_start").val(),
        time_end: $("#lab_time_end").val(),
        days: collectDays("lab")
    };

    // -----------------------------
    // BASIC VALIDATION
    // -----------------------------
    function invalidBlock(title, msg) {
        Swal.fire({
            icon: "warning",
            title: title,
            html: msg,
            customClass: { popup: 'swal-top' }
        });
    }

    if (!lec.room_id || !lec.time_start || !lec.time_end || lec.days.length === 0) {
        invalidBlock("Lecture Incomplete", "Please complete lecture schedule.");
        return;
    }

    if (!lab.room_id || !lab.time_start || !lab.time_end || lab.days.length === 0) {
        invalidBlock("Laboratory Incomplete", "Please complete laboratory schedule.");
        return;
    }

    if (lec.time_end <= lec.time_start) {
        invalidBlock("Lecture Time Error", "Lecture end time must be later than start time.");
        return;
    }

    if (lab.time_end <= lab.time_start) {
        invalidBlock("Lab Time Error", "Lab end time must be later than start time.");
        return;
    }

    if (!isWithinSupportedScheduleWindow(lec.time_start, lec.time_end)) {
        invalidBlock("Lecture Time Error", "Lecture schedule must stay within 7:30 AM to 5:30 PM.");
        return;
    }

    if (!isWithinSupportedScheduleWindow(lab.time_start, lab.time_end)) {
        invalidBlock("Lab Time Error", "Laboratory schedule must stay within 7:30 AM to 5:30 PM.");
        return;
    }

    // -----------------------------
    // BUILD PAYLOAD
    // -----------------------------
    const payload = {
        save_dual_schedule: 1,
        offering_id: offering_id,
        schedules: [
            {
                type: "LEC",
                room_id: lec.room_id,
                time_start: lec.time_start,
                time_end: lec.time_end,
                days_json: JSON.stringify(lec.days)
            },
            {
                type: "LAB",
                room_id: lab.room_id,
                time_start: lab.time_start,
                time_end: lab.time_end,
                days_json: JSON.stringify(lab.days)
            }
        ]
    };

    // -----------------------------
    // AJAX SAVE
    // -----------------------------
    $.ajax({
        url: "../backend/query_class_schedule.php",
        type: "POST",
        dataType: "json",
        data: payload,
success: function (res) {

    if (res.status === "conflict") {
        Swal.fire({
            icon: "error",
            title: "Schedule Conflict",
            html: res.message,
            allowOutsideClick: false,
            customClass: { popup: 'swal-top' }
        });
        return;
    }

    if (res.status === "ok") {
        Swal.fire({
            icon: "success",
            title: "Schedule Saved",
            timer: 1200,
            showConfirmButton: false
        });

        $("#dualScheduleModal").modal("hide");

        setTimeout(function () {
            loadScheduleTable();
        }, 300);
        return;
    }

    Swal.fire("Error", res.message || "Unknown error.", "error");
},
        error: function (xhr) {
            Swal.fire("Error", xhr.responseText, "error");
        }
    });

});

});




