<?php
// Control de Vacunas – Grupo 020
// Vistas: Por Zona | Por Unidad Médica | Por Vacuna
// Sin Composer. Actualizar: python actualizar.py
date_default_timezone_set('America/Mexico_City');
$jsonZones   = __DIR__ . '/cache_data.json';
$jsonUnits   = __DIR__ . '/cache_units.json';
$jsonVaccines= __DIR__ . '/cache_vaccines.json';
$zones  = ['CARBONÍFERA','CENTRO','LAGUNA','NORTE','SUR'];
$zonesData = $unitsData = $vaccinesData = [];
$error = null; $lastUpdate = null;

foreach ($zones as $z) $zonesData[$z] = ['vacunas'=>[],'unidades'=>[]];

if (file_exists($jsonZones) && file_exists($jsonUnits) && file_exists($jsonVaccines)) {
    $dz = json_decode(file_get_contents($jsonZones),    true);
    $du = json_decode(file_get_contents($jsonUnits),    true);
    $dv = json_decode(file_get_contents($jsonVaccines), true);
    if ($dz && $du && $dv) {
        $zonesData    = $dz;
        $unitsData    = $du;
        $vaccinesData = $dv;
        $lastUpdate   = date('d/m/Y H:i', filemtime($jsonZones));
    } else {
        $error = 'Archivos de caché corruptos. Ejecuta <code>python actualizar.py</code>.';
    }
} else {
    $error = 'Archivos de caché no encontrados.<br>Ejecuta <code>python actualizar.py</code>.';
}

$zonesJson    = json_encode($zonesData,    JSON_UNESCAPED_UNICODE);
$unitsJson    = json_encode($unitsData,    JSON_UNESCAPED_UNICODE);
$vaccinesJson = json_encode($vaccinesData, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Control de Vacunas – Grupo 020</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root{
  --bg:#f1f5f9; --white:#fff; --sf2:#f8fafc;
  --brd:#e2e8f0; --brd2:#cbd5e1;
  --grn:#15803d; --grn-md:#16a34a; --grn-lt:#dcfce7; --grn-rg:#bbf7d0;
  --blu:#1d4ed8; --blu-lt:#dbeafe;
  --red:#dc2626; --red-lt:#fee2e2;
  --amb:#d97706; --amb-lt:#fef3c7;
  --vio:#6d28d9; --vio-lt:#ede9fe;
  --slat:#0f172a; --body:#334155; --mut:#64748b; --fnt:#94a3b8;
  --r:10px; --sh:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);
  --sh2:0 4px 16px rgba(0,0,0,.09); --hh:62px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--body);font-size:14px;min-height:100vh}

/* HEADER */
.hdr{background:var(--white);border-bottom:1px solid var(--brd);height:var(--hh);
  display:flex;align-items:center;padding:0 28px;gap:14px;
  position:sticky;top:0;z-index:200;box-shadow:var(--sh)}
.hdr-icon{width:42px;height:42px;border-radius:10px;flex-shrink:0;
  background:linear-gradient(135deg,#16a34a,#166534);
  display:flex;align-items:center;justify-content:center;font-size:22px;
  box-shadow:0 2px 8px rgba(22,163,74,.28)}
.hdr-titles{display:flex;flex-direction:column;gap:1px}
.hdr-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;color:var(--slat)}
.hdr-sub{font-size:.73rem;color:var(--mut);font-weight:500}
.hdr-sep{width:1px;height:28px;background:var(--brd);margin:0 4px}
.hdr-pill{display:flex;align-items:center;gap:7px;background:var(--grn-lt);
  border:1px solid var(--grn-rg);border-radius:20px;padding:4px 13px;
  font-size:.73rem;font-weight:600;color:var(--grn)}
.hdr-pill .dot{width:7px;height:7px;border-radius:50%;background:#22c55e;animation:pulse 2.5s infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 2px rgba(34,197,94,.2)}50%{box-shadow:0 0 0 5px rgba(34,197,94,.05)}}
.hdr-date{margin-left:auto;font-size:.76rem;color:var(--mut);font-weight:500}

/* VIEW TABS */
.view-tabs{background:var(--white);border-bottom:2px solid var(--brd);
  display:flex;align-items:stretch;padding:0 28px;gap:0}
.view-tab{font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;font-weight:700;
  padding:13px 24px;border:none;background:transparent;color:var(--mut);
  cursor:pointer;border-bottom:3px solid transparent;transition:all .18s;
  white-space:nowrap;position:relative;top:2px;display:flex;align-items:center;gap:8px}
.view-tab:hover{color:var(--body);background:var(--sf2)}
.view-tab.on{color:var(--grn);border-bottom-color:var(--grn-md)}
.vt-badge{font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:10px;
  background:var(--sf2);border:1px solid var(--brd);color:var(--mut)}
.view-tab.on .vt-badge{background:var(--grn-lt);border-color:var(--grn-rg);color:var(--grn)}

/* ZONE SUB-TABS */
.zone-tabs{background:var(--white);border-bottom:1px solid var(--brd);
  display:flex;align-items:stretch;padding:0 28px;gap:2px;overflow-x:auto}
.zone-tab{font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;font-weight:700;
  letter-spacing:.03em;padding:10px 20px;border:none;background:transparent;
  color:var(--mut);cursor:pointer;border-bottom:3px solid transparent;
  transition:color .15s,border-color .15s;white-space:nowrap;position:relative;top:1px}
.zone-tab:hover{color:var(--body)}
.zone-tab.on{color:var(--grn);border-bottom-color:var(--grn-md)}

/* VIEWS */
.view{display:none}.view.on{display:block}

/* SHARED LAYOUT */
.main{display:grid;grid-template-columns:310px 1fr;gap:18px;
  padding:20px 28px;height:calc(100vh - var(--hh) - 46px - 44px)}
.main-full{display:flex;flex-direction:column;gap:14px;
  padding:20px 28px;height:calc(100vh - var(--hh) - 46px)}

/* STAT CARDS */
.stats{grid-column:1/-1;display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.stat{background:var(--white);border:1px solid var(--brd);border-radius:var(--r);
  padding:16px 18px;display:flex;align-items:flex-start;gap:13px;
  box-shadow:var(--sh);transition:box-shadow .18s}
.stat:hover{box-shadow:var(--sh2)}
.stat-ico{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;
  justify-content:center;font-size:21px;flex-shrink:0}
.ico-g{background:var(--grn-lt)}.ico-b{background:var(--blu-lt)}
.ico-a{background:var(--amb-lt)}.ico-r{background:var(--red-lt)}.ico-v{background:var(--vio-lt)}
.stat-info{display:flex;flex-direction:column;gap:2px;min-width:0}
.stat-lbl{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--mut)}
.stat-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.6rem;font-weight:800;
  color:var(--slat);line-height:1.1;white-space:nowrap}
.stat-sub{font-size:.7rem;color:var(--fnt)}

/* PANELS */
.left{display:flex;flex-direction:column;gap:14px;min-height:0;overflow:hidden}
.card{background:var(--white);border:1px solid var(--brd);border-radius:var(--r);
  box-shadow:var(--sh);overflow:hidden}
.card-h{padding:12px 16px;border-bottom:1px solid var(--brd);display:flex;align-items:center;gap:8px}
.card-h-lbl{font-family:'Plus Jakarta Sans',sans-serif;font-size:.78rem;
  font-weight:700;color:var(--slat);text-transform:uppercase;letter-spacing:.05em}
.card-b{padding:14px 16px}

/* PIE */
.pie-wrap{position:relative;display:flex;align-items:center;justify-content:center;height:196px}
.pie-center{position:absolute;text-align:center;pointer-events:none}
.pie-num{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.45rem;font-weight:800;color:var(--slat);line-height:1}
.pie-lbl{font-size:.66rem;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}

/* LEGEND */
.legend{display:flex;flex-direction:column;gap:3px;overflow-y:auto;padding:0 2px}
.leg-row{display:flex;align-items:center;gap:9px;padding:5px 7px;border-radius:6px;transition:background .12s}
.leg-row:hover{background:var(--sf2)}
.leg-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.leg-name{flex:1;font-size:.77rem;color:var(--body);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.leg-val{font-size:.82rem;font-weight:700;color:var(--grn);min-width:44px;text-align:right;font-variant-numeric:tabular-nums}

/* UNIT CHIPS */
.units{display:flex;flex-wrap:wrap;gap:5px;max-height:96px;overflow-y:auto}
.unit-chip{background:var(--sf2);border:1px solid var(--brd);border-radius:6px;padding:3px 9px;font-size:.7rem;color:var(--mut)}

/* TABLE */
.tpanel{background:var(--white);border:1px solid var(--brd);border-radius:var(--r);
  box-shadow:var(--sh);display:flex;flex-direction:column;overflow:hidden;min-height:0}
.ttoolbar{display:flex;align-items:center;gap:10px;padding:12px 18px;
  border-bottom:1px solid var(--brd);flex-shrink:0;flex-wrap:wrap}
.ttoolbar-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;
  font-weight:700;color:var(--slat);text-transform:uppercase;letter-spacing:.05em;flex:1}
.search{display:flex;align-items:center;gap:7px;background:var(--sf2);
  border:1px solid var(--brd);border-radius:8px;padding:6px 12px;transition:border-color .15s}
.search:focus-within{border-color:var(--grn-md)}
.search svg{color:var(--fnt);flex-shrink:0}
.search input{border:none;background:transparent;font-family:'Inter',sans-serif;
  font-size:.83rem;color:var(--body);outline:none;width:170px}
.search input::placeholder{color:var(--fnt)}
.tscroll{overflow-y:auto;flex:1}
table{width:100%;border-collapse:collapse}
thead tr{position:sticky;top:0;background:var(--sf2);z-index:1}
thead th{padding:10px 16px;text-align:left;font-size:.73rem;font-weight:600;
  letter-spacing:.05em;text-transform:uppercase;color:var(--mut);
  border-bottom:1px solid var(--brd);cursor:pointer;user-select:none;white-space:nowrap;transition:color .12s}
thead th:hover{color:var(--body)}
thead th.sorted{color:var(--grn)}
thead th .si{font-size:.65rem;margin-left:3px;opacity:.45}
thead th.sorted .si{opacity:1}
tbody tr{border-bottom:1px solid var(--brd);transition:background .1s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#f0fdf4}
tbody tr.zero-row{background:#fffbf5}
td{padding:10px 16px;font-size:.84rem;color:var(--body)}
td.vname{font-weight:500;color:var(--slat)}
td.num{font-size:.9rem;font-weight:600;text-align:right;font-variant-numeric:tabular-nums}
td.tot{font-size:.95rem;font-weight:700;text-align:right;font-variant-numeric:tabular-nums;color:var(--grn)}
.badge{display:inline-flex;align-items:center;font-size:.69rem;font-weight:600;padding:2px 8px;border-radius:20px}
.b-ok{background:var(--grn-lt);color:var(--grn)}
.b-zero{background:var(--red-lt);color:var(--red)}
.b-low{background:var(--amb-lt);color:var(--amb)}

/* UNIT VIEW */
.two-col{display:grid;grid-template-columns:280px 1fr;gap:18px;flex:1;min-height:0;overflow:hidden}
.list-panel{background:var(--white);border:1px solid var(--brd);border-radius:var(--r);
  box-shadow:var(--sh);display:flex;flex-direction:column;overflow:hidden}
.list-head{padding:12px 14px;border-bottom:1px solid var(--brd);flex-shrink:0}
.list-search{display:flex;align-items:center;gap:6px;background:var(--sf2);
  border:1px solid var(--brd);border-radius:7px;padding:5px 10px;
  transition:border-color .15s;margin-top:8px}
.list-search:focus-within{border-color:var(--grn-md)}
.list-search svg{color:var(--fnt);flex-shrink:0}
.list-search input{border:none;background:transparent;font-family:'Inter',sans-serif;
  font-size:.82rem;color:var(--body);outline:none;width:100%}
.list-scroll{overflow-y:auto;flex:1}
.list-item{display:flex;flex-direction:column;gap:3px;padding:10px 14px;
  border-bottom:1px solid var(--brd);cursor:pointer;transition:background .12s}
.list-item:last-child{border-bottom:none}
.list-item:hover{background:var(--sf2)}
.list-item.on{background:#f0fdf4;border-left:3px solid var(--grn-md)}
.list-item-name{font-size:.82rem;font-weight:600;color:var(--slat)}
.list-item-meta{display:flex;align-items:center;gap:6px}
.zone-pill{font-size:.68rem;font-weight:600;padding:1px 7px;border-radius:10px}
.list-item-total{font-size:.75rem;font-weight:700;color:var(--grn);margin-left:auto}

.detail-col{display:flex;flex-direction:column;gap:14px;min-height:0;overflow:hidden}
.mini-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;flex-shrink:0}
.mini-stats .stat{padding:12px 14px}
.mini-stats .stat-val{font-size:1.35rem}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;flex:1;min-height:0;overflow:hidden}

/* VACCINE VIEW */
.vac-grid{display:grid;grid-template-columns:260px 1fr;gap:18px;flex:1;min-height:0;overflow:hidden}

/* vaccine list items */
.vac-item{display:flex;align-items:center;gap:10px;padding:10px 14px;
  border-bottom:1px solid var(--brd);cursor:pointer;transition:background .12s}
.vac-item:last-child{border-bottom:none}
.vac-item:hover{background:var(--sf2)}
.vac-item.on{background:#f0fdf4;border-left:3px solid var(--grn-md)}
.vac-item-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.vac-item-name{flex:1;font-size:.82rem;font-weight:600;color:var(--slat)}
.vac-item-total{font-size:.78rem;font-weight:700;color:var(--grn);font-variant-numeric:tabular-nums}

/* vaccine detail */
.vac-detail{display:flex;flex-direction:column;gap:14px;min-height:0;overflow:hidden}
.vac-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;flex:1;min-height:0;overflow:hidden}

/* zone bar chart */
.zone-bars{display:flex;flex-direction:column;gap:8px;padding:4px 0}
.zone-bar-row{display:flex;align-items:center;gap:10px}
.zone-bar-label{font-size:.76rem;font-weight:600;color:var(--body);width:100px;flex-shrink:0;text-align:right}
.zone-bar-track{flex:1;background:var(--sf2);border-radius:4px;height:20px;overflow:hidden;border:1px solid var(--brd)}
.zone-bar-fill{height:100%;border-radius:4px;transition:width .5s ease;display:flex;align-items:center;padding:0 8px}
.zone-bar-val{font-size:.72rem;font-weight:700;color:#fff;white-space:nowrap}

/* empty state */
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;
  height:100%;gap:12px;color:var(--fnt)}
.empty-state .es-icon{font-size:3rem;opacity:.35}
.empty-state .es-txt{font-size:.9rem;font-weight:500}

/* error */
.err-wrap{padding:32px}
.err-box{background:#fff5f5;border:1px solid #fecaca;border-radius:var(--r);
  padding:24px 28px;color:var(--body);line-height:1.8;font-size:.9rem}
.err-box strong{color:var(--red)}
.err-box code{background:var(--sf2);border:1px solid var(--brd);padding:1px 7px;border-radius:4px;font-size:.85em;color:var(--grn)}
.err-box ol{margin-top:12px;padding-left:22px;color:var(--mut)}

::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--brd2);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:var(--mut)}

@media(max-width:960px){
  .main,.main-full{height:auto;padding-bottom:28px}
  .main{grid-template-columns:1fr}
  .stats,.mini-stats{grid-template-columns:repeat(2,1fr)}
  .two-col,.vac-grid,.detail-grid,.vac-detail-grid{grid-template-columns:1fr}
  .tpanel{min-height:400px}
}
</style>
</head>
<body>

<!-- HEADER -->
<header class="hdr">
  <div class="hdr-icon">💉</div>
  <div class="hdr-titles">
    <div class="hdr-title">Control de Vacunas</div>
    <div class="hdr-sub">Grupo 020 · Delegación Coahuila</div>
  </div>
  <div class="hdr-sep"></div>
  <div class="hdr-pill"><div class="dot"></div>IMSS · Activo</div>
  <?php if($lastUpdate): ?>
  <div class="hdr-date">Actualizado: <?=htmlspecialchars($lastUpdate)?></div>
  <?php endif ?>
</header>

<?php if($error): ?>
<div class="err-wrap"><div class="err-box">
  <strong>⚠️ Datos no disponibles</strong><br><?=$error?>
  <ol>
    <li>Coloca <code>Control_Existencias_por_Unidad.xlsx</code> en la carpeta del proyecto</li>
    <li>Ejecuta: <code>python actualizar.py</code></li>
    <li>Recarga esta página</li>
  </ol>
</div></div>
<?php else: ?>

<!-- VIEW TABS -->
<nav class="view-tabs">
  <button class="view-tab on"  id="vtab-zona" onclick="switchView('zona')">
    🗺️ Por Zona <span class="vt-badge"><?=count($zones)?></span>
  </button>
  <button class="view-tab" id="vtab-unit" onclick="switchView('unit')">
    🏥 Por Unidad Médica <span class="vt-badge"><?=count($unitsData)?></span>
  </button>
  <button class="view-tab" id="vtab-vac" onclick="switchView('vac')">
    💉 Por Vacuna <span class="vt-badge"><?=count($vaccinesData)?></span>
  </button>
</nav>


<!-- ═══════════════ VISTA 1: POR ZONA ═══════════════ -->
<div class="view on" id="view-zona">
  <nav class="zone-tabs">
    <?php foreach($zones as $i=>$z): ?>
    <button class="zone-tab <?=$i===0?'on':''?>"
            onclick="switchZone(this,'<?=htmlspecialchars($z,ENT_QUOTES)?>')">
      <?=htmlspecialchars($z)?>
    </button>
    <?php endforeach ?>
  </nav>
  <div class="main">
    <div class="stats">
      <div class="stat"><div class="stat-ico ico-g">📦</div>
        <div class="stat-info"><div class="stat-lbl">Total Existencias</div>
          <div class="stat-val" id="zs-total">—</div><div class="stat-sub">Unidad + Almacén</div></div></div>
      <div class="stat"><div class="stat-ico ico-b">🏥</div>
        <div class="stat-info"><div class="stat-lbl">En Unidad</div>
          <div class="stat-val" id="zs-unidad">—</div><div class="stat-sub">Stock en unidad</div></div></div>
      <div class="stat"><div class="stat-ico ico-g">🏬</div>
        <div class="stat-info"><div class="stat-lbl">En Almacén OOAD</div>
          <div class="stat-val" id="zs-almacen">—</div><div class="stat-sub">Stock en almacén</div></div></div>
      <div class="stat"><div class="stat-ico ico-r">⚠️</div>
        <div class="stat-info"><div class="stat-lbl">Sin Existencias</div>
          <div class="stat-val" id="zs-zero" style="color:var(--red)">—</div>
          <div class="stat-sub">Vacunas con total = 0</div></div></div>
    </div>
    <div class="left">
      <div class="card" style="flex:0 0 auto">
        <div class="card-h"><span>📊</span><span class="card-h-lbl">Distribución por Vacuna</span></div>
        <div class="card-b">
          <div class="pie-wrap">
            <canvas id="zPie" width="196" height="196"></canvas>
            <div class="pie-center"><div class="pie-num" id="zPieNum">—</div><div class="pie-lbl">Total</div></div>
          </div>
        </div>
      </div>
      <div class="card" style="flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden">
        <div class="card-h"><span>🗂️</span><span class="card-h-lbl">Vacunas</span></div>
        <div class="card-b" style="flex:1;overflow:hidden;display:flex;flex-direction:column;padding-top:10px">
          <div class="legend" id="zLegend" style="max-height:none;flex:1"></div>
        </div>
      </div>
      <div class="card" style="flex:0 0 auto">
        <div class="card-h"><span>🏥</span>
          <span class="card-h-lbl">Unidades — Zona <span id="zLabel"></span></span></div>
        <div class="card-b"><div class="units" id="zUnits"></div></div>
      </div>
    </div>
    <div class="tpanel">
      <div class="ttoolbar">
        <div class="ttoolbar-title">Inventario Detallado</div>
        <div class="search">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" id="zSrch" placeholder="Buscar vacuna…" oninput="zFilterTable()">
        </div>
      </div>
      <div class="tscroll">
        <table><thead><tr>
          <th onclick="zSort(0)" id="zth0">Vacuna <span class="si">↕</span></th>
          <th onclick="zSort(1)" id="zth1" style="text-align:right">En Unidad <span class="si">↕</span></th>
          <th onclick="zSort(2)" id="zth2" style="text-align:right">En Almacén <span class="si">↕</span></th>
          <th onclick="zSort(3)" id="zth3" style="text-align:right">Total <span class="si">↕</span></th>
          <th>Estado</th>
        </tr></thead><tbody id="zTbody"></tbody></table>
      </div>
    </div>
  </div>
</div>


<!-- ═══════════════ VISTA 2: POR UNIDAD ═══════════════ -->
<div class="view" id="view-unit">
  <div class="main-full">
    <div class="two-col">
      <div class="list-panel">
        <div class="list-head">
          <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;font-weight:700;
                      color:var(--slat);text-transform:uppercase;letter-spacing:.05em">🏥 Unidades Médicas</div>
          <div class="list-search">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" id="uListSrch" placeholder="Filtrar unidades…" oninput="filterUnitList()">
          </div>
        </div>
        <div class="list-scroll" id="uList"></div>
      </div>
      <div class="detail-col" id="uDetail">
        <div class="empty-state"><div class="es-icon">🏥</div><div class="es-txt">Selecciona una unidad médica</div></div>
      </div>
    </div>
  </div>
</div>


<!-- ═══════════════ VISTA 3: POR VACUNA ═══════════════ -->
<div class="view" id="view-vac">
  <div class="main-full">
    <div class="vac-grid">

      <!-- Vaccine list -->
      <div class="list-panel">
        <div class="list-head">
          <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;font-weight:700;
                      color:var(--slat);text-transform:uppercase;letter-spacing:.05em">💉 Vacunas</div>
          <div class="list-search">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" id="vListSrch" placeholder="Filtrar vacuna…" oninput="filterVacList()">
          </div>
        </div>
        <div class="list-scroll" id="vList"></div>
      </div>

      <!-- Vaccine detail -->
      <div class="vac-detail" id="vDetail">
        <div class="empty-state"><div class="es-icon">💉</div><div class="es-txt">Selecciona una vacuna</div></div>
      </div>

    </div>
  </div>
</div>

<?php endif ?>

<script>
const ZONES_DATA    = <?=$zonesJson?>;
const UNITS_DATA    = <?=$unitsJson?>;
const VACCINES_DATA = <?=$vaccinesJson?>;
const ZONES_LIST    = <?=json_encode($zones, JSON_UNESCAPED_UNICODE)?>;

const COLORS = [
  '#166534','#15803d','#16a34a','#22c55e','#4ade80','#065f46','#0f766e',
  '#0284c7','#1d4ed8','#4338ca','#6d28d9','#a21caf','#be185d','#9f1239',
  '#b45309','#92400e','#1e3a5f','#0c4a6e','#14532d','#052e16','#1a1a2e',
  '#16213e','#0f3460','#533483',
];
const ZONE_COLORS = {
  'CARBONÍFERA':'#854d0e','CENTRO':'#1d4ed8','LAGUNA':'#0f766e',
  'NORTE':'#6d28d9','SUR':'#be185d'
};
const ZONE_COLORS_LT = {
  'CARBONÍFERA':'#fef3c7','CENTRO':'#dbeafe','LAGUNA':'#ccfbf1',
  'NORTE':'#ede9fe','SUR':'#fce7f3'
};

// ── helper ────────────────────────────────────────────────────
const ico = (id) => document.getElementById(id);
const fmt = (n)  => Number(n).toLocaleString('es-MX');
const badge = (t) => t===0 ? '<span class="badge b-zero">SIN STOCK</span>'
                   : t<20  ? '<span class="badge b-low">BAJO</span>'
                   :         '<span class="badge b-ok">OK</span>';
function searchIco(){
  return `<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>`;
}

// ── VIEW SWITCHER ─────────────────────────────────────────────
const viewInited = {zona:true, unit:false, vac:false};
function switchView(v){
  document.querySelectorAll('.view').forEach(el=>el.classList.remove('on'));
  document.querySelectorAll('.view-tab').forEach(el=>el.classList.remove('on'));
  ico('view-'+v).classList.add('on');
  ico('vtab-'+v).classList.add('on');
  if(!viewInited[v]){
    viewInited[v]=true;
    if(v==='unit') buildUnitList();
    if(v==='vac')  buildVacList();
  }
}

// ══════════════════════════════════════════════════════════════
//  ZONA VIEW
// ══════════════════════════════════════════════════════════════
let zChart=null, curZone=ZONES_LIST[0], zSc=3, zSd=-1, zRows=[];

function switchZone(btn,z){
  document.querySelectorAll('.zone-tab').forEach(b=>b.classList.remove('on'));
  btn.classList.add('on'); curZone=z; zSc=3; zSd=-1;
  document.querySelectorAll('#view-zona thead th').forEach(t=>t.classList.remove('sorted'));
  ico('zSrch').value=''; renderZone();
}
function renderZone(){
  const d=ZONES_DATA[curZone]||{vacunas:{},unidades:[]};
  const vacs=d.vacunas||{}, unis=d.unidades||[];
  const names=Object.keys(vacs).sort();
  const gT=names.reduce((s,n)=>s+vacs[n].total,0);
  const gU=names.reduce((s,n)=>s+vacs[n].unidad,0);
  const gA=names.reduce((s,n)=>s+vacs[n].almacen,0);
  const gZ=names.filter(n=>vacs[n].total===0).length;
  ico('zs-total').textContent=fmt(gT); ico('zs-unidad').textContent=fmt(gU);
  ico('zs-almacen').textContent=fmt(gA); ico('zs-zero').textContent=gZ;
  ico('zPieNum').textContent=fmt(gT); ico('zLabel').textContent=curZone;
  const pn=names.filter(n=>vacs[n].total>0);
  const pv=pn.map(n=>vacs[n].total);
  const pc=pn.map((_,i)=>COLORS[i%COLORS.length]);
  const ctx=ico('zPie').getContext('2d');
  if(zChart) zChart.destroy();
  zChart=new Chart(ctx,{type:'doughnut',data:{labels:pn.length?pn:['Sin datos'],
    datasets:[{data:pv.length?pv:[1],backgroundColor:pc.length?pc:['#e2e8f0'],borderWidth:2,borderColor:'#fff'}]},
    options:{cutout:'63%',responsive:false,plugins:{legend:{display:false},
      tooltip:{callbacks:{label:c=>` ${c.label}: ${fmt(c.parsed)}`}}}}});
  const leg=ico('zLegend'); leg.innerHTML='';
  names.forEach(n=>{
    const ci=pn.indexOf(n); const col=ci>=0?pc[ci]:'#cbd5e1';
    const el=document.createElement('div'); el.className='leg-row';
    el.innerHTML=`<div class="leg-dot" style="background:${col}"></div>
      <div class="leg-name" title="${n}">${n}</div>
      <div class="leg-val">${fmt(vacs[n].total)}</div>`;
    leg.appendChild(el);
  });
  const ul=ico('zUnits'); ul.innerHTML='';
  unis.forEach(u=>{const c=document.createElement('span');c.className='unit-chip';c.textContent=u;ul.appendChild(c);});
  zRows=names.map(n=>({name:n,...vacs[n]})); zRenderTable();
}
function zRenderTable(){
  const key=['name','unidad','almacen','total'][zSc];
  zRows.sort((a,b)=>key==='name'?zSd*a.name.localeCompare(b.name,'es'):zSd*(a[key]-b[key]));
  const q=ico('zSrch').value.toLowerCase();
  const fr=q?zRows.filter(r=>r.name.toLowerCase().includes(q)):zRows;
  const tb=ico('zTbody'); tb.innerHTML='';
  fr.forEach(r=>{const tr=document.createElement('tr');
    if(r.total===0) tr.classList.add('zero-row');
    tr.innerHTML=`<td class="vname">${r.name}</td><td class="num">${fmt(r.unidad)}</td>
      <td class="num">${fmt(r.almacen)}</td><td class="tot">${fmt(r.total)}</td><td>${badge(r.total)}</td>`;
    tb.appendChild(tr);});
}
function zFilterTable(){zRenderTable();}
function zSort(col){
  if(zSc===col)zSd*=-1;else{zSc=col;zSd=col===0?1:-1;}
  document.querySelectorAll('#view-zona thead th').forEach(t=>t.classList.remove('sorted'));
  ico('zth'+col).classList.add('sorted'); zRenderTable();
}

// ══════════════════════════════════════════════════════════════
//  UNIT VIEW
// ══════════════════════════════════════════════════════════════
let curUnit=null, uChart=null, uSc=3, uSd=-1, uRows=[];

function buildUnitList(){ renderUnitList(''); }
function renderUnitList(q){
  const ul=ico('uList'); ul.innerHTML='';
  Object.entries(UNITS_DATA)
    .filter(([k])=>!q||k.toLowerCase().includes(q))
    .forEach(([name,d])=>{
      const total=Object.values(d.vacunas).reduce((s,v)=>s+v.total,0);
      const zc=ZONE_COLORS[d.zona]||'#64748b';
      const div=document.createElement('div'); div.className='list-item'+(curUnit===name?' on':'');
      div.innerHTML=`<div class="list-item-name">${name}</div>
        <div class="list-item-meta">
          <span class="zone-pill" style="background:${ZONE_COLORS_LT[d.zona]||'#f8fafc'};border:1px solid ${zc}40;color:${zc}">${d.zona}</span>
          <span class="list-item-total">${fmt(total)}</span></div>`;
      div.onclick=()=>selectUnit(name); ul.appendChild(div);
    });
}
function filterUnitList(){ renderUnitList(ico('uListSrch').value.toLowerCase()); }
function selectUnit(name){
  curUnit=name; uSc=3; uSd=-1;
  document.querySelectorAll('.list-item').forEach(el=>
    el.classList.toggle('on', el.querySelector('.list-item-name')&&el.querySelector('.list-item-name').textContent===name));
  renderUnitDetail(name);
}
function renderUnitDetail(name){
  const d=UNITS_DATA[name]; if(!d) return;
  const vacs=d.vacunas||{}, names=Object.keys(vacs).sort();
  const gT=names.reduce((s,n)=>s+vacs[n].total,0);
  const gU=names.reduce((s,n)=>s+vacs[n].unidad,0);
  const gA=names.reduce((s,n)=>s+vacs[n].almacen,0);
  const gZ=names.filter(n=>vacs[n].total===0).length;
  const zc=ZONE_COLORS[d.zona]||'#64748b';
  const zclt=ZONE_COLORS_LT[d.zona]||'#f8fafc';
  const det=ico('uDetail');
  det.innerHTML=`
    <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800;color:var(--slat)">${name}</div>
      <span style="font-size:.73rem;font-weight:600;padding:3px 10px;border-radius:12px;
        background:${zclt};border:1px solid ${zc}40;color:${zc}">${d.zona}</span>
    </div>
    <div class="mini-stats stats">
      <div class="stat"><div class="stat-ico ico-g" style="font-size:18px">📦</div>
        <div class="stat-info"><div class="stat-lbl">Total</div><div class="stat-val">${fmt(gT)}</div>
          <div class="stat-sub">Unidad + Almacén</div></div></div>
      <div class="stat"><div class="stat-ico ico-b" style="font-size:18px">🏥</div>
        <div class="stat-info"><div class="stat-lbl">En Unidad</div><div class="stat-val">${fmt(gU)}</div>
          <div class="stat-sub">Stock en unidad</div></div></div>
      <div class="stat"><div class="stat-ico ico-g" style="font-size:18px">🏬</div>
        <div class="stat-info"><div class="stat-lbl">En Almacén</div><div class="stat-val">${fmt(gA)}</div>
          <div class="stat-sub">Stock OOAD</div></div></div>
      <div class="stat"><div class="stat-ico ico-r" style="font-size:18px">⚠️</div>
        <div class="stat-info"><div class="stat-lbl">Sin Stock</div>
          <div class="stat-val" style="color:var(--red)">${gZ}</div>
          <div class="stat-sub">Vacunas en cero</div></div></div>
    </div>
    <div class="detail-grid">
      <div class="tpanel" style="overflow:hidden">
        <div class="ttoolbar"><div class="ttoolbar-title">📊 Distribución</div></div>
        <div style="padding:14px;display:flex;flex-direction:column;gap:10px;overflow-y:auto;flex:1">
          <div class="pie-wrap" style="height:180px">
            <canvas id="uPie" width="180" height="180"></canvas>
            <div class="pie-center"><div class="pie-num">${fmt(gT)}</div><div class="pie-lbl">Total</div></div>
          </div>
          <div class="legend" id="uLegend" style="max-height:none"></div>
        </div>
      </div>
      <div class="tpanel">
        <div class="ttoolbar"><div class="ttoolbar-title">📋 Por Vacuna</div>
          <div class="search">${searchIco()}
            <input type="text" id="uSrch" placeholder="Buscar…" oninput="uFilterTable()">
          </div>
        </div>
        <div class="tscroll"><table>
          <thead><tr>
            <th onclick="uSort(0)" id="uth0">Vacuna <span class="si">↕</span></th>
            <th onclick="uSort(1)" id="uth1" style="text-align:right">En Unidad <span class="si">↕</span></th>
            <th onclick="uSort(2)" id="uth2" style="text-align:right">En Almacén <span class="si">↕</span></th>
            <th onclick="uSort(3)" id="uth3" style="text-align:right">Total <span class="si">↕</span></th>
            <th>Estado</th>
          </tr></thead><tbody id="uTbody"></tbody>
        </table></div>
      </div>
    </div>`;
  const pn=names.filter(n=>vacs[n].total>0);
  const pv=pn.map(n=>vacs[n].total);
  const pc=pn.map((_,i)=>COLORS[i%COLORS.length]);
  if(uChart) uChart.destroy();
  uChart=new Chart(ico('uPie').getContext('2d'),{type:'doughnut',
    data:{labels:pn.length?pn:['Sin datos'],datasets:[{data:pv.length?pv:[1],
      backgroundColor:pc.length?pc:['#e2e8f0'],borderWidth:2,borderColor:'#fff'}]},
    options:{cutout:'60%',responsive:false,plugins:{legend:{display:false},
      tooltip:{callbacks:{label:c=>` ${c.label}: ${fmt(c.parsed)}`}}}}});
  const leg=ico('uLegend');
  names.forEach(n=>{const ci=pn.indexOf(n);const col=ci>=0?pc[ci]:'#cbd5e1';
    const el=document.createElement('div');el.className='leg-row';
    el.innerHTML=`<div class="leg-dot" style="background:${col}"></div>
      <div class="leg-name" title="${n}">${n}</div><div class="leg-val">${fmt(vacs[n].total)}</div>`;
    leg.appendChild(el);});
  uRows=names.map(n=>({name:n,...vacs[n]})); uRenderTable();
}
function uRenderTable(){
  const key=['name','unidad','almacen','total'][uSc];
  uRows.sort((a,b)=>key==='name'?uSd*a.name.localeCompare(b.name,'es'):uSd*(a[key]-b[key]));
  const q=(ico('uSrch')||{value:''}).value.toLowerCase();
  const fr=q?uRows.filter(r=>r.name.toLowerCase().includes(q)):uRows;
  const tb=ico('uTbody'); if(!tb) return; tb.innerHTML='';
  fr.forEach(r=>{const tr=document.createElement('tr');
    if(r.total===0) tr.classList.add('zero-row');
    tr.innerHTML=`<td class="vname">${r.name}</td><td class="num">${fmt(r.unidad)}</td>
      <td class="num">${fmt(r.almacen)}</td><td class="tot">${fmt(r.total)}</td><td>${badge(r.total)}</td>`;
    tb.appendChild(tr);});
}
function uFilterTable(){uRenderTable();}
function uSort(col){
  if(uSc===col)uSd*=-1;else{uSc=col;uSd=col===0?1:-1;}
  document.querySelectorAll('#uDetail thead th').forEach(t=>t.classList.remove('sorted'));
  const th=ico('uth'+col);if(th)th.classList.add('sorted'); uRenderTable();
}

// ══════════════════════════════════════════════════════════════
//  VACCINE VIEW
// ══════════════════════════════════════════════════════════════
let curVac=null, vSc=3, vSd=-1, vRows=[];
const VAC_COLORS = COLORS; // reuse palette

function buildVacList(){ renderVacList(''); }
function renderVacList(q){
  const vl=ico('vList'); vl.innerHTML='';
  Object.entries(VACCINES_DATA)
    .filter(([k])=>!q||k.toLowerCase().includes(q))
    .forEach(([vac,d],i)=>{
      const total=Object.values(d.zonas).reduce((s,z)=>s+z.total,0);
      const col=VAC_COLORS[i%VAC_COLORS.length];
      const div=document.createElement('div'); div.className='vac-item'+(curVac===vac?' on':'');
      div.innerHTML=`<div class="vac-item-dot" style="background:${col}"></div>
        <div class="vac-item-name">${vac}</div>
        <div class="vac-item-total">${fmt(total)}</div>`;
      div.onclick=()=>selectVac(vac); vl.appendChild(div);
    });
}
function filterVacList(){ renderVacList(ico('vListSrch').value.toLowerCase()); }

function selectVac(vac){
  curVac=vac; vSc=3; vSd=-1;
  document.querySelectorAll('.vac-item').forEach(el=>
    el.classList.toggle('on', el.querySelector('.vac-item-name').textContent===vac));
  renderVacDetail(vac);
}

function renderVacDetail(vac){
  const d=VACCINES_DATA[vac]; if(!d) return;
  const zonas=d.zonas||{}, units=d.unidades||[];
  const gT=Object.values(zonas).reduce((s,z)=>s+z.total,0);
  const gU=Object.values(zonas).reduce((s,z)=>s+z.unidad,0);
  const gA=Object.values(zonas).reduce((s,z)=>s+z.almacen,0);
  const gZ=Object.values(zonas).filter(z=>z.total===0).length;
  const maxZoneTotal=Math.max(...Object.values(zonas).map(z=>z.total),1);

  // Build zone bars
  let zoneBars='';
  ZONES_LIST.forEach(z=>{
    if(!zonas[z]) return;
    const zv=zonas[z]; const pct=Math.round(zv.total/maxZoneTotal*100);
    const zc=ZONE_COLORS[z]||'#64748b';
    zoneBars+=`<div class="zone-bar-row">
      <div class="zone-bar-label">${z}</div>
      <div class="zone-bar-track">
        <div class="zone-bar-fill" style="width:${pct}%;background:${zc}">
          ${pct>12?`<span class="zone-bar-val">${fmt(zv.total)}</span>`:''}
        </div>
      </div>
      ${pct<=12?`<span style="font-size:.72rem;font-weight:700;color:${zc};min-width:44px">${fmt(zv.total)}</span>`:''}
    </div>`;
  });

  const det=ico('vDetail');
  det.innerHTML=`
    <!-- title -->
    <div style="display:flex;align-items:center;gap:12px;flex-shrink:0">
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;color:var(--slat)">💉 ${vac}</div>
      <span style="font-size:.73rem;font-weight:600;padding:3px 11px;border-radius:12px;
        background:var(--grn-lt);border:1px solid var(--grn-rg);color:var(--grn)">${units.length} unidades</span>
    </div>

    <!-- stats -->
    <div class="mini-stats stats">
      <div class="stat"><div class="stat-ico ico-g" style="font-size:18px">📦</div>
        <div class="stat-info"><div class="stat-lbl">Total Nacional</div><div class="stat-val">${fmt(gT)}</div>
          <div class="stat-sub">Todas las zonas</div></div></div>
      <div class="stat"><div class="stat-ico ico-b" style="font-size:18px">🏥</div>
        <div class="stat-info"><div class="stat-lbl">En Unidades</div><div class="stat-val">${fmt(gU)}</div>
          <div class="stat-sub">Stock en unidad</div></div></div>
      <div class="stat"><div class="stat-ico ico-g" style="font-size:18px">🏬</div>
        <div class="stat-info"><div class="stat-lbl">En Almacenes</div><div class="stat-val">${fmt(gA)}</div>
          <div class="stat-sub">Stock OOAD</div></div></div>
      <div class="stat"><div class="stat-ico ico-v" style="font-size:18px">🗺️</div>
        <div class="stat-info"><div class="stat-lbl">Zonas Activas</div>
          <div class="stat-val" style="color:var(--vio)">${Object.keys(zonas).length}</div>
          <div class="stat-sub">Con existencias</div></div></div>
    </div>

    <!-- detail grid -->
    <div class="vac-detail-grid">

      <!-- LEFT: por zona (barras + tabla) -->
      <div class="tpanel">
        <div class="ttoolbar"><div class="ttoolbar-title">🗺️ Distribución por Zona</div></div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:16px;overflow-y:auto;flex:1">
          <div class="zone-bars">${zoneBars}</div>
          <table>
            <thead><tr>
              <th style="cursor:default">Zona</th>
              <th style="text-align:right;cursor:default">En Unidad</th>
              <th style="text-align:right;cursor:default">En Almacén</th>
              <th style="text-align:right;cursor:default">Total</th>
            </tr></thead>
            <tbody>
              ${ZONES_LIST.filter(z=>zonas[z]).map(z=>{
                const zv=zonas[z]; const zc=ZONE_COLORS[z]||'#64748b'; const zclt=ZONE_COLORS_LT[z]||'#f8fafc';
                return `<tr>
                  <td><span style="font-size:.75rem;font-weight:600;padding:2px 9px;border-radius:10px;
                    background:${zclt};border:1px solid ${zc}40;color:${zc}">${z}</span></td>
                  <td class="num">${fmt(zv.unidad)}</td>
                  <td class="num">${fmt(zv.almacen)}</td>
                  <td class="tot">${fmt(zv.total)}</td>
                </tr>`;}).join('')}
            </tbody>
          </table>
        </div>
      </div>

      <!-- RIGHT: por unidad -->
      <div class="tpanel">
        <div class="ttoolbar"><div class="ttoolbar-title">🏥 Detalle por Unidad Médica</div>
          <div class="search">${searchIco()}
            <input type="text" id="vSrch" placeholder="Buscar unidad…" oninput="vFilterTable()">
          </div>
        </div>
        <div class="tscroll"><table>
          <thead><tr>
            <th onclick="vSort(0)" id="vth0">Unidad Médica <span class="si">↕</span></th>
            <th onclick="vSort(1)" id="vth1">Zona <span class="si">↕</span></th>
            <th onclick="vSort(2)" id="vth2" style="text-align:right">En Unidad <span class="si">↕</span></th>
            <th onclick="vSort(3)" id="vth3" style="text-align:right">En Almacén <span class="si">↕</span></th>
            <th onclick="vSort(4)" id="vth4" style="text-align:right">Total <span class="si">↕</span></th>
            <th>Estado</th>
          </tr></thead>
          <tbody id="vTbody"></tbody>
        </table></div>
      </div>
    </div>`;

  vRows=units.map(u=>({name:u.nombre,zona:u.zona,unidad:u.unidad,almacen:u.almacen,total:u.total}));
  vRenderTable();
}

function vRenderTable(){
  const key=['name','zona','unidad','almacen','total'][vSc];
  vRows.sort((a,b)=>{
    if(key==='name'||key==='zona') return vSd*a[key].localeCompare(b[key],'es');
    return vSd*(a[key]-b[key]);
  });
  const q=(ico('vSrch')||{value:''}).value.toLowerCase();
  const fr=q?vRows.filter(r=>r.name.toLowerCase().includes(q)||r.zona.toLowerCase().includes(q)):vRows;
  const tb=ico('vTbody'); if(!tb) return; tb.innerHTML='';
  fr.forEach(r=>{
    const tr=document.createElement('tr');
    if(r.total===0) tr.classList.add('zero-row');
    const zc=ZONE_COLORS[r.zona]||'#64748b'; const zclt=ZONE_COLORS_LT[r.zona]||'#f8fafc';
    tr.innerHTML=`<td class="vname">${r.name}</td>
      <td><span style="font-size:.72rem;font-weight:600;padding:2px 8px;border-radius:10px;
        background:${zclt};border:1px solid ${zc}40;color:${zc}">${r.zona}</span></td>
      <td class="num">${fmt(r.unidad)}</td>
      <td class="num">${fmt(r.almacen)}</td>
      <td class="tot">${fmt(r.total)}</td>
      <td>${badge(r.total)}</td>`;
    tb.appendChild(tr);});
}
function vFilterTable(){vRenderTable();}
function vSort(col){
  if(vSc===col)vSd*=-1;else{vSc=col;vSd=(col<=1)?1:-1;}
  document.querySelectorAll('#vDetail thead th').forEach(t=>t.classList.remove('sorted'));
  const th=ico('vth'+col);if(th)th.classList.add('sorted'); vRenderTable();
}

// ── INIT ─────────────────────────────────────────────────────
renderZone();
</script>
</body>
</html>
