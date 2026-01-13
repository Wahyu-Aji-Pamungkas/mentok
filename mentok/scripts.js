const API = (path) => `api/${path}`;

const $ = (s) => document.querySelector(s);
const $$ = (s) => Array.from(document.querySelectorAll(s));


let ME = null;
let ITEMS = [];
let TX = [];

function show(el){ el.hidden = false; }
function hide(el){ el.hidden = true; }

async function apiGet(url){
  const r = await fetch(url, { credentials: "include" });
  const j = await r.json();
  if(!j.ok) throw new Error(j.message || "Error");
  return j.data;
}
async function apiPost(url, body){
  const r = await fetch(url, {
    method:"POST",
    headers:{ "Content-Type":"application/json" },
    body: JSON.stringify(body),
    credentials: "include"
  });
  const j = await r.json();
  if(!j.ok) throw new Error(j.message || "Error");
  return j.data;
}
async function apiDelete(url){
  const r = await fetch(url, { method:"DELETE", credentials:"include" });
  const j = await r.json();
  if(!j.ok) throw new Error(j.message || "Error");
  return j.data;
}

function roleLabel(r){ return r === "OWNER" ? "Owner" : "Karyawan"; }
function txLabel(t){ return t==="IN"?"Masuk":t==="OUT"?"Keluar":"Penyesuaian"; }

/* REVISI: hanya KARYAWAN boleh buat transaksi stok */
function canCreateStockTx(){
  return ME && ME.role === "KARYAWAN";
}

async function loadMe(){
  ME = await apiGet(API("auth.php?action=me"));
  $("#userChip").textContent = `${ME.username} • ${roleLabel(ME.role)}`;
  show($("#topActions"));
}

async function login(){
  const username = $("#loginUsername").value.trim();
  const password = $("#loginPassword").value.trim();
  await apiPost(API("auth.php?action=login"), { username, password });
  location.reload();
}

async function logout(){
  await apiPost(API("auth.php?action=logout"), {});
  location.reload();
}

async function loadItems(){
  ITEMS = await apiGet(API("items.php"));
}

async function loadTx(){
  TX = await apiGet(API("transactions.php"));
}

async function loadDashboard(){
  const kpi = await apiGet(API("reports.php?action=summary"));
  $("#kpiTotalBarang").textContent = kpi.total_items;
  $("#kpiTotalStok").textContent = kpi.total_stock;
  $("#kpiLowStock").textContent = kpi.low_items;
  $("#kpiTxToday").textContent = kpi.tx_today;

  const low = await apiGet(API("reports.php?action=lowstock"));
  const wrap = $("#lowStockList");
  if(!low.length){
    wrap.className = "empty";
    wrap.textContent = "Belum ada barang yang stoknya menipis.";
  } else {
    wrap.className = "";
    wrap.innerHTML = low.slice(0,6).map(i => `
      <div class="note" style="margin-top:10px">
        <div><b>${i.name}</b> <span class="muted small">(${i.code} • ${i.category})</span></div>
        <div class="muted">Stok: <b>${i.stock}</b> ${i.unit} • Minimum: <b>${i.min_stock}</b> ${i.unit}</div>
      </div>
    `).join("");
  }

  // recent items
  const body = $("#recentItemsBody");
  const recent = ITEMS.slice(0,8);
  body.innerHTML = recent.length ? recent.map(i => `
    <tr>
      <td>${i.code}</td>
      <td>${i.name}</td>
      <td>${i.category}</td>
      <td class="right">${i.stock}</td>
      <td class="right">${i.min_stock}</td>
    </tr>
  `).join("") : `<tr><td colspan="5" class="muted">Belum ada data barang.</td></tr>`;
}

function setActiveTab(tab){
  $$(".navItem").forEach(b => b.classList.toggle("active", b.dataset.tab===tab));
  $$(".tab").forEach(t => hide(t));
  show($(`#tab-${tab}`));
  renderByTab(tab);
}

function renderItemsTable(){
  const body = $("#itemsBody");
  body.innerHTML = ITEMS.map(i => {
    const stock = Number(i.stock);
    const min = Number(i.min_stock);

    let badge = `<span class="badge ok">✅ Aman</span>`;

    if (stock === 0) {
      badge = `<span class="badge low">⛔ Kosong</span>`;
    } else if (stock <= min) {
      badge = `<span class="badge low">⚠ Menipis</span>`;
    }

    // RULES UI:
    // OWNER: view only (no tombol tambah/hapus)
    // KARYAWAN: boleh tambah & hapus, no edit
    const canDelete = ME.role === "KARYAWAN";

    return `
      <tr>
        <td>${i.code}</td>
        <td>${i.name}</td>
        <td>${i.category}</td>
        <td class="right">${i.stock} ${i.unit}</td>
        <td class="right">${i.min_stock} ${i.unit}</td>
        <td>${badge}</td>
        <td class="right">
          ${canDelete ? `<button class="iconBtn" data-del="${i.id}">Hapus</button>` : `<span class="muted small">—</span>`}
        </td>
      </tr>
    `;
  }).join("") || `<tr><td colspan="7" class="muted">Tidak ada data.</td></tr>`;

  body.querySelectorAll("button[data-del]").forEach(btn => {
    btn.addEventListener("click", async () => {
      const id = btn.dataset.del;
      if(!confirm("Hapus barang ini?")) return;
      await apiDelete(API(`items.php?id=${id}`));
      await loadItems();
      renderItemsTable();
      await loadDashboard();
    });
  });

  // tombol tambah barang hanya untuk karyawan
  $("#btnOpenAddItem").hidden = !(ME.role === "KARYAWAN");
}

function renderTxTable(){
  const body = $("#txBody");
  body.innerHTML = TX.map(t => `
    <tr>
      <td>${t.trx_time}</td>
      <td>${t.trx_type} (${txLabel(t.trx_type)})</td>
      <td>${t.name} <span class="muted small">(${t.code})</span></td>
      <td class="right">${t.qty}</td>
      <td>${t.note || ""}</td>
      <td>${t.username}</td>
    </tr>
  `).join("") || `<tr><td colspan="6" class="muted">Belum ada transaksi.</td></tr>`;
}

function fillTxItemSelect(){
  const sel = $("#txItem");
  sel.innerHTML = ITEMS.map(i => `<option value="${i.id}">${i.name} (${i.code}) — stok ${i.stock}</option>`).join("");
}

async function renderSettings(){
  // settings hanya OWNER
  $("#tab-pengaturan").hidden = !(ME.role === "OWNER");
  $("#navSettings").hidden = !(ME.role === "OWNER");
  if (ME.role !== "OWNER") return;

  const users = await apiGet(API("users.php"));
  const body = $("#usersBody");
  body.innerHTML = users.map(u => `
    <tr>
      <td>${u.username}</td>
      <td>${u.role}</td>
      <td class="right">
        ${u.id === ME.id ? `<span class="muted small">—</span>` : `<button class="iconBtn" data-deluser="${u.id}">Hapus</button>`}
      </td>
    </tr>
  `).join("");

  body.querySelectorAll("button[data-deluser]").forEach(btn => {
    btn.addEventListener("click", async () => {
      const id = btn.dataset.deluser;
      if(!confirm("Hapus user ini?")) return;
      await apiDelete(API(`users.php?id=${id}`));
      await renderSettings();
    });
  });
}

async function renderByTab(tab){
  if(tab === "dashboard"){
    await loadDashboard();
    return;
  }
  if(tab === "barang"){
    renderItemsTable();
  }
  if(tab === "transaksi"){
    // ini adalah tab "Transaksi Stok" (riwayat)
    renderTxTable();
  }
  if(tab === "laporan"){
    // untuk ringkas, pakai KPI dashboard saja dulu
    await loadReport();
    return;
  }
  if(tab === "pengaturan"){
    await renderSettings();
  }
}

function openModal(id){ $(id).hidden = false; }
function closeModal(id){ $(id).hidden = true; }

async function addItem(){
  const payload = {
    code: $("#itemCode").value,
    name: $("#itemName").value,
    category: $("#itemCategory").value,
    stock: Number($("#itemStock").value || 0),
    min_stock: Number($("#itemMin").value || 5),
    unit: $("#itemUnit").value,
    note: $("#itemNote").value
  };
  await apiPost(API("items.php"), payload);
  closeModal("#modalItem");
  await loadItems();
  renderItemsTable();
  await loadDashboard();
}

async function addTransaction(){
  // REVISI: Owner tidak boleh buat transaksi stok
  if (!canCreateStockTx()) {
    throw new Error("Owner tidak dapat membuat transaksi stok.");
  }

  const payload = {
    trx_type: $("#txType").value,
    item_id: Number($("#txItem").value),
    qty: Number($("#txQty").value),
    note: $("#txNote").value
  };

  await apiPost(API("transactions.php"), payload);

  closeModal("#modalTx");
  await loadItems();
  await loadTx();
  fillTxItemSelect();
  renderTxTable();
  await loadDashboard();
}

async function addUser(){
  const payload = {
    username: $("#newUserUsername").value.trim(),
    password: $("#newUserPassword").value.trim(),
    role: $("#newUserRole").value
  };
  await apiPost(API("users.php"), payload);
  $("#newUserUsername").value = "";
  $("#newUserPassword").value = "";
  $("#newUserRole").value = "KARYAWAN";
  await renderSettings();
}


  //Laporan Harian dan Mingguan
async function loadReport(){

  const reportIn7  = $("#reportIn7");
  if(!reportIn7) return;

  $("#btnExportCSV").onclick = () => {
    window.open("api/reports.php?action=export");
  };

  const reportOut7 = $("#reportOut7");
  const reportTx7  = $("#reportTx7");
  const reportLow  = $("#reportLow");
  const dailyReportBody  = $("#dailyReportBody");
  const weeklyReportBody = $("#weeklyReportBody");

  try {
    /* ===== KPI ===== */
    const kpi = await apiGet(API("reports.php?action=kpi"));

    reportIn7.textContent  = kpi.in7;
    reportOut7.textContent = kpi.out7;
    reportTx7.textContent  = kpi.tx7;
    reportLow.textContent  = kpi.low;

    /* ===== DAILY ===== */
    const daily = await apiGet(API("reports.php?action=daily"));

    dailyReportBody.innerHTML = daily.length ? daily.map(d => `
      <tr>
        <td>${d.tanggal}</td>
        <td class="right">${d.in_qty}</td>
        <td class="right">${d.out_qty}</td>
        <td class="right">${d.adj_qty}</td>
        <td class="right">${d.total_tx}</td>
      </tr>
    `).join("") : `
      <tr><td colspan="5" class="muted">Tidak ada data</td></tr>
    `;

    /* ===== WEEKLY ===== */
    const weekly = await apiGet(API("reports.php?action=weekly"));

    weeklyReportBody.innerHTML = weekly.length ? weekly.map(w => `
      <tr>
        <td>${w.minggu}</td>
        <td class="right">${w.in_qty}</td>
        <td class="right">${w.out_qty}</td>
        <td class="right">${w.adj_qty}</td>
        <td class="right">${w.total_tx}</td>
      </tr>
    `).join("") : `
      <tr><td colspan="5" class="muted">Tidak ada data</td></tr>
    `;

  } catch (e) {
    console.error("Laporan error:", e);
    alert("Gagal memuat laporan");
  }
}


async function boot(){
  // bind auth
  $("#btnFillDemo").addEventListener("click", () => {
    $("#loginUsername").value = "owner";
    $("#loginPassword").value = "owner123";
  });
  $("#btnLogin").addEventListener("click", async () => {
    try { await login(); } catch(e){ alert(e.message); }
  });
  $("#btnLogout").addEventListener("click", async () => {
    try { await logout(); } catch(e){ alert(e.message); }
  });

  // cek login
  try{
    await loadMe();
  } catch {
    show($("#authCard"));
    hide($("#app"));
    hide($("#topActions"));
    return;
  }

  // show app
  hide($("#authCard"));
  show($("#app"));

  // nav
  $$(".navItem").forEach(b => b.addEventListener("click", () => setActiveTab(b.dataset.tab)));

  // load initial data
  await loadItems();
  await loadTx();
  fillTxItemSelect();

  // tombol tambah barang (karyawan)
  $("#btnOpenAddItem").addEventListener("click", () => openModal("#modalItem"));
  $("#btnSaveItem").addEventListener("click", async () => {
    try { await addItem(); } catch(e){ alert(e.message); }
  });

  // ===== REVISI: Owner tidak boleh buat transaksi stok =====
  const btnOpenTx = $("#btnOpenTx");
  const btnSaveTx = $("#btnSaveTx");

  // Owner tetap bisa lihat riwayat transaksi, tapi tidak bisa buat transaksi
  btnOpenTx.hidden = !canCreateStockTx(); // sembunyikan tombol buat transaksi jika OWNER

  btnOpenTx.addEventListener("click", () => {
    if (!canCreateStockTx()) return;
    openModal("#modalTx");
  });

  btnSaveTx.addEventListener("click", async () => {
    try {
      if (!canCreateStockTx()) {
        alert("Owner tidak dapat membuat transaksi stok.");
        return;
      }
      await addTransaction();
    } catch(e){
      alert(e.message);
    }
  });


  // pengaturan user (owner)
  $("#btnAddUser").addEventListener("click", async () => {
    try { await addUser(); } catch(e){ alert(e.message); }
  });

  // modal close
  $$("[data-close]").forEach(el => el.addEventListener("click", (e) => {
    const modal = e.target.closest(".modal");
    if(modal) modal.hidden = true;
  }));

  // role: hide settings for karyawan
  $("#navSettings").hidden = !(ME.role === "OWNER");
  $("#tab-pengaturan").hidden = !(ME.role === "OWNER");

  // start at dashboard
  setActiveTab("dashboard");
}

boot();
