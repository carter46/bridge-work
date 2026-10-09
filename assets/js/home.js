/*
 * Homepage: featured jobs, category tiles and the hero search, all from api/jobs.php (with fallbacks).
 * If no source loads, the static fallback in index.html stays visible.
 */
(function () {
  var SOURCES = window.HJ.JOB_SOURCES;
  var EMPLOYMENT_LABELS = { full_time: "Full-time", part_time: "Part-time", contract: "Contract", temporary: "Temporary" };
  var ARRANGEMENT_LABELS = { remote: "Remote", hybrid: "Hybrid", onsite: "On-site" };
  var MAX_FEATURED = 6;
  var esc = window.HJ.escape;

  var featuredBox = document.getElementById("featured-jobs");
  var tilesBox = document.getElementById("category-tiles");

  function applyHref(job) {
    return "apply.html?job=" + encodeURIComponent(job.id) + "&role=" + encodeURIComponent(job.title);
  }

  function placeText(job) {
    return job.location_text || ARRANGEMENT_LABELS[job.work_arrangement] || "";
  }

  function scheduleText(job) {
    if (job.schedule_note) return job.schedule_note;
    return job.employment_types.map(function (t) { return EMPLOYMENT_LABELS[t] || t; }).join(" / ");
  }

  /* ---------- Featured jobs ---------- */

  function jobCard(job, categoryName, index) {
    var meta = [placeText(job), scheduleText(job)].filter(Boolean).join(" · ");
    return '<a class="reveal group flex h-full flex-col rounded-xl border border-slate-200 bg-white p-6 transition-colors hover:border-primary" href="' + esc(applyHref(job)) + '" style="--reveal-delay:' + (index % 2) * 80 + 'ms">' +
      '<span class="text-sm text-text-muted">' + esc(categoryName || "") + "</span>" +
      '<span class="mt-2 text-lg font-bold text-primary group-hover:text-secondary transition-colors">' + esc(job.title) + "</span>" +
      (job.company_name ? '<span class="mt-1 text-sm font-medium text-text-main">' + esc(job.company_name) + "</span>" : "") +
      (meta ? '<span class="mt-2 text-sm text-text-muted">' + esc(meta) + "</span>" : "") +
      '<span class="mt-auto pt-5 flex items-center justify-between gap-3">' +
      '<span class="font-semibold text-primary">' + esc(job.pay_label || "") + "</span>" +
      '<span class="text-sm font-semibold text-secondary">Apply →</span></span>' +
      (job.employer_verified ? '<span class="mt-3 text-xs font-semibold text-emerald-700">Verified employer</span>' : "") +
      "</a>";
  }

  function renderFeatured(data, names) {
    if (!featuredBox) return;
    var featured = data.jobs.filter(function (job) { return job.is_featured; }).slice(0, MAX_FEATURED);
    if (!featured.length) return; // keep the "Explore every open role" card
    featuredBox.innerHTML = '<div class="grid grid-cols-1 sm:grid-cols-2 auto-rows-fr gap-4">' +
      featured.map(function (job, i) { return jobCard(job, names[job.category], i); }).join("") +
      "</div>";
    window.HJ.observeReveal(featuredBox);
  }

  function renderStats(data) {
    var box = document.getElementById("home-stats");
    var roles = document.getElementById("home-stat-roles");
    var pay = document.getElementById("home-stat-pay");
    if (!box || !roles || !pay || !data.jobs.length) return;
    roles.textContent = data.jobs.length;
    var label = (data.stats && data.stats.pay_label) || "";
    if (/\/hr$/.test(label)) {
      pay.innerHTML = esc(label.replace(/\/hr$/, "")) + '<span class="text-base font-semibold text-text-muted">/hr</span>';
    } else if (label) {
      pay.textContent = label;
    } else {
      pay.parentNode.classList.add("hidden");
    }
    box.classList.remove("hidden");
    box.classList.add("grid");
  }

  /* ---------- Category tiles ---------- */

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

  /* ---------- Hero search: "What" suggests jobs, "Where" suggests places ---------- */

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
    var places = window.HJ.placeOptions(data.jobs);

    function hide() {
      resultsBox.classList.add("hidden");
      resultsBox.innerHTML = "";
    }

    function open(html) {
      resultsBox.innerHTML = html;
      resultsBox.classList.remove("hidden");
    }

    function heading(text) {
      return '<p class="px-4 pt-3 pb-1 text-xs font-semibold text-text-subtle">' + esc(text) + "</p>";
    }

    function matchingJobs(what, where) {
      var terms = what.toLowerCase().split(/\s+/).filter(Boolean);
      return data.jobs.filter(function (job) {
        return terms.every(function (t) { return job._search.indexOf(t) !== -1; }) &&
          window.HJ.placeMatches(job._place, where);
      });
    }

    function showJobs() {
      var what = whatInput.value.trim();
      var where = whereInput.value.trim();
      if (!what) { hide(); return; }
      var found = matchingJobs(what, where);
      if (!found.length) {
        open('<div class="px-4 py-4 text-sm text-text-muted">No roles match “' + esc(what) + "”" + (where ? " in “" + esc(where) + "”" : "") + " right now. " +
          '<a class="font-semibold text-secondary hover:underline" href="apply.html">Send us your CV</a> and we\'ll contact you when one opens.</div>');
        return;
      }
      var rows = found.slice(0, MAX_SUGGESTIONS).map(function (job) {
        var meta = [names[job.category], placeText(job), job.pay_label].filter(Boolean).join(" · ");
        return '<a class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none" href="' + esc(applyHref(job)) + '">' +
          '<span class="min-w-0"><span class="block font-semibold text-slate-900 truncate">' + esc(job.title) + "</span>" +
          '<span class="block text-xs text-text-muted truncate">' + esc(meta) + "</span></span>" +
          '<span class="shrink-0 text-xs font-semibold text-secondary">Apply →</span></a>';
      }).join("");
      open(heading(found.length === 1 ? "1 matching role" : found.length + " matching roles") +
        '<div class="divide-y divide-slate-100">' + rows + "</div>" +
        '<a class="block px-4 py-3 text-sm font-semibold text-secondary border-t border-slate-200 hover:bg-slate-50" href="' + esc(jobsUrl(what, where)) + '">' +
        (found.length > MAX_SUGGESTIONS ? "See all " + found.length + " roles →" : "Open in job search →") + "</a>");
    }

    function showPlaces() {
      var typed = whereInput.value.trim().toLowerCase();
      var list = places.filter(function (p) {
        return !typed || p.label.toLowerCase().split(/[^a-z0-9]+/).some(function (w) { return w.indexOf(typed) === 0; }) ||
          p.label.toLowerCase().indexOf(typed) === 0;
      });
      if (!list.length) {
        open('<div class="px-4 py-4 text-sm text-text-muted">No roles are listed in “' + esc(whereInput.value.trim()) + '” yet. ' +
          '<button class="font-semibold text-secondary hover:underline" data-place="Remote" type="button">Show remote roles</button></div>');
        return;
      }
      open(heading("Locations") + '<div class="pb-2">' + list.map(function (p) {
        return '<button class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-slate-50 focus:bg-slate-50 focus:outline-none" data-place="' + esc(p.label) + '" type="button">' +
          '<span class="font-medium text-slate-900">' + esc(p.label) + "</span>" +
          '<span class="text-xs text-text-muted">' + p.count + (p.count === 1 ? " role" : " roles") + "</span></button>";
      }).join("") + "</div>");
    }

    resultsBox.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-place]");
      if (!btn) return;
      whereInput.value = btn.getAttribute("data-place");
      if (whatInput.value.trim()) showJobs();
      else hide();
    });

    whatInput.addEventListener("input", showJobs);
    whatInput.addEventListener("focus", showJobs);
    whereInput.addEventListener("input", showPlaces);
    whereInput.addEventListener("focus", showPlaces);
    document.addEventListener("click", function (e) {
      // A picked place re-renders the panel, so its button is already detached here.
      if (e.target.isConnected && !searchForm.parentNode.contains(e.target)) hide();
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") hide();
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
    renderStats(data);
    renderFeatured(data, names);
    renderTiles(data);
    setupSearch(data, names);
  }, function () {
    // Leave the static fallback content in place.
  });
})();
