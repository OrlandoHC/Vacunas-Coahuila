<?php
/**
 * Remisiones Semanales – Grupo 020  [v3 - ZipArchive nativo, sin namespace XML]
 * Lee Remisiones_Delegacion.xlsx directamente con ZipArchive (sin Composer, sin Python).
 * Cachea en rem_cache.json para no reprocesar en cada visita.
 * Si hay error de caché corrupto, borra rem_cache.json y recarga.
 */

// Borrar caché corrupto automáticamente si existe
$cacheFileEarly = __DIR__ . '/rem_cache.json';
if (file_exists($cacheFileEarly)) {
    $testCache = @json_decode(file_get_contents($cacheFileEarly), true);
    if (!$testCache) @unlink($cacheFileEarly);
}

// ── Configuración ──────────────────────────────────────────────
$xlsxFile  = __DIR__ . '/Remisiones_Delegacion.xlsx';
$diccFile  = __DIR__ . '/dicc_unidades_coahuila.xlsx';
$cacheFile = __DIR__ . '/rem_cache.json';
$ZONES     = ['CARBONÍFERA','CENTRO','LAGUNA','NORTE','SUR'];

// Columnas base-0 (dentro de la fila de datos, a partir de la fila 11)
define('C_UNIT',  5);   // NOMBRE UNIDAD
define('C_FECHA', 10);  // FECHA REMISION (serial Excel)
define('C_DESC',  20);  // DESCRIPCION ARTICULO
define('C_ENV',   22);  // CANTIDAD ENVIADA
define('C_REC',   23);  // CANTIDAD RECIBIDA
define('C_DEV',   24);  // CANTIDAD DEVUELTA
define('C_IMP',   25);  // IMPORTE ENVIADO

// ── Mapeo manual de zonas (complemento al diccionario) ─────────
$MANUAL_MAP = [
    'Direccion UMF 14'=>'CARBONÍFERA','Direccion UMF 15'=>'LAGUNA',
    'Direccion UMF 26'=>'LAGUNA','Direccion UMF 60'=>'SUR',
    'Direccion UMF 61'=>'CARBONÍFERA','Direccion UMF 62'=>'CARBONÍFERA',
    'Direccion UMF 64'=>'CARBONÍFERA','Direccion UMF 88'=>'CENTRO',
    'Direccion UMR-EM 52'=>'SUR','Farmacia HGSZ-MF 13'=>'CARBONÍFERA',
    'Farmacia HGSZ-MF 21'=>'LAGUNA','Farmacia HGSZ-MF 6'=>'NORTE',
    'Farmacia HGZ 11'=>'LAGUNA','Farmacia HGZ-MF 24'=>'LAGUNA',
    'Farmacia HRS Ramos'=>'NORTE','Farmacia HRS San Buenaventura'=>'NORTE',
    'U Med Familiar 50 Direccion de la Unidad Médica'=>'CENTRO',
    'U Med Familiar 60 Direccion de la Unidad Médica'=>'SUR',
    'U Med Familiar 62 Direccion de la Unidad Médica'=>'CARBONÍFERA',
    'U Med Familiar 64 Direccion de la Unidad Médica'=>'CARBONÍFERA',
];

// ── Leer xlsx con ZipArchive ───────────────────────────────────
// Strips XML namespaces so xpath works without prefix registration
function stripNs(string $xml): string {
    $xml = preg_replace('/\s+xmlns[^=]*="[^"]*"/', '', $xml);
    $xml = preg_replace('/<(\/?)([a-zA-Z0-9]+):/', '<$1', $xml);
    return $xml;
}

function colIndex(string $ref): int {
    preg_match('/^([A-Z]+)/', $ref, $m);
    $col = 0;
    foreach (str_split($m[1]) as $ch) {
        $col = $col * 26 + (ord($ch) - ord('A') + 1);
    }
    return $col - 1;
}

function readXlsx(string $path): array {
    if (!file_exists($path)) return [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];

    // Shared strings
    $strings = [];
    $ssRaw = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssRaw) {
        $ss = simplexml_load_string(stripNs($ssRaw));
        if ($ss) {
            foreach ($ss->xpath('//si') as $si) {
                $t = $si->xpath('t');
                if ($t && isset($t[0])) {
                    $strings[] = (string)$t[0];
                } else {
                    $parts = $si->xpath('.//t');
                    $strings[] = implode('', array_map(fn($p) => (string)$p, $parts ?: []));
                }
            }
        }
    }

    // Sheet
    $sheetRaw = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetRaw) return [];

    $sheet = simplexml_load_string(stripNs($sheetRaw));
    if (!$sheet) return [];

    $rows = [];
    foreach ($sheet->xpath('//row') as $row) {
        $rNum = (int)$row['r'];
        $cols = [];
        foreach ($row->xpath('c') as $c) {
            $ref = (string)$c['r'];
            $col = colIndex($ref);
            $vNodes = $c->xpath('v');
            $val = $vNodes ? (string)$vNodes[0] : null;
            if ($val !== null && (string)$c['t'] === 's') {
                $val = $strings[(int)$val] ?? '';
            }
            $cols[$col] = $val;
        }
        $rows[$rNum] = $cols;
    }
    return $rows;
}

function colVal(array $row, int $col): ?string {
    return isset($row[$col]) ? trim((string)$row[$col]) : null;
}

function colFloat(array $row, int $col): float {
    $v = $row[$col] ?? null;
    return $v !== null ? (float)$v : 0.0;
}

// Excel serial date → Y-m-d
function excelDate(?string $serial): string {
    if (!$serial || !is_numeric($serial)) return '';
    $days = (int)floor((float)$serial);
    if ($days < 1) return '';
    $ts = ($days - 25569) * 86400;
    return date('Y-m-d', $ts);
}

function shortName(string $desc): string {
    $d = strtoupper($desc);
    if (str_contains($d,'ANTIALACRAN'))   return 'ANTIALACRAN';
    if (str_contains($d,'ANTIARACNIDO'))  return 'ANTIARACNIDO F/I';
    if (str_contains($d,'ANTICORALILLO'))return 'ANTICORALILLO';
    if (str_contains($d,'ANTIVIPERINO')) return 'ANTIVIPERINO';
    if (str_contains($d,'BCG'))           return 'BCG';
    if (str_contains($d,'COVID')||
       (str_contains($d,'ARNM')&&str_contains($d,'SARS'))) return 'COVID-19';
    if (str_contains($d,'DOBLE VIRAL'))   return 'DOBLE VIRAL (SR)';
    if (str_contains($d,'ANTIPERTUSSIS')&&str_contains($d,'DIFTERICO')&&
        str_contains($d,'TETANICO')&&!str_contains($d,'HEXAVALENTE')&&
        !str_contains($d,'HEPATITIS'))    return 'DPT';
    if (str_contains($d,'ANTITETANICA')&&str_contains($d,'INMUNOGLOBULINA')) return 'GAMA ANITETA...';
    if (str_contains($d,'ANTIRRABICA')&&str_contains($d,'INMUNOGLOBULINA'))  return 'GAMA ANITRA...';
    if (str_contains($d,'HEPATITIS A')&&
       (str_contains($d,'ADULTO')||str_contains($d,'1.0 ML'))) return 'HEPATITIS A AD...';
    if (str_contains($d,'HEPATITIS A'))   return 'HEPATITIS A';
    if (str_contains($d,'HEPATITIS B')&&!str_contains($d,'DIFTERIA')) return 'HEPATITIS B';
    if (str_contains($d,'DIFTERIA')&&str_contains($d,'HEPATITIS B'))  return 'HEXAVALENTE';
    if (str_contains($d,'INFLUENZA'))     return 'INFLUENZA TET...';
    if (str_contains($d,'13-VALENTE'))    return 'NEUMO 13 V';
    if (str_contains($d,'20-VALENTE'))    return 'NEUMO 20 V';
    if (str_contains($d,'ANTINEUMOCOCCICA')||str_contains($d,'NEUMOCOCICA')) return 'NEUMO 23 V';
    if (str_contains($d,'ROTAVIRUS'))     return 'ROTAVIRUS';
    if (str_contains($d,'TOXOIDES TETANICO Y DIFTERICO')) return 'TD';
    if (str_contains($d,'TOSFERINA ACELULAR')) return 'TDPA';
    if (str_contains($d,'TRIPLE VIRAL'))  return 'TRIPLE VIRAL (S...)';
    if (str_contains($d,'PAPILOMA')||str_contains($d,'VPH')) return 'VHP 9 V';
    if (str_contains($d,'VITAMINA A'))    return 'VITAMINA A';
    if (str_contains($d,'ANTIRRABICA'))   return 'VACUNA ANTIR...';
    if (str_contains($d,'VARICELA'))      return 'VARICELA';
    return mb_substr($desc,0,20);
}

// ── Cargar diccionario de zonas ────────────────────────────────
function loadDict(string $diccFile, array $MANUAL_MAP, array $ZONES): array {
    $map = $MANUAL_MAP;
    if (!file_exists($diccFile)) return $map;
    $rows = readXlsx($diccFile);
    if (!$rows) return $map;
    // Header in first non-empty row
    $hdrRow = null;
    foreach ($rows as $r) { if ($r) { $hdrRow = $r; break; } }
    if (!$hdrRow) return $map;
    $cu = $cz = null;
    foreach ($hdrRow as $i => $h) {
        $hu = strtoupper((string)$h);
        if ($cu===null && str_contains($hu,'NOMBRE')) $cu = $i;
        if ($cz===null && str_contains($hu,'ZONA'))   $cz = $i;
    }
    if ($cu===null || $cz===null) return $map;
    $first = true;
    foreach ($rows as $row) {
        if ($first) { $first=false; continue; }
        $u = trim((string)($row[$cu]??''));
        $z = strtoupper(trim((string)($row[$cz]??'')));
        $z = str_replace('CARBONIFERA','CARBONÍFERA',$z);
        if ($u && in_array($z,$ZONES) && !isset($map[$u])) $map[$u] = $z;
    }
    return $map;
}

function getZone(string $unit, array $map, array $ZONES): string {
    if (isset($map[$unit])) return $map[$unit];
    preg_match('/\b(\d+)\b/', $unit, $m);
    if ($m) {
        foreach ($map as $k=>$v) {
            if (preg_match('/\b'.$m[1].'\b/', $k)) return $v;
        }
    }
    return 'CENTRO';
}

// ── Proceso principal ──────────────────────────────────────────
function buildData(string $xlsxFile, string $diccFile, array $MANUAL_MAP, array $ZONES): array {
    $unitMap = loadDict($diccFile, $MANUAL_MAP, $ZONES);
    $rows    = readXlsx($xlsxFile);

    $zonesD = array_fill_keys($ZONES, ['vacunas'=>[],'unidades'=>[]]);
    $unitsD = [];
    $vacsD  = [];
    $fechas = [];

    // Data rows start at row 11 (key 11 in 1-based Excel rows)
    foreach ($rows as $rNum => $row) {
        if ($rNum < 11) continue;
        $unit = colVal($row, C_UNIT);
        $desc = colVal($row, C_DESC);
        if (!$unit || !$desc) continue;

        $zone  = getZone($unit, $unitMap, $ZONES);
        $vac   = shortName($desc);
        $env   = colFloat($row, C_ENV);
        $rec   = colFloat($row, C_REC);
        $dev   = colFloat($row, C_DEV);
        $imp   = colFloat($row, C_IMP);
        $fraw  = colVal($row, C_FECHA);
        $fecha = excelDate($fraw);
        if ($fecha) $fechas[] = $fecha;

        // — zona —
        if (!in_array($unit, $zonesD[$zone]['unidades']))
            $zonesD[$zone]['unidades'][] = $unit;
        if (!isset($zonesD[$zone]['vacunas'][$vac]))
            $zonesD[$zone]['vacunas'][$vac] = ['enviada'=>0,'recibida'=>0,'devuelta'=>0,'importe'=>0];
        $zonesD[$zone]['vacunas'][$vac]['enviada']  += $env;
        $zonesD[$zone]['vacunas'][$vac]['recibida'] += $rec;
        $zonesD[$zone]['vacunas'][$vac]['devuelta'] += $dev;
        $zonesD[$zone]['vacunas'][$vac]['importe']  += $imp;

        // — unidad —
        if (!isset($unitsD[$unit]))
            $unitsD[$unit] = ['zona'=>$zone,'vacunas'=>[]];
        if (!isset($unitsD[$unit]['vacunas'][$vac]))
            $unitsD[$unit]['vacunas'][$vac] = ['enviada'=>0,'recibida'=>0,'devuelta'=>0,'importe'=>0];
        $unitsD[$unit]['vacunas'][$vac]['enviada']  += $env;
        $unitsD[$unit]['vacunas'][$vac]['recibida'] += $rec;
        $unitsD[$unit]['vacunas'][$vac]['devuelta'] += $dev;
        $unitsD[$unit]['vacunas'][$vac]['importe']  += $imp;

        // — vacuna —
        if (!isset($vacsD[$vac])) $vacsD[$vac] = ['zonas'=>[],'unidades'=>[]];
        if (!isset($vacsD[$vac]['zonas'][$zone]))
            $vacsD[$vac]['zonas'][$zone] = ['enviada'=>0,'recibida'=>0,'devuelta'=>0,'importe'=>0];
        $vacsD[$vac]['zonas'][$zone]['enviada']  += $env;
        $vacsD[$vac]['zonas'][$zone]['recibida'] += $rec;
        $vacsD[$vac]['zonas'][$zone]['devuelta'] += $dev;
        $vacsD[$vac]['zonas'][$zone]['importe']  += $imp;
    }

    // Ordenar y redondear
    foreach ($ZONES as $z) {
        sort($zonesD[$z]['unidades']);
        ksort($zonesD[$z]['vacunas']);
        foreach ($zonesD[$z]['vacunas'] as &$v)
            foreach ($v as &$n) $n = (int)round($n);
    }
    uksort($unitsD, fn($a,$b)=>strcmp(
        array_search($unitsD[$a]['zona'],$ZONES).'|'.$a,
        array_search($unitsD[$b]['zona'],$ZONES).'|'.$b
    ));
    foreach ($unitsD as &$ud) {
        ksort($ud['vacunas']);
        foreach ($ud['vacunas'] as &$v) foreach ($v as &$n) $n = (int)round($n);
    }

    // Build per-vaccine units list
    foreach ($vacsD as $vac => &$vd) {
        ksort($vd['zonas']);
        foreach ($vd['zonas'] as &$v) foreach ($v as &$n) $n = (int)round($n);
        $vd['unidades'] = [];
        foreach ($unitsD as $uname => $ud) {
            if (isset($ud['vacunas'][$vac])) {
                $vd['unidades'][] = array_merge(
                    ['nombre'=>$uname,'zona'=>$ud['zona']],
                    $ud['vacunas'][$vac]
                );
            }
        }
        usort($vd['unidades'], fn($a,$b)=>
            array_search($a['zona'],$ZONES) <=> array_search($b['zona'],$ZONES) ?:
            strcmp($a['nombre'],$b['nombre'])
        );
    }
    ksort($vacsD);

    $fechas = array_filter(array_unique($fechas));
    sort($fechas);
    $meta = [
        'fecha_inicio'   => $fechas[0] ?? '',
        'fecha_fin'      => end($fechas) ?: '',
        'total_piezas'   => (int)array_sum(array_map(
            fn($ud)=>array_sum(array_column($ud['vacunas'],'enviada')), $unitsD)),
        'total_recibidas'=> (int)array_sum(array_map(
            fn($ud)=>array_sum(array_column($ud['vacunas'],'recibida')), $unitsD)),
        'total_importe'  => round(array_sum(array_map(
            fn($ud)=>array_sum(array_column($ud['vacunas'],'importe')), $unitsD)),2),
        'total_unidades' => count($unitsD),
        'total_vacunas'  => count($vacsD),
    ];

    return compact('zonesD','unitsD','vacsD','meta');
}

// ── Cache: regenerar si el xlsx es más nuevo que el cache ──────
$error = null; $lastUpdate = null;
$zD = $uD = $vD = []; $meta = [];

if (!file_exists($xlsxFile)) {
    $error = 'No se encontró <strong>Remisiones_Delegacion.xlsx</strong> en la carpeta del proyecto.';
} else {
    $xlsxMtime = filemtime($xlsxFile);
    $useCache  = file_exists($cacheFile) && filemtime($cacheFile) >= $xlsxMtime;

    if ($useCache) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) {
            ['zonesD'=>$zD,'unitsD'=>$uD,'vacsD'=>$vD,'meta'=>$meta] = $cached;
            $lastUpdate = date('d/m/Y H:i', filemtime($cacheFile));
        } else {
            $useCache = false;
        }
    }

    if (!$useCache) {
        $result = buildData($xlsxFile, $diccFile, $MANUAL_MAP, $ZONES);
        ['zonesD'=>$zD,'unitsD'=>$uD,'vacsD'=>$vD,'meta'=>$meta] = $result;
        file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
        $lastUpdate = date('d/m/Y H:i');
    }
}

$zJ = json_encode($zD, JSON_UNESCAPED_UNICODE);
$uJ = json_encode($uD, JSON_UNESCAPED_UNICODE);
$vJ = json_encode($vD, JSON_UNESCAPED_UNICODE);
$mJ = json_encode($meta, JSON_UNESCAPED_UNICODE);

function fmtDate(string $d): string {
    if (!$d) return '';
    $p = explode('-',$d);
    return count($p)===3 ? "{$p[2]}/{$p[1]}/{$p[0]}" : $d;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Remisiones Semanales – Grupo 020</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root{
  --bg:#f1f5f9;--white:#fff;--sf2:#f8fafc;
  --brd:#e2e8f0;--brd2:#cbd5e1;
  --grn:#15803d;--grn-lt:#dcfce7;--grn-rg:#bbf7d0;
  --blu:#1d4ed8;--blu-lt:#dbeafe;--blu-rg:#bfdbfe;
  --red:#dc2626;--red-lt:#fee2e2;
  --amb:#d97706;--amb-lt:#fef3c7;
  --vio:#6d28d9;--vio-lt:#ede9fe;
  --eme:#0f766e;--eme-lt:#ccfbf1;
  --slat:#0f172a;--body:#334155;--mut:#64748b;--fnt:#94a3b8;
  --r:10px;--sh:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);
  --sh2:0 4px 16px rgba(0,0,0,.09);--hh:62px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--body);font-size:14px;min-height:100vh}
.hdr{background:var(--white);border-bottom:1px solid var(--brd);height:var(--hh);
  display:flex;align-items:center;padding:0 28px;gap:14px;
  position:sticky;top:0;z-index:200;box-shadow:var(--sh)}
.hdr-icon{width:42px;height:42px;border-radius:10px;flex-shrink:0;
  background:linear-gradient(135deg,#0284c7,#1d4ed8);
  display:flex;align-items:center;justify-content:center;font-size:22px;
  box-shadow:0 2px 8px rgba(29,78,216,.28)}
.hdr-titles{display:flex;flex-direction:column;gap:1px}
.hdr-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;color:var(--slat)}
.hdr-sub{font-size:.73rem;color:var(--mut);font-weight:500}
.hdr-sep{width:1px;height:28px;background:var(--brd);margin:0 4px}
.hdr-pill{display:flex;align-items:center;gap:7px;background:var(--blu-lt);
  border:1px solid var(--blu-rg);border-radius:20px;padding:4px 13px;
  font-size:.73rem;font-weight:600;color:var(--blu)}
.hdr-pill .dot{width:7px;height:7px;border-radius:50%;background:#60a5fa;animation:pulse 2.5s infinite}
@keyframes pulse{0%,100%{box-shadow:0 0 0 2px rgba(96,165,250,.2)}50%{box-shadow:0 0 0 5px rgba(96,165,250,.05)}}
.hdr-periodo{margin-left:auto;display:flex;flex-direction:column;align-items:flex-end;gap:1px}
.hdr-periodo .per{font-size:.8rem;font-weight:700;color:var(--slat)}
.hdr-periodo .upd{font-size:.7rem;color:var(--mut)}
.view-tabs{background:var(--white);border-bottom:2px solid var(--brd);
  display:flex;align-items:stretch;padding:0 28px}
.view-tab{font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;font-weight:700;
  padding:13px 24px;border:none;background:transparent;color:var(--mut);
  cursor:pointer;border-bottom:3px solid transparent;transition:all .18s;
  white-space:nowrap;position:relative;top:2px;display:flex;align-items:center;gap:8px}
.view-tab:hover{color:var(--body);background:var(--sf2)}
.view-tab.on{color:var(--blu);border-bottom-color:var(--blu)}
.vt-badge{font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:10px;
  background:var(--sf2);border:1px solid var(--brd);color:var(--mut)}
.view-tab.on .vt-badge{background:var(--blu-lt);border-color:var(--blu-rg);color:var(--blu)}
.zone-tabs{background:var(--white);border-bottom:1px solid var(--brd);
  display:flex;align-items:stretch;padding:0 28px;gap:2px;overflow-x:auto}
.zone-tab{font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;font-weight:700;
  letter-spacing:.03em;padding:10px 20px;border:none;background:transparent;
  color:var(--mut);cursor:pointer;border-bottom:3px solid transparent;
  transition:color .15s,border-color .15s;white-space:nowrap;position:relative;top:1px}
.zone-tab:hover{color:var(--body)}.zone-tab.on{color:var(--blu);border-bottom-color:var(--blu)}
.view{display:none}.view.on{display:block}
.main{display:grid;grid-template-columns:310px 1fr;gap:18px;
  padding:20px 28px;height:calc(100vh - var(--hh) - 46px - 44px)}
.main-full{display:flex;flex-direction:column;gap:14px;
  padding:20px 28px;height:calc(100vh - var(--hh) - 46px)}
.stats{grid-column:1/-1;display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
.stat{background:var(--white);border:1px solid var(--brd);border-radius:var(--r);
  padding:14px 16px;display:flex;align-items:flex-start;gap:12px;
  box-shadow:var(--sh);transition:box-shadow .18s}
.stat:hover{box-shadow:var(--sh2)}
.stat-ico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;
  justify-content:center;font-size:20px;flex-shrink:0}
.ico-g{background:var(--grn-lt)}.ico-b{background:var(--blu-lt)}
.ico-a{background:var(--amb-lt)}.ico-e{background:var(--eme-lt)}.ico-v{background:var(--vio-lt)}
.stat-info{display:flex;flex-direction:column;gap:2px;min-width:0}
.stat-lbl{font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--mut)}
.stat-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.45rem;font-weight:800;
  color:var(--slat);line-height:1.1;white-space:nowrap}
.stat-sub{font-size:.68rem;color:var(--fnt)}
.left{display:flex;flex-direction:column;gap:14px;min-height:0;overflow:hidden}
.card{background:var(--white);border:1px solid var(--brd);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden}
.card-h{padding:12px 16px;border-bottom:1px solid var(--brd);display:flex;align-items:center;gap:8px}
.card-h-lbl{font-family:'Plus Jakarta Sans',sans-serif;font-size:.78rem;font-weight:700;
  color:var(--slat);text-transform:uppercase;letter-spacing:.05em}
.card-b{padding:14px 16px}
.pie-wrap{position:relative;display:flex;align-items:center;justify-content:center;height:196px}
.pie-center{position:absolute;text-align:center;pointer-events:none}
.pie-num{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:800;color:var(--slat);line-height:1}
.pie-lbl{font-size:.66rem;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}
.legend{display:flex;flex-direction:column;gap:3px;overflow-y:auto;padding:0 2px}
.leg-row{display:flex;align-items:center;gap:9px;padding:5px 7px;border-radius:6px;transition:background .12s}
.leg-row:hover{background:var(--sf2)}
.leg-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.leg-name{flex:1;font-size:.77rem;color:var(--body);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.leg-val{font-size:.82rem;font-weight:700;color:var(--blu);min-width:44px;text-align:right;font-variant-numeric:tabular-nums}
.units{display:flex;flex-wrap:wrap;gap:5px;max-height:96px;overflow-y:auto}
.unit-chip{background:var(--sf2);border:1px solid var(--brd);border-radius:6px;padding:3px 9px;font-size:.7rem;color:var(--mut)}
.tpanel{background:var(--white);border:1px solid var(--brd);border-radius:var(--r);
  box-shadow:var(--sh);display:flex;flex-direction:column;overflow:hidden;min-height:0}
.ttoolbar{display:flex;align-items:center;gap:10px;padding:12px 18px;
  border-bottom:1px solid var(--brd);flex-shrink:0;flex-wrap:wrap}
.ttoolbar-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;
  font-weight:700;color:var(--slat);text-transform:uppercase;letter-spacing:.05em;flex:1}
.search{display:flex;align-items:center;gap:7px;background:var(--sf2);
  border:1px solid var(--brd);border-radius:8px;padding:6px 12px;transition:border-color .15s}
.search:focus-within{border-color:var(--blu)}
.search svg{color:var(--fnt);flex-shrink:0}
.search input{border:none;background:transparent;font-family:'Inter',sans-serif;
  font-size:.83rem;color:var(--body);outline:none;width:160px}
.search input::placeholder{color:var(--fnt)}
.tscroll{overflow-y:auto;flex:1}
table{width:100%;border-collapse:collapse}
thead tr{position:sticky;top:0;background:var(--sf2);z-index:1}
thead th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:600;
  letter-spacing:.05em;text-transform:uppercase;color:var(--mut);
  border-bottom:1px solid var(--brd);cursor:pointer;user-select:none;white-space:nowrap;transition:color .12s}
thead th:hover{color:var(--body)}thead th.sorted{color:var(--blu)}
thead th .si{font-size:.65rem;margin-left:3px;opacity:.45}thead th.sorted .si{opacity:1}
tbody tr{border-bottom:1px solid var(--brd);transition:background .1s}
tbody tr:last-child{border-bottom:none}tbody tr:hover{background:#eff6ff}
td{padding:9px 14px;font-size:.83rem;color:var(--body)}
td.vname{font-weight:500;color:var(--slat)}
td.num{font-size:.9rem;font-weight:600;text-align:right;font-variant-numeric:tabular-nums}
td.env{font-size:.95rem;font-weight:700;text-align:right;font-variant-numeric:tabular-nums;color:var(--blu)}
td.imp{font-size:.82rem;font-weight:600;text-align:right;font-variant-numeric:tabular-nums;color:var(--mut)}
.badge{display:inline-flex;align-items:center;font-size:.69rem;font-weight:600;padding:2px 8px;border-radius:20px}
.b-ok{background:var(--grn-lt);color:var(--grn)}.b-pen{background:var(--amb-lt);color:var(--amb)}.b-no{background:var(--red-lt);color:var(--red)}
.two-col{display:grid;grid-template-columns:280px 1fr;gap:18px;flex:1;min-height:0;overflow:hidden}
.list-panel{background:var(--white);border:1px solid var(--brd);border-radius:var(--r);
  box-shadow:var(--sh);display:flex;flex-direction:column;overflow:hidden}
.list-head{padding:12px 14px;border-bottom:1px solid var(--brd);flex-shrink:0}
.list-search{display:flex;align-items:center;gap:6px;background:var(--sf2);
  border:1px solid var(--brd);border-radius:7px;padding:5px 10px;transition:border-color .15s;margin-top:8px}
.list-search:focus-within{border-color:var(--blu)}
.list-search svg{color:var(--fnt);flex-shrink:0}
.list-search input{border:none;background:transparent;font-family:'Inter',sans-serif;font-size:.82rem;color:var(--body);outline:none;width:100%}
.list-scroll{overflow-y:auto;flex:1}
.list-item{display:flex;flex-direction:column;gap:3px;padding:10px 14px;
  border-bottom:1px solid var(--brd);cursor:pointer;transition:background .12s}
.list-item:last-child{border-bottom:none}.list-item:hover{background:var(--sf2)}
.list-item.on{background:#eff6ff;border-left:3px solid var(--blu)}
.list-item-name{font-size:.82rem;font-weight:600;color:var(--slat)}
.list-item-meta{display:flex;align-items:center;gap:6px}
.zone-pill{font-size:.68rem;font-weight:600;padding:1px 7px;border-radius:10px}
.list-item-total{font-size:.75rem;font-weight:700;color:var(--blu);margin-left:auto}
.detail-col{display:flex;flex-direction:column;gap:14px;min-height:0;overflow:hidden}
.mini-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;flex-shrink:0}
.mini-stats .stat{padding:11px 13px}.mini-stats .stat-val{font-size:1.25rem}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;flex:1;min-height:0;overflow:hidden}
.vac-grid{display:grid;grid-template-columns:260px 1fr;gap:18px;flex:1;min-height:0;overflow:hidden}
.vac-detail{display:flex;flex-direction:column;gap:14px;min-height:0;overflow:hidden}
.vac-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;flex:1;min-height:0;overflow:hidden}
.vac-item{display:flex;align-items:center;gap:10px;padding:10px 14px;
  border-bottom:1px solid var(--brd);cursor:pointer;transition:background .12s}
.vac-item:last-child{border-bottom:none}.vac-item:hover{background:var(--sf2)}
.vac-item.on{background:#eff6ff;border-left:3px solid var(--blu)}
.vac-item-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.vac-item-name{flex:1;font-size:.82rem;font-weight:600;color:var(--slat)}
.vac-item-total{font-size:.78rem;font-weight:700;color:var(--blu);font-variant-numeric:tabular-nums}
.zone-bars{display:flex;flex-direction:column;gap:8px;padding:4px 0}
.zone-bar-row{display:flex;align-items:center;gap:10px}
.zone-bar-label{font-size:.76rem;font-weight:600;color:var(--body);width:100px;flex-shrink:0;text-align:right}
.zone-bar-track{flex:1;background:var(--sf2);border-radius:4px;height:20px;overflow:hidden;border:1px solid var(--brd)}
.zone-bar-fill{height:100%;border-radius:4px;transition:width .5s ease;display:flex;align-items:center;padding:0 8px}
.zone-bar-val{font-size:.72rem;font-weight:700;color:#fff;white-space:nowrap}
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;
  height:100%;gap:12px;color:var(--fnt)}
.empty-state .es-icon{font-size:3rem;opacity:.35}
.empty-state .es-txt{font-size:.9rem;font-weight:500}
.err-wrap{padding:32px}
.err-box{background:#fff5f5;border:1px solid #fecaca;border-radius:var(--r);
  padding:24px 28px;color:var(--body);line-height:1.8;font-size:.9rem}
.err-box strong{color:var(--red)}
.err-box code{background:var(--sf2);border:1px solid var(--brd);padding:1px 7px;border-radius:4px;font-size:.85em;color:var(--blu)}
.err-box ol{margin-top:12px;padding-left:22px;color:var(--mut)}
::-webkit-scrollbar{width:5px;height:5px}::-webkit-scrollbar-track{background:transparent}
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
<header class="hdr">
  <div class="hdr-icon">📦</div>
  <div class="hdr-titles">
    <div class="hdr-title">Remisiones Semanales</div>
    <div class="hdr-sub">Grupo 020 · Delegación Coahuila · OOAD → Unidades Médicas</div>
  </div>
  <div class="hdr-sep"></div>
  <div class="hdr-pill"><div class="dot"></div>IMSS · Semanal</div>
  <?php if(!empty($meta)): ?>
  <div class="hdr-periodo">
    <div class="per">📅 <?=fmtDate($meta['fecha_inicio'])?> — <?=fmtDate($meta['fecha_fin'])?></div>
    <?php if($lastUpdate): ?><div class="upd">Procesado: <?=htmlspecialchars($lastUpdate)?></div><?php endif ?>
  </div>
  <?php endif ?>
</header>

<?php if($error): ?>
<div class="err-wrap"><div class="err-box">
  <strong>⚠️ Datos no disponibles</strong><br><?=$error?>
  <ol>
    <li>Coloca <code>Remisiones_Delegacion.xlsx</code> en la carpeta del proyecto</li>
    <li>Vuelve a hacer deploy en Railway (el PHP genera los datos automáticamente al cargar)</li>
  </ol>
</div></div>
<?php else: ?>

<nav class="view-tabs">
  <button class="view-tab on" id="vtab-zona" onclick="sv('zona')">🗺️ Por Zona <span class="vt-badge"><?=count($ZONES)?></span></button>
  <button class="view-tab" id="vtab-unit" onclick="sv('unit')">🏥 Por Unidad Médica <span class="vt-badge"><?=count($uD)?></span></button>
  <button class="view-tab" id="vtab-vac"  onclick="sv('vac')">💉 Por Vacuna <span class="vt-badge"><?=count($vD)?></span></button>
</nav>

<!-- VISTA 1: ZONA -->
<div class="view on" id="view-zona">
  <nav class="zone-tabs">
    <?php foreach($ZONES as $i=>$z): ?>
    <button class="zone-tab <?=$i===0?'on':''?>" onclick="switchZone(this,'<?=htmlspecialchars($z,ENT_QUOTES)?>')">
      <?=htmlspecialchars($z)?>
    </button>
    <?php endforeach ?>
  </nav>
  <div class="main">
    <div class="stats">
      <div class="stat"><div class="stat-ico ico-b">📦</div>
        <div class="stat-info"><div class="stat-lbl">Piezas Enviadas</div><div class="stat-val" id="zs-env">—</div><div class="stat-sub">Total remisionado</div></div></div>
      <div class="stat"><div class="stat-ico ico-g">✅</div>
        <div class="stat-info"><div class="stat-lbl">Piezas Recibidas</div><div class="stat-val" id="zs-rec">—</div><div class="stat-sub">Confirmadas en unidad</div></div></div>
      <div class="stat"><div class="stat-ico ico-a">⏳</div>
        <div class="stat-info"><div class="stat-lbl">Pendientes</div><div class="stat-val" id="zs-pen" style="color:var(--amb)">—</div><div class="stat-sub">En tránsito</div></div></div>
      <div class="stat"><div class="stat-ico ico-e">💊</div>
        <div class="stat-info"><div class="stat-lbl">Vacunas</div><div class="stat-val" id="zs-vac">—</div><div class="stat-sub">Tipos distintos</div></div></div>
      <div class="stat"><div class="stat-ico ico-v">💰</div>
        <div class="stat-info"><div class="stat-lbl">Importe</div><div class="stat-val" id="zs-imp" style="font-size:1.1rem">—</div><div class="stat-sub">Valor enviado</div></div></div>
    </div>
    <div class="left">
      <div class="card" style="flex:0 0 auto">
        <div class="card-h"><span>📊</span><span class="card-h-lbl">Piezas por Vacuna</span></div>
        <div class="card-b"><div class="pie-wrap">
          <canvas id="zPie" width="196" height="196"></canvas>
          <div class="pie-center"><div class="pie-num" id="zPieNum">—</div><div class="pie-lbl">Enviadas</div></div>
        </div></div>
      </div>
      <div class="card" style="flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden">
        <div class="card-h"><span>🗂️</span><span class="card-h-lbl">Vacunas</span></div>
        <div class="card-b" style="flex:1;overflow:hidden;display:flex;flex-direction:column;padding-top:10px">
          <div class="legend" id="zLegend" style="max-height:none;flex:1"></div>
        </div>
      </div>
      <div class="card" style="flex:0 0 auto">
        <div class="card-h"><span>🏥</span><span class="card-h-lbl">Unidades — Zona <span id="zLabel"></span></span></div>
        <div class="card-b"><div class="units" id="zUnits"></div></div>
      </div>
    </div>
    <div class="tpanel">
      <div class="ttoolbar">
        <div class="ttoolbar-title">Detalle por Vacuna</div>
        <div class="search"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" id="zSrch" placeholder="Buscar vacuna…" oninput="zRT()"></div>
      </div>
      <div class="tscroll"><table>
        <thead><tr>
          <th onclick="zS(0)" id="zth0">Vacuna <span class="si">↕</span></th>
          <th onclick="zS(1)" id="zth1" style="text-align:right">Enviadas <span class="si">↕</span></th>
          <th onclick="zS(2)" id="zth2" style="text-align:right">Recibidas <span class="si">↕</span></th>
          <th onclick="zS(3)" id="zth3" style="text-align:right">Pendientes <span class="si">↕</span></th>
          <th onclick="zS(4)" id="zth4" style="text-align:right">Importe <span class="si">↕</span></th>
          <th>Estado</th>
        </tr></thead><tbody id="zTbody"></tbody>
      </table></div>
    </div>
  </div>
</div>

<!-- VISTA 2: UNIDAD -->
<div class="view" id="view-unit">
  <div class="main-full"><div class="two-col">
    <div class="list-panel">
      <div class="list-head">
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;font-weight:700;color:var(--slat);text-transform:uppercase;letter-spacing:.05em">🏥 Unidades Médicas</div>
        <div class="list-search"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" id="uListSrch" placeholder="Filtrar…" oninput="rUL()"></div>
      </div>
      <div class="list-scroll" id="uList"></div>
    </div>
    <div class="detail-col" id="uDetail">
      <div class="empty-state"><div class="es-icon">🏥</div><div class="es-txt">Selecciona una unidad médica</div></div>
    </div>
  </div></div>
</div>

<!-- VISTA 3: VACUNA -->
<div class="view" id="view-vac">
  <div class="main-full"><div class="vac-grid">
    <div class="list-panel">
      <div class="list-head">
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:.82rem;font-weight:700;color:var(--slat);text-transform:uppercase;letter-spacing:.05em">💉 Vacunas</div>
        <div class="list-search"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" id="vListSrch" placeholder="Filtrar…" oninput="rVL()"></div>
      </div>
      <div class="list-scroll" id="vList"></div>
    </div>
    <div class="vac-detail" id="vDetail">
      <div class="empty-state"><div class="es-icon">💉</div><div class="es-txt">Selecciona una vacuna</div></div>
    </div>
  </div></div>
</div>

<?php endif ?>
<script>
const ZD=<?=$zJ?>,UD=<?=$uJ?>,VD=<?=$vJ?>,META=<?=$mJ?>;
const ZONES_LIST=<?=json_encode($ZONES,JSON_UNESCAPED_UNICODE)?>;
const COLORS=['#1d4ed8','#2563eb','#3b82f6','#60a5fa','#93c5fd','#0891b2','#0f766e','#059669','#16a34a','#65a30d','#ca8a04','#d97706','#ea580c','#dc2626','#e11d48','#9333ea','#7c3aed'];
const ZC={'CARBONÍFERA':'#854d0e','CENTRO':'#1d4ed8','LAGUNA':'#0f766e','NORTE':'#6d28d9','SUR':'#be185d'};
const ZCL={'CARBONÍFERA':'#fef3c7','CENTRO':'#dbeafe','LAGUNA':'#ccfbf1','NORTE':'#ede9fe','SUR':'#fce7f3'};
const $=id=>document.getElementById(id);
const fmt=n=>Number(n||0).toLocaleString('es-MX');
const fmtM=n=>'$'+Number(n||0).toLocaleString('es-MX',{minimumFractionDigits:0,maximumFractionDigits:0});
const sIco=()=>`<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>`;
const stBadge=(e,r)=>e-r<=0?'<span class="badge b-ok">Recibido</span>':r>0?'<span class="badge b-pen">Parcial</span>':'<span class="badge b-no">Pendiente</span>';
const vInited={zona:true,unit:false,vac:false};
function sv(v){
  document.querySelectorAll('.view').forEach(e=>e.classList.remove('on'));
  document.querySelectorAll('.view-tab').forEach(e=>e.classList.remove('on'));
  $('view-'+v).classList.add('on');$('vtab-'+v).classList.add('on');
  if(!vInited[v]){vInited[v]=true;if(v==='unit')bUL();if(v==='vac')bVL();}
}
// ZONA
let zChart=null,curZ=ZONES_LIST[0],zSc=1,zSd=-1,zRows=[];
function switchZone(btn,z){
  document.querySelectorAll('.zone-tab').forEach(b=>b.classList.remove('on'));
  btn.classList.add('on');curZ=z;zSc=1;zSd=-1;
  document.querySelectorAll('#view-zona thead th').forEach(t=>t.classList.remove('sorted'));
  $('zSrch').value='';rZ();
}
function rZ(){
  const d=ZD[curZ]||{vacunas:{},unidades:[]},vacs=d.vacunas||{},unis=d.unidades||[];
  const names=Object.keys(vacs).sort();
  const gE=names.reduce((s,n)=>s+(vacs[n].enviada||0),0);
  const gR=names.reduce((s,n)=>s+(vacs[n].recibida||0),0);
  const gI=names.reduce((s,n)=>s+(vacs[n].importe||0),0);
  $('zs-env').textContent=fmt(gE);$('zs-rec').textContent=fmt(gR);
  $('zs-pen').textContent=fmt(gE-gR);$('zs-vac').textContent=names.length;
  $('zs-imp').textContent=fmtM(gI);$('zPieNum').textContent=fmt(gE);$('zLabel').textContent=curZ;
  const pn=names.filter(n=>vacs[n].enviada>0),pv=pn.map(n=>vacs[n].enviada),pc=pn.map((_,i)=>COLORS[i%COLORS.length]);
  const ctx=$('zPie').getContext('2d');if(zChart)zChart.destroy();
  zChart=new Chart(ctx,{type:'doughnut',data:{labels:pn.length?pn:['Sin datos'],
    datasets:[{data:pv.length?pv:[1],backgroundColor:pc.length?pc:['#e2e8f0'],borderWidth:2,borderColor:'#fff'}]},
    options:{cutout:'63%',responsive:false,plugins:{legend:{display:false},
      tooltip:{callbacks:{label:c=>` ${c.label}: ${fmt(c.parsed)}`}}}}});
  const leg=$('zLegend');leg.innerHTML='';
  names.forEach(n=>{const ci=pn.indexOf(n),col=ci>=0?pc[ci]:'#cbd5e1';
    const el=document.createElement('div');el.className='leg-row';
    el.innerHTML=`<div class="leg-dot" style="background:${col}"></div><div class="leg-name" title="${n}">${n}</div><div class="leg-val">${fmt(vacs[n].enviada)}</div>`;
    leg.appendChild(el);});
  const ul=$('zUnits');ul.innerHTML='';
  unis.forEach(u=>{const c=document.createElement('span');c.className='unit-chip';c.textContent=u;ul.appendChild(c);});
  zRows=names.map(n=>({name:n,...vacs[n],pendiente:(vacs[n].enviada||0)-(vacs[n].recibida||0)}));zRT();
}
function zRT(){
  const key=['name','enviada','recibida','pendiente','importe'][zSc];
  zRows.sort((a,b)=>key==='name'?zSd*a.name.localeCompare(b.name,'es'):zSd*(a[key]-b[key]));
  const q=$('zSrch').value.toLowerCase(),fr=q?zRows.filter(r=>r.name.toLowerCase().includes(q)):zRows;
  const tb=$('zTbody');tb.innerHTML='';
  fr.forEach(r=>{const tr=document.createElement('tr');
    tr.innerHTML=`<td class="vname">${r.name}</td><td class="env">${fmt(r.enviada)}</td>
      <td class="num">${fmt(r.recibida)}</td>
      <td class="num" style="color:${r.pendiente>0?'var(--amb)':'var(--grn)'}">${fmt(r.pendiente)}</td>
      <td class="imp">${fmtM(r.importe)}</td><td>${stBadge(r.enviada,r.recibida)}</td>`;
    tb.appendChild(tr);});
}
function zS(col){
  if(zSc===col)zSd*=-1;else{zSc=col;zSd=col===0?1:-1;}
  document.querySelectorAll('#view-zona thead th').forEach(t=>t.classList.remove('sorted'));
  $('zth'+col).classList.add('sorted');zRT();
}
// UNIT
let curU=null,uChart=null,uSc=1,uSd=-1,uRows=[];
function bUL(){rUL();}
function rUL(){
  const q=$('uListSrch').value.toLowerCase(),ul=$('uList');ul.innerHTML='';
  Object.entries(UD).filter(([k])=>!q||k.toLowerCase().includes(q)).forEach(([name,d])=>{
    const env=Object.values(d.vacunas).reduce((s,v)=>s+(v.enviada||0),0);
    const zc=ZC[d.zona]||'#64748b';
    const div=document.createElement('div');div.className='list-item'+(curU===name?' on':'');
    div.innerHTML=`<div class="list-item-name">${name}</div><div class="list-item-meta">
      <span class="zone-pill" style="background:${ZCL[d.zona]||'#f8fafc'};border:1px solid ${zc}40;color:${zc}">${d.zona}</span>
      <span class="list-item-total">${fmt(env)} pzas</span></div>`;
    div.onclick=()=>selU(name);ul.appendChild(div);});
}
function selU(name){
  curU=name;uSc=1;uSd=-1;
  document.querySelectorAll('.list-item').forEach(el=>el.classList.toggle('on',el.querySelector('.list-item-name')?.textContent===name));
  rUD(name);
}
function rUD(name){
  const d=UD[name];if(!d)return;
  const vacs=d.vacunas||{},names=Object.keys(vacs).sort();
  const gE=names.reduce((s,n)=>s+(vacs[n].enviada||0),0);
  const gR=names.reduce((s,n)=>s+(vacs[n].recibida||0),0);
  const gI=names.reduce((s,n)=>s+(vacs[n].importe||0),0);
  const zc=ZC[d.zona]||'#64748b',zcl=ZCL[d.zona]||'#f8fafc';
  const det=$('uDetail');
  det.innerHTML=`<div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
    <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800;color:var(--slat)">${name}</div>
    <span style="font-size:.73rem;font-weight:600;padding:3px 10px;border-radius:12px;background:${zcl};border:1px solid ${zc}40;color:${zc}">${d.zona}</span></div>
  <div class="mini-stats stats">
    <div class="stat"><div class="stat-ico ico-b" style="font-size:18px">📦</div>
      <div class="stat-info"><div class="stat-lbl">Enviadas</div><div class="stat-val">${fmt(gE)}</div><div class="stat-sub">Piezas remisionadas</div></div></div>
    <div class="stat"><div class="stat-ico ico-g" style="font-size:18px">✅</div>
      <div class="stat-info"><div class="stat-lbl">Recibidas</div><div class="stat-val">${fmt(gR)}</div><div class="stat-sub">Confirmadas</div></div></div>
    <div class="stat"><div class="stat-ico ico-a" style="font-size:18px">⏳</div>
      <div class="stat-info"><div class="stat-lbl">Pendientes</div><div class="stat-val" style="color:var(--amb)">${fmt(gE-gR)}</div><div class="stat-sub">En tránsito</div></div></div>
    <div class="stat"><div class="stat-ico ico-v" style="font-size:18px">💰</div>
      <div class="stat-info"><div class="stat-lbl">Importe</div><div class="stat-val" style="font-size:1rem">${fmtM(gI)}</div><div class="stat-sub">Valor enviado</div></div></div>
  </div>
  <div class="detail-grid">
    <div class="tpanel" style="overflow:hidden">
      <div class="ttoolbar"><div class="ttoolbar-title">📊 Por Vacuna</div></div>
      <div style="padding:14px;display:flex;flex-direction:column;gap:10px;overflow-y:auto;flex:1">
        <div class="pie-wrap" style="height:180px"><canvas id="uPie" width="180" height="180"></canvas>
          <div class="pie-center"><div class="pie-num">${fmt(gE)}</div><div class="pie-lbl">Enviadas</div></div></div>
        <div class="legend" id="uLeg" style="max-height:none"></div>
      </div>
    </div>
    <div class="tpanel">
      <div class="ttoolbar"><div class="ttoolbar-title">📋 Detalle</div>
        <div class="search">${sIco()}<input type="text" id="uSrch" placeholder="Buscar…" oninput="uRT()"></div></div>
      <div class="tscroll"><table><thead><tr>
        <th onclick="uSrt(0)" id="uth0">Vacuna <span class="si">↕</span></th>
        <th onclick="uSrt(1)" id="uth1" style="text-align:right">Enviadas <span class="si">↕</span></th>
        <th onclick="uSrt(2)" id="uth2" style="text-align:right">Recibidas <span class="si">↕</span></th>
        <th onclick="uSrt(3)" id="uth3" style="text-align:right">Pendientes <span class="si">↕</span></th>
        <th onclick="uSrt(4)" id="uth4" style="text-align:right">Importe <span class="si">↕</span></th>
        <th>Estado</th>
      </tr></thead><tbody id="uTbody"></tbody></table></div>
    </div>
  </div>`;
  const pn=names.filter(n=>vacs[n].enviada>0),pv=pn.map(n=>vacs[n].enviada),pc=pn.map((_,i)=>COLORS[i%COLORS.length]);
  if(uChart)uChart.destroy();
  uChart=new Chart($('uPie').getContext('2d'),{type:'doughnut',
    data:{labels:pn.length?pn:['Sin datos'],datasets:[{data:pv.length?pv:[1],backgroundColor:pc.length?pc:['#e2e8f0'],borderWidth:2,borderColor:'#fff'}]},
    options:{cutout:'60%',responsive:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>` ${c.label}: ${fmt(c.parsed)}`}}}}});
  const leg=$('uLeg');
  names.forEach(n=>{const ci=pn.indexOf(n),col=ci>=0?pc[ci]:'#cbd5e1';
    const el=document.createElement('div');el.className='leg-row';
    el.innerHTML=`<div class="leg-dot" style="background:${col}"></div><div class="leg-name" title="${n}">${n}</div><div class="leg-val">${fmt(vacs[n].enviada)}</div>`;
    leg.appendChild(el);});
  uRows=names.map(n=>({name:n,...vacs[n],pendiente:(vacs[n].enviada||0)-(vacs[n].recibida||0)}));uRT();
}
function uRT(){
  const key=['name','enviada','recibida','pendiente','importe'][uSc];
  uRows.sort((a,b)=>key==='name'?uSd*a.name.localeCompare(b.name,'es'):uSd*(a[key]-b[key]));
  const q=($('uSrch')||{value:''}).value.toLowerCase(),fr=q?uRows.filter(r=>r.name.toLowerCase().includes(q)):uRows;
  const tb=$('uTbody');if(!tb)return;tb.innerHTML='';
  fr.forEach(r=>{const tr=document.createElement('tr');
    tr.innerHTML=`<td class="vname">${r.name}</td><td class="env">${fmt(r.enviada)}</td>
      <td class="num">${fmt(r.recibida)}</td>
      <td class="num" style="color:${r.pendiente>0?'var(--amb)':'var(--grn)'}">${fmt(r.pendiente)}</td>
      <td class="imp">${fmtM(r.importe)}</td><td>${stBadge(r.enviada,r.recibida)}</td>`;
    tb.appendChild(tr);});
}
function uSrt(col){
  if(uSc===col)uSd*=-1;else{uSc=col;uSd=col===0?1:-1;}
  document.querySelectorAll('#uDetail thead th').forEach(t=>t.classList.remove('sorted'));
  const th=$('uth'+col);if(th)th.classList.add('sorted');uRT();
}
// VAC
let curV=null,vSc=1,vSd=-1,vRows=[];
function bVL(){rVL();}
function rVL(){
  const q=$('vListSrch').value.toLowerCase(),vl=$('vList');vl.innerHTML='';
  Object.entries(VD).filter(([k])=>!q||k.toLowerCase().includes(q)).forEach(([vac,d],i)=>{
    const env=Object.values(d.zonas||{}).reduce((s,z)=>s+(z.enviada||0),0);
    const col=COLORS[i%COLORS.length];
    const div=document.createElement('div');div.className='vac-item'+(curV===vac?' on':'');
    div.innerHTML=`<div class="vac-item-dot" style="background:${col}"></div>
      <div class="vac-item-name">${vac}</div><div class="vac-item-total">${fmt(env)}</div>`;
    div.onclick=()=>selV(vac);vl.appendChild(div);});
}
function selV(vac){
  curV=vac;vSc=1;vSd=-1;
  document.querySelectorAll('.vac-item').forEach(el=>el.classList.toggle('on',el.querySelector('.vac-item-name').textContent===vac));
  rVD(vac);
}
function rVD(vac){
  const d=VD[vac];if(!d)return;
  const zonas=d.zonas||{},units=d.unidades||[];
  const gE=Object.values(zonas).reduce((s,z)=>s+(z.enviada||0),0);
  const gR=Object.values(zonas).reduce((s,z)=>s+(z.recibida||0),0);
  const gI=Object.values(zonas).reduce((s,z)=>s+(z.importe||0),0);
  const maxE=Math.max(...Object.values(zonas).map(z=>z.enviada||0),1);
  let bars='';
  ZONES_LIST.forEach(z=>{if(!zonas[z])return;
    const zv=zonas[z],pct=Math.round((zv.enviada||0)/maxE*100),zc=ZC[z]||'#64748b';
    bars+=`<div class="zone-bar-row"><div class="zone-bar-label">${z}</div>
      <div class="zone-bar-track"><div class="zone-bar-fill" style="width:${pct}%;background:${zc}">
        ${pct>12?`<span class="zone-bar-val">${fmt(zv.enviada)}</span>`:''}</div></div>
      ${pct<=12?`<span style="font-size:.72rem;font-weight:700;color:${zc};min-width:44px">${fmt(zv.enviada)}</span>`:''}</div>`;});
  const det=$('vDetail');
  det.innerHTML=`<div style="display:flex;align-items:center;gap:12px;flex-shrink:0">
    <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;color:var(--slat)">💉 ${vac}</div>
    <span style="font-size:.73rem;font-weight:600;padding:3px 11px;border-radius:12px;background:var(--blu-lt);border:1px solid var(--blu-rg);color:var(--blu)">${units.length} unidades</span></div>
  <div class="mini-stats stats">
    <div class="stat"><div class="stat-ico ico-b" style="font-size:18px">📦</div>
      <div class="stat-info"><div class="stat-lbl">Total Enviadas</div><div class="stat-val">${fmt(gE)}</div><div class="stat-sub">Todas las zonas</div></div></div>
    <div class="stat"><div class="stat-ico ico-g" style="font-size:18px">✅</div>
      <div class="stat-info"><div class="stat-lbl">Total Recibidas</div><div class="stat-val">${fmt(gR)}</div><div class="stat-sub">Confirmadas</div></div></div>
    <div class="stat"><div class="stat-ico ico-a" style="font-size:18px">⏳</div>
      <div class="stat-info"><div class="stat-lbl">Pendientes</div><div class="stat-val" style="color:var(--amb)">${fmt(gE-gR)}</div><div class="stat-sub">Sin confirmar</div></div></div>
    <div class="stat"><div class="stat-ico ico-v" style="font-size:18px">💰</div>
      <div class="stat-info"><div class="stat-lbl">Importe</div><div class="stat-val" style="font-size:1rem">${fmtM(gI)}</div><div class="stat-sub">Valor total</div></div></div>
  </div>
  <div class="vac-detail-grid">
    <div class="tpanel">
      <div class="ttoolbar"><div class="ttoolbar-title">🗺️ Por Zona</div></div>
      <div style="padding:16px;display:flex;flex-direction:column;gap:16px;overflow-y:auto;flex:1">
        <div class="zone-bars">${bars}</div>
        <table><thead><tr>
          <th style="cursor:default">Zona</th>
          <th style="text-align:right;cursor:default">Enviadas</th>
          <th style="text-align:right;cursor:default">Recibidas</th>
          <th style="text-align:right;cursor:default">Importe</th>
        </tr></thead><tbody>
          ${ZONES_LIST.filter(z=>zonas[z]).map(z=>{const zv=zonas[z],zc=ZC[z]||'#64748b',zcl=ZCL[z]||'#f8fafc';
            return`<tr><td><span style="font-size:.75rem;font-weight:600;padding:2px 9px;border-radius:10px;
              background:${zcl};border:1px solid ${zc}40;color:${zc}">${z}</span></td>
              <td class="env">${fmt(zv.enviada)}</td><td class="num">${fmt(zv.recibida)}</td>
              <td class="imp">${fmtM(zv.importe)}</td></tr>`;}).join('')}
        </tbody></table>
      </div>
    </div>
    <div class="tpanel">
      <div class="ttoolbar"><div class="ttoolbar-title">🏥 Por Unidad Médica</div>
        <div class="search">${sIco()}<input type="text" id="vSrch" placeholder="Buscar…" oninput="vRT()"></div></div>
      <div class="tscroll"><table><thead><tr>
        <th onclick="vSrt(0)" id="vth0">Unidad <span class="si">↕</span></th>
        <th onclick="vSrt(1)" id="vth1">Zona <span class="si">↕</span></th>
        <th onclick="vSrt(2)" id="vth2" style="text-align:right">Enviadas <span class="si">↕</span></th>
        <th onclick="vSrt(3)" id="vth3" style="text-align:right">Recibidas <span class="si">↕</span></th>
        <th onclick="vSrt(4)" id="vth4" style="text-align:right">Importe <span class="si">↕</span></th>
        <th>Estado</th>
      </tr></thead><tbody id="vTbody"></tbody></table></div>
    </div>
  </div>`;
  vRows=units.map(u=>({...u,pendiente:(u.enviada||0)-(u.recibida||0)}));vRT();
}
function vRT(){
  const key=['nombre','zona','enviada','recibida','importe'][vSc];
  vRows.sort((a,b)=>['nombre','zona'].includes(key)?vSd*a[key].localeCompare(b[key],'es'):vSd*(a[key]-b[key]));
  const q=($('vSrch')||{value:''}).value.toLowerCase();
  const fr=q?vRows.filter(r=>r.nombre.toLowerCase().includes(q)||r.zona.toLowerCase().includes(q)):vRows;
  const tb=$('vTbody');if(!tb)return;tb.innerHTML='';
  fr.forEach(r=>{const zc=ZC[r.zona]||'#64748b',zcl=ZCL[r.zona]||'#f8fafc';
    const tr=document.createElement('tr');
    tr.innerHTML=`<td class="vname">${r.nombre}</td>
      <td><span style="font-size:.72rem;font-weight:600;padding:2px 8px;border-radius:10px;
        background:${zcl};border:1px solid ${zc}40;color:${zc}">${r.zona}</span></td>
      <td class="env">${fmt(r.enviada)}</td><td class="num">${fmt(r.recibida)}</td>
      <td class="imp">${fmtM(r.importe)}</td><td>${stBadge(r.enviada,r.recibida)}</td>`;
    tb.appendChild(tr);});
}
function vSrt(col){
  if(vSc===col)vSd*=-1;else{vSc=col;vSd=col<=1?1:-1;}
  document.querySelectorAll('#vDetail thead th').forEach(t=>t.classList.remove('sorted'));
  const th=$('vth'+col);if(th)th.classList.add('sorted');vRT();
}
rZ();
</script>
</body>
</html>
