/* Copyright 2026, Lime Technology. Licensed under GPL-2.0. */
// Run with @playwright/test available to Node (see tests/README.md).
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium, webkit, expect } = require('@playwright/test');

const assets = path.join(__dirname, '../emhttp/plugins/dynamix');
const browserType = process.env.BROWSER || 'chromium';
const tests = [];
function test(name, run) { tests.push({ name, run }); }

async function fixture(page) {
  await page.setContent(`<!doctype html><html><head></head><body>
    <form id="settings" onchange="window.counts.formInline++">
      <label>Time zone
        <select id="zone" name="timeZone" onchange="window.counts.inline++">
          <option value="America/Los_Angeles" selected>Los Angeles</option>
          <option value="Europe/Dublin">Dublin</option>
          <option value="America/New_York">New York</option>
          <option disabled value="disabled">Disabled option</option>
          <optgroup label="Unavailable" disabled><option>Disabled group</option></optgroup>
        </select>
      </label>
      <label for="other">Other</label><select id="other" name="other">
        <option value="a">Alpha</option><option value="b">Beta</option>
      </select>
      <select id="reference" onchange="window.referenceCounts.inline++">
        <option value="a">Alpha</option><option value="b">Beta</option>
      </select>
      <button type="reset">Reset</button>
    </form><button id="outside">Outside</button>
  </body></html>`);
  for (const file of ['jquery.ui.css', 'default-base.css', 'jquery.searchable-select.css']) {
    await page.addStyleTag({ path: path.join(assets, 'styles', file) });
  }
  await page.addScriptTag({ path: path.join(assets, 'javascript/dynamix.js') });
  await page.addScriptTag({ path: path.join(assets, 'javascript/jquery.searchable-select.js') });
  await page.evaluate(() => {
    window.counts = { dom: 0, jquery: 0, inline: 0, formDom: 0, formJquery: 0, formInline: 0 };
    window.referenceCounts = { dom: 0, jquery: 0, inline: 0 };
    zone.addEventListener('change', () => window.counts.dom++);
    $('#zone').on('change', () => window.counts.jquery++);
    settings.addEventListener('change', event => { if (event.target === zone) window.counts.formDom++; });
    $('#settings').on('change', '#zone', () => window.counts.formJquery++);
    reference.addEventListener('change', () => window.referenceCounts.dom++);
    $('#reference').on('change', () => window.referenceCounts.jquery++);
    $('#zone, #other').searchableSelect();
  });
}

const input = page => page.locator('#zone + .searchable-select input');
const toggle = page => page.locator('#zone + .searchable-select button');
const menu = page => page.locator('.searchable-select-options:visible');

async function chooseDublin(page) {
  await input(page).click();
  await input(page).fill('dUbLiN');
  await expect(menu(page).locator('.ui-menu-item')).toHaveCount(1);
  await menu(page).getByText('Dublin', { exact: true }).click();
  await expect(input(page)).toHaveAttribute('aria-expanded', 'false');
  assert.equal(await page.locator('#zone').inputValue(), 'Europe/Dublin');
}

test('focused field reopens after selection and Escape; open clicks keep the query', async page => {
  await chooseDublin(page);
  assert.equal(await input(page).evaluate(element => element === document.activeElement), true);
  await input(page).click();
  await menu(page).waitFor({ state: 'visible' });
  assert.equal(await input(page).getAttribute('aria-expanded'), 'true');
  assert.equal(await menu(page).locator('.ui-menu-item').count(), 3);
  await input(page).fill('new_york');
  await expect(menu(page).locator('.ui-menu-item')).toHaveCount(1);
  await menu(page).getByText('New York', { exact: true }).waitFor();
  await input(page).click();
  assert.equal(await menu(page).locator('.ui-menu-item').count(), 1);
  await input(page).press('Escape');
  assert.equal(await input(page).getAttribute('aria-expanded'), 'false');
  await input(page).click();
  await menu(page).waitFor({ state: 'visible' });
  assert.equal(await menu(page).locator('.ui-menu-item').count(), 3);
});

test('changed selection emits exactly one native, jQuery, inline and bubbling change', async page => {
  await page.locator('#reference').selectOption('b');
  assert.deepEqual(await page.evaluate(() => window.referenceCounts), { dom: 1, jquery: 1, inline: 1 });
  await chooseDublin(page);
  const expected = { dom: 1, jquery: 1, inline: 1, formDom: 1, formJquery: 1, formInline: 2 };
  // The native reference also bubbles through the form's inline listener.
  assert.deepEqual(await page.evaluate(() => window.counts), expected);
  await toggle(page).click();
  await menu(page).getByText('Dublin', { exact: true }).click();
  assert.deepEqual(await page.evaluate(() => window.counts), expected);
  assert.deepEqual(await page.evaluate(() => [...new FormData(settings).entries()]), [
    ['timeZone', 'Europe/Dublin'], ['other', 'a']
  ]);
});

test('search and dismissal do not change settings; keyboard selection does', async page => {
  await input(page).click();
  await input(page).fill('new_york');
  await expect(menu(page).locator('.ui-menu-item')).toHaveCount(1);
  await menu(page).getByText('New York', { exact: true }).waitFor();
  await page.locator('#outside').click();
  assert.equal(await input(page).inputValue(), 'Los Angeles');
  assert.equal(await page.evaluate(() => Object.values(window.counts).every(value => value === 0)), true);
  await input(page).click();
  await input(page).fill('Dublin');
  await expect(menu(page).locator('.ui-menu-item')).toHaveCount(1);
  await menu(page).getByText('Dublin', { exact: true }).waitFor();
  await input(page).press('ArrowDown');
  await input(page).press('Enter');
  assert.equal(await page.locator('#zone').inputValue(), 'Europe/Dublin');
  assert.equal(await page.evaluate(() => window.counts.dom), 1);
});

test('toggle inside wrapping label closes; external label and instances stay independent', async page => {
  await toggle(page).click();
  await menu(page).waitFor({ state: 'visible' });
  await toggle(page).click();
  assert.equal(await input(page).getAttribute('aria-expanded'), 'false');
  await page.locator('label[for=other]').click();
  assert.equal(await page.locator('#other + .searchable-select input').evaluate(element => element === document.activeElement), true);
  assert.equal(await input(page).getAttribute('aria-expanded'), 'false');
});

test('disable, reset, destroy and reinitialize preserve native ownership', async page => {
  await chooseDublin(page);
  await page.evaluate(() => $('#zone').searchableSelect('disable'));
  assert.equal(await input(page).isDisabled(), true);
  await page.getByRole('button', { name: 'Reset', exact: true }).click();
  await page.waitForFunction(() => $('#zone').searchableSelect('instance').input.val() === 'Los Angeles');
  await page.evaluate(() => $('#zone').searchableSelect('enable'));
  assert.equal(await input(page).isDisabled(), false);
  assert.equal(await page.evaluate(() => window.counts.dom), 1);
  await page.evaluate(() => $('#zone').searchableSelect('destroy'));
  assert.equal(await page.locator('#zone').isVisible(), true);
  await page.evaluate(() => $('#zone').searchableSelect().searchableSelect());
  assert.equal(await page.locator('#zone + .searchable-select').count(), 1);
  await chooseDublin(page);
  assert.equal(await page.evaluate(() => window.counts.dom), 2);
});

(async () => {
  assert.ok(['chromium', 'webkit'].includes(browserType), 'BROWSER must be chromium or webkit');
  const browser = await ({ chromium, webkit })[browserType].launch({ headless: true });
  let failures = 0;
  try {
    for (const { name, run } of tests) {
      const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
      page.setDefaultTimeout(4000);
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      try {
        await fixture(page);
        await run(page);
        assert.deepEqual(errors, []);
        console.log(`PASS ${browserType}: ${name}`);
      } catch (error) {
        failures++;
        console.error(`FAIL ${browserType}: ${name}\n${error.message}`);
      } finally { await page.close(); }
    }
  } finally { await browser.close(); }
  process.exitCode = failures ? 1 : 0;
})().catch(error => { console.error(error); process.exitCode = 1; });
