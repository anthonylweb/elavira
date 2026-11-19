/* Development-only browser QA of real-controller fixture HTML. */
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.argv[2]);
const output = process.argv[3];
(async () => {
 const browser = await chromium.launch({channel: 'msedge', headless: true});
 const page = await browser.newPage({viewport: {width: 320, height: 800}});
 const fixtures = JSON.parse(fs.readFileSync(path.join(output, 'decision-fixtures.json'), 'utf8'));
 const results = [];
 for (const fixture of fixtures) {
  await page.goto('http://127.0.0.1:8769/' + fixture.file);
  const result = await page.evaluate(() => ({
   width: innerWidth, scroll: document.documentElement.scrollWidth,
   question: document.querySelector('legend')?.textContent,
   outcome: document.querySelector('#elavira-decision-result')?.parentElement.textContent,
   badHelpLinks: [...document.querySelectorAll('a[target="_blank"]')].filter(a => !a.textContent.includes('opens in a new tab') || a.rel !== 'noopener noreferrer').length,
   emptyHelp: [...document.querySelectorAll('#elavira-question-help')].filter(p => !p.textContent.trim()).length
  }));
  if (result.scroll > result.width || result.badHelpLinks || result.emptyHelp) throw Error(fixture.file + ': ' + JSON.stringify(result));
  results.push({...fixture, ...result});
 }
 await page.goto('http://127.0.0.1:8769/decision-envelope-2.html');
 await page.locator('input[name="answer"]').first().focus();
 await page.keyboard.press('ArrowDown');
 if (!await page.locator('input[name="answer"][value="no"]').isChecked()) throw Error('Native radio arrow selection failed');
 await page.keyboard.press('Tab');
 if (await page.locator(':focus').getAttribute('value') !== 'answer') throw Error('Continue not next in tab order');
 await page.screenshot({path: path.join(output, 'decision-image-link-320.png'), fullPage:true});
 await page.setViewportSize({width:1280,height:900});
 await page.screenshot({path:path.join(output,'decision-image-link-desktop.png'),fullPage:true});
 await page.goto('http://127.0.0.1:8769/decision-envelope-4.html');
 await page.screenshot({path:path.join(output,'decision-image-link-result.png'),fullPage:true});
 fs.writeFileSync(path.join(output, 'decision-browser-qa.json'), JSON.stringify(results,null,2));
 console.log(JSON.stringify({pages:results.length,cases:new Set(results.map(r=>r.case)).size,reflow320:'passed',nativeKeyboard:'passed',helpLinks:'passed'}));
 await browser.close();
})().catch(error => {console.error(error);process.exitCode=1;});
