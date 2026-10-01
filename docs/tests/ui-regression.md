# UI regression suite

The manual pass to run through the browser after the automated suites are green and before a release. Each case
is something a real user does, written as steps and what must happen. Run them **in order**: later cases use the
data earlier ones create.

Every new feature adds its cases here, in the section of the screen it lives on, in the same format. A case is
only worth adding if it would catch the feature breaking: name the button, the data and what you should see.
Case IDs are stable: a new case takes the next number in its section, and a removed case leaves a gap. While the
app is built in parallel items (`docs/pdr/prd-mappi.md`), each item writes only in its own ID range.

Each run is recorded as a new file in [`runs/`](runs/) with the result of every ID
(`python3 .claude/skills/symfony-react-app/scripts/new-run.py` creates it).

## Before you start

**Start from known data, not from whatever the last run left.** Everything runs in Docker:

```bash
scripts/stack.sh reset       # drops the dev database, migrates, seeds the demo data
scripts/stack.sh status      # every service up; URLs
docker compose logs --tail=5 node   # the last UI build compiled
```

- Open Mailpit (http://localhost:8025) next to the app: cases that send email end with "an email arrives".
- The first request after a code change takes ~30 s (the dev cache rebuilds); wait for it.
- Use one browser profile for the run and nothing else on the app's origin in it: tabs share one session.
- Have ready: a small PDF, a PNG screenshot (to paste), a CSV of members (`name,email,phone,role,area`).

**Accounts.** Dev only, never reuse these passwords. Password for all: `password123`.

| Role | Sign in with | Lands on |
|---|---|---|
| Platform admin (Admin) | `admin@mappi.test` | `/console/ai-experience`, customer selector in the sidebar |
| Account owner (root, Pro plan) | `owner@acme.test` | `/console/ai-experience` |
| Account admin (Customer-Admin) | `admin@acme.test` | `/console/ai-experience` |
| Read-only member | `reader@acme.test` | `/console/ai-experience` → redirected to Questionnaires (no write) |
| Another tenant (Starter plan) | `owner@globex.test` | `/console/ai-experience` |
| New account (onboarding pending) | `owner@newco.test` | `/console/onboarding` |

**On every screen, whatever the case says**, also check:

- The browser console has no errors, and nothing is a blank page.
- No text shows a raw translation key; switching the sidebar language to Español translates the whole screen.
- Tables, forms and buttons are the house components (`CLAUDE.md`); read-only users see disabled controls with a reason.
- A success or error message appears after every save, and it belongs to *that* save.
- Another tenant's data never appears; opening another tenant's URL gives "not found".

---

## 1. Public respondent app (no login) — RSP (item respondent-app), SES (sessions)

**PUB-01 · The root redirects to the marketing site**
Open `/`. **Expected:** the browser goes to the marketing site URL, and Back does not return to `/`.

<!-- RSP-01 – 45: respondent-app. SES-01 – 05: sessions. -->

The SES cases check the sessions API the respondent app runs on. Until the respondent screens exist (RSP) they are
run with `curl` against `http://localhost:8080/api/v1`; afterwards the same checks happen through `/q/{id}` and the
browser's network tab. `{Q}` is the id of an active questionnaire of `owner@acme.test` (create one in the console,
QST cases), `{D}` of a diagnostic one with tiers.

**SES-01 · A respondent starts, saves and submits a questionnaire**
`POST /questionnaire/{Q}/session`, then `PUT /questionnaire/session` with the returned body and a value in the
first question, then `POST /questionnaire/session` with the same body. **Expected:** the start answers the session
itself (no `{message, data}` envelope), `status` `filling` and `on_completed` only `{type}`; the save answers the
value back with `status` still `filling`; the submit answers `{type:"default"}` plus the flow's `cta`/`layout`/
`result_copy` if it has them. Submitting again answers the same, and the console's usage shows one more response.

**SES-02 · A diagnostic's results can be reloaded**
Submit a session of `{D}` with values in its scored questions, then open
`GET /questionnaire/session/{session_id}/results` twice. **Expected:** the submit answers `type` `diagnostic` with
`score {value, max}`, the categories and the reached tier's recommendations (or the lower tier's when it has none);
the results route answers the same diagnostic, the session's `customer_id` and the flow's texts. An unsubmitted
session's results are 404 `SESSION_RESULTS_NOT_FOUND`.

**SES-03 · A file answer gets a signed upload only for a live session**
`POST /signed-urls` with `{filename:"a.pdf", content_type:"application/pdf", customer_id:"<acme id>", session_id,
question_id}` of a session being filled. **Expected:** `{url, fields, key, expires_in:900}` with a key
`{customer_id}/{session_id}/{question_id}/{md5}.pdf`; posting the file with the fields to `url` stores it. The same
call with another `customer_id`, an unknown session or a submitted session is 404 `SESSION_NOT_FOUND`. Signed in as
`owner@acme.test`, `POST /answers-media/download-urls {key}` gives a download link; as `owner@globex.test` it is 403.

**SES-04 · An answer that misses its criteria costs a follow-up**
On a text question with `max_followups` 2 and acceptance criteria, `POST
/questionnaire/session/{id}/answers/{question_id}/evaluate` with the answer "Fine", then poll `GET /jobs/{job_id}`.
**Expected:** the job completes with `status` `not_sense`, an `improvement_message` and `max_followups` 1; a
five-word answer completes with `success` (dev runs on the fake model). `GET /transcription/token` answers a new
`token` each time, with `provider` `browser`.

**SES-05 · A quiz funnel recommends products from the catalog**
Submit a session of a quiz funnel questionnaire whose account has products. **Expected:** the submit answers 202
`{job}` of type `process_completed_session`; polling it ends `COMPLETED` with up to three products of that
catalog, and `GET /questionnaire/session/{id}/results` lists them in `products`. With no products in the catalog the
job completes with `products: []`.

## 2. Authentication — AUTH (item accounts)

**AUTH-00 · Sign in and out as the owner (item 0)**
Open `/console/login`, type `owner@acme.test` / `password123`, press Sign in. **Expected:** the console opens at
AI Experience with the sidebar; the account block shows the owner and the "Pro" badge. Logout → "Sign Out?" →
Sign out returns to `/console/login`, and Back does not show the console again.

<!-- AUTH-01 – 20: accounts. -->

## 3. Users and profile — USR (accounts), BIL (billing)

<!-- USR-01 – 10: accounts. BIL-01 – 20: billing. -->

## 4. Super-admin — ADM (admin)

<!-- ADM-01 – 15: admin. -->

## 5. Questionnaires — QST (authoring), EDT (editor), QF (commerce), GEN (generation), CHAT (chat), ONB (onboarding)

**QST-01 · The listing shows the account's questionnaires, ten per page**
Sign in as `owner@acme.test`, open Questionnaires. **Expected:** the header says "12 questionnaires"; ten rows, newest
first, each with its title, "N questions", type (Standard, Diagnostic, Chaining, Process mapping), an Active switch and
the creation date; the pager says "1–10 of 12" and page 2 shows the other two. "Globex product feedback" never appears.

**QST-02 · The page size changes and goes back to page 1**
On page 2, choose 20 per page. **Expected:** all twelve rows on one page, "1–12 of 12".

**QST-03 · Search runs on the server, every word, any case or accent**
Press Ctrl+K (the search box gets the focus) and type `CLIMA encuesta`. **Expected:** only "Encuesta de clima laboral".
Type `pulse 3` instead: only "Team pulse, week 3".

**QST-04 · A search that finds nothing says so and clears**
Search `zzz`. **Expected:** "Nothing matches "zzz"." with "Clear search"; pressing it empties the box and the twelve rows
come back.

**QST-05 · Type and state filters, and "No matches"**
Type: Diagnostic → only "AI maturity diagnostic". Type: All types, State: Inactive → only "Onboarding feedback". Type:
Quiz funnel with State: Active → "No matches" with "Clear filters", which resets every filter and the search.

**QST-06 · Sort by update, ascending or descending, and the date column follows**
Choose "Sort: Updated" and "Ascending". **Expected:** the date column header reads "Updated"; the oldest update comes
first. Switch back to "Sort: Created" / "Descending".

**QST-07 · Local or UTC dates, remembered**
Choose Time zone: UTC. **Expected:** the dates shift to UTC. Reload the page: UTC is still chosen.

**QST-08 · The Active switch saves at once and survives a reload**
Turn off "Team pulse, week 1". **Expected:** the switch flips immediately and reads "Inactive"; reload: still inactive;
State: Inactive lists it. Turn it back on.

**QST-09 · A failed toggle reverts**
In DevTools set the network offline, toggle any row. **Expected:** the switch flips back and a red message appears. Go
back online.

**QST-10 · Row actions**
On "Customer satisfaction survey": the eye opens `/f/acme-satisfaction` in a new tab; the copy icon says "Link copied"
and the clipboard holds that link; the pencil and the title open `/questionnaires/…/edit`; "Answers" opens its answers
page; "Analytics" (with the "New" badge) opens its dashboard.

**QST-11 · A read-only member only reads**
Sign in as `reader@acme.test`, open Questionnaires. **Expected:** "New Questionnaire", the Active switches and the pencils
are disabled and say "Your read-only role can't …" on hover; the title opens the public page in a new tab; View, Copy
link, Answers and Analytics still work.

**QST-12 · A brand-new account sees what the section is for**
Sign in as `owner@newco.test` (finish onboarding first if the console asks), open Questionnaires. **Expected:** "No questionnaires created yet / Create your first questionnaire to start collecting
responses." with "New Questionnaire" going to `/questionnaires/new`.

**QST-13 · The public flow opens by slug; an unknown one is "not found"**
Open `/api/v1/flow/acme-satisfaction`. **Expected:** JSON whose questionnaire state only carries `questionnaire_id`;
`/api/v1/flow/acme-ai-maturity` shows no tiers anywhere; `/api/v1/flow/nope` answers 404 `FLOW_NOT_FOUND`.

**QST-14 · Spanish**
Switch the sidebar language to Español on Questionnaires. **Expected:** "Cuestionarios", "12 cuestionarios", "Nuevo
cuestionario", the filters, columns, types ("Estándar", "Diagnóstico", "Encadenado") and empty states are all Spanish.

**QST-15 · A copy is named in the account's language**
Open a questionnaire that has answers (it is Locked, EDT-22) and press "Create a copy" → "Yes, create copy".
**Expected:** a new row "(copia) {title}" (Acme's language is Spanish; an English account gets "(copy) {title}") whose
link is `…/f/{slug}-copia`; copying again gives "(copia - 2) {title}". A diagnostic copy keeps its tiers, a chain its
prompts, and the copy has no answers.

**EDT-01 · The type picker**
As `owner@acme.test` press "New Questionnaire". **Expected:** "What do you want to create?" with four cards (Regular
(Default) "Best for surveys", Diagnostic "Best for assessments", Quiz Funnel "Imports from your store", Chaining "Best for
AI generation") and their PRD descriptions. As `owner@globex.test` (Starter), a type the plan lacks is disabled and says
"Your plan doesn't include this feature." (or the limit text when used up). `/design-experience` lands here.

**EDT-02 · The creation container**
Open Regular. **Expected:** breadcrumb "Questionnaires / New Regular", chip "Draft · saved when you create it", steps
Details → Questions → When it ends (2 and 3 disabled, "Complete the previous steps first." on hover), Preview, Back,
Continue disabled with "Write a title to continue." in its tooltip and in the list under the form.

**EDT-03 · Details: slug rules**
Type a title: the hint shows `…/f/{slug-of-title}`. Type `My Survey` as slug: "Lowercase letters, numbers and hyphens
only.". Type `acme-satisfaction` and create the questionnaire: back on Details with "That custom link (slug) is already
in use by another questionnaire. Choose a different one." under the field.

**EDT-04 · Details: landing and disclaimer**
Landing page is on for a new questionnaire. Turn Disclaimer on: Continue says "Write the disclaimer text to continue."
until its text is written.

**EDT-05 · Questions: add, duplicate, delete**
Add question, duplicate it (a copy right under it), delete one. The header of each card opens and closes it.

**EDT-06 · Questions: drag and drop, categories**
Give two questions categories A and B (type a new category, leave the field). **Expected:** they are grouped under A and B
headers. Drag a question of A (the grip, mouse or Space + arrows) onto a question of B: it moves into B. Drop onto the
B header area: same.

**EDT-07 · Input types and their fields**
Switch a question through every input type. **Expected:** option types show choices; the two "with score" types show a
score per choice; text shows Data type (Free with All/Letters/Numbers/Symbols, RFC, NIT, Phone / WhatsApp) and Maximum
follow-ups; above 0 follow-ups, Acceptance criteria (up to 10); range shows Min and Max; message and file show nothing
more.

**EDT-08 · Question validation messages**
Leave a title empty ("Question 1 must have a title."), remove every choice ("Question 1 needs at least one answer
choice."), empty a choice label, type a letter as a score, repeat a score, set range 5–5, set follow-ups 2 without
criteria. **Expected:** each PRD message appears in the list and in Continue's tooltip.

**EDT-09 · Live preview**
Press Preview. **Expected:** a phone frame with the landing (title, description, Start) and each question with its
choices; Desktop widens it; edits show at once. Under 1100 px width the preview is a side panel with a close button.

**EDT-10 · Regular: When it ends**
Thank-you message (Title/Message ≤ 300), Call to action (empty title → "The call to action title is required.", URL
`example.com` → "Enter a full URL starting with http:// or https://."), Capture data.

**EDT-11 · Create asks first; the success screen**
Create → "Create the questionnaire?" / "Nothing has been saved yet…" / "Yes, create". **Expected:** "Questionnaire created
successfully!" with its link, Copy link ("Link copied"), View questionnaire (new tab `/f/{slug}`), Keep editing (the
editor, "Editing · saved when you save changes"), Go to Questionnaires (the new row is first), Create another.

**EDT-12 · Diagnostic: categories and scores**
New Diagnostic. **Expected:** the input types exclude Dropdown and Message; a question without category lists "Every
question needs a category — your tiers are built from these categories."; only text questions → "Add at least one scored
question…"; Required is locked on for scored types (tooltip says why); "Max score" and each area's total update live.

**EDT-13 · Diagnostic: seeded tiers**
With one question scored 0/9, continue to Results. **Expected:** "Top score: 9" and Beginner 0–2, Intermediate 3–5,
Advanced 6–9; "Spread tiers evenly" re-seeds after the questions change.

**EDT-14 · Diagnostic: tier rules**
Empty a name ("Give every tier a name."), empty a "to", set from > to, start at 1, end below the top, leave a gap:
each shows its PRD message under the tiers.

**EDT-15 · Diagnostic: blocks and texts**
Toggle each block (Tier, Total score, Score by area, Recommendations, Action plan, PDF report, Call to action, Capture
data); Recommendations and Action plan show one field per tier; "Texts of the results page" has the 15 texts. Create:
"Your diagnostic is ready." Reopen: the tiers, recommendations, blocks and texts are back.

**EDT-16 · Chaining: prompts**
New Chaining. **Expected:** the timeline Starting point → Prompt 1 → Questionnaire 1; "Add prompt" up to 10 (then disabled
with "Up to 10 prompts."); an empty prompt → "Prompt N can't be empty."; Ending: Another questionnaire / Finish /
Diagnostic / Quiz funnel.

**EDT-17 · Chaining: saving uploads the prompts**
Create a chain with two prompts. **Expected (network tab):** `GET /customer/usage` (live `chain` check), two
`POST /signed-urls` with `upload_type: prompt` and two `PUT /storage/put`, then `POST /questionnaire`. Keep editing shows
both prompt texts and the ending.

**EDT-18 · Chain feature gone at save time**
(Admin: set Acme's `chain` limit to 0 while the editor is open.) Create. **Expected:** "Your plan doesn't include chaining
anymore, so this chain can't be saved." and nothing is created.

**EDT-19 · /:id/edit opens the right editor**
From the listing's pencil: a regular one → `/…/edit/regular`, "AI maturity diagnostic" → `/…/edit/diagnostic`, "Discovery
chain" → `/…/edit/prompt`, each with every step available.

**EDT-20 · Editing saves without asking**
Change a title and press Save changes on any step. **Expected:** no confirmation; "Changes saved"; Keep editing returns to
the editor; the listing shows the new title and update date.

**EDT-21 · The generic editor**
Pencil on "Process map: operations". **Expected:** one page (Details with the `…/f/acme-operations-map` preview,
Questions, Call to action, Capture data) and "Update"; saving shows "Changes saved" and keeps its type in the listing.

**EDT-22 · Locked**
Answer a questionnaire (open its link, answer one question), then open its editor and save (or open it once the answers
listing exists). **Expected:** "Locked" badge, "Locked to preserve answers", "Create a copy" → "Create a copy?" / "A new
questionnaire is created in your account, with no answers, ready to edit." / "Yes, create copy" → the copy's editor,
titled "(copia) …".

**EDT-23 · Read-only users**
As `reader@acme.test` open `/questionnaires/new` or any `/edit` URL. **Expected:** back to the listing.

**EDT-24 · Unknown kinds**
Open `/questionnaires/create/nope`. **Expected:** the type picker.

**EDT-25 · Spanish**
Switch to Español in the editor. **Expected:** every label, step, button, message and the success screen are Spanish
("Detalles", "Continuar", "¡Cuestionario creado con éxito!", "Bloqueado para conservar las respuestas").

<!-- QST-01 – 15: authoring. EDT-01 – 25: editor. QF-01 – 15: commerce. GEN-01 – 05: generation.
     CHAT-01 – 15: chat. ONB-01 – 10: onboarding. -->

## 6. Answers and dashboards — ANS, DSH (analytics)

<!-- ANS-01 – 15, DSH-01 – 10: analytics. -->

## 7. Organizations, assignations and projects — ORG, ASG, ARS, PRJ

**ORG-01 · The card grid**
As `owner@acme.test` open Organizations. **Expected:** one card per organization (Acme Retail, Acme Logistics; never
Globex Labs) with an initial on a colour that stays the same across reloads, the name (cut to 16 characters with "…"),
Active/Inactive, the domain ("No domain" when empty), "N members", and View, Edit, Delete.

**ORG-02 · Search and "filtered to nothing"**
Type `retail` in the search: only Acme Retail. Type `zzz`: "No matches" with "Clear filters", which brings every card
back.

**ORG-03 · Nothing at all**
As `owner@globex.test` delete Globex Labs (or on a fresh account). **Expected:** "No organizations yet. Create your first
one." with the "New Organization" button.

**ORG-04 · Read-only and plan gates**
As `reader@acme.test`: "New Organization", Edit and Delete are disabled with "Your read-only role can't create
resources." / "…can't make changes." on hover; View works; `/organizations/new` and `/:id/edit` redirect to the list.
As an account whose plan lacks organizations (or has used them up), "New Organization" is disabled with the plan text.

**ORG-05 · Create: required name**
Press "New Organization", then "Create organization" with nothing typed. **Expected:** "Name is required" under Name,
the toast "Check the highlighted fields before saving.", nothing is sent.

**ORG-06 · Create by hand**
Name `Acme  Manual Team`, domain `acme-manual.test`, "Add member" twice: `Ramón  Díaz` / ` Ramon@Gmail.com`, and
`Dup Person` with phone `+57 (300) 555-1212`, role `Driver`, area `Logistics`. Leaving each row normalizes it
("ramon diaz", "ramon@gmail.com", "+573005551212"). Create. **Expected:** "Organization created." and the detail page of
"Acme Manual Team" (single space) with both members; the card shows "2 members".

**ORG-07 · Member rules in the form**
Add a member with only a name and press save: "Each member needs at least an email or a phone". Type `ana@` as email:
"Enter a valid email". Type the same email (any case) in two rows: "This member is already in the list" at once, before
saving.

**ORG-08 · Domain warning**
With domain `acme-manual.test` and a member `x@gmail.com`: "1 member uses a domain other than acme-manual.test. They
will be saved anyway." Save works.

**ORG-09 · CSV import**
Download the template: `organization_members_template.csv` with `name,email,phone,role,area`. Import a `;` file with a
BOM and the header `nombre;correo;teléfono;cargo;área`, a quoted `"Ruiz, Carlos"`, a row with no email or phone, one with
`not-an-email`, one repeating an email already in the list and one without a name. **Expected:** "Imported 2 members",
"4 rows were skipped:" and one line per row, e.g. "Row 4 — Sin Correo: it has no email or phone", "Row 6 — Ramon Again:
already in the list", "Row 7 — (no name): the name is empty". The imported rows appear in the list, normalized.

**ORG-10 · CSV without the needed columns**
Import a file whose header has no name column: "The CSV must include a 'name' column". One with a name but neither
email nor phone: "The CSV must include at least an 'email' or 'phone' column". Nothing is added.

**ORG-11 · Edit: members keep their ids**
Edit Acme Manual Team: change a member's role, remove another with its trash button, "Save changes". **Expected:**
"Organization saved.", the detail shows the change and the removed member is gone; an assignation respondent who was
kept (ASG) still logs in (their id did not change).

**ORG-12 · Domain in use**
Edit Acme Retail and set the domain to `globex.test` (Globex's). **Expected:** the toast "Another organization already
uses that email domain." and the form stays as typed.

**ORG-13 · Errors point to the member row**
(Needs a value the form accepts and the API refuses, e.g. a 51-character phone pasted via dev tools.) **Expected:**
"Member {position} ({name}): {detail}" above the list.

**ORG-14 · Detail and delete**
View Acme Retail: status, domain, created and updated dates, description, and the members table (Name, Email, Phone,
Role, Area) with Edit. Back on the list, Delete Acme Manual Team: "Delete organization?" / "This will permanently delete
"Acme Manual Team" and cannot be undone." → Delete: "\"Acme Manual Team\" was deleted." and the card is gone. An
organization with assignations or projects is not deleted: "This organization has assignations or projects. Delete them
first, then delete the organization."

**ORG-15 · Español**
Switch the language to Español on the list, the form and the detail. **Expected:** "Organizaciones", "Nueva
organización", "Editar organización", "Miembros (4)", "Añadir miembro", "Importar CSV", "Descargar plantilla",
"Guardar cambios", the domain warning and the delete dialog, all in Spanish (member error texts from the API stay as the
API writes them).

**ASG-01 · The list**
As `owner@acme.test` (English) open Assignations. **Expected:** "8 assignations", newest first, 10 per page, never a
Globex row. Each row: the name, "Acme Retail" (link to the organization) and the questionnaire (link to its editor), the
audience chip ("Everybody", "Area: Sales"), Type, progress ("2 of 4 people" with a bar for Customer service survey,
"4 of 4 questions" or "Completed"), Created, the Active switch and View / Edit / Copy link / Send reminder / Delete.

**ASG-02 · Type tabs**
Default: only Customer service survey, without the Type column. Follow-up: the follow-ups with a Due column ("Monthly
store report": its date and "in 5 days"; "Safety audit": "overdue by 3 days" in red). A type with no rows: "No
assignations of this type" with "Clear filters", which brings All back.

**ASG-03 · Copy link and Active**
Copy link: toast "Link copied" and the clipboard holds `http://localhost:8080/a/{id}`. Switch Active off on a row:
toast ""{name}" is inactive"; reload: it stays off. Switch it back on.

**ASG-04 · Send reminder from the list**
On Monthly store report press the bell: "Send the reminder now?" / "An email with the link to "Monthly store report"
will be sent to the people who answer it." → "Send reminder": toast "Reminder sent to 2 recipients". Mailpit: one
email each to maria@ and juan@acme-retail.test ("«Monthly store report» vence en 5 días", Spanish: the account's
language) and the status email to owner@acme.test. The bell of a completed follow-up is disabled with "It is complete:
there is nothing to remind"; a default assignation has no bell.

**ASG-05 · Delete**
Create a throwaway assignation (ASG-08), then Delete it: "Delete assignation?" / ""X" will be deleted and its link will
stop working. The answers already given are kept." → Delete: toast ""X" was deleted" and the row is gone.

**ASG-06 · Read-only**
As `reader@acme.test`: "New assignation", Edit, Delete, the bell and the Active switch are disabled with "Your read-only
role can't make changes." (create: "…can't create resources."); View and Copy link work; `/assignations/new` and
`/:id/edit` redirect to the list; the questionnaire line is plain text.

**ASG-07 · Basic step validations**
"New assignation" → Next with nothing chosen. **Expected:** "Organization is required", "Questionnaire is required",
"Name is required" and the toast "Check the highlighted fields before continuing."; "Who responds?" is disabled with
"Choose an organization first.".

**ASG-08 · Create a default assignation**
Type Default; organization: type `retail` in its search (only Acme Retail), pick it ("3 people will respond"… the live
counter shows the audience size); questionnaire: type in the search (results update ~300 ms after typing) and pick one
not yet assigned; Name `Q4 store survey`; Next → Registration (Full name fixed, Email visible and required) → "Create
assignation". **Expected:** "Assignation created!", the link with Copy, and "Go to Assignations"; the list shows it.

**ASG-09 · Who responds**
In the form: People lists the members with checkboxes and a search by name, email, area or role; Area lists "Sales (2
people)", "Operations (1 person)", "Logistics (1 person)"; Role likewise. Checking values updates "N people will
respond". Choosing People with nothing checked and Next: "Check at least one person, area or role, or choose
Everybody". Changing the organization resets the audience to Everybody.

**ASG-10 · Follow-up and due date**
Type Follow-up: the due date field appears with its hint ("The day this follow-up should be completed…"). A date in
the past is accepted. Saving a follow-up without a date works (no due date).

**ASG-11 · Registration step**
Uncheck "Email is required": "At least one of email or phone must be required" (and pressing Create shows it as a
toast). Checking "Phone is required" also makes Phone visible and clears the error. Unchecking a field's Visible
unchecks its Required.

**ASG-12 · A questionnaire of another organization**
In the form choose Acme Logistics and the questionnaire of "Customer service survey" (assigned to Acme Retail).
**Expected:** "This questionnaire is already assigned to "Acme Retail". A copy of the questionnaire will be created and
the copy will be assigned instead." Create: the new assignation points to a copy ("(copia) …") in Questionnaires.

**ASG-13 · Edit**
Edit Customer service survey: the type is not offered; the fields come filled (organization, audience, questionnaire,
name, registration). Change the name → Save changes: "Assignation updated!" and the list shows the new name. Editing a
follow-up and clearing its due date removes it.

**ASG-14 · Organization of an assignation in a project**
Edit "Store opening checklist" (in the project Store opening Q4) and choose another organization → Save: toast "This
assignation belongs to a project, so its organization can't change."

**ASG-15 · Plan gate**
On an account whose plan lacks assignations (or used them all) "New assignation" is disabled with "Your plan doesn't
include assignations." and `/assignations/new` redirects to the list.

**ASG-16 · Default detail**
View Customer service survey. **Expected:** "← Assignations", the name, "Acme Retail · 4 of 4 people · 2 completed · 2
pending", Default badge and the audience chip, Copy link, Edit, Export CSV; "Completed (2)" (María, Juan) and "Pending
(2)" (Lucía In progress, Pedro Pending), columns Name, Email, Attempts, Status and "View answers" (to
/console/questionnaires/{id}/answers/{session}). The chevron next to Attempts opens the attempt history.

**ASG-17 · Search and CSV**
Type `juan`: only Juan, "Completed (1)" and "Pending (0)"; `zzz`: "No respondent matches" with "Clear search". Export
CSV downloads `Customer service survey.csv` with Name, Email, Attempts, Status and one row per member.

**ASG-18 · Follow-up in review**
View Inventory count. **Expected:** Follow-up badge, "Everybody", "In review", "No due date"; subtitle "Acme Retail ·
Every question is answered"; the notice "Review every answer … · 4 left"; the table #, Question, Answer, Answered,
Review ("Not reviewed") and "View answer"; "Who can carry it on (4)" with the members; "Send for correction" disabled
with "Reject at least one answer, and review them all, to send it for correction."

**ASG-19 · Reviewing**
"View answer" on question 1: dialog "Question 1 of 4" with the answer, Comment, Reject / Approve and the arrows.
Approve: toast "Review saved" and the dialog jumps to question 2. Reject question 2 with a comment, approve the rest:
"Every answer of this attempt is reviewed". The page shows "Changes requested", "You rejected 1 answer — …" and "Send
for correction" enabled.

**ASG-20 · Send for correction**
On Safety audit press "Send for correction": the dialog lists "2. What needs to be fixed, and by when?" with "Add a
photo of each fire extinguisher." → confirm: toast "Attempt 2 sent to 3 recipients"; Mailpit has the correction emails
(«Safety audit» necesita correcciones). The page shows attempt 2 open, "On question 2 of 4", the approved answers as
"Approved before" and "Send reminder" again.

**ASG-21 · Attempts**
On Safety audit choose "Attempt 1" in the attempt selector: the URL gets `?attempt=1`, "This is a previous attempt: it
can only be read.", and the review dialog has no Approve/Reject.

**ASG-22 · Follow-up nobody opened**
View Monthly store report: "Nobody has opened the follow-up yet", "in 5 days", "Send reminder" (confirm → "Reminder
sent to 2 recipients"), "There are no answers in this attempt yet." and "Who can carry it on (2)".

**ASG-23 · Not found**
Open /console/assignations/{a Globex id} as Acme, or a made-up id: "This assignation does not exist." with "Back to
Assignations".

**ASG-24 · Daily reminders**
`docker compose exec php php bin/console app:assignations:send-reminders`. **Expected:** "Sent N reminder(s)…" for the
open follow-ups not reminded today (UTC); running it again the same day sends 0. Mailpit: the respondents' reminders
and the owner's status.

**ASG-25 · Respondent login (API, until ARS)**
`POST /api/v1/assignations/{Monthly store report}/sessions` with `{"name":"x","email":"maria@acme-retail.test"}`: 200
with `token` (rt.…), `questionnaire` and `flow`; with Lucía's email: 403 NOT_IN_AUDIENCE (area Sales only); with an
unknown email: 403 USER_NOT_FOUND; with neither email nor phone: 400 MISSING_IDENTIFIER.

**ASG-26 · Español**
Switch to Español on the list, the form and both details. **Expected:** "Asignaciones", "Nueva asignación", tabs
"Todas, Estándar, Seguimiento", "2 de 4 personas", "vencido hace 3 días", "¿Enviar el recordatorio ahora?", "¿Quién
responde?", "Responderán N personas", "Registro", "En revisión", "Quién puede continuarlo (4)", "Enviar a corrección"…
no raw translation keys.

<!-- ASG-27 – 30: free. -->

**PRJ-01 · The list**
As `owner@acme.test` (language English) open Projects. **Expected:** "2 projects", newest first: "Supplier audit" and
"Store opening Q4", each with "AR" on a colour, the name, "Acme Retail · created {date}". Supplier audit: "Overdue",
"0 of 1 approved" with an empty bar, its date (5 days before seeding, UTC) with "overdue by N days" (red), next step "Open overdue". Store opening
Q4: "Needs your review", "0 of 2 approved" with the bar at 75%, its date (30 days after seeding) with "in N days", next step
"Review answers". Never a Globex project.

**PRJ-02 · Tabs**
To review: only Store opening Q4. Overdue: only Supplier audit. In progress, In correction, Completed: "No projects
match" with "Clear filters", which brings back All and both rows.

**PRJ-03 · Search**
Type `supplier` (the list updates ~300 ms after you stop typing): only Supplier audit. Type `retail`: both (the
organization's name is searched too). Type `zzz`: "No projects match" → "Clear filters" empties the search.

**PRJ-04 · Expanded row**
Click the chevron of Store opening Q4. **Expected:** a table with "Store opening checklist" · "Question 3 of 4" · "Not
complete yet" · "In progress" · Open, and "Visual merchandising review" · "4 of 4 questions" · "1 of 4 reviewed" ·
"Needs your review" · Review (primary). Review/Open go to /console/assignations/{id}. The chevron's name says
Show/Hide the assignations of the project.

**PRJ-05 · Next step**
"Review answers" goes to the assignation in review; "Open overdue" to Supplier compliance. A project without
assignations shows "No assignations" and "Add assignations", which opens the edit dialog.

**PRJ-06 · State legend**
Under the table: "What the states mean" with Not started, In progress, Needs your review, In correction, Completed,
Overdue and No assignations, each with one line.

**PRJ-07 · Edit dialog**
⋯ → Edit on Store opening Q4. **Expected:** Name, Organization "Acme Retail" read-only with "A project's organization
can't change.", Description, Deadline, and Assignations with Store opening checklist and Visual merchandising review
checked and Staff training plan unchecked (Supplier compliance is not offered: it is in another project). Clear the
deadline and Save changes: "Choose a deadline for the project". Clear the name: "Name is required".

**PRJ-08 · Saving the edit**
Set a new deadline and check Staff training plan → Save changes: toast "Project "Store opening Q4" updated", the row
shows "0 of 3 approved" and the new date. Edit again, uncheck Staff training plan and put the deadline back: 2 again.

**PRJ-09 · Delete confirmation**
⋯ → Delete on Supplier audit: "Delete this project?" / ""Supplier audit" will be deleted. Its assignations and their
answers are kept; they just stop belonging to a project." Cancel keeps it. (Deleting it for real unlinks Supplier
compliance, which then shows up in Store opening Q4's edit dialog.)

**PRJ-10 · Read-only**
As `reader@acme.test`: "New project" is disabled with "Your read-only role can't create resources."; in ⋯, Edit and
Delete are disabled with "Your read-only role can't make changes."; expand still works.

**PRJ-11 · New project**
As the owner, "New project" links to /console/projects/new. On an account whose plan lacks assignations it is disabled
with "Your plan doesn't include assignations.".

**PRJ-12 · Español**
Switch to Español. **Expected:** "Proyectos", "Nuevo proyecto", tabs "Todos, Por revisar, En progreso, En corrección,
Vencidos, Completados", "Requiere tu revisión", "0 de 2 aprobadas", "en N días" / "vencido hace N días", "Revisar
respuestas", "Pregunta 3 de 4", the legend, "¿Eliminar este proyecto?" and the edit dialog, all in Spanish; no raw
translation keys.

<!-- ORG-01 – 15: organizations. ASG-01 – 30: assignations. ARS-01 – 15: assignation-respondent.
     PRJ-01 – 12: projects. PRJ-13 – 20: project-wizard. -->

## 8. Branding, integrations and documentation — BRD, INT, DOC

<!-- BRD-01 – 10: branding. INT-01 – 12: integrations. DOC-01 – 08: docs. -->

## Emails

**MAIL-01 · Every email is readable and links to a real screen**
After the run, open each email in Mailpit. **Expected:** no translation keys, the Mappi layout, the sender
`support@mappi.test`, the account's language, and every link opens a real screen.
