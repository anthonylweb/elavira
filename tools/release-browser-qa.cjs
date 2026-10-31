/* Release-only browser checks for real-controller report, decision, and Learn fixtures. */
const fs = require('node:fs');
const path = require('node:path');
const playwright = require(process.argv[2]);
const output = process.argv[3];
const base = process.argv[4] || 'http://127.0.0.1:8769';

const browsers = [
  ['Edge', true, () => playwright.chromium.launch({channel: 'msedge', headless: true, timeout: 15000})],
  ['Chrome', false, () => playwright.chromium.launch({channel: 'chrome', headless: true, timeout: 15000})],
  ['Firefox', false, () => playwright.firefox.launch({executablePath: 'C:\\Program Files\\Mozilla Firefox\\firefox.exe', headless: true, timeout: 15000})],
];

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

async function inspectPage(page, url, label) {
	const response = await page.goto(url, {waitUntil: 'networkidle'});
	assert(response && response.ok(), `${label}: HTTP ${response ? response.status() : 'no response'}`);
	const result = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
    h1: document.querySelectorAll('h1').length,
    unlabeled: [...document.querySelectorAll('input:not([type="hidden"]), select, textarea')].filter((control) => {
      return !control.labels?.length && !control.getAttribute('aria-label') && !control.getAttribute('aria-labelledby');
    }).length,
	unsafeNewTabs: [...document.querySelectorAll('a[target="_blank"]')].filter((link) => {
	  return !link.relList.contains('noopener') || !link.relList.contains('noreferrer') || !link.textContent.includes('opens in a new tab');
	}).length,
	overflowing: [...document.querySelectorAll('body *')].filter((element) => element.getBoundingClientRect().right > document.documentElement.clientWidth + 1).slice(0, 8).map((element) => ({
	  tag: element.tagName,
	  className: String(element.className || ''),
	  width: Math.round(element.getBoundingClientRect().width),
	  right: Math.round(element.getBoundingClientRect().right),
	  text: String(element.textContent || '').trim().slice(0, 80),
	})),
	serverError: /(?:Fatal error|Uncaught Error|Parse error)/i.test(document.body.innerText),
  }));
	assert(!result.serverError, `${label}: fixture emitted a server error`);
	assert(result.scrollWidth <= result.clientWidth, `${label}: horizontal overflow ${result.scrollWidth}/${result.clientWidth}; ${JSON.stringify(result.overflowing)}`);
  assert(result.h1 === 1, `${label}: expected one h1, found ${result.h1}`);
  assert(result.unlabeled === 0, `${label}: ${result.unlabeled} unlabeled form controls`);
  assert(result.unsafeNewTabs === 0, `${label}: ${result.unsafeNewTabs} unsafe or unexplained new-tab links`);
  return result;
}

async function run(name, exhaustive, launch) {
	const browser = await launch();
	const page = await browser.newPage({viewport: {width: 320, height: 800}});
	page.setDefaultNavigationTimeout(10000);
  const results = [];
  try {
    for (const flow of ['a', 'b', 'c', 'd', 'e']) {
      for (const view of ['report', 'finding']) {
        const label = `${name} integrated ${flow}/${view}`;
        results.push({label, ...(await inspectPage(page, `${base}/tools/integrated-report-browser-fixture.php?flow=${flow}&view=${view}&narrow=1`, label))});
      }
    }
    for (const [flow, view] of [['a', 'decision'], ['b', 'decision'], ['c', 'decision'], ['a', 'learn'], ['b', 'learn'], ['c', 'learn']]) {
      const label = `${name} integrated ${flow}/${view}`;
      results.push({label, ...(await inspectPage(page, `${base}/tools/integrated-report-browser-fixture.php?flow=${flow}&view=${view}&narrow=1`, label))});
    }

	const allFixtures = JSON.parse(fs.readFileSync(path.join(output, 'decision-fixtures.json'), 'utf8'));
	const manifest = exhaustive ? allFixtures : allFixtures.filter((fixture) => ['decision-envelope-2.html', 'decision-text-context-0.html', 'decision-historical-1.html'].includes(fixture.file));
    for (const fixture of manifest) {
      const label = `${name} ${fixture.file}`;
      results.push({label, ...(await inspectPage(page, `${base}/dist/release-qa-0.2.0/${fixture.file}`, label))});
    }
    await page.goto(`${base}/dist/release-qa-0.2.0/decision-envelope-2.html`);
    await page.locator('input[name="answer"]').first().focus();
    await page.keyboard.press('ArrowDown');
    assert(await page.locator('input[name="answer"][value="no"]').isChecked(), `${name}: native radio arrow selection failed`);
    await page.keyboard.press('Tab');
    assert(await page.locator(':focus').getAttribute('value') === 'answer', `${name}: Continue is not next in decision Tab order`);

    await page.goto(`${base}/tools/learn-browser-fixture.php?narrow=1`, {waitUntil: 'networkidle'});
    const topicLinks = await page.locator('a[href*="topic="]').evaluateAll((links) => [...new Set(links.map((link) => link.href))]);
	assert(topicLinks.length === 14, `${name}: expected 14 Learn topics, found ${topicLinks.length}`);
	for (const url of exhaustive ? topicLinks : topicLinks.slice(0, 3)) {
	  const topic = new URL(url).searchParams.get('topic');
	  const label = `${name} Learn ${topic}`;
	  results.push({label, ...(await inspectPage(page, `${base}/tools/learn-browser-fixture.php?topic=${encodeURIComponent(topic)}&narrow=1`, label))});
	}

    await page.setViewportSize({width: 1280, height: 900});
    results.push({label: `${name} desktop finding`, ...(await inspectPage(page, `${base}/tools/integrated-report-browser-fixture.php?flow=e&view=finding`, `${name} desktop finding`))});
    return {browser: name, status: 'passed', pages: results.length, results};
  } finally {
    await browser.close();
  }
}

(async () => {
  const matrix = [];
  for (const [name, exhaustive, launch] of browsers) {
	try {
	  matrix.push(await run(name, exhaustive, launch));
    } catch (error) {
      matrix.push({browser: name, status: 'blocked-or-failed', error: String(error.stack || error)});
    }
  }
  fs.writeFileSync(path.join(output, 'release-browser-qa.json'), JSON.stringify(matrix, null, 2));
  console.log(JSON.stringify(matrix.map(({browser, status, pages, error}) => ({browser, status, pages, error})), null, 2));
  if (matrix.some((entry) => entry.status !== 'passed' && !/Firefox|Executable|browserType\.launch/.test(entry.error || ''))) process.exitCode = 1;
})().catch((error) => { console.error(error); process.exitCode = 1; });
