(function () {
  var SOURCES = window.HJ.JOB_SOURCES;
  var PILL_ACTIVE = ["bg-primary", "text-on-primary"];
  var PILL_INACTIVE = ["bg-surface-container", "text-on-surface", "hover:bg-surface-container-high"];
  var BAND_ACTIVE = ["bg-primary", "text-on-primary"];
  var BAND_INACTIVE = ["bg-surface-container-low", "text-on-surface", "hover:bg-surface-container"];
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
  var categorySelect = document.getElementById("category-filter");
  var typeSelect = document.getElementById("type-filter");
  var arrangementSelect = document.getElementById("arrangement-filter");
  var regionSelect = document.getElementById("region-filter");
  var flexibleCheckbox = document.getElementById("flexible-filter");
  var sortSelect = document.getElementById("sort-select");
  var pillBar = document.getElementById("quick-pills");
  var checkboxBox = document.getElementById("category-checkboxes");
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
    return { category: "all", type: "all", arrangement: "all", region: "all", flexible: false, band: null, hidden: {}, loc: "" };
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
    if (checkboxBox) checkboxBox.innerHTML = '<p class="font-body-sm text-body-sm text-on-surface-variant p-2">Categories are unavailable right now.</p>';
    resultsCount.textContent = "0";
    sectorCount.textContent = "0";
  }

  function showSnapshotNote(payload) {
    var note = document.getElementById("jobs-snapshot-note");
    var fromSnapshot = payload._hjSource && payload._hjSource !== SOURCES[0];
    setFlexVisible(note, !!fromSnapshot);
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
    var hadFilterParams = /[?&](q|loc|category|type|arrangement|region|flexible|band|work)=/.test(window.location.search);
    applyUrlParams();
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
        if (payLabel) payLabel.textContent = "Hourly Pay Range";
      } else {
        pay.textContent = label || "Varies";
        if (payLabel) payLabel.textContent = "Pay Range";
      }
    }
  }

  function renderCategoryControls() {
    categorySelect.innerHTML = '<option value="all">All Sectors (' + data.categories.length + ")</option>" +
      data.categories.map(function (c) {
        return '<option value="' + esc(c.slug) + '">' + esc(c.name) + "</option>";
      }).join("");

    var pills = '<button class="pill-btn px-space-md py-1.5 rounded-full font-label-md text-label-md whitespace-nowrap transition-colors" data-cat="all" type="button">All Roles (' + data.jobs.length + ")</button>";
    data.categories.forEach(function (c) {
      pills += '<button class="pill-btn px-space-md py-1.5 rounded-full font-label-md text-label-md whitespace-nowrap transition-colors" data-cat="' + esc(c.slug) + '" type="button">' +
        esc(c.name) + (c.pay_label ? " (" + esc(c.pay_label) + ")" : "") + "</button>";
    });
    var hasFlexible = data.jobs.some(function (j) { return j.schedule === "flexible"; });
    if (hasFlexible) {
      pills += '<button class="pill-btn px-space-md py-1.5 rounded-full font-label-md text-label-md whitespace-nowrap transition-colors" data-flexible="1" type="button">Flexible Hours</button>';
    }
    pillBar.innerHTML = pills;
    pillBar.querySelectorAll(".pill-btn").forEach(function (pill) {
      pill.addEventListener("click", function () {
        if (pill.hasAttribute("data-flexible")) {
          state.flexible = !state.flexible;
        } else {
          state.category = pill.getAttribute("data-cat");
          if (state.category === "all") state.flexible = false;
        }
        update();
      });
    });

    if (checkboxBox) {
      checkboxBox.innerHTML = data.categories.map(function (c) {
        return '<label class="flex items-center justify-between p-2 rounded hover:bg-surface-container-low cursor-pointer group">' +
          '<span class="flex items-center gap-space-sm"><input checked class="category-cb w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer" type="checkbox" value="' + esc(c.slug) + '"/>' +
          '<span class="font-body-md text-body-md text-on-surface group-hover:text-primary">' + esc(c.name) + "</span></span>" +
          '<span class="font-label-sm text-label-sm bg-surface-container px-2 py-0.5 rounded text-on-surface-variant">' + c.count + "</span></label>";
      }).join("");
      checkboxBox.querySelectorAll(".category-cb").forEach(function (cb) {
        cb.addEventListener("change", function () {
          if (cb.checked) delete state.hidden[cb.value];
          else state.hidden[cb.value] = true;
          update();
        });
      });
    }
  }

  /* ---------- Filtering ---------- */

  function matches(job, terms, useLoc) {
    if (useLoc && !window.HJ.placeMatches(job._place, state.loc)) return false;
    if (state.category !== "all" && job.category !== state.category) return false;
    if (state.hidden[job.category]) return false;
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

  function metaItem(icon, text) {
    return '<span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">' + esc(icon) + "</span>" + esc(text) + "</span>";
  }

  function jobCard(job) {
    var badges = '<span class="font-label-sm text-label-sm px-2 py-0.5 bg-surface-container text-primary rounded">' + esc(job.ref_code) + "</span>";
    if (job.employer_verified) {
      badges += '<span class="inline-flex items-center gap-1 font-label-sm text-label-sm px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded"><span class="material-symbols-outlined text-[14px]">verified</span>Verified employer</span>';
    }
    if (job.badge) {
      badges += '<span class="font-label-sm text-label-sm text-secondary">' + esc(job.badge) + "</span>";
    }

    var meta = [];
    if (job.pay_label) meta.push(metaItem("payments", job.pay_label));
    if (job.skills) meta.push(metaItem(job.icon || "work", job.skills));
    if (job.company_name) meta.push(metaItem("apartment", job.company_name));
    if (job.location_text) {
      meta.push(metaItem(job.applicant_region === "uk_europe" || job.applicant_region === "uk_only" ? "location_on" : "public", job.location_text));
    } else if (ARRANGEMENT_LABELS[job.work_arrangement]) {
      meta.push(metaItem("home_work", ARRANGEMENT_LABELS[job.work_arrangement]));
    }
    if (job.schedule_note) {
      meta.push(metaItem("schedule", job.schedule_note));
    } else {
      var parts = job.employment_types.map(function (t) { return EMPLOYMENT_LABELS[t] || t; });
      if (job.schedule === "flexible") parts.push("Flexible hours");
      if (parts.length) meta.push(metaItem("schedule", parts.join(" / ")));
    }

    var href = "apply.html?job=" + encodeURIComponent(job.id) + "&role=" + encodeURIComponent(job.title);
    return '<article class="job-item bg-surface-container-lowest p-5 sm:p-space-lg rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col md:flex-row md:items-center justify-between gap-space-md">' +
      '<div class="flex flex-col gap-space-xs min-w-0">' +
      '<div class="flex flex-wrap items-center gap-space-xs">' + badges + "</div>" +
      '<h3 class="font-title-md text-title-md text-primary font-bold">' + esc(job.title) + "</h3>" +
      (job.description ? '<p class="font-body-sm text-body-sm text-on-surface-variant">' + esc(job.description) + "</p>" : "") +
      '<div class="flex flex-wrap items-center gap-x-space-md gap-y-1 font-body-sm text-body-sm text-on-surface-variant">' + meta.join("") + "</div>" +
      "</div>" +
      '<a class="w-full md:w-auto flex-shrink-0 inline-flex justify-center px-space-md py-2.5 bg-secondary text-on-secondary hover:bg-secondary-container hover:text-on-secondary-container font-label-lg text-label-lg rounded-lg transition-colors font-bold shadow-sm" href="' + esc(href) + '">Apply Now</a>' +
      "</article>";
  }

  function groupSection(category, jobs) {
    return '<section class="job-category-group flex flex-col gap-space-md" data-category="' + esc(category.slug) + '">' +
      '<div class="bg-surface-container-low p-space-md rounded-xl">' +
      '<div class="flex flex-wrap items-center gap-space-sm">' +
      '<span class="w-8 h-8 rounded-lg bg-primary text-on-primary flex items-center justify-center"><span class="material-symbols-outlined text-[20px]">' + esc(category.icon || "work") + "</span></span>" +
      '<h2 class="font-headline-md text-xl sm:text-headline-md text-primary font-bold">' + esc(category.name) + "</h2>" +
      (category.pay_label ? '<span class="px-2 py-0.5 bg-primary-fixed text-on-primary-fixed font-label-sm text-label-sm rounded-full">' + esc(category.pay_label.replace("/hr", "/Hr")) + "</span>" : "") +
      "</div>" +
      (category.description ? '<p class="font-body-md text-body-md text-on-surface-variant mt-space-xs">' + esc(category.description) + "</p>" : "") +
      "</div>" +
      '<div class="job-list flex flex-col gap-space-sm">' + jobs.map(jobCard).join("") + "</div>" +
      "</section>";
  }

  function syncControls() {
    categorySelect.value = state.category;
    typeSelect.value = state.type;
    arrangementSelect.value = state.arrangement;
    regionSelect.value = state.region;
    flexibleCheckbox.checked = state.flexible;

    pillBar.querySelectorAll(".pill-btn").forEach(function (pill) {
      var active = pill.hasAttribute("data-flexible") ? state.flexible : pill.getAttribute("data-cat") === state.category;
      toggleClasses(pill, PILL_ACTIVE, active);
      toggleClasses(pill, PILL_INACTIVE, !active);
      pill.setAttribute("aria-pressed", active ? "true" : "false");
    });

    if (checkboxBox) {
      checkboxBox.querySelectorAll(".category-cb").forEach(function (cb) {
        cb.checked = !state.hidden[cb.value];
      });
    }

    bandButtons.forEach(function (btn) {
      var active = !!state.band && state.band[0] === Number(btn.getAttribute("data-min")) && state.band[1] === Number(btn.getAttribute("data-max"));
      toggleClasses(btn, BAND_ACTIVE, active);
      toggleClasses(btn, BAND_INACTIVE, !active);
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

    var html = found.groups.map(function (g) { return groupSection(g.category, sortJobs(g.jobs)); }).join("");
    var total = found.total;
    var sectors = found.groups.length;

    if (state.loc) {
      locationText.textContent = locMissed
        ? "No roles are listed for “" + state.loc + "” yet, so we're showing every role that matches your other filters. Most roles are remote."
        : "Showing roles for “" + state.loc + "”.";
    }
    setFlexVisible(locationNote, !!state.loc);

    groupsBox.innerHTML = html;
    resultsCount.textContent = total;
    resultsNoun.textContent = total === 1 ? "Role" : "Roles";
    sectorCount.textContent = sectors;
    sectorNoun.textContent = sectors === 1 ? "Sector" : "Sectors";

    if (data.jobs.length === 0) {
      emptyTitle.textContent = "No open roles right now";
      emptyText.textContent = "New roles are added regularly. Send us your CV and our team will match you with suitable openings.";
    } else {
      emptyTitle.textContent = "No roles match your filters";
      emptyText.textContent = "Try a different keyword or filter, or send us your CV and our team will match you with suitable openings.";
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
    var loc = (params.get("loc") || "").trim();
    if (q) searchInput.value = q.slice(0, 100);

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

    state.loc = loc.slice(0, 80);
  }

  function resetFilters() {
    searchInput.value = "";
    state = freshState();
    sortSelect.value = "newest";
    update();
  }

  /* ---------- Events ---------- */

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    update();
    if (results) results.scrollIntoView({ behavior: "smooth", block: "start" });
  });
  searchInput.addEventListener("input", update);
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

  if (locationClear) locationClear.addEventListener("click", function () { state.loc = ""; update(); });
  if (retryButton) retryButton.addEventListener("click", load);

  load();
})();
