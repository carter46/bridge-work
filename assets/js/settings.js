/*
 * Applies the contact details saved in Admin -> Settings to the page.
 *   data-setting="key"       -> element text
 *   data-setting-href="key"  -> link target (contact_email -> mailto:, phone_href -> tel:, others used as-is);
 *                               any ?subject=… on an existing mailto: link is kept
 *   data-setting-src="key"   -> image source
 * The values already in the HTML stay in place if neither source can be loaded.
 * Reads the static snapshot first (fast, cacheable), then falls back to the API.
 */
(function () {
  var SOURCES = ["assets/data/site-settings.json", "api/settings.php"];

  function apply(settings) {
    window.HJ.settings = settings;

    document.querySelectorAll("[data-setting]").forEach(function (el) {
      var value = settings[el.getAttribute("data-setting")];
      if (typeof value === "string" && value !== "") el.textContent = value;
    });

    document.querySelectorAll("[data-setting-href]").forEach(function (el) {
      var key = el.getAttribute("data-setting-href");
      var value = settings[key];
      if (typeof value !== "string" || value === "") {
        // Optional links (e.g. WhatsApp) stay hidden until a value is set.
        if (el.hasAttribute("data-setting-optional")) el.classList.add("hidden");
        return;
      }
      var current = el.getAttribute("href") || "";
      if (key === "contact_email") {
        var query = current.indexOf("?") !== -1 ? current.slice(current.indexOf("?")) : "";
        el.setAttribute("href", "mailto:" + value + query);
      } else if (key === "phone_href") {
        el.setAttribute("href", "tel:" + value);
      } else if (/^https?:\/\//i.test(value)) {
        el.setAttribute("href", value);
      }
      if (el.hasAttribute("data-setting-optional")) el.classList.remove("hidden");
    });

    document.querySelectorAll("[data-setting-src]").forEach(function (el) {
      var value = settings[el.getAttribute("data-setting-src")];
      if (typeof value === "string" && value !== "" && !/^\s*javascript:/i.test(value)) {
        el.setAttribute("src", value);
        if (settings.site_name) el.setAttribute("alt", settings.site_name);
      }
    });

    document.dispatchEvent(new CustomEvent("hj:settings", { detail: settings }));
  }

  window.HJ.fetchJson(SOURCES, function (s) {
    return s && typeof s.site_name === "string" && typeof s.contact_email === "string";
  }).then(apply, function () {
    // Keep the contact details already in the HTML.
  });
})();
