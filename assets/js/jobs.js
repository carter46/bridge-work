(function () {
  var SOURCES = window.HJ.JOB_SOURCES;
  var LINK_ACTIVE = ["bg-primary", "text-white", "font-semibold"];
  var LINK_INACTIVE = ["text-text-main", "hover:bg-slate-100"];
  var EMPLOYMENT_LABELS = { full_time: "Full-time", part_time: "Part-time", contract: "Contract", temporary: "Temporary" };
  var ARRANGEMENT_LABELS = { remote: "Remote", hybrid: "Hybrid", onsite: "On-site" };
  var REGION_LABELS = { worldwide: "Open worldwide", uk_europe: "UK & Europe", uk_only: "UK only" };
  // Links from the old site used ?work=…; map them to the new independent filters.
  var LEGACY_WORK = {
    worldwide: { region: "worldwide" },
    ukeu: { region: "uk_europe" },
    flexible: { flexible: true },
    remote: { arrangement: "remote" }
  };

  var esc = window.HJ.escape;
  var form = document.getElementById("job-search-form");
  var searchInput = document.getElementById("role-search");
  var locationInput = document.getElementById("location-search");
  var locationOptions = document.getElementById("location-options");
  var categorySelect = document.getElementById("category-filter");
  var typeSelect = document.getElementById("type-filter");
  var arrangementSelect = document.getElementById("arrangement-filter");
  var regionSelect = document.getElementById("region-filter");
  var flexibleCheckbox = document.getElementById("flexible-filter");
  var sortSelect = document.getElementById("sort-select");
  var categoryList = document.getElementById("category-list");
  var bandButtons = document.querySelectorAll(".band-btn");
  var groupsBox = document.getElementById("job-groups");
  var loadingBox = document.getElementById("jobs-loading");
  var errorBox = document.getElementById("jobs-error");
  var retryButton = document.getElementById("jobs-retry");
  var emptyState = document.getElementById("jobs-empty");
  var emptyTitle = document.getElementById("jobs-empty-title");
  var emptyText = document.getElementById("jobs-empty-text");
  var resultsCount = document.getElementById("results-count");
  var resultsNoun = document.getElementById("results-noun");
  var sectorCount = document.getElementById("sector-count");
  var sectorNoun = document.getElementById("sector-noun");
  var locationNote = document.getElementById("location-note");
  var locationText = document.getElementById("location-text");
  var locationClear = document.getElementById("location-clear");
  var results = document.getElementById("job-results");

  var data = null;
  var categoryBySlug = {};
  var state = freshState();

  function freshState() {
    return { category: "all", type: "all", arrangement: "all", region: "all", flexible: false, band: null, loc: "" };
  }

  function toggleClasses(el, classes, on) {
    classes.forEach(function (c) { el.classList.toggle(c, on); });
  }

  function setFlexVisible(el, visible) {
    if (!el) return;
    el.classList.toggle("hidden", !visible);
    el.classList.toggle("flex", visible);
  }

  function isValidPayload(payload) {
    return payload && Array.isArray(payload.jobs) && Array.isArray(payload.categories);
  }

  /* ---------- Loading ---------- */

  function load() {
    setFlexVisible(errorBox, false);
    if (loadingBox) loadingBox.classList.remove("hidden");
    window.HJ.fetchJson(SOURCES, isValidPayload).then(init, showError);
  }

  function showError() {
    if (loadingBox) loadingBox.classList.add("hidden");
    setFlexVisible(errorBox, true);
    setFlexVisible(emptyState, false);
    ["stat-total", "stat-sectors", "stat-pay"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = "—";
    });
    if (categoryList) categoryList.innerHTML = '<li class="py-2 text-sm text-text-muted">Unavailable right now.</li>';
    resultsCount.textContent = "0";
    sectorCount.textContent = "0";
  }

  function showSnapshotNote(payload) {
    var note = document.getElementById("jobs-snapshot-note");
    var fromSnapshot = payload._hjSource && payload._hjSource !== SOURCES[0];
    if (note) note.classList.toggle("hidden", !fromSnapshot);
    var dateEl = document.getElementById("jobs-snapshot-date");
    if (!fromSnapshot || !dateEl) return;
    var when = payload.generated_at ? new Date(payload.generated_at) : null;
    dateEl.textContent = when && !isNaN(when.getTime())
      ? " (updated " + when.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" }) + ")"
      : "";
  }

  function init(payload) {
    data = payload;
    categoryBySlug = {};
    data.categories.forEach(function (c) { categoryBySlug[c.slug] = c; });
    data.jobs.forEach(function (job, index) {
      job._order = index;
      job._search = window.HJ.jobSearchText(job, categoryBySlug[job.category] ? categoryBySlug[job.category].name : "");
      job._place = window.HJ.jobPlaceWords(job);
    });

    if (loadingBox) loadingBox.classList.add("hidden");
    showSnapshotNote(payload);
    renderStats();
    renderCategoryControls();
    renderLocationOptions();
    var hadFilterParams = /[?&](q|loc|category|type|arrangement|region|flexible|band|work)=/.test(window.location.search);
    applyUrlParams();
    if (locationInput) locationInput.value = state.loc;
    update();

    if (hadFilterParams && results) {
      // Wait for load so the Tailwind CDN has styled the page before measuring scroll position.
      if (document.readyState === "complete") results.scrollIntoView({ block: "start" });
      else window.addEventListener("load", function () { results.scrollIntoView({ block: "start" }); });
    }
  }

  /* ---------- Static parts built from the data ---------- */

  function renderStats() {
    var total = document.getElementById("stat-total");
    var sectors = document.getElementById("stat-sectors");
    var pay = document.getElementById("stat-pay");
    var payLabel = document.getElementById("stat-pay-label");
    var label = (data.stats && data.stats.pay_label) || "";
    if (total) total.textContent = data.jobs.length;
    if (sectors) sectors.textContent = data.categories.length;
    if (pay) {
      if (/\/hr$/.test(label)) {
        pay.textContent = label.replace(/\/hr$/, "");
        if (payLabel) payLabel.textContent = "Hourly pay";
      } else {
        pay.textContent = label || "Varies";
        if (payLabel) payLabel.textContent = "Pay";
      }
    }
  }

  function categoryLink(slug, name, count) {
    return '<li><button class="category-link w-full flex items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors" data-cat="' + esc(slug) + '" type="button">' +
      "<span>" + esc(name) + '</span><span class="text-xs opacity-70">' + count + "</span></button></li>";
  }

  function renderCategoryControls() {
    categorySelect.innerHTML = '<option value="all">All sectors</option>' +
      data.categories.map(function (c) {
        return '<option value="' + esc(c.slug) + '">' + esc(c.name) + "</option>";
      }).join("");

    if (categoryList) {
      categoryList.innerHTML = categoryLink("all", "All roles", data.jobs.length) +
        data.categories.map(function (c) { return categoryLink(c.slug, c.name, c.count); }).join("");
      categoryList.querySelectorAll(".category-link").forEach(function (btn) {
        btn.addEventListener("click", function () {
          state.category = btn.getAttribute("data-cat");
          update();
          if (results && results.getBoundingClientRect().top < 0) results.scrollIntoView({ behavior: "smooth", block: "start" });
        });
      });
    }
  }

  function renderLocationOptions() {
    if (!locationOptions) return;
    locationOptions.innerHTML = window.HJ.placeOptions(data.jobs).map(function (p) {
      return '<option value="' + esc(p.label) + '">' + p.count + (p.count === 1 ? " role" : " roles") + "</option>";
    }).join("");
  }

  /* ---------- Filtering ---------- */

  function matches(job, terms, useLoc) {
    if (useLoc && !window.HJ.placeMatches(job._place, state.loc)) return false;
    if (state.category !== "all" && job.category !== state.category) return false;
    if (state.type !== "all" && job.employment_types.indexOf(state.type) === -1) return false;
    if (state.arrangement !== "all" && job.work_arrangement !== state.arrangement) return false;
    if (state.region !== "all" && job.applicant_region !== state.region) return false;
    if (state.flexible && job.schedule !== "flexible") return false;
    if (state.band) {
      // Only USD roles have an hourly equivalent; other currencies never match a USD band.
      if (job.hourly_min == null && job.hourly_max == null) return false;
      var min = job.hourly_min != null ? job.hourly_min : job.hourly_max;
      var max = job.hourly_max != null ? job.hourly_max : job.hourly_min;
      if (!(min <= state.band[1] && max >= state.band[0])) return false;
    }
    for (var i = 0; i < terms.length; i++) {
      if (job._search.indexOf(terms[i]) === -1) return false;
    }
    return true;
  }

  function sortJobs(list) {
    var byRate = sortSelect.value === "rate";
    return list.slice().sort(function (a, b) {
      if (byRate) {
        return ((b.hourly_max || 0) - (a.hourly_max || 0)) || ((b.hourly_min || 0) - (a.hourly_min || 0)) || (a._order - b._order);
      }
      var ta = a.posted_at ? Date.parse(a.posted_at) : 0;
      var tb = b.posted_at ? Date.parse(b.posted_at) : 0;
      return (tb - ta) || (a._order - b._order);
    });
  }

  /* ---------- Rendering ---------- */

  function jobRow(job) {
    var place = job.location_text || ARRANGEMENT_LABELS[job.work_arrangement] || "";
    var schedule = job.schedule_note;
    if (!schedule) {
      var parts = job.employment_types.map(function (t) { return EMPLOYMENT_LABELS[t] || t; });
      if (job.schedule === "flexible") parts.push("Flexible hours");
      schedule = parts.join(" / ");
    }
    var meta = [job.company_name, place, schedule, job.skills].filter(Boolean);
    var flags = [];
    if (job.employer_verified) flags.push('<span class="text-emerald-700 font-semibold">Verified employer</span>');
    if (job.badge) flags.push('<span class="text-secondary font-semibold">' + esc(job.badge) + "</span>");

    var href = "apply.html?job=" + encodeURIComponent(job.id) + "&role=" + encodeURIComponent(job.title);
    return '<li class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-5 py-5 sm:px-6 hover:bg-slate-50/70 transition-colors">' +
      '<div class="min-w-0">' +
      '<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">' +
      '<h3 class="text-base sm:text-lg font-bold text-primary"><a class="hover:text-secondary transition-colors" href="' + esc(href) + '">' + esc(job.title) + "</a></h3>" +
      '<span class="text-xs text-text-subtle">' + esc(job.ref_code) + "</span>" +
      (flags.length ? '<span class="text-xs">' + flags.join(' <span class="text-slate-300">·</span> ') + "</span>" : "") +
      "</div>" +
      (meta.length ? '<p class="mt-1 text-sm text-text-muted">' + meta.map(esc).join(' <span class="text-slate-300">·</span> ') + "</p>" : "") +
      (job.description ? '<p class="mt-2 text-sm text-text-muted leading-relaxed">' + esc(job.description) + "</p>" : "") +
      "</div>" +
      '<div class="flex sm:flex-col items-center sm:items-end justify-between gap-2 shrink-0">' +
      (job.pay_label ? '<span class="font-semibold text-primary whitespace-nowrap">' + esc(job.pay_label) + "</span>" : "") +
      '<a class="inline-flex items-center justify-center px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-primary hover:border-secondary hover:bg-secondary hover:text-white transition-colors" href="' + esc(href) + '">Apply</a>' +
      "</div></li>";
  }

  function groupSection(category, jobs) {
    var summary = jobs.length + (jobs.length === 1 ? " role" : " roles") + (category.pay_label ? " · " + category.pay_label : "");
    return '<section class="job-category-group" data-category="' + esc(category.slug) + '">' +
      '<div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">' +
      '<h2 class="text-xl font-bold text-primary tracking-tight">' + esc(category.name) + "</h2>" +
      '<span class="text-sm text-text-muted">' + esc(summary) + "</span>" +
      "</div>" +
      (category.description ? '<p class="mt-1 text-sm text-text-muted">' + esc(category.description) + "</p>" : "") +
      '<ul class="job-list mt-4 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white overflow-hidden">' + jobs.map(jobRow).join("") + "</ul>" +
      "</section>";
  }

  function syncControls() {
    categorySelect.value = state.category;
    typeSelect.value = state.type;
    arrangementSelect.value = state.arrangement;
    regionSelect.value = state.region;
    flexibleCheckbox.checked = state.flexible;

    if (categoryList) {
      categoryList.querySelectorAll(".category-link").forEach(function (btn) {
        var active = btn.getAttribute("data-cat") === state.category;
        toggleClasses(btn, LINK_ACTIVE, active);
        toggleClasses(btn, LINK_INACTIVE, !active);
        btn.setAttribute("aria-pressed", active ? "true" : "false");
      });
    }

    bandButtons.forEach(function (btn) {
      var active = !!state.band && state.band[0] === Number(btn.getAttribute("data-min")) && state.band[1] === Number(btn.getAttribute("data-max"));
      toggleClasses(btn, LINK_ACTIVE, active);
      toggleClasses(btn, LINK_INACTIVE, !active);
      btn.setAttribute("aria-pressed", active ? "true" : "false");
    });
  }

  function collect(terms, useLoc) {
    var groups = [];
    var total = 0;
    data.categories.forEach(function (category) {
      var jobs = data.jobs.filter(function (job) { return job.category === category.slug && matches(job, terms, useLoc); });
      if (!jobs.length) return;
      total += jobs.length;
      groups.push({ category: category, jobs: jobs });
    });
    return { groups: groups, total: total };
  }

  function render() {
    var terms = searchInput.value.toLowerCase().split(/\s+/).filter(Boolean);
    var found = collect(terms, !!state.loc);
    var locMissed = false;
    if (state.loc && found.total === 0) {
      // Nothing listed for that place: show what matches everything else instead of an empty page.
      var withoutLoc = collect(terms, false);
      if (withoutLoc.total > 0) {
        found = withoutLoc;
        locMissed = true;
      }
    }

    groupsBox.innerHTML = found.groups.map(function (g) { return groupSection(g.category, sortJobs(g.jobs)); }).join("");
    var total = found.total;
    var sectors = found.groups.length;
    resultsCount.textContent = total;
    resultsNoun.textContent = total === 1 ? "role" : "roles";
    sectorCount.textContent = sectors;
    sectorNoun.textContent = sectors === 1 ? "sector" : "sectors";

    if (state.loc) {
      locationText.textContent = locMissed
        ? "No roles are listed for “" + state.loc + "” yet, so we're showing every role that matches your other filters. Most roles are remote."
        : "Showing roles for “" + state.loc + "”.";
    }
    setFlexVisible(locationNote, !!state.loc);

    if (data.jobs.length === 0) {
      emptyTitle.textContent = "No open roles right now";
      emptyText.textContent = "New roles are added regularly. Send us your CV and we'll match you with suitable openings.";
    } else {
      emptyTitle.textContent = "No roles match your filters";
      emptyText.textContent = "Try a different keyword or filter, or send us your CV and we'll match you with suitable openings.";
    }
    setFlexVisible(emptyState, total === 0);
  }

  function writeUrl() {
    if (!window.history || !window.history.replaceState) return;
    // Keep parameters this page doesn't manage (utm_*, gclid).
    var params = new URLSearchParams(window.location.search);
    ["q", "loc", "category", "type", "arrangement", "region", "flexible", "band", "work"].forEach(function (key) { params.delete(key); });
    var q = searchInput.value.trim();
    if (q) params.set("q", q);
    if (state.loc) params.set("loc", state.loc);
    if (state.category !== "all") params.set("category", state.category);
    if (state.type !== "all") params.set("type", state.type);
    if (state.arrangement !== "all") params.set("arrangement", state.arrangement);
    if (state.region !== "all") params.set("region", state.region);
    if (state.flexible) params.set("flexible", "1");
    if (state.band) params.set("band", state.band[0] + "-" + state.band[1]);
    var query = params.toString();
    window.history.replaceState(null, "", window.location.pathname + (query ? "?" + query : "") + window.location.hash);
  }

  function update() {
    if (!data) return;
    syncControls();
    render();
    writeUrl();
  }

  function resolveCategory(slug) {
    if (!slug) return null;
    if (categoryBySlug[slug]) return slug;
    for (var i = 0; i < data.categories.length; i++) {
      if ((data.categories[i].aliases || []).indexOf(slug) !== -1) return data.categories[i].slug;
    }
    return null;
  }

  function applyUrlParams() {
    var params = new URLSearchParams(window.location.search);
    var q = (params.get("q") || "").trim();
    if (q) searchInput.value = q.slice(0, 100);
    state.loc = (params.get("loc") || "").trim().slice(0, 80);

    var category = resolveCategory((params.get("category") || "").toLowerCase());
    if (category) state.category = category;

    var type = params.get("type");
    if (EMPLOYMENT_LABELS[type]) state.type = type;
    var arrangement = params.get("arrangement");
    if (ARRANGEMENT_LABELS[arrangement]) state.arrangement = arrangement;
    var region = params.get("region");
    if (REGION_LABELS[region]) state.region = region;
    if (params.get("flexible") === "1") state.flexible = true;

    var band = (params.get("band") || "").match(/^(\d+)-(\d+)$/);
    if (band) state.band = [Number(band[1]), Number(band[2])];

    var legacy = LEGACY_WORK[(params.get("work") || "").toLowerCase()];
    if (legacy) {
      if (legacy.region) state.region = legacy.region;
      if (legacy.arrangement) state.arrangement = legacy.arrangement;
      if (legacy.flexible) state.flexible = true;
    }
  }

  function resetFilters() {
    searchInput.value = "";
    if (locationInput) locationInput.value = "";
    state = freshState();
    sortSelect.value = "newest";
    update();
  }

  /* ---------- Events ---------- */

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (locationInput) state.loc = locationInput.value.trim().slice(0, 80);
    update();
    if (results) results.scrollIntoView({ behavior: "smooth", block: "start" });
  });
  searchInput.addEventListener("input", update);
  if (locationInput) {
    // Picking a suggestion (or clearing the box) applies straight away; free typing waits for Search.
    locationInput.addEventListener("input", function () {
      var value = locationInput.value.trim();
      var picked = locationOptions && Array.prototype.some.call(locationOptions.options, function (o) { return o.value === value; });
      if (picked || value === "") {
        state.loc = value;
        update();
      }
    });
  }
  categorySelect.addEventListener("change", function () { state.category = categorySelect.value; update(); });
  typeSelect.addEventListener("change", function () { state.type = typeSelect.value; update(); });
  arrangementSelect.addEventListener("change", function () { state.arrangement = arrangementSelect.value; update(); });
  regionSelect.addEventListener("change", function () { state.region = regionSelect.value; update(); });
  flexibleCheckbox.addEventListener("change", function () { state.flexible = flexibleCheckbox.checked; update(); });
  sortSelect.addEventListener("change", update);

  bandButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var band = [Number(btn.getAttribute("data-min")), Number(btn.getAttribute("data-max"))];
      var alreadyActive = state.band && state.band[0] === band[0] && state.band[1] === band[1];
      state.band = alreadyActive ? null : band;
      update();
    });
  });

  document.querySelectorAll("[data-reset-filters]").forEach(function (btn) {
    btn.addEventListener("click", resetFilters);
  });

  if (locationClear) locationClear.addEventListener("click", function () {
    state.loc = "";
    if (locationInput) locationInput.value = "";
    update();
  });
  if (retryButton) retryButton.addEventListener("click", load);

  load();
})();
