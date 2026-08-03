(() => {
  const root = document.getElementById('cslm-dispatch-app');
  const cfg = window.CSLMDispatch;
  const state = { tab: 'loads', carriers: [], drivers: [], loads: [], invoices: [], busy: false };
  const labels = { loads: 'Loads', carriers: 'Carriers', drivers: 'Drivers', invoices: 'Invoices' };
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  const request = async (path, options = {}) => {
    const response = await fetch(cfg.restUrl + path, { ...options, headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce, ...(options.headers || {}) } });
    const body = await response.json();
    if (!response.ok) throw new Error(body.message || 'Request failed');
    return body;
  };
  async function refresh() {
    state.busy = true; render();
    try { [state.carriers, state.drivers, state.loads, state.invoices] = await Promise.all(Object.keys(labels).slice(1,3).concat(['loads','invoices']).map(x => request(x))); }
    catch (error) { notice(error.message, true); }
    state.busy = false; render();
  }
  function money(value) { return '$' + Number(value || 0).toFixed(2); }
  function render() {
    const active = state.loads.filter(x => !['delivered','cancelled'].includes(x.status)).length;
    const earned = state.invoices.filter(x => x.status === 'paid').reduce((sum,x) => sum + Number(x.amount), 0);
    root.innerHTML = `<div class="cslm-shell"><aside><div class="cslm-brand">🚚 <b>CSLM Dispatch</b></div>${Object.entries(labels).map(([id,label]) => `<button data-tab="${id}" class="${state.tab===id?'active':''}">${label}</button>`).join('')}<small>${esc(cfg.settings.company_name)}<br>${esc(cfg.settings.phone)}<br>${esc(cfg.settings.email)}</small></aside><main><header><div><p>CSLM LLC · FLORIDA OPERATIONS</p><h1>${labels[state.tab]}</h1></div><button class="primary" data-add="${state.tab}">+ Add ${labels[state.tab].slice(0,-1)}</button></header><div class="offer"><b>Introductory pricing:</b> first completed load free, then ${esc(cfg.settings.promotional_fee)}% temporarily; standard fee ${esc(cfg.settings.standard_fee)}%.</div><section class="metrics"><article><span>Active loads</span><strong>${active}</strong></article><article><span>Carriers</span><strong>${state.carriers.length}</strong></article><article><span>Paid fees</span><strong>${money(earned)}</strong></article></section><section class="panel">${state.busy?'<p>Loading…</p>':table()}</section></main></div>`;
  }
  function table() {
    const rows = state[state.tab];
    if (!rows.length) return `<div class="empty">No ${labels[state.tab].toLowerCase()} yet.</div>`;
    const cells = {
      loads: x => [x.load_number, `${x.origin} → ${x.destination}`, x.broker, money(x.gross_pay), `${Number(x.fee_percent).toFixed(1)}%`, x.status],
      carriers: x => [x.company_name, x.contact_name, x.mc_number || '—', x.equipment, x.first_load_free==='1'?'Free first load':'Standard', x.status],
      drivers: x => [x.name, x.phone, x.equipment_type, x.equipment_number || '—', x.current_location || '—', x.status],
      invoices: x => [x.invoice_number, money(x.amount), x.due_at || '—', x.status],
    };
    const heads = {loads:['Load','Route','Broker','Gross','Fee','Status'],carriers:['Company','Contact','MC','Equipment','Offer','Status'],drivers:['Driver','Phone','Equipment','Unit','Location','Status'],invoices:['Invoice','Amount','Due','Status']}[state.tab];
    return `<table><thead><tr>${heads.map(h=>`<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.map(x=>`<tr>${cells[state.tab](x).map((c,i)=>`<td>${i===0?'<b>'+esc(c)+'</b>':esc(c)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
  }
  function modal(resource) {
    const forms = {
      carriers: `<input name="company_name" placeholder="Carrier company" required><input name="contact_name" placeholder="Contact name"><input name="phone" placeholder="Phone"><input name="email" type="email" placeholder="Email"><input name="mc_number" placeholder="MC number"><input name="equipment" value="Dry Van" placeholder="Equipment"><textarea name="preferred_lanes" placeholder="Preferred lanes"></textarea><input name="minimum_rate" type="number" step=".01" placeholder="Minimum rate per mile"><input name="default_fee_percent" type="number" step=".01" value="0.1" placeholder="Fee %"><label><input name="first_load_free" type="checkbox" checked> First load free</label><select name="status"><option>prospect</option><option>trial</option><option>active</option></select>`,
      drivers: `<input name="name" placeholder="Driver name" required><input name="phone" placeholder="Phone"><input name="email" type="email" placeholder="Email"><input name="equipment_type" value="Dry Van" placeholder="Equipment type"><input name="equipment_number" placeholder="Truck/unit number"><input name="current_location" placeholder="Current location"><input name="available_at" type="datetime-local"><select name="status"><option>available</option><option>dispatched</option><option>off_duty</option></select>`,
      loads: `<input name="load_number" placeholder="Load number" required><input name="origin" placeholder="Origin" required><input name="destination" placeholder="Destination" required><input name="broker" placeholder="Broker"><input name="gross_pay" type="number" step=".01" placeholder="Gross pay"><input name="fee_percent" type="number" step=".01" value="0.1" placeholder="Fee %"><label><input name="first_load_free" type="checkbox"> Apply free first load</label><select name="status"><option>planned</option><option>booked</option><option>in_transit</option><option>delivered</option></select>`,
      invoices: `<input name="invoice_number" value="CSLM-${Date.now().toString().slice(-8)}" placeholder="Invoice number" required><input name="amount" type="number" step=".01" placeholder="Amount"><input name="due_at" type="date"><select name="status"><option>draft</option><option>sent</option><option>paid</option><option>overdue</option></select>`,
    };
    document.body.insertAdjacentHTML('beforeend', `<div class="cslm-modal"><form data-form="${resource}"><h2>Add ${labels[resource].slice(0,-1)}</h2>${forms[resource]}<div><button type="button" data-close>Cancel</button><button class="primary" type="submit">Save</button></div></form></div>`);
  }
  function notice(message, error=false) { const box=document.createElement('div');box.className='cslm-notice '+(error?'error':'');box.textContent=message;document.body.appendChild(box);setTimeout(()=>box.remove(),4000); }
  document.addEventListener('click', event => { const tab=event.target.closest('[data-tab]');if(tab){state.tab=tab.dataset.tab;render();}const add=event.target.closest('[data-add]');if(add)modal(add.dataset.add);if(event.target.closest('[data-close]'))event.target.closest('.cslm-modal').remove(); });
  document.addEventListener('submit', async event => { const form=event.target.closest('[data-form]');if(!form)return;event.preventDefault();const data=Object.fromEntries(new FormData(form));form.querySelectorAll('input[type=checkbox]').forEach(x=>data[x.name]=x.checked?1:0);try{await request(form.dataset.form,{method:'POST',body:JSON.stringify(data)});form.closest('.cslm-modal').remove();notice('Saved');await refresh();}catch(error){notice(error.message,true);} });
  refresh();
})();
