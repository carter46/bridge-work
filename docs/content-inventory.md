# Hardcoded content inventory

Audit of every hardcoded brand, contact and "verified" value on the public site, taken before the PHP/MySQL migration. Each occurrence is classified as one of:

- **Configurable**: tagged with a `data-setting*` attribute. `assets/js/settings.js` replaces it with the value saved in Admin → Settings. The HTML keeps the current value as a fallback.
- **Static**: left in the file on purpose. Changing it needs a file edit.
- **Rewrite**: copy changed because it made a claim the data can't back up, such as "verified".

Line numbers refer to the files before the migration edits. To re-check after edits, search for the literal values (see "Re-audit" at the end).

## Settings keys

| Key | Current value | Used for |
| --- | --- | --- |
| `site_name` | Hubjob Platform | Logo `alt` text, admin and email branding |
| `logo_url` | static/images/logo22.png | Header and footer logo (`data-setting-src`) |
| `contact_email` | info@hubjobplatform.com | Text and `mailto:` links (`data-setting-href` keeps any `?subject=`) |
| `contact_phone` / `phone_href` | +44 1202 958648 / +441202958648 | Text and `tel:` links |
| `whatsapp_number` | (empty) | Optional WhatsApp link (hidden while empty) |
| `address_full` | 42 Windham Road, Bournemouth, Dorset, BH1 2AW, UK | Footer and contact page |
| `address_short` | 42 Windham Road, BH1 2AW | Map pins |
| `address_city` | Bournemouth | "Bournemouth Desk", "Bournemouth Placement Hub" |
| `address_region_line` | Bournemouth, Dorset, UK | About, FAQ and "UK Head Office" |
| `map_url` | Google Maps search for the address | Map pin links |
| `office_hours` | Mon – Fri: 8:30 AM – 6:00 PM GMT | Contact, FAQ and Apply |
| `response_time` / `response_time_short` | 24 hours / 24h | "Replies within 24h", "within 24 hours" |
| `copyright_name` | Hubjob Platform | Footer © line |
| `retention_months` | 12 | Privacy page "How long we keep it" |

## Occurrences by file

### Shared header and footer (every page: index, about, faq, jobs, apply, contact, privacy)
- Header logo `<img … src="static/images/logo22.png">`: **configurable** (`logo_url`, `site_name`).
- Footer logo: **configurable**.
- Footer address `42 Windham Road, Bournemouth, Dorset, BH1 2AW, UK`: **configurable** (`address_full`).
- Footer email link (`mailto:` plus text): **configurable** (`contact_email`).
- Footer phone link (`tel:` plus text): **configurable** (`contact_phone`).
- Footer "Post a Job" and "Search Talent" `mailto:` links: **configurable** (`contact_email`, subject kept).
- Footer © line "Hubjob Platform": **configurable** (`copyright_name`).
- `aria-label="Hubjob Platform home"`: **static**.
- `<title>` and `<meta name="description">`: **static** (crawlers may not run JavaScript).
- Google Ads gtag `AW-17786385681`: **static** (needs a code change).

### index.html
- L6, L7 title and meta: **static**. L7 "verified international employers" was **rewritten**.
- L70 hero "top verified companies": **rewritten** to "trusted companies".
- L104 "Verified direct contracts and permanent roles added this week.": **rewritten**. The 6 made-up listings (L110–203, including "Bournemouth, UK" at L195) were **removed** and are now rendered from featured jobs in the database.
- L218, L281–285, L334, L342 brand mentions in marketing copy and testimonials: **static**.
- L291 "verified employers worldwide": **rewritten**.
- L298–299 "Verified Global Employers" card: **rewritten** to "Employer Checks", explaining the per-job badge.
- Category tiles L220–245: **now rendered from the database**. The static fallback lists the 8 new categories.

### about.html
- L7, L83 "verified employers": **rewritten**.
- L76, L101, L200, L231 brand mentions: **static**.
- L118 "Bournemouth, Dorset, United Kingdom": **configurable** (`address_region_line`).
- L133 "London / Bournemouth", L253 "Dorset Operations Center (Bournemouth)": **static** (office description copy).
- L160–161 "Verified Employer Network": **rewritten** to "Employer Checks".

### faq.html
- L7 meta, L75, L91, L196, L220, L247, L295, L362, L453 "verified" wording: **rewritten**.
- L123–124 "Vetted Employers / Strict authenticity checks": **rewritten**.
- L138 "Bournemouth & London team", L319, L391 city mentions in copy: **static**.
- L334–335 "Our 4-Step Verification Guarantee … Every role posted on Hubjob goes through…": **rewritten** to describe the check behind the "Verified employer" badge.
- L393–401 email and phone cards: **configurable**.
- L406 "Bournemouth, Dorset, UK": **configurable** (`address_region_line`).
- L416 "Bournemouth Desk": city **configurable**.
- L417 office hours: **configurable**.
- L421 "Replies within 24h": **configurable** (`response_time_short`).
- L427 "42 Windham Road, BH1 2AW": **configurable** (`address_short`).
- L430 "within 24 business hours": **configurable** (`response_time`).
- L432 "Submit an Inquiry" `mailto:`: **configurable** (`contact_email`, subject kept).

### jobs.html
- L7 meta "verified employers": **rewritten**.
- L74 "Live Verified Opportunities": **rewritten** to "Live Opportunities".
- L82 "30 Verified Openings": **now generated** ("N Open Roles").
- L83–84 sector count and pay range: **now generated**.
- L85 "< 24h": **configurable** (`response_time_short`).
- L197–200 "Verified Direct Employers Only / Every listing is checked…": **rewritten** to explain the per-job badge.
- L211 "within 24 hours": **configurable**.
- L216–221 "Bournemouth Placement Hub" phone card: **configurable** (city and phone).
- L233 "Showing 30 Verified Roles": **rewritten** to "Showing N Roles".
- L250–690 all 30 hardcoded listings, including the TECH-02 "Verified Partner" badge: **removed** and served from the database. The badge is kept in `source_text`, and shows only when "Employer verified" is ticked.
- L721 brand mention: **static**.

### apply.html
- L7 meta "within 24 hours": **static** (meta).
- L184 "shared only with verified employers": **rewritten**.
- L201 "within 24 hours": **configurable**.
- L205 "verified employers": **rewritten**.
- L225 "Verified employers only / Every company goes through…": **rewritten**.
- L233 "Bournemouth placement desk … Mon – Fri, 8:30 AM – 6:00 PM GMT": city and hours **configurable**.
- L235–236 email and phone: **configurable**.

### contact.html
- L78 "Bournemouth desk … 24 business hours": city and response time **configurable**.
- L87–101 email, phone, address and hours cards: **configurable**.
- L209 "verified employers": **rewritten**.
- L225–226 desk city and hours: **configurable**.
- L230 "Replies within 24h": **configurable**.
- L234–236 map link and short address: **configurable** (`map_url`, `address_short`).
- L239 "24 business hours": **configurable**.
- L241–242, L252 `mailto:` and `tel:` buttons: **configurable**.

### assets/js/apply-form.js
- The fallback error message with the email address: **configurable** (read from settings at runtime).

### sendmail.php
- Hardcoded recipient `info@hubjobplatform.com` and placeholder SMTP: **removed**. It now uses `notification_email` and the SMTP settings in the database (see `includes/mailer.php`).

## Copy rewrites needing sign-off

| Where | Before | After |
| --- | --- | --- |
| index hero | "…with top verified companies." | "…with trusted companies." |
| index featured | "Verified direct contracts and permanent roles added this week." | "Hand-picked roles from our current openings." |
| index why-us | "Verified Global Employers / Work with trusted, vetted companies…" | "Employer Checks / Listings marked "Verified employer" have passed our legal, pay and compliance checks." |
| about mission | "…international candidates and verified employers…" | "…international candidates and employers…" |
| about values | "Verified Employer Network / Direct partnerships with certified sponsors…" | "Employer Checks / Employers who pass our legal, pay and compliance checks are marked "Verified employer" on their listings." |
| faq trust strip | "Vetted Employers / Strict authenticity checks" | "Employer Checks / Look for the Verified badge" |
| faq charter | "Our 4-Step Verification Guarantee / Every role posted on Hubjob goes through…" | "How Employer Verification Works / Listings marked "Verified employer" have completed all four steps below…" |
| faq answers | "…ensuring every company is verified and reliable." | "…employers who complete our checks are marked "Verified employer" on their listings." |
| jobs sidebar | "Verified Direct Employers Only / Every listing is checked…" | "Look for the Verified badge / Listings marked "Verified employer" have passed our legal, pay and compliance checks." |
| jobs results | "Showing 30 Verified Roles" | "Showing N Roles" |
| apply/contact | "shared only with verified employers" | "shared only with employers relevant to the roles you apply for" |
| apply sidebar | "Verified employers only / Every company goes through…" | "Employer checks / Employers marked "Verified" have passed legal, pay and visa feasibility checks." |

## Re-audit

After any content change, search the public files for the literal values: `info@hubjobplatform.com`, `1202`, `Windham`, `BH1`, `logo22`, `8:30`, `24h`, `24 hours`, `verified`. Every match should either carry a `data-setting` / `data-setting-href` / `data-setting-src` attribute or be on the static list above.
