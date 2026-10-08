(function () {
  var ACTIVE_PILL = ["bg-primary", "text-on-primary", "shadow-sm"];
  var INACTIVE_PILL = ["bg-surface-container", "text-on-surface-variant", "hover:bg-surface-variant"];

  var searchInput = document.getElementById("faq-search");
  var searchBtn = document.getElementById("faq-search-btn");
  var pills = document.querySelectorAll(".filter-pill");
  var items = document.querySelectorAll(".faq-item");
  var noResults = document.getElementById("no-results");
  var resetBtn = document.getElementById("reset-search");
  var currentCategory = "all";

  function setExpanded(item, expanded) {
    var btn = item.querySelector(".faq-toggle");
    var body = item.querySelector(".faq-body");
    var iconBox = item.querySelector(".faq-icon-box");
    btn.setAttribute("aria-expanded", expanded ? "true" : "false");
    body.classList.toggle("hidden", !expanded);
    if (iconBox) iconBox.classList.toggle("rotate-180", expanded);
  }

  items.forEach(function (item) {
    item.querySelector(".faq-toggle").addEventListener("click", function () {
      setExpanded(item, this.getAttribute("aria-expanded") !== "true");
    });
  });

  function setActivePill(activePill) {
    pills.forEach(function (pill) {
      var isActive = pill === activePill;
      ACTIVE_PILL.forEach(function (c) { pill.classList.toggle(c, isActive); });
      INACTIVE_PILL.forEach(function (c) { pill.classList.toggle(c, !isActive); });
      pill.setAttribute("aria-pressed", isActive ? "true" : "false");
    });
  }

  function filterFaqs() {
    var query = (searchInput ? searchInput.value : "").toLowerCase().trim();
    var visible = 0;

    items.forEach(function (item) {
      var category = item.getAttribute("data-category") || "";
      var keywords = (item.getAttribute("data-keywords") || "").toLowerCase();
      var text = item.textContent.toLowerCase();
      var matchesCategory = currentCategory === "all" || category === currentCategory;
      var matchesQuery = query === "" || text.indexOf(query) !== -1 || keywords.indexOf(query) !== -1;
      var show = matchesCategory && matchesQuery;

      item.classList.toggle("hidden", !show);
      if (show) {
        visible++;
        if (query.length > 2) setExpanded(item, true);
      }
    });

    if (noResults) noResults.classList.toggle("hidden", visible !== 0);
  }

  pills.forEach(function (pill) {
    pill.addEventListener("click", function () {
      setActivePill(pill);
      currentCategory = pill.getAttribute("data-filter") || "all";
      filterFaqs();
    });
  });

  if (searchInput) {
    searchInput.addEventListener("input", filterFaqs);
    searchInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        filterFaqs();
      }
    });
  }
  if (searchBtn) searchBtn.addEventListener("click", filterFaqs);

  if (resetBtn) {
    resetBtn.addEventListener("click", function () {
      if (searchInput) searchInput.value = "";
      currentCategory = "all";
      setActivePill(pills[0]);
      filterFaqs();
    });
  }

  setActivePill(pills[0]);
})();
