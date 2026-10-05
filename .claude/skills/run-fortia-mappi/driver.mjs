// Headless Chromium driver for the Mappi console/respondent UI. Runs INSIDE the compose `e2e` image (drive.sh
// starts it). The pages bake absolute http://localhost:8080 URLs (APP_URL), which inside the container is nothing:
// the browser keeps using localhost:8080 as its origin and every request is re-sent to http://nginx. Each argv word pair is a step, executed in order:
//
//   login <email> [password]     sign in on /console/login (default password: password123)
//   goto <path>                  open a path (e.g. /console/questionnaires, /a/<assignationId>)
//   click <text>                 click the button/link/tab with that accessible name (falls back to any text)
//   fill <label> <value>         type into the field with that label
//   press <key>                  keyboard key (Enter, Escape…)
//   wait <ms|text>               sleep ms, or wait until the text is visible
//   ss <name>                    full-page screenshot → backend/var/run-shots/<name>.png
//   text                         print the page's visible text (first 3000 chars)
//   eval <js>                    evaluate an expression in the page, print the JSON result
//
// Console errors, failed requests (>=400) and page errors are printed as they happen.
import {createRequire} from 'node:module';
import {mkdirSync} from 'node:fs';

const {chromium} = createRequire('/pw/')('playwright');
const BASE = process.env.BASE_URL || 'http://localhost:8080';
const UPSTREAM = process.env.UPSTREAM_URL || 'http://nginx';
const SHOTS = '/app/var/run-shots';
mkdirSync(SHOTS, {recursive: true});

const ARITY = {login: 1, goto: 1, click: 1, fill: 2, press: 1, wait: 1, ss: 1, text: 0, eval: 1};
const args = process.argv.slice(2);
const steps = [];
for (let i = 0; i < args.length; ) {
  const op = args[i++];
  if (!(op in ARITY)) throw new Error(`unknown step "${op}" (expected: ${Object.keys(ARITY).join(', ')})`);
  const params = args.slice(i, i + ARITY[op]);
  i += ARITY[op];
  // login takes an optional password: consume it when the next word isn't a step name.
  if (op === 'login' && i < args.length && !(args[i] in ARITY)) params.push(args[i++]);
  steps.push([op, params]);
}

const browser = await chromium.launch();
const context = await browser.newContext({viewport: {width: 1440, height: 900}, locale: 'en-US'});
// route.continue({url}) to another host fails with ERR_BLOCKED_BY_CLIENT: fetch it ourselves and fulfill.
await context.route(`${BASE}/**`, async (route) => {
  try {
    const response = await route.fetch({url: UPSTREAM + route.request().url().slice(BASE.length), maxRedirects: 0, timeout: 130000});
    await route.fulfill({response});
  } catch (e) {
    await route.abort().catch(() => {});
  }
});
// The Symfony web debug toolbar (fixed, bottom) covers the console's footer controls: hide it.
await context.addInitScript(() => {
  addEventListener('DOMContentLoaded', () => {
    const style = document.createElement('style');
    style.textContent = '.sf-toolbar, .sf-minitoolbar { display: none !important; }';
    document.head.append(style);
  });
});
const page = await context.newPage();
page.setDefaultTimeout(Number(process.env.STEP_TIMEOUT_MS || 60000)); // first request after a PHP change ~30 s
page.on('console', (m) => m.type() === 'error' && console.log(`  [console.error] ${m.text()}`));
page.on('pageerror', (e) => console.log(`  [pageerror] ${e.message}`));
page.on('response', (r) => r.status() >= 400 && console.log(`  [http ${r.status()}] ${r.request().method()} ${r.url()}`));

const byName = (text) =>
  page.getByRole('button', {name: text, exact: true})
    .or(page.getByRole('link', {name: text, exact: true}))
    .or(page.getByRole('tab', {name: text, exact: true}))
    .or(page.getByText(text, {exact: true}))
    .first();

let code = 0;
try {
  for (const [op, p] of steps) {
    console.log(`> ${op} ${p.join(' ')}`);
    switch (op) {
      case 'login':
        await page.goto(`${BASE}/console/login`);
        await page.getByLabel('Email').fill(p[0]);
        await page.getByLabel('Password').fill(p[1] || 'password123');
        await page.getByRole('button', {name: 'Sign in', exact: true}).click();
        await page.waitForURL((u) => !u.pathname.endsWith('/login'));
        await page.waitForLoadState('networkidle');
        console.log(`  now at ${page.url()}`);
        break;
      case 'goto':
        await page.goto(BASE + p[0]);
        await page.waitForLoadState('networkidle');
        break;
      case 'click':
        await byName(p[0]).click();
        await page.waitForLoadState('networkidle');
        break;
      case 'fill':
        await page.getByLabel(p[0]).first().fill(p[1]);
        break;
      case 'press':
        await page.keyboard.press(p[0]);
        break;
      case 'wait':
        if (/^\d+$/.test(p[0])) await page.waitForTimeout(Number(p[0]));
        else await page.getByText(p[0]).first().waitFor();
        break;
      case 'ss': {
        const file = `${SHOTS}/${p[0]}.png`;
        await page.screenshot({path: file, fullPage: true});
        console.log(`  saved backend/var/run-shots/${p[0]}.png (${page.url()})`);
        break;
      }
      case 'text':
        console.log((await page.innerText('body')).slice(0, 3000));
        break;
      case 'eval':
        console.log(JSON.stringify(await page.evaluate(p[0]), null, 2));
        break;
    }
  }
} catch (e) {
  code = 1;
  console.log(`! ${e.message.split('\n')[0]}`);
  await page.screenshot({path: `${SHOTS}/_failure.png`, fullPage: true}).catch(() => {});
  console.log(`  failure screenshot: backend/var/run-shots/_failure.png (${page.url()})`);
}
await browser.close();
process.exit(code);
