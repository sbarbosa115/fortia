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

### Respondent app — RSP (item respondent-app)

The RSP cases answer the seeded questionnaires of Acme as an anonymous respondent, in a private window (no console
session, empty local storage). `{S}` is `/f/acme-respondent-showcase` (one question per answer control, with a
disclaimer, a landing page, a voice question and the data capture), `{D}` is `/f/acme-ai-maturity` (a diagnostic with
two categories and three tiers), `{C}` is `/f/acme-discovery-chain` (a chain with one prompt stage). `/q/{id}` takes
the questionnaire id that `GET /api/v1/flow/{slug}` returns. Acme's account language is `es-CO`.

**RSP-01 · The respondent app shows a neutral skeleton, then the brand**
Open `{S}`. **Expected:** a light-grey page with a spinner first, then the respondent look (warm off-white
background, black buttons, terracotta eyebrows); without Acme styles the default theme stays. No console errors.

**RSP-02 · The account's language is the starting language**
Open `{S}` in a browser set to English. **Expected:** the texts are Spanish (Acme is `es-CO`): "Antes de empezar".

**RSP-03 · A language picked by hand wins for the visit**
On `{S}` press `EN` in the top-right switch, then reload. **Expected:** everything is English and stays English after
the reload; `<html lang>` is `en`. A new private window starts in Spanish again.

**RSP-04 · The disclaimer comes first and keeps its line breaks**
Open `{S}`. **Expected:** the "Before you start" dialog with the "Private" badge and the two disclaimer lines on
separate lines; "Not now, thanks" and "Accept and continue". Escape or the backdrop count as "Not now".

**RSP-05 · Declining the disclaimer closes or ends**
Press "Not now, thanks". **Expected:** the tab closes if the browser lets it; otherwise "You can now close this tab."
Reloading shows the disclaimer again.

**RSP-06 · Accepting the disclaimer lasts 24 h**
Press "Accept and continue", reload. **Expected:** the disclaimer does not come back (until "Start over" or 24 h).

**RSP-07 · The mic check appears before voice questions and can be skipped (D12)**
After accepting on `{S}`. **Expected:** "Let's test your microphone" with the sentence to read and the microphone
button; "Skip the mic check" goes on to the landing and the check does not come back after a reload.

**RSP-08 · The mic check hears the respondent**
On a machine with a microphone (Chrome), reach the mic check, tap the microphone once, allow the permission, read the
sentence, tap the red stop button. **Expected:** "Preparing your microphone...", then red wave bars and
"Listening…", then "Your microphone works!" with "We heard" and the transcription; "Continue to the questionnaire".

**RSP-09 · The landing shows the title, description and count**
**Expected:** eyebrow "Get started", the serif title "Respondent showcase", "Every kind of answer, one per question.",
"Start questionnaire" and "12 questions".

**RSP-10 · The questions header shows progress**
Press "Start questionnaire". **Expected:** the monogram (or the brand logo), "Step 1 of 12" and "8%", a continuous
bar on a phone width and one dash per question on a wide screen; the URL hash is the question's id; eyebrow "01".

**RSP-11 · A message question only shows content**
On question 01. **Expected:** title and description, no control, "Next" enabled, "Back" disabled. Press Enter
outside any field: it moves to question 02.

**RSP-12 · Radio cards have letters and unlock Next**
Question 02 "Which plan do you use?". **Expected:** cards A Starter, B Pro, C Enterprise; "Next" disabled until one is
picked; arrow keys move between them.

**RSP-13 · An exclusive checkbox clears the others**
Question 03: check Email and Chat, then "None of the above", then Email. **Expected:** "None" alone after the third
click, Email alone after the fourth; "Next" disabled with nothing checked.

**RSP-14 · A select starts on "Select an option"**
Question 04. **Expected:** the dropdown shows "Select an option"; choosing Mexico enables "Next".

**RSP-15 · The slider's starting position is not an answer**
Question 05. **Expected:** the thumb starts at 5 but the value reads "–" with "Move or tap the slider to answer" and
"Next" disabled; tapping or moving it shows the number and enables "Next".

**RSP-16 · A text format rule speaks while typing**
Question 06 "What is your first name?" (letters only): type `abc1`. **Expected:** "Only letters are allowed" in red
under the field and "Next" disabled; `Ana María` clears it; Enter moves on (no line break).

**RSP-17 · The email error shows when leaving the field**
Question 07: type `ana@acme`, click outside. **Expected:** "Enter a valid email address (e.g. name@example.com)."
appears only after leaving the field; `ana@acme.test` clears it.

**RSP-18 · An optional question can be skipped**
Question 08 (phone, optional). **Expected:** a "Skip" button next to "Next"; "Skip" moves on; required questions
have no "Skip".

**RSP-19 · The phone answer accepts an international number**
Go back to question 08, type `+57 (300) 123-4567`, leave the field. **Expected:** no error; `123` shows "Enter a valid
phone number: digits only, optional international prefix (e.g. +57)."

**RSP-20 · Ranking: the initial order is already an answer**
Question 09. **Expected:** Price, Quality, Support numbered 1–3, "Next" enabled at once; dragging (mouse or touch) or
the up/down arrows reorder them and the numbers follow.

**RSP-21 · An answer that misses its criteria asks for more (AI follow-up)**
Question 10 "What would you improve, and why?": type `Fine`, press Next. **Expected:** "One moment / We're reviewing
your answer" full screen, then the same question with "1 attempt left" and the improvement message; "Next" stays
disabled until the answer changes.

**RSP-22 · A complete answer passes the follow-up**
Add ` - I would improve the onboarding because new users get lost on the first day` and press Next. **Expected:** the
review screen, then question 11.

**RSP-23 · A file is uploaded with progress and saved by key**
Question 11: "Choose files" and pick a PDF. **Expected:** "Uploading... N%", then the file listed with "Remove
{name}"; "1 / 10 files"; "Next" enabled only when no file is still uploading.

**RSP-24 · A pasted screenshot is renamed**
On question 11, copy a screenshot to the clipboard and press Ctrl+V on the page. **Expected:** a file
`screenshot-YYYYMMDD-HHmmss.png` is added and uploads.

**RSP-25 · Files over the limits are refused with a reason**
Pick 12 files on question 11 (Acme allows 10). **Expected:** "2 files were not added: you can attach up to 10
files." and "You've reached the limit of 10 files. Remove one to add another."; a file over 500 MB: "That file is
larger than 500 MB. Please choose a smaller one."

**RSP-26 · A voice answer is recorded and transcribed**
Question 12, Chrome with a microphone: tap "Tap to record your answer", speak, tap stop. **Expected:** "Preparing
microphone...", red stop button, 9 moving bars, "Listening... 00:0N" and the live text; then "Recording 1" with the
text, "Remove recording", "Record again", "Add to my answer" and "Record again from scratch".

**RSP-27 · A blocked microphone explains how to unblock it**
Block the microphone for localhost in the site settings and tap record. **Expected:** "Microphone access is blocked",
the browser-permission explanation, the lock step, "Try again" and "We only use your microphone while you're
recording."

**RSP-28 · A respondent without a microphone can type the voice answer (D12)**
On question 12 press "Can't record? Type your answer instead", type `Busy but good week`, "Add to my answer".
**Expected:** "Recording 1" shows the text and "Next" is enabled.

**RSP-29 · The data capture asks for name, email and phone**
Press "Next" on the last question. **Expected:** "One last step", "Where should we send your results?", the three
required fields and the privacy note. "See my results" with empty fields shows the three errors; editing a field
clears its error; the phone field only keeps digits and a leading +.

**RSP-30 · Submitting opens the canonical results link**
Fill `Ana María`, `ana@acme.test`, `+57 300 123 4567`, press "See my results". **Expected:** "Processing", then
`/session/{id}/results` with "Your answers have been submitted successfully." and the thanks text. The session is
`completed` with the `user_data` (console answers, or the database).

**RSP-31 · Progress survives a reload, with the resume modal**
Answer `{S}` up to question 07 and reload. **Expected:** "Pick up where you left off", "Saved just now", "Question 7
of 12" with its percentage, "Continue" (back on question 07 with the answers kept) and "Start over" with "Your 6 saved
answers will be deleted"; no network request creates a new session on the reload.

**RSP-32 · Start over begins a new session**
In the resume modal press "Start over". **Expected:** the disclaimer and the mic check come back and the questions
are empty; a new `session_id` is created (network tab).

**RSP-33 · Going back from the results starts a new session**
After RSP-30 press the browser's Back. **Expected:** `{S}` starts again with nothing answered (no resume modal).

**RSP-34 · A submission that fails keeps the answers**
On the last question, set the browser offline (DevTools) and finish. **Expected:** back on the last question with
"We couldn't send your answers. Check your connection and try again." and the answers kept; online again, Finish
works.

**RSP-35 · A diagnostic shows its tier, scores and the lower tier's plan**
Open `{D}`, answer "Sometimes" and "Yes", finish. **Expected:** "Successfully completed", "Thank you so much for your
support", "Your level: Leader", "Overall score 11 / 14" with 79 %, "Score by area" Usage 57 % · 4 / 7 and Governance
100 % · 7 / 7, the recommendation "Share your playbook across teams." and the action plan "Pick a pilot this month."
(taken from the lower tier, never a higher one); "Full report in PDF" with "Download".

**RSP-36 · The results link can be reloaded and shared**
Reload the `/session/{id}/results` page of RSP-35, then open it in another private window. **Expected:** the same
diagnostic (fetched from `GET …/results`), in Acme's language.

**RSP-37 · The diagnostic PDF downloads**
Press "Download" on RSP-35. **Expected:** "Preparing your PDF…" then `YourResults.pdf` with the tier, the scores by
area, the recommendations and the action plan, the accent in the brand's primary colour.

**RSP-38 · The layout and the texts of a flow drive the diagnostic**
In the console set `{D}`'s results layout to score + tier and its title to `Tu resultado`, answer `{D}` again.
**Expected:** only the tier and the overall score show, under "Tu resultado"; no areas, recommendations or PDF.

**RSP-39 · A diagnostic with three or more areas draws the radar**
Create a diagnostic with three categories in the console and answer it. **Expected:** "Your category profile" with a
radar of the three areas (each as % of its maximum) and the legend "Your score (%)".

**RSP-40 · A chain shows its stage and generates the next one**
Open `{C}` and finish its first question. **Expected:** "Stage 1 of 2" in the header, then `/f/acme-discovery-chain/
generating` with "One moment / Preparing your next questions" and messages rotating every 3.5 s; on success the
generated questions load at `/f/acme-discovery-chain` with "Stage 2 of 2". Until the generation item ships
`POST /questionnaire/prompt`: "We couldn't prepare your next questions." with "Try again".

**RSP-41 · Unknown links say so**
Open `/q/00000000-0000-4000-8000-000000000000` and `/f/no-such-flow`. **Expected:** "This questionnaire does not
exist." with "Want to create this questionnaire?" (to the console), and "This flow could not be found."

**RSP-42 · The response limit is explained**
Use up Acme's responses (or an account at its limit) and open its questionnaire. **Expected:** "The response limit
has been reached."

**RSP-43 · The privacy page is plain and never branded**
Open `/privacy`. **Expected:** white page, "Legal", "Privacy Policy", "Last updated April 20, 2026", nine numbered
sections (Who we are … Changes), the support email as a mailto link and "© {year}. All rights reserved."; in Spanish
with the switch picked earlier.

**RSP-44 · A brand with styles recolours the respondent app**
Give Acme styles (console customization when built, or a `customer_styles` row: `body.background #ffffff`,
`button.primary.background #0055ff`, `logoUrl`), open `{S}`. **Expected:** white background, blue buttons and selected
cards, the logo instead of the monogram; an unsafe value (e.g. `url(javascript:…)`) is ignored.

**RSP-45 · The account's pixels fire, only when configured**
Set Acme's `pixel_id` (settings) and open `{S}`, then finish it. **Expected:** in the network tab, Meta's
`fbevents.js` loads and a `PageView` is sent for that pixel on each route, and a `Lead` on finishing; with no pixel id
and no global one nothing is loaded.

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
store report": its date and "in 5 days"; "Safety audit": its date and "completed" while it waits for review, "overdue by
3 days" in red once it is sent for correction, ASG-20). A type with no rows: "No
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

**ARS-01 · The login slide**
Open `/a/{Monthly store report}` (no saved token). **Expected:** the brand's logo and ES/EN switch, the assignation's name
as eyebrow, "Inicia sesión" / "Sign in", "Cuéntanos quién eres para responder.", one field per control of the
registration slide (Nombre completo*, Correo* by default; Teléfono, Cargo, Área when the assignation shows them, with
their placeholders), and "Continuar" disabled until every required field is filled.

**ARS-02 · Not in the audience**
Log in as `Lucía Fernández` / `lucia@acme-retail.test` (area Operations; the audience is Sales). **Expected:** "Esta
evaluación no está dirigida a ti. Si crees que es un error, contacta a quien te la envió." under the form; still on the
login.

**ARS-03 · Unknown member**
Log in with `nobody@acme-retail.test`. **Expected:** "No te encontramos en esta organización. Revisa el nombre y el
correo con los que te invitaron."

**ARS-04 · Log in by email (the name is not used to find you)**
On a follow-up of Acme Retail log in with any name and ` MARIA@acme-retail.test ` (spaces, capitals). **Expected:** the
first question opens (no resume modal, no disclaimer of the login); `localStorage['organization-user-token']` holds an
`rt.` token; the request body had the name lowercased without accents (`maria gomez` for "María  Gómez").

**ARS-05 · Log in by phone**
On an assignation whose registration shows Phone (required) log in as María with `300 111 2233`: "No te
encontramos…" (the member's phone has the country code). With `+57 (300) 111-22-33`: the questionnaire opens.

**ARS-06 · Resume without login**
Answer question 1, press Siguiente, reload. **Expected:** question 2 again, without the login and without "Retoma
donde lo dejaste".

**ARS-07 · Shared progress on another device**
In a private window log in to the same follow-up as Juan. **Expected:** he lands on the first question nobody answered
or skipped yet, with María's answers filled in.

**ARS-08 · Finish**
Answer every question and Finalizar. **Expected:** "Procesando", then `/session/{id}/results` (default results); the
token is gone from localStorage.

**ARS-09 · Completed: in review**
Open the follow-up's link again. **Expected:** amber hourglass, "Tus respuestas están en revisión" / "Si algo necesita
cambios, recibirás un correo con el enlace para corregirlo." — no login. Logging in elsewhere while it is complete
(409) shows the same screen.

**ARS-10 · Completed: approved and plain completed**
Approve every answer in the console. **Expected:** green check, "Tus respuestas fueron aprobadas" / "Gracias por
participar. No hay nada más que hacer.". A completed follow-up without reviews yet: "Este seguimiento ya se completó."
/ "No es necesario responderlo de nuevo.".

**ARS-11 · Retry: lands on the rejected question**
In the console reject question 2 with a comment, approve the rest, "Enviar a corrección". Open the link (the old
token is of attempt 1: the login shows). Log in. **Expected:** "Paso 2 de 4" with "Requiere corrección" and the
reviewer's comment, the answer empty.

**ARS-12 · Retry: locked answers**
Press Atrás. **Expected:** question 1 shows "Aprobada / Esta respuesta fue aprobada y no se puede cambiar." with the
previous answer, the field disabled, no "Omitir". Answer the rejected one and finish: "en revisión" again.

**ARS-13 · Default assignation**
On "Customer service survey" log in as Pedro: a new session; finishing opens the results; opening the link again shows
the login (a default assignation never shows the completed screen; each member can answer again).

**ARS-14 · Not found and limit**
`/a/{made-up uuid}`, a Globex-less id or an inactive assignation (switch Active off in the console): "Este cuestionario
no existe.". An account whose plan lacks assignations: "Se alcanzó el límite de respuestas.".

**ARS-15 · English**
Press EN on the login, the completed screens and the runner. **Expected:** "Sign in", "Full name", "Email" /
"you@email.com", "Phone" / "Your phone number", "Continue", "This assessment isn't addressed to you…", "Your answers
are being reviewed", "Your answers were approved", "Needs correction"… no raw keys; the choice stays on reload.

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

### Branding — BRD (item branding)

The BRD cases use `/console/customization`. Dev reads websites with the offline fake (`BRAND_EXTRACTOR=fake`): the same
host always gives the same palette and font, no logo is found, and any address containing `unreachable` fails like a
site that does not answer. `{S}` is the RSP showcase (`/f/acme-respondent-showcase`). These cases change Acme's brand:
run them after the RSP cases.

**BRD-01 · The screen loads the account's brand and a live preview**
Sign in as `owner@acme.test`, open Personalización. **Expected:** "URL del sitio web" with the hint "No te preocupes…",
"URL del logo" with a preview box ("Sin logo" when empty), "Fuente" with Inter, Roboto, Poppins, Montserrat, Playfair
Display and Lora, "Color de marca" with a colour picker, and on the right "Vista previa": a question screen. Without
saved styles the preview has the respondent look (off-white page, black button, Montserrat).

**BRD-02 · The preview follows every change before saving**
Type `#8249DF` as the brand colour, pick "Playfair Display", paste a logo URL (e.g. `https://www.python.org/static/img/python-logo.png`).
**Expected:** the preview's button and link turn violet with white text, the question title uses Playfair Display,
the logo shows in the logo box and in the preview header. A light colour such as `#FDE047` gives dark button text.

**BRD-03 · An invalid colour or URL blocks saving**
Type `#12` as the colour and `logo` as the logo URL, press Guardar. **Expected:** "Revisa los campos marcados." and the
two fields' messages; nothing is sent (no request in the network tab).

**BRD-04 · Reset only restores the defaults on screen**
Press Restablecer. **Expected:** toast "Valores predeterminados restablecidos"; logo empty, Montserrat, `#18181b`; the
website stays. Reload: the saved values come back (nothing was saved).

**BRD-05 · Saving without a website change merges the styles**
Leave the website as it is, set `#8249DF` and Poppins, press Guardar. **Expected:** "Guardando tus estilos…" under the
header, then "Estilos actualizados correctamente"; after a reload the form shows the saved values. Perfil → Plan y uso:
"styles" went up by one.

**BRD-06 · A new website is read and the styles are designed from it**
Type `https://acme-brand.example` as the website. **Expected:** the note "Al guardar, leeremos este sitio web…" appears.
Press Guardar: "Leyendo tu sitio web…" → "Eligiendo colores y fuentes…" → "Guardando tus estilos…", then the success
toast; the colour and font change to the site's palette (the logo and colour typed before were not sent).

**BRD-07 · An unreachable website fails without saving or counting**
Type `https://unreachable.example`, press Guardar. **Expected:** a red toast "No pudimos leer ese sitio web…"; after a
reload the old website and styles are still there and the "styles" usage did not change.

**BRD-08 · The respondent app shows the saved brand**
After BRD-05 or BRD-06, open `{S}` (or `/q/{id}`) in a private window. **Expected:** after the neutral skeleton, the
page uses the saved colours (buttons, links), the heading font and the logo (when one is saved); with no logo the
monogram shows. No console errors.

**BRD-09 · Read-only users and exhausted plans cannot save**
Sign in as `reader@acme.test` and open Personalización. **Expected:** every field disabled, Guardar and Restablecer
disabled with "Tu rol de solo lectura no puede hacer cambios." on hover. As `owner@globex.test` (Starter, 2 styles a
month) after two saves: Guardar is disabled with the plan-limit reason.

**BRD-10 · Each account only sees and changes its own brand**
As `owner@globex.test`, save `#059669`. **Expected:** Acme's Personalización and `{S}` are unchanged; Globex's website is
never shown on Acme's screen. Switching the sidebar language to English translates the whole screen (titles, hints,
stages, preview texts).

### Integrations — INT (item integrations)

**INT-01 · Three tabs**
As `owner@acme.test` open Integrations. **Expected:** the title, "API keys" selected, then "Webhooks" and "API
reference"; the arrow keys move between tabs. With no keys: "No API keys yet" with "Create API key".

**INT-02 · Create a key: required name and default expiration**
Press "Create API key", then "Create key" with nothing typed. **Expected:** "Name is required" under Name, nothing is
sent. Expiration shows "7 days" by default (options 7 / 30 / 60 / 90 days and Never).

**INT-03 · The key is shown once**
Name `CRM sync`, expiration "Never", "Create key". **Expected:** "API key created.", a dialog "Your new API key" with a
`QAIRE-` + 64 hex value, the warning that it can't be seen again, and "Copy" ("Copied to the clipboard."). Press "Done":
the row "CRM sync" shows Created = today, Expires "Never", Last used "Never"; the key itself is nowhere on the page.

**INT-04 · Use the key from outside**
Copy the curl of "List questionnaires" from "API reference", put the key in it and run it in a terminal. **Expected:**
`{"message":"OK","data":{"questionnaires":[…],"pagination":{…}}}` with Acme's questionnaires only (never Globex's).
Reload Integrations: "Last used" now shows the date and time. Plans: the API usage went up by one.

**INT-05 · Answers of a questionnaire, and another account's**
Run the "answers" curl with an Acme questionnaire that has responses: `sessions` newest first, each with
`answers: [{title, value, min?, max?}]`. With a Globex questionnaire id: `404 QUESTIONNAIRE_NOT_FOUND`.

**INT-06 · Revoke**
Press the bin icon of "CRM sync" → "Revoke API key?" → "Revoke". **Expected:** "API key revoked.", the row disappears;
the curl of INT-04 now answers `401 INVALID_API_KEY` (the same message as with no key at all).

**INT-07 · Read-only and plan gates**
As `reader@acme.test`: the keys and webhooks are listed, but "Create API key", "Add webhook", Revoke, Edit and Delete are
disabled with "Your read-only role can't create resources." / "…can't make changes." on hover. As `owner@globex.test`
(Starter plan, no API or webhooks): "Create API key" says "Your plan doesn't include the external API." and "Add
webhook" "Your plan doesn't include webhooks."

**INT-08 · Add a webhook: https only**
Webhooks tab → "Add webhook", type `http://example.com/hook`, "Add webhook". **Expected:** "The URL must start with
https://" under the field, nothing is sent. Event "Response completed" and method POST are fixed. Type
`https://webhook.site/<your id>` (or any https receiver you control) → "Webhook added." and the row with the URL,
"Response completed" and POST.

**INT-09 · A completed response is delivered, signed**
Answer and submit an Acme questionnaire in the respondent app. **Expected:** the receiver gets a POST with
`X-Signature: sha256=…`, `X-Event-Type: questionnaire.completed`, `X-Delivery-Id`, and the body
`{customer_id, event_type, questionnaire_id, data:{id, answers}}`. In Integrations, the list icon of the webhook opens
"Deliveries" with the row "Delivered", 1 attempt, "HTTP 200".

**INT-10 · A failing receiver is retried**
Edit the webhook to `https://httpstat.us/500` (or a receiver answering 500), submit another response. **Expected:**
"Deliveries" shows "Retrying", "HTTP 500" and the next retry about a minute later; after the minute
(`docker compose exec php php bin/console app:webhooks:retry`) attempts goes to 2 and the next retry is 5 minutes
later. After six failed attempts it shows "Failed".

**INT-11 · Edit and delete a webhook**
Edit → change the URL → "Save changes": "Webhook saved." and the new URL. Bin icon → "Delete webhook?" → "Delete":
"Webhook deleted." and "No webhooks yet" with "Add webhook".

**INT-12 · API reference and Spanish**
API reference shows the base URL of this deployment, both endpoints with GET, the X-API-Key header and the
pagination/errors notes; each block has "Copy". Switch the sidebar to Español: "Integraciones", "Claves de API",
"Referencia de la API", "¿Revocar la clave de API?", "Debe empezar por https://" — no raw translation keys.

## Emails

**MAIL-01 · Every email is readable and links to a real screen**
After the run, open each email in Mailpit. **Expected:** no translation keys, the Mappi layout, the sender
`support@mappi.test`, the account's language, and every link opens a real screen.
