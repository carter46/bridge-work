(function () {
  // Mobile navigation
  var toggle = document.getElementById("mobile-menu-toggle");
  var menu = document.getElementById("mobile-menu");

  if (toggle && menu) {
    var icon = toggle.querySelector(".material-symbols-outlined");

    function setOpen(open) {
      menu.classList.toggle("hidden", !open);
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
      if (icon) icon.textContent = open ? "close" : "menu";
    }

    toggle.addEventListener("click", function () {
      setOpen(menu.classList.contains("hidden"));
    });

    menu.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        setOpen(false);
      });
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") setOpen(false);
    });

    window.matchMedia("(min-width: 768px)").addEventListener("change", function (e) {
      if (e.matches) setOpen(false);
    });
  }

  // Footer year
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });

  // Floating apply button: fades in after a delay, hides near the footer
  var floatingBtn = document.getElementById("floatingApplyBtn");
  if (floatingBtn) {
    var footer = document.querySelector("footer");

    setTimeout(function () {
      floatingBtn.classList.add("is-visible");
    }, 5000);

    window.addEventListener(
      "scroll",
      function () {
        if (!footer) return;
        var nearFooter = footer.getBoundingClientRect().top < window.innerHeight - 50;
        floatingBtn.classList.toggle("is-hidden", nearFooter);
      },
      { passive: true }
    );
  }
})();
