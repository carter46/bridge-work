/*
 * Applies the contact details saved in Admin -> Settings to the page.
 *   data-setting="key"       -> element text
 *   data-setting-href="key"  -> link target (contact_email -> mailto:, phone_href -> tel:, others used as-is);
 *                               any ?subject=… on an existing mailto: link is kept
 *   data-setting-src="key"   -> image source
 * The values already in the HTML stay in place if neither source can be loaded.
 * Reads the live API first so admin changes show on the next page load; the static copy is the fallback.
 * The site name written into the HTML ("Hubjob Platform") is replaced in page text and the tab title.
 */
(function () {
  var SOURCES = ["api/settings.php", "assets/data/site-settings.json?v=" + Date.now()];
  var HTML_SITE_NAME = "Hubjob Platform";

  function renameSite(name) {
    if (typeof name !== "string" || name === "" || name === HTML_SITE_NAME) return;
    var swap = function (text) { return text.split(HTML_SITE_NAME).join(name); };
    document.title = swap(document.title);
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        var tag = node.parentNode && node.parentNode.nodeName;
        if (tag === "SCRIPT" || tag === "STYLE" || tag === "NOSCRIPT") return NodeFilter.FILTER_REJECT;
        return node.nodeValue.indexOf(HTML_SITE_NAME) !== -1 ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
      }
    });
    var nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(function (node) { node.nodeValue = swap(node.nodeValue); });
    document.querySelectorAll("[aria-label*='" + HTML_SITE_NAME + "'], [alt*='" + HTML_SITE_NAME + "']").forEach(function (el) {
      ["aria-label", "alt"].forEach(function (attr) {
        if (el.hasAttribute(attr)) el.setAttribute(attr, swap(el.getAttribute(attr)));
      });
    });
  }

  function apply(settings) {
    window.HJ.settings = settings;
    renameSite(settings.site_name);

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
