(function () {
  // Mobile sidebar
  var sidebar = document.getElementById("admin-sidebar");
  var backdrop = document.querySelector("[data-sidebar-backdrop]");

  function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle("hidden", !open);
    sidebar.classList.toggle("flex", open);
    sidebar.classList.toggle("w-72", open);
    sidebar.classList.toggle("flex-col", open);
    sidebar.classList.toggle("fixed", open);
    sidebar.classList.toggle("inset-y-0", open);
    if (backdrop) backdrop.classList.toggle("hidden", !open);
  }

  document.querySelectorAll("[data-sidebar-open]").forEach(function (btn) {
    btn.addEventListener("click", function () { setSidebar(true); });
  });
  document.querySelectorAll("[data-sidebar-close]").forEach(function (btn) {
    btn.addEventListener("click", function () { setSidebar(false); });
  });
  if (backdrop) backdrop.addEventListener("click", function () { setSidebar(false); });

  // Confirmation prompts on destructive buttons and forms
  document.addEventListener("click", function (e) {
    var el = e.target.closest("[data-confirm]");
    if (el && el.tagName !== "FORM" && !window.confirm(el.getAttribute("data-confirm"))) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);
  document.querySelectorAll("form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      if (!window.confirm(form.getAttribute("data-confirm"))) e.preventDefault();
    });
  });

  // Material icon name preview: <input data-icon-preview="target-id">
  document.querySelectorAll("[data-icon-preview]").forEach(function (input) {
    var target = document.getElementById(input.getAttribute("data-icon-preview"));
    if (!target) return;
    function update() { target.textContent = input.value.trim() || "work"; }
    input.addEventListener("input", update);
    update();
  });

  // "Select all" checkbox for bulk actions: <input type="checkbox" data-check-all="name">
  document.querySelectorAll("[data-check-all]").forEach(function (master) {
    var name = master.getAttribute("data-check-all");
    master.addEventListener("change", function () {
      document.querySelectorAll('input[type="checkbox"][name="' + name + '"]').forEach(function (cb) {
        cb.checked = master.checked;
      });
    });
  });

  // New job: keep the suggested ref code in step with the category until it is typed by hand.
  // <select data-ref-target="input-id"> with <option data-ref-suggest="CODE">
  document.querySelectorAll("select[data-ref-target]").forEach(function (select) {
    var input = document.getElementById(select.getAttribute("data-ref-target"));
    if (!input) return;
    var auto = input.value;
    select.addEventListener("change", function () {
      var option = select.options[select.selectedIndex];
      var next = option && option.getAttribute("data-ref-suggest");
      if (next && input.value.toUpperCase() === auto.toUpperCase()) {
        input.value = next;
        auto = next;
      }
    });
  });

  // Elements shown only when a select has a given value: <div data-show-when="field=value1,value2">
  document.querySelectorAll("[data-show-when]").forEach(function (block) {
    var parts = block.getAttribute("data-show-when").split("=");
    var field = document.querySelector('[name="' + parts[0] + '"]');
    var values = (parts[1] || "").split(",");
    if (!field) return;
    function update() { block.classList.toggle("hidden", values.indexOf(field.value) === -1); }
    field.addEventListener("change", update);
    update();
  });
})();
