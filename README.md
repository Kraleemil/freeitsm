<h1 align="center">FreeITSM</h1>

<p align="center"><strong>Free, open-source IT Service Management — self-hosted, AI-included, no per-seat fees. Ever.</strong></p>

<p align="center">
<a href="https://github.com/edmozley/freeitsm/blob/main/LICENSE"><img src="https://img.shields.io/github/license/edmozley/freeitsm?style=flat-square&color=blue" alt="MIT License"></a>
<img src="https://img.shields.io/badge/PHP-7.4--8.4-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 7.4–8.4">
<img src="https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL 8.0+">
<img src="https://img.shields.io/badge/Docker-Ready-2496ED?style=flat-square&logo=docker&logoColor=white" alt="Docker Ready">
<a href="https://github.com/edmozley/freeitsm/stargazers"><img src="https://img.shields.io/github/stars/edmozley/freeitsm?style=flat-square&color=gold" alt="GitHub stars"></a>
</p>

<p align="center">
🌍 <a href="https://freeitsm.co.uk">freeitsm.co.uk</a> &nbsp;·&nbsp;
📖 <a href="https://github.com/edmozley/freeitsm/wiki">Documentation Wiki</a> &nbsp;·&nbsp;
💬 <a href="https://github.com/edmozley/freeitsm/discussions">Discussions</a> &nbsp;·&nbsp;
🐛 <a href="https://github.com/edmozley/freeitsm/issues">Issues</a>
</p>

---

FreeITSM is a complete web-based ITSM platform: **22 integrated modules** covering tickets, assets, knowledge, changes, problems, tasks, a CMDB, workflows, an LMS and more — plus a **self-service portal** for your end users. It runs on a plain PHP + MySQL stack (WAMP, XAMPP, LAMP, or Docker), so your data stays on your server.

**Why teams pick it:**

- 🆓 **Genuinely free** — MIT licence, no per-seat/per-agent fees, no "Enterprise tier". Everything ships to everyone.
- 🏠 **Self-hosted** — your tickets, your customers' conversations and your knowledge base live in your database, under your backups and your privacy policy.
- 🤖 **AI included, not upsold** — reply cleanup, knowledge Q&A, form generation, course authoring, RCA drafting and more, all bring-your-own-key (Anthropic, OpenAI, or OpenRouter).
- 📥 **Every channel becomes a ticket** — email (Microsoft 365, Gmail, IMAP), WhatsApp, Slack, an embeddable web chat widget, and a portal that even staff **without a company email address** can use.
- 🔔 **A notification bell you can actually leave on** — replies, assignments, notes and SLA warnings on your tickets, on every screen. It never tells you about your own actions, groups repeat changes to one ticket into a single entry, and stays silent for bulk updates, so it does not become the thing everyone ignores.

## Screenshots

<table>
<tr>
<td align="center"><strong>Watchtower</strong><br><img src="https://freeitsm.co.uk/images/screenshots/watchtower_1.png" width="350" alt="Watchtower"></td>
<td align="center"><strong>Tickets</strong><br><img src="https://freeitsm.co.uk/images/screenshots/tickets_1.png" width="350" alt="Tickets"></td>
<td align="center"><strong>Assets</strong><br><img src="https://freeitsm.co.uk/images/screenshots/assets_1.png" width="350" alt="Assets"></td>
</tr>
<tr>
<td align="center"><strong>Knowledge</strong><br><img src="https://freeitsm.co.uk/images/screenshots/knowledge_1.png" width="350" alt="Knowledge"></td>
<td align="center"><strong>Changes</strong><br><img src="https://freeitsm.co.uk/images/screenshots/changes_1.png" width="350" alt="Changes"></td>
<td align="center"><strong>Calendar</strong><br><img src="https://freeitsm.co.uk/images/screenshots/calendar_1.png" width="350" alt="Calendar"></td>
</tr>
</table>

<p align="center"><a href="https://freeitsm.co.uk/screenshots.html"><strong>View all 57 screenshots →</strong></a></p>

## 🚀 Quick Start

The fastest route is Docker — no PHP, MySQL or web server setup required:

```bash
git clone https://github.com/edmozley/freeitsm.git
cd freeitsm
docker compose up -d
```

Then open [http://localhost:8080/setup/](http://localhost:8080/setup/) to verify the installation and create your admin account. The first sign-in uses **admin** / **freeitsm** and FreeITSM will make you choose a new password before it lets you do anything else.

- **Manual install** (WAMP / XAMPP / LAMP): follow the **[Installation guide](https://github.com/edmozley/freeitsm/wiki/Installation)** — prerequisites, database setup, encryption key, and configuration files.
- **First login**: `admin` / `freeitsm` — change it immediately via the account menu.
- **Demo data**: System → Demo Data populates every module with realistic sample data, so you can evaluate with the system feeling alive.

## Modules

| Module | What it does |
|--------|--------------|
| [Watchtower](https://github.com/edmozley/freeitsm/wiki/Watchtower) | Unified attention dashboard — one glance shows what needs you across every module. Counts every status you have, under the name and colour you gave it, so renaming or adding one keeps working. Settings turn off cards you don't watch and narrow what each count includes |
| [Tickets](https://github.com/edmozley/freeitsm/wiki/Tickets) | Outlook-style inbox with email, WhatsApp, Slack and web chat channels, SLAs, CSAT, canned responses, multi-select bulk actions, snooze, collision detection, AI reply cleanup |
| [Self-Service Portal](https://github.com/edmozley/freeitsm/wiki/Self-Service-Portal) | End-user portal — request catalogue, knowledge, replies, screen recording; works even with no email address. Can be set as the page people land on (**System → Branding**), with a per-analyst override |
| [Tasks](https://github.com/edmozley/freeitsm/wiki/Tasks) | Kanban board, list, calendar and timeline views for internal work |
| [Assets](https://github.com/edmozley/freeitsm/wiki/Assets) | Asset register with custody tracking, locations, warranties, [QR labels and an in-app camera scanner for stocktakes](https://github.com/edmozley/freeitsm/wiki/Asset-QR-Labels), a per-person view of who holds what with a printable handover document, vCenter and Intune sync |
| [Knowledge](https://github.com/edmozley/freeitsm/wiki/Knowledge) | Rich-text articles with AI chat, vector search, review workflow and per-audience visibility |
| [Change Management](https://github.com/edmozley/freeitsm/wiki/Change-Management) | ITIL changes with CAB voting, risk matrix and post-implementation review |
| [Problem Management](https://github.com/edmozley/freeitsm/wiki/Problem-Management) | Root causes behind recurring incidents, known errors, AI-assisted RCA |
| [Workflows](https://github.com/edmozley/freeitsm/wiki/Workflows) | Cross-module automation — visual canvas, 138+ triggers, outbound webhooks, AI co-author |
| [CMDB](https://github.com/edmozley/freeitsm/wiki/CMDB) | Typed configuration items with relationships, impact analysis and AI summaries |
| [Network Mapper](https://github.com/edmozley/freeitsm/wiki/Network-Mapper) | Architecture diagrams where every node is bound to a real CMDB object |
| [Calendar](https://github.com/edmozley/freeitsm/wiki/Calendar) | Team calendar with categories, and an iCal feed for your phone |
| [Morning Checks](https://github.com/edmozley/freeitsm/wiki/Morning-Checks) | Daily infrastructure health checks with optional groups routed to a team or analyst, raise a ticket or task from any check, trend charts and PDF export |
| [Reporting](https://github.com/edmozley/freeitsm/wiki/Reporting) | System logs, audit trails, and an Intune device dashboard with drill-down |
| [Software](https://github.com/edmozley/freeitsm/wiki/Software) | Software inventory from an agent script, plus licence management |
| [Forms](https://github.com/edmozley/freeitsm/wiki/Forms) | Dynamic form builder with AI assist, section headings, conditional questions, versioning and submissions reporting |
| [Contracts](https://github.com/edmozley/freeitsm/wiki/Contracts) | Supplier and contract lifecycle, plus an AI-powered RFP Builder |
| [Service Status](https://github.com/edmozley/freeitsm/wiki/Service-Status) | Service health dashboard driven by incident tracking |
| [War Room](https://github.com/edmozley/freeitsm/wiki/War-Room) | Chat on your own server for when Teams, Slack or the internet are down — channels, direct messages, search, attachments, and an AI situation report that drafts the update to the business |
| [LMS](https://github.com/edmozley/freeitsm/wiki/LMS) | Author courses in-app (with AI) or upload SCORM; assign, take and track them |
| [Process Mapper](https://github.com/edmozley/freeitsm/wiki/Process-Mapper) | Flowchart builder with swimlanes, custom step types and Mermaid export |
| [System](https://github.com/edmozley/freeitsm/wiki/System) | Administration — analysts, teams, roles, encryption, database verify, demo data |

A **System Wiki** module also auto-documents the codebase from within the app, and a [browser extension](https://github.com/edmozley/freeitsm/wiki/Browser-Extension) puts the Watchtower badge count in your Chrome/Edge toolbar.

## Highlights

- **[REST API](https://github.com/edmozley/freeitsm/wiki/REST-API)** — 200+ key-authenticated endpoints with granular per-key permissions, a live OpenAPI spec, and interactive in-app docs with code samples in seven languages.
- **[Single Sign-On](https://github.com/edmozley/freeitsm/wiki/Single-Sign-On) & [LDAP / Active Directory](https://github.com/edmozley/freeitsm/wiki/LDAP-and-Active-Directory)** — OIDC providers side by side (Keycloak, Entra, Okta, …), or bind straight to your on-prem directory with group-gated just-in-time provisioning. Local login always remains as break-glass.
- **[Security](https://github.com/edmozley/freeitsm/wiki/Security)** — AES-256-GCM encryption at rest for secrets, TOTP MFA whose attempt limit is held on the account rather than in the browser session (so it cannot be reset by signing in again), brute-force protection, role-based permissions down to individual settings tabs, and audit trails throughout. Independently audited in August 2026; both rounds and the findings still open are [documented in full](https://github.com/edmozley/freeitsm/wiki/Security-Hardening-2026-08). Attachments arriving by email, portal or chat are checked against an allow-list and stored under a name FreeITSM chooses, so an uploaded file can never be executed; **System → Security** sets which file types you accept — leave it empty for everything FreeITSM considers safe, or name just the types you want, noting that you can only narrow the list and never widen it to something executable — and whether a type you don't accept is kept as a download-only copy or not kept at all. Either way the ticket records what happened.
- **[Attach documents to anything](https://github.com/edmozley/freeitsm/wiki/Attached-Documents)** — contracts, assets, knowledge articles, problems and changes can all carry documents. Drag a file in, **or paste a link to one held in SharePoint, Google Drive or whatever document system you already use** — a link is a first-class document, so there is no pressure to move anything. The same document can be attached to several records, so one warranty covering eleven laptops is stored once rather than eleven times, and an ⓘ shows everywhere it lives. **Who can see a document is decided entirely by what it is attached to**: if you can see the contract you can read its documents, and if you cannot, it does not appear in the list, in search, in ⌘K, or at its own web address — including as it changes, because the question is asked when you ask it rather than written down at upload. The text inside PDFs and Office files is read in the background so ⌘K finds the contract that *mentions* a clause, not just the one named after it ([Apache Tika](https://tika.apache.org/) needed for PDFs and scans).
- **Custom asset fields** — the built-in asset details describe a computer, because that is what the inventory agent reports. Custom fields are how you record everything else: printers, monitors, headsets, televisions, anything. Define a field once — text, a number with a unit, a date, yes/no, a list to pick from, or a link to a person or another asset — then choose which asset types record it. **A field is defined once and reused**, so "serial number" on a headset is the same field as on a television and one search finds both. Fields can also be added to **individual assets**: if three of your ten meeting-room televisions are being trialled as smart TVs, those three carry an IP address and the other seven do not — not blank, absent. Adding a field takes effect immediately with no database change and nothing already recorded is touched, and a field is retired rather than deleted, so everything ever recorded against it survives and comes straight back if you reinstate it. On a multi-company install a company can add fields of its own alongside the shared ones.
- **[Multi-tenancy](https://github.com/edmozley/freeitsm/wiki/Multi-Tenancy)** — host multiple client companies in one install (built for MSPs), each walled off from the others. Invisible until you add a second company.
- **[Webhooks](https://github.com/edmozley/freeitsm/wiki/Webhooks)** — push any event to Slack, Teams, Discord or any endpoint, with HMAC signing, retries and a delivery dashboard.
- **Service status with history** — every tracked service carries a day-by-day availability strip, an uptime percentage over 7/30/90/365 days, and the incidents behind it. Worked out from your existing incidents, so it covers outages that already happened. Which impact levels count as downtime is set per level; planned maintenance is excluded by default.
- **[Import people from Active Directory](https://github.com/edmozley/freeitsm/wiki/Directory-Sync)** — bring your whole directory in, so people exist before they ever sign in. That matters because the staff who hold equipment are largely the staff who never log in. Name, job title, department, office, phone, employee number and the reporting line all come across. **Preview runs the same job and changes nothing**, nobody is ever deleted, and a safety brake stops an import that suddenly finds far fewer people than last time — because a mistyped starting point looks exactly like everybody leaving at once. **Browse the directory and tick what you want** rather than typing a distinguished name — the tree shows a head count per branch, so you can see that ticking *Staff* brings in 32 people before running anything, and ticking a branch takes anything added to it later too, with individual parts untickable to leave contractors or service accounts out. A **Field mapping** tab pairs each FreeITSM field with its directory attribute and will read one real person to show you the value each row would actually import, alongside every attribute that person carries — so a mistyped attribute name is caught before an import, rather than as an empty column somebody notices weeks later.
- **People, not just logins** — a person record carries job title, department, office, phone, employee number and who they report to, and **Assets → Users** is a directory you can add to, edit and mark leavers in. Somebody who leaves is never deleted: they stay on every ticket, asset and handover document, and if they are still holding equipment the screen tells you. Groundwork for [importing people from a directory](https://github.com/edmozley/freeitsm/wiki/Directory-Sync), and useful on its own without one.
- **[Email send log](https://github.com/edmozley/freeitsm/wiki/Email-Send-Log)** — every email FreeITSM tries to send is recorded, whether it worked or not, in an **Outbound** tab beside the existing inbound mailbox activity. All eight sending routes are covered — analyst replies, ticket templates, workflow actions, SLA alerts, portal and system mail, password resets, and shared knowledge articles and change records — with the recipient, which part of FreeITSM sent it, and the provider's own words when it failed. A failed automated email used to be visible only in a log file on the server, which meant the first sign of one was usually somebody saying they never got a reply.
- **Email templates that can link back to the ticket** — automatic emails carry a **`[ticket_url]`** merge code that resolves to the requester's own view of their ticket, so a confirmation email can say *track it here* instead of leaving somebody to go and find it. The body is edited in a small formatting toolbar rather than by hand-writing HTML, with a **Preview** tab that fills in every merge code with sample values so you see the email as it will arrive. Because these emails are usually sent by the overnight mail collector — when there is no browser request to work an address out from — the templates screen carries the **public web address** the links are built on, and says so plainly if a template uses `[ticket_url]` while nothing is set.
- **Automatic replies can be limited to particular senders** — scope any email template to an address or a whole domain, so external senders stop receiving an internal-sounding auto-reply. **Which template applies is decided by how specific it is, never by the order they are listed in**: a template naming `someone@a.com` beats one naming `a.com`, and both beat one that goes to everyone — so there is no ordering to get wrong, and dragging rows cannot change what gets sent. A new template applies to **everyone** until you narrow it, which means an installation always has a catch-all unless one is deliberately removed. Type an address into **Check what a sender would get** and FreeITSM names the template that would go back and why, using the same code that will choose it for real. And if every template for an event has been restricted, the screen says so — while any email that is not sent because nobody matched is recorded as **Not sent**, with the reason, in the mailbox's send log, so the question *"why did this customer never hear back?"* has an answer long after everybody has forgotten the setup screen.
- **Analysts keep their own email signatures** — written under **Preferences**, with formatting and merge codes for your own details, and **more than one is allowed**: a formal signature for customers, a short one for colleagues, one per language if you answer in several. Exactly one is the **Default**, which is what gets used automatically — so anyone who only wants a single signature never sees a choice, and picking a different one stays deliberate rather than a decision on every reply. Signatures are **per analyst and nothing else**: there is no shared or install-wide signature to administer, and nobody can see or change anybody else's. A **My details** section adds job title, department, phone and mobile to your account so a signature has something to merge, and a code you have no value for is removed rather than left showing at the bottom of your emails. Open a reply and your default signature is already in the editor with a blank line above it — **placed there visibly rather than added when you press Send**, so you can change or remove it for one email and what you see is what the customer gets. A **Signature** button beside Templates swaps it for another or takes it out.
- **Mailboxes say where their tickets came from, and tell you when something is off** — each mailbox carries a **Default ticket origin**, so tickets it opens are recorded as having come from Email, or from Monitoring, or from whatever you call it. It is set per mailbox rather than once for all email, because a helpdesk address and an alerting address both arrive as email and are not the same source. Each mailbox also carries an **!** next to its name that lists everything quietly wrong with it — no origin set, reading the wrong inbox, never checked for mail, no folder chosen for imported mail, an IMAP mailbox that cannot send replies — because a mailbox can be connected, green and collecting mail and still not be doing what you assume. Any warning can be **dismissed** where it is something you meant, which clears the mark but keeps the item listed with a Restore beside it; errors cannot be dismissed, since reading the wrong inbox is a fault rather than a preference.
- **Raise a ticket for someone in a few keystrokes** — search the requester by name or email and pick them, with their company shown so similar names are easy to tell apart. Anyone genuinely new can still be added inline without leaving the form.
- **Ticket list you can tune** — show priority, status and the assigned agent on each row in the inbox, each independently, as a left-edge colour bar, a corner block, a pill with the word, a dot, or initials. It is a per-analyst preference with an install-wide default, set at **Tickets → Settings → Row display** with a live preview.
- **Command palette** — press **⌘K / Ctrl-K** anywhere to jump to any module, search across tickets, changes, problems, knowledge, contracts, assets and CMDB items by name or reference, or run a quick action, all from the keyboard. Results respect your module access and active company.
- **Internationalisation** — [24 languages](https://github.com/edmozley/freeitsm/wiki/Internationalisation) with per-analyst locale, plus [per-analyst timezones](https://github.com/edmozley/freeitsm/wiki/Timezones-and-Time-Handling), [theming and dark mode](https://github.com/edmozley/freeitsm/wiki/Theming-and-Dark-Mode), and a [mobile-friendly](https://github.com/edmozley/freeitsm/wiki/Mobile-Friendly) core flow — the [ticket inbox](https://github.com/edmozley/freeitsm/wiki/Mobile-Friendly-Tickets), [Assets](https://github.com/edmozley/freeitsm/wiki/Mobile-Friendly-Assets), the [Calendar](https://github.com/edmozley/freeitsm/wiki/Mobile-Friendly-Calendar), the [Knowledge Base](https://github.com/edmozley/freeitsm/wiki/Mobile-Friendly-Knowledge), [Service Status](https://github.com/edmozley/freeitsm/wiki/Mobile-Friendly-Service-Status) and the War Room are built to work properly on a phone, without changing anything on the desktop.

## Documentation

Everything lives in the **[Documentation Wiki](https://github.com/edmozley/freeitsm/wiki)**:

| Guide | Covers |
|-------|--------|
| [Installation](https://github.com/edmozley/freeitsm/wiki/Installation) | Docker and manual setup, prerequisites, configuration files |
| [Architecture](https://github.com/edmozley/freeitsm/wiki/Architecture) | Technology stack, directory layout, shared components, database conventions |
| [Security](https://github.com/edmozley/freeitsm/wiki/Security) | Authentication, authorisation layers, encryption, going-live checklist |
| [REST API](https://github.com/edmozley/freeitsm/wiki/REST-API) | How the public API works, plus per-module endpoint guides |
| [API Reference](https://github.com/edmozley/freeitsm/wiki/API-Reference) | The internal session-based endpoints behind the UI |

There are also long-form **[deep-dive articles](https://freeitsm.co.uk/deep-dive/)** on the website covering individual features, and a **[release history](https://freeitsm.co.uk/updates.php)**.

**Technology stack:** PHP 7.4–8.4 · MySQL 8.0+ · vanilla JavaScript (no frameworks) · TinyMCE · Apache, or nginx using the [config it ships with](https://github.com/edmozley/freeitsm/wiki/Running-on-nginx).

## 👋 From the maintainer

FreeITSM is a one-developer project — your engagement is what keeps it moving:

- ⭐ **If you use FreeITSM, please [star the repo](https://github.com/edmozley/freeitsm/stargazers)** — it's the single biggest signal that the work is landing.
- 📬 **Feedback, ideas, bugs?** Email me directly at [ed@freeitsm.co.uk](mailto:ed@freeitsm.co.uk) — I read every message — or use [Discussions](https://github.com/edmozley/freeitsm/discussions) and [Issues](https://github.com/edmozley/freeitsm/issues).
- 🔒 **Found a security problem?** Please report it privately rather than in an issue — see **[SECURITY.md](SECURITY.md)**. Every FreeITSM install is self-hosted, so operators need a chance to upgrade before anything is public.
- 🌍 Mentioning [freeitsm.co.uk](https://freeitsm.co.uk) on Reddit, Hacker News, Spiceworks or LinkedIn genuinely helps and means a lot.

Contributions are welcome — the first external pull request was merged in 2026 and more are encouraged.

## License

[MIT](LICENSE) — free for commercial and personal use.
