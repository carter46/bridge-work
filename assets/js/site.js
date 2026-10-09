// Shared helpers for settings.js, jobs.js, home.js and apply-form.js.
window.HJ = window.HJ || {};

// Fetch JSON from each URL in turn; resolves with the first response that passes isValid.
window.HJ.fetchJson = function (urls, isValid) {
  var index = 0;
  function next() {
    if (index >= urls.length) return Promise.reject(new Error("No data source available"));
    var url = urls[index++];
    return fetch(url, { cache: "no-cache", credentials: "same-origin" })
      .then(function (response) {
        if (!response.ok) throw new Error(url + " returned " + response.status);
        return response.json();
      })
      .then(function (data) {
        if (isValid && !isValid(data)) throw new Error(url + " returned unexpected data");
        // Lets callers tell live data from the saved fallback copy.
        if (data && typeof data === "object") {
          try { Object.defineProperty(data, "_hjSource", { value: url, enumerable: false }); } catch (e) { /* frozen */ }
        }
        return data;
      })
      .catch(function (err) {
        if (window.console) console.warn("[hubjob] " + err.message);
        return next();
      });
  }
  return next();
};

// Fade in elements with class "reveal" as they scroll into view. Call again for content added later.
window.HJ.observeReveal = (function () {
  var observer = "IntersectionObserver" in window
    ? new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-revealed");
          observer.unobserve(entry.target);
        });
      }, { rootMargin: "0px 0px -40px 0px", threshold: 0.1 })
    : null;
  return function (root) {
    (root || document).querySelectorAll(".reveal:not(.is-revealed)").forEach(function (el) {
      if (observer) observer.observe(el);
      else el.classList.add("is-revealed");
    });
  };
})();

window.HJ.escape = function (value) {
  return String(value == null ? "" : value).replace(/[&<>"']/g, function (c) {
    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
  });
};

// Remember campaign parameters from the landing page so the application form can send them.
(function () {
  try {
    var params = new URLSearchParams(window.location.search);
    var keys = ["utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content"];
    var found = {};
    var any = false;
    keys.forEach(function (key) {
      var value = params.get(key);
      if (value) { found[key] = value.slice(0, 255); any = true; }
    });
    if (any && !sessionStorage.getItem("hj_utm")) {
      found.utm_landing = (window.location.pathname + window.location.search).slice(0, 255);
      sessionStorage.setItem("hj_utm", JSON.stringify(found));
    }
  } catch (e) { /* storage blocked: tracking is optional */ }
})();

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

  window.HJ.observeReveal();

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
