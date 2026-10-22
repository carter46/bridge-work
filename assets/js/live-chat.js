/*
 * Smartsupp live chat. The key comes from Admin -> Settings -> Live chat and is
 * published through the normal site settings, so saving it there turns chat on
 * (and clearing it turns chat off) on every public page. No key = no chat code loaded.
 */
(function () {
  var loaded = false;

  function load(settings) {
    if (loaded || !settings) return;
    var key = typeof settings.smartsupp_key === "string" ? settings.smartsupp_key.trim() : "";
    if (!/^[A-Za-z0-9_-]{10,100}$/.test(key)) return;
    loaded = true;
    // Keep the floating Apply button clear of the chat bubble (both sit bottom-right).
    document.documentElement.classList.add("hj-chat-on");

    window._smartsupp = window._smartsupp || {};
    window._smartsupp.key = key;
    window.smartsupp || (function (d) {
      var s, c, o = window.smartsupp = function () { o._.push(arguments); };
      o._ = [];
      s = d.getElementsByTagName("script")[0];
      c = d.createElement("script");
      c.type = "text/javascript";
      c.charset = "utf-8";
      c.async = true;
      c.src = "https://www.smartsuppchat.com/loader.js?";
      s.parentNode.insertBefore(c, s);
    })(document);
  }

  // Settings may already be applied, or arrive with the hj:settings event from settings.js.
  if (window.HJ && window.HJ.settings) load(window.HJ.settings);
  document.addEventListener("hj:settings", function (e) { load(e.detail); });
})();
