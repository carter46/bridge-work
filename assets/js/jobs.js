(function () {
  var PILL_ACTIVE = ["bg-primary", "text-on-primary"];
  var PILL_INACTIVE = ["bg-surface-container", "text-on-surface", "hover:bg-surface-container-high"];
  var BAND_ACTIVE = ["bg-primary", "text-on-primary"];
  var BAND_INACTIVE = ["bg-surface-container-low", "text-on-surface", "hover:bg-surface-container"];
  var CATEGORIES = ["tech", "operations", "marketing", "sales", "design", "education"];
  var WORK_TYPES = ["worldwide", "ukeu", "flexible"];

  var form = document.getElementById("job-search-form");
  var searchInput = document.getElementById("role-search");
  var categorySelect = document.getElementById("category-filter");
  var workSelect = document.getElementById("work-type-filter");
  var sortSelect = document.getElementById("sort-select");
  var pills = document.querySelectorAll("#quick-pills .pill-btn");
  var groups = document.querySelectorAll(".job-category-group");
  var checkboxes = document.querySelectorAll(".category-cb");
  var bandButtons = document.querySelectorAll(".band-btn");
  var resultsCount = document.getElementById("results-count");
  var resultsNoun = document.getElementById("results-noun");
  var sectorCount = document.getElementById("sector-count");
  var sectorNoun = document.getElementById("sector-noun");
  var emptyState = document.getElementById("jobs-empty");
  var locationNote = document.getElementById("location-note");
  var locationText = document.getElementById("location-text");
  var results = document.getElementById("job-results");

  var state = { category: "all", work: "all", band: null };

  function toggleClasses(el, classes, on) {
    classes.forEach(function (c) { el.classList.toggle(c, on); });
  }

  function setFlexVisible(el, visible) {
    if (!el) return;
    el.classList.toggle("hidden", !visible);
    el.classList.toggle("flex", visible);
  }

  // Index each listing once: original order (for "newest" sort) and searchable text without icon ligatures.
  groups.forEach(function (group) {
    group.querySelectorAll(".job-item").forEach(function (item, index) {
      var clone = item.cloneNode(true);
      clone.querySelectorAll(".material-symbols-outlined").forEach(function (icon) { icon.remove(); });
      item.setAttribute("data-order", index);
      item.setAttribute("data-search", clone.textContent.replace(/\s+/g, " ").toLowerCase());
    });
  });

  function syncControls() {
    categorySelect.value = state.category;
    workSelect.value = state.work;

    pills.forEach(function (pill) {
      var isActive;
      if (pill.hasAttribute("data-work")) {
        isActive = state.category === "all" && state.work === pill.getAttribute("data-work");
      } else {
        var cat = pill.getAttribute("data-cat");
        isActive = cat === state.category && !(cat === "all" && state.work === "flexible");
      }
      toggleClasses(pill, PILL_ACTIVE, isActive);
      toggleClasses(pill, PILL_INACTIVE, !isActive);
      pill.setAttribute("aria-pressed", isActive ? "true" : "false");
    });

    bandButtons.forEach(function (btn) {
      var isActive = !!state.band &&
        state.band[0] === Number(btn.getAttribute("data-min")) &&
        state.band[1] === Number(btn.getAttribute("data-max"));
      toggleClasses(btn, BAND_ACTIVE, isActive);
      toggleClasses(btn, BAND_INACTIVE, !isActive);
      btn.setAttribute("aria-pressed", isActive ? "true" : "false");
    });
  }

  function filterJobs() {
    var terms = searchInput.value.toLowerCase().split(/\s+/).filter(Boolean);
    var enabled = Array.prototype.filter.call(checkboxes, function (cb) { return cb.checked; })
      .map(function (cb) { return cb.value; });
    var total = 0;
    var sectors = 0;

    groups.forEach(function (group) {
      var cat = group.getAttribute("data-category");
      var groupAllowed = (state.category === "all" || state.category === cat) && enabled.indexOf(cat) !== -1;
      var visible = 0;

      group.querySelectorAll(".job-item").forEach(function (item) {
        var text = item.getAttribute("data-search");
        var work = (item.getAttribute("data-work") || "").split(" ");
        var min = Number(item.getAttribute("data-min"));
        var max = Number(item.getAttribute("data-max"));
        var show = groupAllowed &&
          terms.every(function (t) { return text.indexOf(t) !== -1; }) &&
          (state.work === "all" || work.indexOf(state.work) !== -1) &&
          (!state.band || (min <= state.band[1] && max >= state.band[0]));

        item.classList.toggle("hidden", !show);
        if (show) visible++;
      });

      group.classList.toggle("hidden", visible === 0);
      total += visible;
      if (visible > 0) sectors++;
    });

    resultsCount.textContent = total;
    resultsNoun.textContent = total === 1 ? "Role" : "Roles";
    sectorCount.textContent = sectors;
    sectorNoun.textContent = sectors === 1 ? "Sector" : "Sectors";
    setFlexVisible(emptyState, total === 0);
  }

  function sortJobs() {
    var byRate = sortSelect.value === "rate";
    groups.forEach(function (group) {
      var list = group.querySelector(".job-list");
      var items = Array.prototype.slice.call(list.querySelectorAll(".job-item"));
      items.sort(function (a, b) {
        if (byRate) {
          return (b.getAttribute("data-max") - a.getAttribute("data-max")) ||
            (b.getAttribute("data-min") - a.getAttribute("data-min"));
        }
        return a.getAttribute("data-order") - b.getAttribute("data-order");
      });
      items.forEach(function (item) { list.appendChild(item); });
    });
  }

  function update() {
    syncControls();
    filterJobs();
  }

  function resetFilters() {
    searchInput.value = "";
    state = { category: "all", work: "all", band: null };
    checkboxes.forEach(function (cb) { cb.checked = true; });
    sortSelect.value = "newest";
    setFlexVisible(locationNote, false);
    sortJobs();
    update();
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    update();
    if (results) results.scrollIntoView({ behavior: "smooth", block: "start" });
  });

  searchInput.addEventListener("input", filterJobs);

  categorySelect.addEventListener("change", function () {
    state.category = categorySelect.value;
    update();
  });

  workSelect.addEventListener("change", function () {
    state.work = workSelect.value;
    update();
  });

  pills.forEach(function (pill) {
    pill.addEventListener("click", function () {
      if (pill.hasAttribute("data-work")) {
        state.category = "all";
        state.work = pill.getAttribute("data-work");
      } else {
        state.category = pill.getAttribute("data-cat");
        if (state.category === "all") state.work = "all";
      }
      update();
    });
  });

  bandButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var band = [Number(btn.getAttribute("data-min")), Number(btn.getAttribute("data-max"))];
      var alreadyActive = state.band && state.band[0] === band[0] && state.band[1] === band[1];
      state.band = alreadyActive ? null : band;
      update();
    });
  });

  checkboxes.forEach(function (cb) { cb.addEventListener("change", filterJobs); });

  sortSelect.addEventListener("change", sortJobs);

  document.querySelectorAll("[data-reset-filters]").forEach(function (btn) {
    btn.addEventListener("click", resetFilters);
  });

  // Pre-fill from links such as the homepage search (?q=&loc=) and category links (?category=).
  var params = new URLSearchParams(window.location.search);
  var q = (params.get("q") || "").trim();
  var loc = (params.get("loc") || "").trim();
  var category = (params.get("category") || "").toLowerCase();
  var work = (params.get("work") || "").toLowerCase();

  if (q) searchInput.value = q;
  if (CATEGORIES.indexOf(category) !== -1) state.category = category;
  if (WORK_TYPES.indexOf(work) !== -1) state.work = work;
  if (loc && !/remote/i.test(loc)) {
    locationText.textContent = loc;
    setFlexVisible(locationNote, true);
  }

  update();

  // Wait for load so the Tailwind CDN has styled the page before measuring scroll position.
  if ((q || category || loc) && results) {
    window.addEventListener("load", function () {
      results.scrollIntoView({ block: "start" });
    });
  }
})();
