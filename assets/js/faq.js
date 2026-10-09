// FAQ: topic buttons and the search box filter the question list; the line above the list says what is shown.
(function () {
  var ACTIVE = ["bg-primary", "text-white", "border-primary"];
  var INACTIVE = ["bg-white", "text-primary", "border-slate-300", "hover:border-primary"];

  var list = document.getElementById("faq-list");
  var topicBar = document.getElementById("faq-topics");
  var searchInput = document.getElementById("faq-search");
  var status = document.getElementById("faq-status");
  var empty = document.getElementById("faq-empty");
  var resetBtn = document.getElementById("faq-reset");
  if (!list || !topicBar || !searchInput) return;

  var items = Array.prototype.slice.call(list.querySelectorAll(".faq-item"));
  var buttons = Array.prototype.slice.call(topicBar.querySelectorAll(".faq-topic"));
  var topic = "all";

  function topicOf(item) { return item.getAttribute("data-topic"); }

  function topicLabel(btn) {
    return btn.firstChild ? btn.firstChild.nodeValue.trim() : "";
  }

  buttons.forEach(function (btn) {
    var t = btn.getAttribute("data-topic");
    var count = t === "all" ? items.length : items.filter(function (item) { return topicOf(item) === t; }).length;
    var badge = btn.querySelector("[data-count]");
    if (badge) badge.textContent = "(" + count + ")";
  });

  function update() {
    var query = searchInput.value.trim().toLowerCase();
    var terms = query.split(/\s+/).filter(Boolean);
    var shown = 0;

    items.forEach(function (item) {
      var text = item.textContent.toLowerCase();
      var match = (topic === "all" || topicOf(item) === topic) &&
        terms.every(function (term) { return text.indexOf(term) !== -1; });
      item.hidden = !match;
      if (match) {
        shown++;
        if (query.length > 2) item.open = true;
      }
    });

    var activeBtn = null;
    buttons.forEach(function (btn) {
      var active = btn.getAttribute("data-topic") === topic;
      if (active) activeBtn = btn;
      ACTIVE.forEach(function (c) { btn.classList.toggle(c, active); });
      INACTIVE.forEach(function (c) { btn.classList.toggle(c, !active); });
      btn.setAttribute("aria-pressed", active ? "true" : "false");
    });

    var noun = shown === 1 ? "question" : "questions";
    var where = topic === "all" || !activeBtn ? "" : " in " + topicLabel(activeBtn);
    if (query) {
      status.textContent = shown + " " + noun + where + " match “" + searchInput.value.trim() + "”";
    } else {
      status.textContent = "Showing " + (topic === "all" ? "all " : "") + shown + " " + noun + where;
    }
    empty.classList.toggle("hidden", shown !== 0);
  }

  buttons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      topic = btn.getAttribute("data-topic");
      update();
      // Keep the buttons and the first answers in view after the list changes length.
      var top = topicBar.getBoundingClientRect().top;
      if (top < 80 || top > window.innerHeight * 0.5) {
        topicBar.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  });

  searchInput.addEventListener("input", update);
  searchInput.addEventListener("keydown", function (e) {
    if (e.key === "Enter") e.preventDefault();
  });

  if (resetBtn) {
    resetBtn.addEventListener("click", function () {
      searchInput.value = "";
      topic = "all";
      update();
      searchInput.focus();
    });
  }

  update();
})();
