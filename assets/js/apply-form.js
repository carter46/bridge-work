(function () {
  var form = document.getElementById("applicationForm");
  if (!form) return;

  var MAX_CV_BYTES = 2 * 1024 * 1024;
  var CV_EXTENSIONS = ["pdf", "doc", "docx", "rtf", "txt"];

  var countries = [
    {code: 'GB', name: 'United Kingdom'}, {code: 'US', name: 'United States'}, {code: 'CA', name: 'Canada'},
    {code: 'AU', name: 'Australia'}, {code: 'AF', name: 'Afghanistan'}, {code: 'AL', name: 'Albania'},
    {code: 'DZ', name: 'Algeria'}, {code: 'AS', name: 'American Samoa'}, {code: 'AD', name: 'Andorra'},
    {code: 'AO', name: 'Angola'}, {code: 'AI', name: 'Anguilla'}, {code: 'AQ', name: 'Antarctica'},
    {code: 'AG', name: 'Antigua and Barbuda'}, {code: 'AR', name: 'Argentina'}, {code: 'AM', name: 'Armenia'},
    {code: 'AW', name: 'Aruba'}, {code: 'AT', name: 'Austria'}, {code: 'AZ', name: 'Azerbaijan'},
    {code: 'BS', name: 'Bahamas'}, {code: 'BH', name: 'Bahrain'}, {code: 'BD', name: 'Bangladesh'},
    {code: 'BB', name: 'Barbados'}, {code: 'BY', name: 'Belarus'}, {code: 'BE', name: 'Belgium'},
    {code: 'BZ', name: 'Belize'}, {code: 'BJ', name: 'Benin'}, {code: 'BM', name: 'Bermuda'},
    {code: 'BT', name: 'Bhutan'}, {code: 'BO', name: 'Bolivia'}, {code: 'BA', name: 'Bosnia and Herzegovina'},
    {code: 'BW', name: 'Botswana'}, {code: 'BR', name: 'Brazil'}, {code: 'BN', name: 'Brunei'},
    {code: 'BG', name: 'Bulgaria'}, {code: 'BF', name: 'Burkina Faso'}, {code: 'BI', name: 'Burundi'},
    {code: 'CV', name: 'Cabo Verde'}, {code: 'KH', name: 'Cambodia'}, {code: 'CM', name: 'Cameroon'},
    {code: 'KY', name: 'Cayman Islands'}, {code: 'CF', name: 'Central African Republic'}, {code: 'TD', name: 'Chad'},
    {code: 'CL', name: 'Chile'}, {code: 'CN', name: 'China'}, {code: 'CO', name: 'Colombia'},
    {code: 'KM', name: 'Comoros'}, {code: 'CG', name: 'Congo'}, {code: 'CD', name: 'Congo (DRC)'},
    {code: 'CK', name: 'Cook Islands'}, {code: 'CR', name: 'Costa Rica'}, {code: 'CI', name: 'Côte d\'Ivoire'},
    {code: 'HR', name: 'Croatia'}, {code: 'CU', name: 'Cuba'}, {code: 'CY', name: 'Cyprus'},
    {code: 'CZ', name: 'Czech Republic'}, {code: 'DK', name: 'Denmark'}, {code: 'DJ', name: 'Djibouti'},
    {code: 'DM', name: 'Dominica'}, {code: 'DO', name: 'Dominican Republic'}, {code: 'EC', name: 'Ecuador'},
    {code: 'EG', name: 'Egypt'}, {code: 'SV', name: 'El Salvador'}, {code: 'GQ', name: 'Equatorial Guinea'},
    {code: 'ER', name: 'Eritrea'}, {code: 'EE', name: 'Estonia'}, {code: 'SZ', name: 'Eswatini'},
    {code: 'ET', name: 'Ethiopia'}, {code: 'FK', name: 'Falkland Islands'}, {code: 'FO', name: 'Faroe Islands'},
    {code: 'FJ', name: 'Fiji'}, {code: 'FI', name: 'Finland'}, {code: 'FR', name: 'France'},
    {code: 'GF', name: 'French Guiana'}, {code: 'PF', name: 'French Polynesia'}, {code: 'GA', name: 'Gabon'},
    {code: 'GM', name: 'Gambia'}, {code: 'GE', name: 'Georgia'}, {code: 'DE', name: 'Germany'},
    {code: 'GH', name: 'Ghana'}, {code: 'GI', name: 'Gibraltar'}, {code: 'GR', name: 'Greece'},
    {code: 'GL', name: 'Greenland'}, {code: 'GD', name: 'Grenada'}, {code: 'GP', name: 'Guadeloupe'},
    {code: 'GU', name: 'Guam'}, {code: 'GT', name: 'Guatemala'}, {code: 'GG', name: 'Guernsey'},
    {code: 'GN', name: 'Guinea'}, {code: 'GW', name: 'Guinea-Bissau'}, {code: 'GY', name: 'Guyana'},
    {code: 'HT', name: 'Haiti'}, {code: 'HN', name: 'Honduras'}, {code: 'HK', name: 'Hong Kong'},
    {code: 'HU', name: 'Hungary'}, {code: 'IS', name: 'Iceland'}, {code: 'IN', name: 'India'},
    {code: 'ID', name: 'Indonesia'}, {code: 'IR', name: 'Iran'}, {code: 'IQ', name: 'Iraq'},
    {code: 'IE', name: 'Ireland'}, {code: 'IM', name: 'Isle of Man'}, {code: 'IL', name: 'Israel'},
    {code: 'IT', name: 'Italy'}, {code: 'JM', name: 'Jamaica'}, {code: 'JP', name: 'Japan'},
    {code: 'JE', name: 'Jersey'}, {code: 'JO', name: 'Jordan'}, {code: 'KZ', name: 'Kazakhstan'},
    {code: 'KE', name: 'Kenya'}, {code: 'KI', name: 'Kiribati'}, {code: 'KP', name: 'Korea (North)'},
    {code: 'KR', name: 'Korea (South)'}, {code: 'KW', name: 'Kuwait'}, {code: 'KG', name: 'Kyrgyzstan'},
    {code: 'LA', name: 'Laos'}, {code: 'LV', name: 'Latvia'}, {code: 'LB', name: 'Lebanon'},
    {code: 'LS', name: 'Lesotho'}, {code: 'LR', name: 'Liberia'}, {code: 'LY', name: 'Libya'},
    {code: 'LI', name: 'Liechtenstein'}, {code: 'LT', name: 'Lithuania'}, {code: 'LU', name: 'Luxembourg'},
    {code: 'MO', name: 'Macao'}, {code: 'MG', name: 'Madagascar'}, {code: 'MW', name: 'Malawi'},
    {code: 'MY', name: 'Malaysia'}, {code: 'MV', name: 'Maldives'}, {code: 'ML', name: 'Mali'},
    {code: 'MT', name: 'Malta'}, {code: 'MH', name: 'Marshall Islands'}, {code: 'MQ', name: 'Martinique'},
    {code: 'MR', name: 'Mauritania'}, {code: 'MU', name: 'Mauritius'}, {code: 'YT', name: 'Mayotte'},
    {code: 'MX', name: 'Mexico'}, {code: 'FM', name: 'Micronesia'}, {code: 'MD', name: 'Moldova'},
    {code: 'MC', name: 'Monaco'}, {code: 'MN', name: 'Mongolia'}, {code: 'ME', name: 'Montenegro'},
    {code: 'MS', name: 'Montserrat'}, {code: 'MA', name: 'Morocco'}, {code: 'MZ', name: 'Mozambique'},
    {code: 'MM', name: 'Myanmar'}, {code: 'NA', name: 'Namibia'}, {code: 'NR', name: 'Nauru'},
    {code: 'NP', name: 'Nepal'}, {code: 'NL', name: 'Netherlands'}, {code: 'NC', name: 'New Caledonia'},
    {code: 'NZ', name: 'New Zealand'}, {code: 'NI', name: 'Nicaragua'}, {code: 'NE', name: 'Niger'},
    {code: 'NG', name: 'Nigeria'}, {code: 'NU', name: 'Niue'}, {code: 'NF', name: 'Norfolk Island'},
    {code: 'MK', name: 'North Macedonia'}, {code: 'MP', name: 'Northern Mariana Islands'}, {code: 'NO', name: 'Norway'},
    {code: 'OM', name: 'Oman'}, {code: 'PK', name: 'Pakistan'}, {code: 'PW', name: 'Palau'},
    {code: 'PS', name: 'Palestine'}, {code: 'PA', name: 'Panama'}, {code: 'PG', name: 'Papua New Guinea'},
    {code: 'PY', name: 'Paraguay'}, {code: 'PE', name: 'Peru'}, {code: 'PH', name: 'Philippines'},
    {code: 'PN', name: 'Pitcairn'}, {code: 'PL', name: 'Poland'}, {code: 'PT', name: 'Portugal'},
    {code: 'PR', name: 'Puerto Rico'}, {code: 'QA', name: 'Qatar'}, {code: 'RE', name: 'Réunion'},
    {code: 'RO', name: 'Romania'}, {code: 'RU', name: 'Russia'}, {code: 'RW', name: 'Rwanda'},
    {code: 'BL', name: 'Saint Barthélemy'}, {code: 'SH', name: 'Saint Helena'}, {code: 'KN', name: 'Saint Kitts and Nevis'},
    {code: 'LC', name: 'Saint Lucia'}, {code: 'MF', name: 'Saint Martin'}, {code: 'PM', name: 'Saint Pierre and Miquelon'},
    {code: 'VC', name: 'Saint Vincent and the Grenadines'}, {code: 'WS', name: 'Samoa'}, {code: 'SM', name: 'San Marino'},
    {code: 'ST', name: 'São Tomé and Príncipe'}, {code: 'SA', name: 'Saudi Arabia'}, {code: 'SN', name: 'Senegal'},
    {code: 'RS', name: 'Serbia'}, {code: 'SC', name: 'Seychelles'}, {code: 'SL', name: 'Sierra Leone'},
    {code: 'SG', name: 'Singapore'}, {code: 'SX', name: 'Sint Maarten'}, {code: 'SK', name: 'Slovakia'},
    {code: 'SI', name: 'Slovenia'}, {code: 'SB', name: 'Solomon Islands'}, {code: 'SO', name: 'Somalia'},
    {code: 'ZA', name: 'South Africa'}, {code: 'GS', name: 'South Georgia and the South Sandwich Islands'}, {code: 'SS', name: 'South Sudan'},
    {code: 'ES', name: 'Spain'}, {code: 'LK', name: 'Sri Lanka'}, {code: 'SD', name: 'Sudan'},
    {code: 'SR', name: 'Suriname'}, {code: 'SJ', name: 'Svalbard and Jan Mayen'}, {code: 'SE', name: 'Sweden'},
    {code: 'CH', name: 'Switzerland'}, {code: 'SY', name: 'Syria'}, {code: 'TW', name: 'Taiwan'},
    {code: 'TJ', name: 'Tajikistan'}, {code: 'TZ', name: 'Tanzania'}, {code: 'TH', name: 'Thailand'},
    {code: 'TL', name: 'Timor-Leste'}, {code: 'TG', name: 'Togo'}, {code: 'TK', name: 'Tokelau'},
    {code: 'TO', name: 'Tonga'}, {code: 'TT', name: 'Trinidad and Tobago'}, {code: 'TN', name: 'Tunisia'},
    {code: 'TR', name: 'Turkey'}, {code: 'TM', name: 'Turkmenistan'}, {code: 'TC', name: 'Turks and Caicos Islands'},
    {code: 'TV', name: 'Tuvalu'}, {code: 'UG', name: 'Uganda'}, {code: 'UA', name: 'Ukraine'},
    {code: 'AE', name: 'United Arab Emirates'}, {code: 'UY', name: 'Uruguay'}, {code: 'UZ', name: 'Uzbekistan'},
    {code: 'VU', name: 'Vanuatu'}, {code: 'VA', name: 'Vatican City'}, {code: 'VE', name: 'Venezuela'},
    {code: 'VN', name: 'Vietnam'}, {code: 'VG', name: 'Virgin Islands (British)'}, {code: 'VI', name: 'Virgin Islands (U.S.)'},
    {code: 'WF', name: 'Wallis and Futuna'}, {code: 'EH', name: 'Western Sahara'}, {code: 'YE', name: 'Yemen'},
    {code: 'ZM', name: 'Zambia'}, {code: 'ZW', name: 'Zimbabwe'}
  ];

  var dialCodes = {
    'GB': '+44', 'US': '+1', 'CA': '+1', 'AU': '+61', 'AF': '+93', 'AL': '+355', 'DZ': '+213',
    'AS': '+1', 'AD': '+376', 'AO': '+244', 'AI': '+1', 'AQ': '+672', 'AG': '+1', 'AR': '+54',
    'AM': '+374', 'AW': '+297', 'AT': '+43', 'AZ': '+994', 'BS': '+1', 'BH': '+973', 'BD': '+880',
    'BB': '+1', 'BY': '+375', 'BE': '+32', 'BZ': '+501', 'BJ': '+229', 'BM': '+1', 'BT': '+975',
    'BO': '+591', 'BA': '+387', 'BW': '+267', 'BR': '+55', 'BN': '+673', 'BG': '+359', 'BF': '+226',
    'BI': '+257', 'CV': '+238', 'KH': '+855', 'CM': '+237', 'KY': '+1', 'CF': '+236', 'TD': '+235',
    'CL': '+56', 'CN': '+86', 'CO': '+57', 'KM': '+269', 'CG': '+242', 'CD': '+243', 'CK': '+682',
    'CR': '+506', 'CI': '+225', 'HR': '+385', 'CU': '+53', 'CY': '+357', 'CZ': '+420', 'DK': '+45',
    'DJ': '+253', 'DM': '+1', 'DO': '+1', 'EC': '+593', 'EG': '+20', 'SV': '+503', 'GQ': '+240',
    'ER': '+291', 'EE': '+372', 'SZ': '+268', 'ET': '+251', 'FK': '+500', 'FO': '+298', 'FJ': '+679',
    'FI': '+358', 'FR': '+33', 'GF': '+594', 'PF': '+689', 'GA': '+241', 'GM': '+220', 'GE': '+995',
    'DE': '+49', 'GH': '+233', 'GI': '+350', 'GR': '+30', 'GL': '+299', 'GD': '+1', 'GP': '+590',
    'GU': '+1', 'GT': '+502', 'GG': '+44', 'GN': '+224', 'GW': '+245', 'GY': '+592', 'HT': '+509',
    'HN': '+504', 'HK': '+852', 'HU': '+36', 'IS': '+354', 'IN': '+91', 'ID': '+62', 'IR': '+98',
    'IQ': '+964', 'IE': '+353', 'IM': '+44', 'IL': '+972', 'IT': '+39', 'JM': '+1', 'JP': '+81',
    'JE': '+44', 'JO': '+962', 'KZ': '+7', 'KE': '+254', 'KI': '+686', 'KP': '+850', 'KR': '+82',
    'KW': '+965', 'KG': '+996', 'LA': '+856', 'LV': '+371', 'LB': '+961', 'LS': '+266', 'LR': '+231',
    'LY': '+218', 'LI': '+423', 'LT': '+370', 'LU': '+352', 'MO': '+853', 'MG': '+261', 'MW': '+265',
    'MY': '+60', 'MV': '+960', 'ML': '+223', 'MT': '+356', 'MH': '+692', 'MQ': '+596', 'MR': '+222',
    'MU': '+230', 'YT': '+262', 'MX': '+52', 'FM': '+691', 'MD': '+373', 'MC': '+377', 'MN': '+976',
    'ME': '+382', 'MS': '+1', 'MA': '+212', 'MZ': '+258', 'MM': '+95', 'NA': '+264', 'NR': '+674',
    'NP': '+977', 'NL': '+31', 'NC': '+687', 'NZ': '+64', 'NI': '+505', 'NE': '+227', 'NG': '+234',
    'NU': '+683', 'NF': '+672', 'MK': '+389', 'MP': '+1', 'NO': '+47', 'OM': '+968', 'PK': '+92',
    'PW': '+680', 'PS': '+970', 'PA': '+507', 'PG': '+675', 'PY': '+595', 'PE': '+51', 'PH': '+63',
    'PN': '+64', 'PL': '+48', 'PT': '+351', 'PR': '+1', 'QA': '+974', 'RE': '+262', 'RO': '+40',
    'RU': '+7', 'RW': '+250', 'BL': '+590', 'SH': '+290', 'KN': '+1', 'LC': '+1', 'MF': '+590',
    'PM': '+508', 'VC': '+1', 'WS': '+685', 'SM': '+378', 'ST': '+239', 'SA': '+966', 'SN': '+221',
    'RS': '+381', 'SC': '+248', 'SL': '+232', 'SG': '+65', 'SX': '+1', 'SK': '+421', 'SI': '+386',
    'SB': '+677', 'SO': '+252', 'ZA': '+27', 'GS': '+500', 'SS': '+211', 'ES': '+34', 'LK': '+94',
    'SD': '+249', 'SR': '+597', 'SJ': '+47', 'SE': '+46', 'CH': '+41', 'SY': '+963', 'TW': '+886',
    'TJ': '+992', 'TZ': '+255', 'TH': '+66', 'TL': '+670', 'TG': '+228', 'TK': '+690', 'TO': '+676',
    'TT': '+1', 'TN': '+216', 'TR': '+90', 'TM': '+993', 'TC': '+1', 'TV': '+688', 'UG': '+256',
    'UA': '+380', 'AE': '+971', 'UY': '+598', 'UZ': '+998', 'VU': '+678', 'VA': '+39', 'VE': '+58',
    'VN': '+84', 'VG': '+1', 'VI': '+1', 'WF': '+681', 'EH': '+212', 'YE': '+967', 'ZM': '+260', 'ZW': '+263'
  };

  function flagUrl(code) {
    return "https://flagcdn.com/w20/" + code.toLowerCase() + ".png";
  }

  function flagImg(code, className) {
    var img = document.createElement("img");
    img.src = flagUrl(code);
    img.alt = "";
    img.loading = "lazy";
    if (className) img.className = className;
    img.onerror = function () { img.style.display = "none"; };
    return img;
  }

  function createCountryPicker(opts) {
    var input = document.getElementById(opts.inputId);
    var wrapper = input.closest(".nationality-wrapper");
    var flagDisplay = document.getElementById(opts.flagId);
    var dropdown = document.getElementById(opts.dropdownId);
    var search = document.getElementById(opts.searchId);
    var list = document.getElementById(opts.listId);

    input.setAttribute("aria-haspopup", "listbox");
    input.setAttribute("aria-expanded", "false");

    function isOpen() {
      return dropdown.classList.contains("active");
    }

    function close() {
      dropdown.classList.remove("active");
      wrapper.classList.remove("active");
      input.setAttribute("aria-expanded", "false");
    }

    function open() {
      search.value = "";
      render("");
      dropdown.classList.add("active");
      wrapper.classList.add("active");
      input.setAttribute("aria-expanded", "true");
      search.focus();
    }

    function select(country) {
      input.value = opts.valueFor(country);
      flagDisplay.innerHTML = "";
      flagDisplay.appendChild(flagImg(country.code));
      flagDisplay.classList.add("show");
      input.classList.add("has-flag");
      if (opts.onSelect) opts.onSelect(country);
    }

    function clear() {
      input.value = "";
      flagDisplay.innerHTML = "";
      flagDisplay.classList.remove("show");
      input.classList.remove("has-flag");
    }

    function render(filter) {
      var term = filter.toLowerCase();
      list.innerHTML = "";
      countries.forEach(function (country) {
        if (country.name.toLowerCase().indexOf(term) === -1) return;
        var option = document.createElement("button");
        option.type = "button";
        option.className = "nationality-option";
        option.setAttribute("role", "option");
        option.appendChild(flagImg(country.code, "nationality-flag"));
        var label = document.createElement("span");
        label.textContent = opts.labelFor(country);
        option.appendChild(label);
        option.addEventListener("click", function () {
          select(country);
          close();
          input.focus();
        });
        list.appendChild(option);
      });
    }

    input.addEventListener("click", function (e) {
      e.preventDefault();
      if (isOpen()) close(); else open();
    });

    input.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " " || e.key === "ArrowDown") {
        e.preventDefault();
        open();
      }
    });

    search.addEventListener("input", function () {
      render(search.value);
    });

    search.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        close();
        input.focus();
      } else if (e.key === "Enter") {
        e.preventDefault();
        var first = list.querySelector(".nationality-option");
        if (first) first.click();
      }
    });

    document.addEventListener("click", function (e) {
      if (!wrapper.contains(e.target)) close();
    });

    return { select: select, clear: clear };
  }

  // Phone country code picker
  var phoneCodeInput = document.getElementById("phone-code");
  var ukCountry = countries[0];
  var phonePicker = createCountryPicker({
    inputId: "phone-country",
    flagId: "phone-flag-display",
    dropdownId: "phone-dropdown",
    searchId: "phone-search",
    listId: "phone-options",
    labelFor: function (c) { return c.name + " (" + (dialCodes[c.code] || "") + ")"; },
    valueFor: function (c) { return c.name + " (" + (dialCodes[c.code] || "") + ")"; },
    onSelect: function (c) { phoneCodeInput.value = dialCodes[c.code] || ""; }
  });
  phonePicker.select(ukCountry);

  // Country of residence picker
  var residenceInput = document.getElementById("residence");
  var residenceCode = document.getElementById("residence-code");
  var residenceError = document.getElementById("residence-error");

  function clearResidenceError() {
    residenceError.classList.add("hidden");
    residenceInput.setCustomValidity("");
    residenceInput.classList.remove("error");
  }

  var residencePicker = createCountryPicker({
    inputId: "residence",
    flagId: "residence-flag-display",
    dropdownId: "residence-dropdown",
    searchId: "residence-search",
    listId: "residence-options",
    labelFor: function (c) { return c.name; },
    valueFor: function (c) { return c.name; },
    onSelect: function (c) {
      residenceCode.value = c.code;
      clearResidenceError();
    }
  });

  residenceInput.addEventListener("click", clearResidenceError);

  // Role and job, pre-filled from "Apply" links (?job=ID&role=Title)
  var params = new URLSearchParams(window.location.search);
  var roleInput = document.getElementById("role");
  var jobIdInput = form.querySelector('input[name="job_id"]');
  var roleParam = params.get("role");
  var jobParam = params.get("job");
  if (roleInput && roleParam) roleInput.value = roleParam.slice(0, 120);

  function showJobContext(job) {
    var box = document.getElementById("job-context");
    if (!box) return;
    document.getElementById("job-context-title").textContent = job.title;
    document.getElementById("job-context-ref").textContent = job.ref_code ? "(Ref " + job.ref_code + ")" : "";
    box.classList.remove("hidden");
    box.classList.add("flex");
  }

  var validJobParam = jobParam && /^\d{1,9}$/.test(jobParam);
  if (jobIdInput && (validJobParam || roleParam)) {
    window.HJ.fetchJson(["api/jobs.php", "assets/data/jobs-snapshot.json"], function (d) {
      return d && Array.isArray(d.jobs);
    }).then(function (data) {
      var job = null;
      var wantedTitle = (roleParam || "").trim().toLowerCase();
      data.jobs.forEach(function (j) {
        if (validJobParam ? String(j.id) === jobParam : String(j.title).toLowerCase() === wantedTitle) job = job || j;
      });
      if (!job) return;
      jobIdInput.value = String(job.id);
      if (roleInput && !roleInput.value) roleInput.value = job.title.slice(0, 120);
      showJobContext(job);
    }, function () { /* the role text from the link is still submitted */ });
  }

  // Campaign tracking captured by site.js on the landing page
  try {
    var utm = JSON.parse(sessionStorage.getItem("hj_utm") || "{}");
    Object.keys(utm).forEach(function (key) {
      var field = form.querySelector('input[type="hidden"][name="' + key + '"]');
      if (field && typeof utm[key] === "string") field.value = utm[key].slice(0, 255);
    });
  } catch (e) { /* storage blocked: tracking is optional */ }

  // Spam timing token
  var tokenInput = form.querySelector('input[name="form_token"]');

  function loadToken() {
    return fetch("api/form-token.php", { cache: "no-store", credentials: "same-origin" })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && d.token && tokenInput) tokenInput.value = d.token; })
      .catch(function () { /* the server will ask the applicant to reload */ });
  }
  loadToken();

  // Optional captcha, enabled from the admin settings
  var captchaBox = document.getElementById("captcha-box");
  var captchaProvider = "none";
  var captchaLoaded = false;

  function loadCaptcha(settings) {
    if (captchaLoaded || !captchaBox || !settings) return;
    var provider = settings.captcha_provider;
    var siteKey = settings.captcha_site_key;
    if ((provider !== "turnstile" && provider !== "recaptcha") || !siteKey) return;
    captchaLoaded = true;
    captchaProvider = provider;
    var widget = document.createElement("div");
    widget.className = provider === "turnstile" ? "cf-turnstile" : "g-recaptcha";
    widget.setAttribute("data-sitekey", siteKey);
    captchaBox.appendChild(widget);
    captchaBox.classList.remove("hidden");
    var script = document.createElement("script");
    script.src = provider === "turnstile"
      ? "https://challenges.cloudflare.com/turnstile/v0/api.js"
      : "https://www.google.com/recaptcha/api.js";
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
  }

  function resetCaptcha() {
    try {
      if (captchaProvider === "turnstile" && window.turnstile) window.turnstile.reset();
      if (captchaProvider === "recaptcha" && window.grecaptcha) window.grecaptcha.reset();
    } catch (e) { /* widget not ready */ }
  }

  if (window.HJ.settings) loadCaptcha(window.HJ.settings);
  document.addEventListener("hj:settings", function (e) { loadCaptcha(e.detail); });

  function contactEmail() {
    var s = window.HJ.settings;
    return s && s.contact_email ? s.contact_email : "info@hubjobplatform.com";
  }

  // CV upload
  var uploadArea = document.getElementById("upload-area");
  var fileInput = document.getElementById("cv");
  var fileNameDisplay = document.getElementById("file-name");

  function showFileMessage(text, isError) {
    fileNameDisplay.textContent = text;
    fileNameDisplay.classList.toggle("text-error", isError);
    fileNameDisplay.classList.toggle("text-emerald-700", !isError && text !== "");
  }

  function validateFile() {
    if (!fileInput.files.length) {
      showFileMessage("", false);
      return true;
    }
    var file = fileInput.files[0];
    var ext = file.name.split(".").pop().toLowerCase();
    if (CV_EXTENSIONS.indexOf(ext) === -1) {
      fileInput.value = "";
      showFileMessage("That file type isn't supported. Please upload a PDF, DOC, DOCX, RTF or TXT file.", true);
      return false;
    }
    if (file.size > MAX_CV_BYTES) {
      fileInput.value = "";
      showFileMessage("That file is larger than 2MB. Please upload a smaller file.", true);
      return false;
    }
    showFileMessage("Selected file: " + file.name, false);
    return true;
  }

  uploadArea.addEventListener("dragover", function (e) {
    e.preventDefault();
    uploadArea.classList.add("dragover");
  });
  uploadArea.addEventListener("dragleave", function () {
    uploadArea.classList.remove("dragover");
  });
  uploadArea.addEventListener("drop", function (e) {
    e.preventDefault();
    uploadArea.classList.remove("dragover");
    fileInput.files = e.dataTransfer.files;
    validateFile();
  });
  fileInput.addEventListener("change", validateFile);

  // Submission
  var submitButton = form.querySelector('button[type="submit"]');
  var submitLabel = submitButton.querySelector("[data-submit-label]") || submitButton;
  var originalLabel = submitLabel.textContent;

  function showMessage(type, text) {
    var existing = document.getElementById("form-message");
    if (existing) existing.remove();
    var msg = document.createElement("div");
    msg.id = "form-message";
    msg.className = "form-alert form-alert--" + type;
    msg.setAttribute("role", type === "error" ? "alert" : "status");
    msg.textContent = text;
    form.appendChild(msg);
    return msg;
  }

  function setSubmitting(submitting) {
    submitButton.disabled = submitting;
    submitButton.classList.toggle("opacity-70", submitting);
    submitLabel.textContent = submitting ? "Sending..." : originalLabel;
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!residenceCode.value.trim()) {
      residenceError.classList.remove("hidden");
      residenceInput.setCustomValidity("Please select your country of residence");
      residenceInput.classList.add("error");
      residenceInput.scrollIntoView({ behavior: "smooth", block: "center" });
      residenceInput.focus();
      return;
    }
    clearResidenceError();

    var formData = new FormData(form);
    formData.set("phone", phoneCodeInput.value + " " + document.getElementById("phone").value);

    setSubmitting(true);
    showMessage("info", "Submitting your application...");

    fetch(form.getAttribute("action") || "api/apply.php", { method: "POST", body: formData, credentials: "same-origin" })
      .then(function (response) {
        return response.json().catch(function () {
          throw new Error("Invalid response");
        });
      })
      .then(function (data) {
        var success = data.status === "success";
        var text = data.message || "An error occurred. Please try again.";
        if (!success && text.indexOf("@") === -1) {
          text += " If the problem continues, email us at " + contactEmail() + ".";
        }
        var msg = showMessage(success ? "success" : "error", text);

        if (success) {
          var keepJobId = jobIdInput ? jobIdInput.value : "";
          var keepRole = roleInput ? roleInput.value : "";
          form.reset();
          if (jobIdInput) jobIdInput.value = keepJobId;
          if (roleInput && keepJobId) roleInput.value = keepRole;
          phonePicker.select(ukCountry);
          residencePicker.clear();
          residenceCode.value = "";
          showFileMessage("", false);
          msg.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }

        setTimeout(function () {
          if (msg.parentNode) msg.remove();
        }, 10000);
      })
      .catch(function () {
        showMessage("error", "An error occurred. Please try again or contact us directly at " + contactEmail() + ".");
      })
      .then(function () {
        // Every attempt gets a fresh token and captcha so a retry is not rejected.
        resetCaptcha();
        loadToken();
        setSubmitting(false);
      });
  });
})();
