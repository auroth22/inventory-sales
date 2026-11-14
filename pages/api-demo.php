<?php
require '../includes/auth.php';
require '../includes/db.php';
require_login();

$page_title = 'API Explorer';
require '../includes/header.php';
?>
<style>
    .muted { color: var(--muted); }
    .api-head { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .pill { font-family: Consolas, monospace; font-size: 12px; padding: 3px 10px; border-radius: 999px; border: 1px solid var(--line); color: var(--muted); }
    .badge { display: inline-block; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 999px; background: rgba(242, 139, 130, 0.18); color: var(--red); }
    .api-section { margin-top: 28px; }
    .api-section h2 { margin: 0 0 6px; }
    .endpoint { font-family: Consolas, monospace; font-size: 13px; color: var(--accent); margin-bottom: 10px; }
    .api-search { margin: 10px 0; }
    .api-search input { padding: 8px 10px; width: min(320px, 100%); }
    .log { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
    .log li { background: var(--surface); border: 1px solid var(--line); border-radius: 8px; padding: 8px 12px; }
    .log summary { cursor: pointer; font-family: Consolas, monospace; font-size: 13px; }
    .log .ok { color: var(--green); font-weight: bold; }
    .log .bad { color: var(--red); font-weight: bold; }
    .log pre { margin: 10px 0 2px; padding: 10px; background: var(--bg); border-radius: 6px; overflow-x: auto; font-size: 12px; max-height: 260px; }
</style>

<div class="api-head">
    <h1>API Explorer</h1>
    <span class="pill">read-only &middot; JSON &middot; GET</span>
</div>
<p class="muted">Everything on this page is loaded with JavaScript <code>fetch()</code> from the API. The page never reads the database directly.</p>

<div class="cards">
    <div class="card"><span class="num" id="k-sales">&hellip;</span><span>Sales today</span></div>
    <div class="card"><span class="num" id="k-revenue">&hellip;</span><span>Revenue today</span></div>
    <div class="card warn"><span class="num" id="k-low">&hellip;</span><span>Low stock items</span></div>
    <div class="card"><span class="num" id="k-products">&hellip;</span><span>Active products</span></div>
</div>

<section class="api-section">
    <h2>Low stock</h2>
    <div class="endpoint">GET /api/low-stock.php</div>
    <table>
        <thead><tr><th>Product</th><th>Stock</th><th>Reorder level</th></tr></thead>
        <tbody id="low-body"><tr><td colspan="3" class="muted">Loading...</td></tr></tbody>
    </table>
</section>

<section class="api-section">
    <h2>Products</h2>
    <div class="endpoint">GET /api/products.php?q=<span id="q-echo"></span></div>
    <div class="api-search">
        <input type="text" id="q" placeholder="Type to search (calls the API as you type)" autocomplete="off">
    </div>
    <table>
        <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Stock</th></tr></thead>
        <tbody id="prod-body"><tr><td colspan="4" class="muted">Loading...</td></tr></tbody>
    </table>
</section>

<section class="api-section">
    <h2>Request log</h2>
    <p class="muted">Each call the page makes. Click one to see the raw JSON that came back.</p>
    <ul class="log" id="log"></ul>
</section>

<script>
    const API = '../api/';
    const logEl = document.getElementById('log');

    const peso = n => '\u20B1' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function addLog(path, status, ms, data) {
        const li = document.createElement('li');
        const details = document.createElement('details');
        const summary = document.createElement('summary');

        const code = document.createElement('span');
        code.textContent = status || 'ERR';
        code.className = status === 200 ? 'ok' : 'bad';

        summary.append('GET /api/' + path + '  \u2192  ', code, '  \u00B7  ' + ms + ' ms');
        const pre = document.createElement('pre');
        pre.textContent = JSON.stringify(data, null, 2);

        details.append(summary, pre);
        li.appendChild(details);
        logEl.prepend(li);
        while (logEl.children.length > 8) { logEl.lastChild.remove(); }
    }

    async function call(path) {
        const t0 = performance.now();
        let status = 0, data = { error: 'Request failed' };
        try {
            const res = await fetch(API + path, { headers: { 'Accept': 'application/json' } });
            status = res.status;
            data = await res.json();
        } catch (e) { /* keep the default error */ }
        addLog(path, status, Math.round(performance.now() - t0), data);
        if (status !== 200) { throw new Error(String(status)); }
        return data;
    }

    function row(cells, className) {
        const tr = document.createElement('tr');
        if (className) { tr.className = className; }
        cells.forEach(c => {
            const td = document.createElement('td');
            if (c instanceof Node) { td.appendChild(c); } else { td.textContent = c; }
            tr.appendChild(td);
        });
        return tr;
    }

    function message(tbody, cols, text) {
        tbody.innerHTML = '';
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = cols; td.className = 'muted'; td.textContent = text;
        tr.appendChild(td); tbody.appendChild(tr);
    }

    // ----- Sales today -----
    call('sales-today.php').then(d => {
        document.getElementById('k-sales').textContent = d.sales_count;
        document.getElementById('k-revenue').textContent = peso(d.total);
    }).catch(() => {
        document.getElementById('k-sales').textContent = '-';
        document.getElementById('k-revenue').textContent = '-';
    });

    // ----- Low stock -----
    call('low-stock.php').then(d => {
        document.getElementById('k-low').textContent = d.count;
        const body = document.getElementById('low-body');
        body.innerHTML = '';
        if (d.count === 0) { message(body, 3, 'Nothing is running low.'); return; }
        d.low_stock.forEach(p => {
            const name = document.createElement('span');
            name.textContent = p.name + ' ';
            if (p.stock === 0) {
                const b = document.createElement('span');
                b.className = 'badge'; b.textContent = 'OUT';
                name.appendChild(b);
            }
            body.appendChild(row([name, p.stock, p.reorder_level], 'low'));
        });
    }).catch(() => {
        document.getElementById('k-low').textContent = '-';
        message(document.getElementById('low-body'), 3, 'Could not load (are you logged in?).');
    });

    // ----- Products + live search -----
    const prodBody = document.getElementById('prod-body');
    const qInput = document.getElementById('q');
    const qEcho = document.getElementById('q-echo');

    function loadProducts(q) {
        qEcho.textContent = q ? encodeURIComponent(q) : '';
        const path = 'products.php' + (q ? '?q=' + encodeURIComponent(q) : '');
        call(path).then(d => {
            if (!q) { document.getElementById('k-products').textContent = d.count; }
            prodBody.innerHTML = '';
            if (d.count === 0) { message(prodBody, 4, 'No products match.'); return; }
            d.products.forEach(p => {
                prodBody.appendChild(row([p.name, p.category || '-', peso(p.price), p.stock], p.low_stock ? 'low' : ''));
            });
        }).catch(() => {
            if (!q) { document.getElementById('k-products').textContent = '-'; }
            message(prodBody, 4, 'Could not load (are you logged in?).');
        });
    }

    let timer;
    qInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadProducts(qInput.value.trim()), 250);
    });
    loadProducts('');
</script>
<?php require '../includes/footer.php'; ?>