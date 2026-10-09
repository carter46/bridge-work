/*
 * Homepage: featured jobs and category tiles from api/jobs.php (snapshot fallback).
 * If neither source loads, the static fallback in index.html stays visible.
 */
(function () {
  var SOURCES = window.HJ.JOB_SOURCES;
  var EMPLOYMENT_LABELS = { full_time: "Full-time", part_time: "Part-time", contract: "Contract", temporary: "Temporary" };
  var ARRANGEMENT_LABELS = { remote: "Remote", hybrid: "Hybrid", onsite: "On-site" };
  var MAX_FEATURED = 6;
  var esc = window.HJ.escape;

  var featuredBox = document.getElementById("featured-jobs");
  var tilesBox = document.getElementById("category-tiles");

  function jobRow(job, categoryName) {
    var href = "apply.html?job=" + encodeURIComponent(job.id) + "&role=" + encodeURIComponent(job.title);
    var meta = [];
    if (job.company_name) meta.push('<span class="font-medium text-slate-700">' + esc(job.company_name) + "</span>");
    if (job.location_text) meta.push("<span>" + esc(job.location_text) + "</span>");
    else if (ARRANGEMENT_LABELS[job.work_arrangement]) meta.push("<span>" + esc(ARRANGEMENT_LABELS[job.work_arrangement]) + "</span>");
    if (job.pay_label) meta.push("<span>" + esc(job.pay_label) + "</span>");
    if (job.schedule_note) {
      meta.push("<span>" + esc(job.schedule_note) + "</span>");
    } else if (job.employment_types.length) {
      meta.push("<span>" + esc(job.employment_types.map(function (t) { return EMPLOYMENT_LABELS[t] || t; }).join(" / ")) + "</span>");
    }
    if (job.employer_verified) {
      meta.push('<span class="inline-flex items-center gap-1 text-emerald-700"><span class="material-symbols-outlined text-[16px]">verified</span>Verified employer</span>');
    }

    return '<div class="py-5 sm:py-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 hover:bg-slate-50/70 -mx-4 px-4 transition-colors">' +
      '<div class="min-w-0 flex-1">' +
      '<a class="text-base sm:text-lg font-bold text-slate-900 hover:text-secondary transition-colors" href="' + esc(href) + '">' + esc(job.title) + "</a>" +
      '<div class="mt-1 flex flex-wrap items-center gap-x-3 sm:gap-x-4 gap-y-1 text-sm text-text-muted">' +
      meta.join('<span aria-hidden="true">•</span>') +
      "</div></div>" +
      '<div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-1 shrink-0">' +
      '<span class="text-xs text-text-subtle">' + esc(job.badge || categoryName || "") + "</span>" +
      '<a class="text-sm font-semibold text-secondary hover:underline" href="' + esc(href) + '">Apply →</a>' +
      "</div></div>";
  }

  function renderFeatured(data, names) {
    if (!featuredBox) return;
    var featured = data.jobs.filter(function (job) { return job.is_featured; }).slice(0, MAX_FEATURED);
    if (!featured.length) return; // keep the "Explore every open role" card
    featuredBox.innerHTML = '<div class="divide-y divide-slate-200 border-t border-b border-slate-200">' +
      featured.map(function (job) { return jobRow(job, names[job.category]); }).join("") +
      "</div>";
  }

  // Equal-size cards; the wrapper fades in on scroll, the card keeps its own hover effects.
  function renderTiles(data) {
    if (!tilesBox || !data.categories.length) return;
    tilesBox.innerHTML = data.categories.map(function (c, i) {
      return '<div class="reveal h-full" style="--reveal-delay:' + (i % 4) * 80 + 'ms">' +
        '<a class="group flex h-full flex-col rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-all duration-200 hover:border-secondary/40 hover:shadow-lg" href="jobs.html?category=' + encodeURIComponent(c.slug) + '">' +
        '<span class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-lg bg-primary/5 text-primary transition-colors group-hover:bg-secondary group-hover:text-white">' +
        '<span class="material-symbols-outlined text-[24px]">' + esc(c.icon || "work") + "</span></span>" +
        '<h3 class="text-base font-bold text-slate-900 transition-colors group-hover:text-secondary">' + esc(c.name) + "</h3>" +
        (c.description ? '<p class="mt-2 text-sm leading-relaxed text-text-muted">' + esc(c.description) + "</p>" : "") +
        '<span class="mt-auto pt-5 flex items-center justify-between gap-2 text-sm font-semibold text-secondary">' +
        "<span>" + c.count + (c.count === 1 ? " open role" : " open roles") +
        (c.pay_label ? '<span class="font-medium text-text-muted"> · ' + esc(c.pay_label) + "</span>" : "") + "</span>" +
        '<span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-1">arrow_forward</span></span>' +
        "</a></div>";
    }).join("");
    window.HJ.observeReveal(tilesBox);
  }

  /* ---------- Hero search: matching jobs appear while typing ---------- */

  var MAX_SUGGESTIONS = 6;
  var searchForm = document.getElementById("hero-search");
  var whatInput = document.getElementById("search-what");
  var whereInput = document.getElementById("search-where");
  var resultsBox = document.getElementById("hero-search-results");

  function jobsUrl(what, where) {
    var params = new URLSearchParams();
    if (what) params.set("q", what);
    if (where) params.set("loc", where);
    var query = params.toString();
    return "jobs.html" + (query ? "?" + query : "");
  }

  function setupSearch(data, names) {
    if (!searchForm || !whatInput || !whereInput || !resultsBox) return;
    data.jobs.forEach(function (job) {
      job._search = window.HJ.jobSearchText(job, names[job.category]);
      job._place = window.HJ.jobPlaceWords(job);
    });

    function find(what, where) {
      var terms = what.toLowerCase().split(/\s+/).filter(Boolean);
      var byWhat = data.jobs.filter(function (job) {
        return terms.every(function (t) { return job._search.indexOf(t) !== -1; });
      });
      var byBoth = byWhat.filter(function (job) { return window.HJ.placeMatches(job._place, where); });
      // Nothing listed for that place: fall back to the "What" matches and say so.
      return { jobs: byBoth.length || !where ? byBoth : byWhat, placeMissed: !!where && !byBoth.length && byWhat.length > 0 };
    }

    function suggestion(job) {
      var meta = [names[job.category], job.location_text || ARRANGEMENT_LABELS[job.work_arrangement], job.pay_label].filter(Boolean);
      var href = "apply.html?job=" + encodeURIComponent(job.id) + "&role=" + encodeURIComponent(job.title);
      return '<a class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none" href="' + esc(href) + '">' +
        '<span class="min-w-0"><span class="block font-semibold text-slate-900 truncate">' + esc(job.title) + "</span>" +
        '<span class="block text-xs text-text-muted truncate">' + esc(meta.join(" · ")) + "</span></span>" +
        '<span class="shrink-0 text-xs font-semibold text-secondary">Apply →</span></a>';
    }

    function show() {
      var what = whatInput.value.trim();
      var where = whereInput.value.trim();
      if (!what && !where) {
        resultsBox.classList.add("hidden");
        resultsBox.innerHTML = "";
        return;
      }
      var found = find(what, where);
      var html = "";
      if (found.placeMissed) {
        html += '<p class="px-4 py-2 text-xs text-text-muted bg-slate-50 border-b border-slate-100">No roles listed for “' + esc(where) + "” yet. Most roles below are remote:</p>";
      }
      if (found.jobs.length) {
        html += '<div class="divide-y divide-slate-100">' + found.jobs.slice(0, MAX_SUGGESTIONS).map(suggestion).join("") + "</div>";
        html += '<a class="block px-4 py-3 text-sm font-semibold text-secondary border-t border-slate-200 hover:bg-slate-50" href="' + esc(jobsUrl(what, where)) + '">' +
          (found.jobs.length > MAX_SUGGESTIONS ? "See all " + found.jobs.length + " matching roles →" : "Open in job search →") + "</a>";
      } else {
        html += '<div class="px-4 py-4 text-sm text-text-muted">No roles match “' + esc(what || where) + '” right now. ' +
          '<a class="font-semibold text-secondary hover:underline" href="apply.html">Send us your CV</a> and we\'ll match you when one opens.</div>';
      }
      resultsBox.innerHTML = html;
      resultsBox.classList.remove("hidden");
    }

    whatInput.addEventListener("input", show);
    whereInput.addEventListener("input", show);
    whatInput.addEventListener("focus", show);
    whereInput.addEventListener("focus", show);
    document.addEventListener("click", function (e) {
      if (!searchForm.parentNode.contains(e.target)) resultsBox.classList.add("hidden");
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") resultsBox.classList.add("hidden");
    });
    searchForm.addEventListener("submit", function (e) {
      e.preventDefault();
      window.location.href = jobsUrl(whatInput.value.trim(), whereInput.value.trim());
    });
  }

  window.HJ.fetchJson(SOURCES, function (d) {
    return d && Array.isArray(d.jobs) && Array.isArray(d.categories);
  }).then(function (data) {
    var names = {};
    data.categories.forEach(function (c) { names[c.slug] = c.name; });
    renderFeatured(data, names);
    renderTiles(data);
    setupSearch(data, names);
  }, function () {
    // Leave the static fallback content in place.
  });
})();
