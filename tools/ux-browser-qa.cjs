const {chromium}=require(process.argv[2]);
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:1000}});
 const base='http://127.0.0.1:8771/tools/ux-browser-fixture.php';
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const results=[];
 const form=id=>page.locator('form').filter({has:page.locator('#occurrence-status-'+id)});
 try {
 await page.goto(base+'?view=report&fresh=1');
 assert.equal(await page.locator('.elavira-report-actions').count(),2);
 const actions=await page.locator('.elavira-action-row').evaluateAll(rows=>rows.map(r=>r.innerHTML));assert.equal(actions[0],actions[1]);
 assert.equal(await page.locator('select[id^=occurrence-status-]').count(),3);
 await page.locator('#occurrence-status-1').selectOption('needs_correction');
 assert.equal(await page.locator('#occurrence-note-1').getAttribute('required'),'');
 await page.locator('#occurrence-note-1').fill('   ');
 await form(1).getByRole('button').click();
 assert.equal(await page.locator('#occurrence-note-1').getAttribute('aria-invalid'),'true');
 assert.match(await page.locator('#occurrence-note-1-error').innerText(),/Explain what needs correction/);
 assert.equal(await page.evaluate(()=>document.activeElement.id),'occurrence-note-1');
 // Bypass JS/native checks to exercise the actual POST controller and rejected draft redirect.
 await form(1).evaluate(f=>f.submit());
 await page.waitForURL('**elavira_notice=rejected*');
 assert.equal(await page.locator('#occurrence-status-1').inputValue(),'needs_correction');
 assert.equal(await page.locator('#occurrence-note-1').getAttribute('aria-invalid'),'true');
 assert.match(await page.locator('#occurrence-note-1-error').innerText(),/has not changed/);
 const note='Correct the link purpose.\n'+('LongNote'.repeat(65));
 await page.locator('#occurrence-note-1').fill(note);
 await form(1).getByRole('button').click();await page.waitForURL('**elavira_notice=updated*');
 await page.reload();assert.equal(await page.locator('#occurrence-status-1').inputValue(),'needs_correction');
 assert.equal(await page.locator('.elavira-latest-note p').innerText(),note);
 assert.equal(await page.locator('#occurrence-note-1').inputValue(),'');
 assert.equal(await page.locator('.elavira-activity-history ol, .elavira-activity-history li').count(),0);
 assert.match(await page.locator('.elavira-activity-history').last().innerText(),/Anthony|Elavira audit/);
 await page.goto(base+'?view=report');
 assert.match(await page.locator('.elavira-finding-meta').innerText(),/Needs Correction occurrences \(included in active\): 1/);
 assert.equal(await page.locator('#occurrence-status-2').inputValue(),'open');
 await page.locator('#occurrence-note-2').fill('Independent accepted note');await page.locator('#occurrence-status-2').selectOption('reviewed_not_issue');
 await form(2).getByRole('button').click();await page.waitForURL('**elavira_notice=updated*');
 await page.goto(base+'?view=report');
 assert.equal(await page.locator('details.elavira-accepted').getAttribute('open'),null);
 await page.locator('#occurrence-status-3').selectOption('addressed');await form(3).getByRole('button').click();await page.waitForURL('**elavira_notice=updated*');
 await page.goto(base+'?view=report');
 assert.deepEqual(await page.locator('.elavira-occurrences > li').evaluateAll(nodes=>nodes.map(n=>n.className)),['elavira-occurrence-active','elavira-occurrence-addressed','elavira-occurrence-accepted']);
 results.push('Correction client/server validation, persistence, counts, independent accepted and addressed review');
 const downloadResponse = await page.request.get(base+'?view=export');
 assert.equal(downloadResponse.status(),200);
 assert.match(downloadResponse.headers()['content-type'],/spreadsheetml/);
 fs.writeFileSync('dist/ux-qa/correction-report.xlsx',await downloadResponse.body());

 for(const width of [1440,800,320]) {
  await page.setViewportSize({width,height:1000});await page.goto(base+'?view=report');
  await page.locator('details.elavira-accepted summary').click();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`overflow ${width}`);
  const geometry=await page.locator('.elavira-review-row').first().evaluate(row=>[...row.children].map(n=>({x:n.getBoundingClientRect().x,y:n.getBoundingClientRect().y})));
  if(width>=800) assert.equal(geometry[0].y,geometry[1].y);else assert.ok(geometry[0].y<geometry[1].y);
  const duplicates=await page.locator('[id]').evaluateAll(nodes=>nodes.map(n=>n.id).filter((x,i,a)=>a.indexOf(x)!==i));assert.deepEqual(duplicates,[]);
  const missingLabels=await page.locator('select,textarea,input:not([type=hidden])').evaluateAll(nodes=>nodes.filter(n=>!n.labels.length).map(n=>n.id));assert.deepEqual(missingLabels,[]);
  await page.screenshot({path:`dist/ux-qa/report-${width}.png`,fullPage:true});
  await page.goto(base+'?view=learn');assert.equal(await page.getByRole('heading',{name:'How Elavira helps',exact:true}).count(),0);
  assert.equal(await page.getByRole('heading',{name:'Guided Decisions',exact:true}).count(),0);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await page.screenshot({path:`dist/ux-qa/learn-${width}.png`,fullPage:true});
 }
 results.push('1440/800/320 layouts, long notes, labels, no duplicate IDs, Learn width and removed placeholders');
 await page.setViewportSize({width:1440,height:1000});await page.goto(base+'?view=finding&occurrence=1');
 for(const a of await page.locator('a[target=_blank]').all()) {
  assert.ok(!(await a.innerText()).includes('opens in a new tab'));
  assert.ok(await a.getAttribute('aria-describedby'));
 }
 await page.locator('#occurrence-status-1').focus();await page.keyboard.press('Tab');assert.equal(await page.evaluate(()=>document.activeElement.id),'occurrence-note-1');
 assert.notEqual(await page.locator('#occurrence-note-1').evaluate(n=>getComputedStyle(n).outlineStyle),'none');
 await page.screenshot({path:'dist/ux-qa/finding.png',fullPage:true});
 await page.goto(base+'?view=report');
 await page.locator('.elavira-report-actions').first().getByRole('button',{name:'Reset for this page',exact:true}).click();
 await page.waitForURL('**elavira_notice=exceptions_reset*');
 assert.match(await page.locator('.notice-success').innerText(),/Historical reports and decisions were preserved/);
 await page.locator('.elavira-report-actions').last().getByRole('button',{name:'Reset all reviewed exceptions',exact:true}).click();
 await page.waitForURL('**elavira_notice=exceptions_reset*');
 await page.goto(base+'?view=finding&occurrence=2');
 assert.match(await page.locator('.elavira-activity-history').first().innerText(),/Independent accepted note/);
 results.push('Matching top/bottom action markup, XLSX download, both reset scopes preserve activity');
 await page.goto(base+'?view=finding&flow=f&fresh=1');
 assert.equal(await page.locator('.elavira-structural-warning').count(),1);
 assert.match(await page.locator('.elavira-structural-warning strong').last().innerText(),/Human Review/);
 assert.equal(await page.locator('.notice-info').count(),0);
 await page.screenshot({path:'dist/ux-qa/structural.png',fullPage:true});
 results.push('Keyboard order, visible focus, new-tab descriptions, combined structural warning');
 assert.deepEqual(errors,[]);
 fs.writeFileSync('dist/ux-qa/results.json',JSON.stringify({results,errors},null,2));
 console.log(results.join('\n'));
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
