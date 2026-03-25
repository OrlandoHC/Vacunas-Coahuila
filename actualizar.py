"""
actualizar.py — Control de Vacunas Grupo 020
Genera: cache_data.json | cache_units.json | cache_vaccines.json

Uso:    python actualizar.py
Req:    pip install openpyxl
"""
import sys, json, re
from pathlib import Path

try:
    import openpyxl
except ImportError:
    print("ERROR: pip install openpyxl"); sys.exit(1)

SCRIPT_DIR   = Path(__file__).parent
DEFAULT_XLSX = SCRIPT_DIR / "Control_Existencias_por_Unidad.xlsx"
OUT_ZONES    = SCRIPT_DIR / "cache_data.json"
OUT_UNITS    = SCRIPT_DIR / "cache_units.json"
OUT_VACCINES = SCRIPT_DIR / "cache_vaccines.json"
DICC_XLSX    = SCRIPT_DIR / "dicc_unidades_coahuila.xlsx"

COL_UNIT = 5;  COL_DESC = 17;  COL_EX_U = 34;  COL_EX_A = 36

ZONES = ['CARBONÍFERA', 'CENTRO', 'LAGUNA', 'NORTE', 'SUR']

MANUAL_MAP = {
    'Direccion UMF 14':              'CARBONÍFERA',
    'Direccion UMF 15':              'LAGUNA',
    'Direccion UMF 26':              'LAGUNA',
    'Direccion UMF 61':              'CARBONÍFERA',
    'Direccion UMF 62':              'CARBONÍFERA',
    'Direccion UMF 64':              'CARBONÍFERA',
    'Direccion UMF 88':              'CENTRO',
    'Direccion UMR-EM 52':           'SUR',
    'Farmacia HGSZ-MF 13':          'CARBONÍFERA',
    'Farmacia HGSZ-MF 21':          'LAGUNA',
    'Farmacia HGSZ-MF 6':           'NORTE',
    'Farmacia HGZ 11':              'LAGUNA',
    'Farmacia HGZ-MF 24':           'LAGUNA',
    'Farmacia HRS Ramos':           'NORTE',
    'Farmacia HRS San Buenaventura':'NORTE',
}

UNIT_ZONE: dict = {}


# ── 1. Cargar diccionario ──────────────────────────────────────
def load_dict():
    if not DICC_XLSX.exists():
        print("  AVISO: dicc_unidades_coahuila.xlsx no encontrado → usando complemento manual.")
        UNIT_ZONE.update(MANUAL_MAP)
        return
    wb   = openpyxl.load_workbook(DICC_XLSX, data_only=True)
    rows = list(wb.active.iter_rows(values_only=True))
    wb.close()
    hdr  = [str(c).upper().strip() if c else '' for c in rows[0]]
    cu = cz = None
    for i, h in enumerate(hdr):
        if cu is None and 'NOMBRE' in h: cu = i
        if cz is None and 'ZONA'   in h: cz = i
    n = 0
    for row in rows[1:]:
        u = str(row[cu] or '').strip()
        z = str(row[cz] or '').strip().upper().replace('CARBONIFERA', 'CARBONÍFERA')
        if u and z in ZONES:
            UNIT_ZONE[u] = z
            n += 1
    for u, z in MANUAL_MAP.items():
        if u not in UNIT_ZONE:
            UNIT_ZONE[u] = z
    print(f"  {n} del diccionario + {len(MANUAL_MAP)} del complemento manual.")


def get_zone(unit: str) -> str:
    if unit in UNIT_ZONE:
        return UNIT_ZONE[unit]
    m = re.search(r'\b(\d+)\b', unit)
    if m:
        for k, v in UNIT_ZONE.items():
            if re.search(rf'\b{m.group(1)}\b', k):
                return v
    return 'CENTRO'


# ── 2. Nombre corto de vacuna ──────────────────────────────────
def short_name(desc: str) -> str:
    d = str(desc).upper()
    if 'ANTIALACRAN'   in d: return 'ANTIALACRAN'
    if 'ANTIARACNIDO'  in d: return 'ANTIARACNIDO F/I'
    if 'ANTICORALILLO' in d: return 'ANTICORALILLO'
    if 'ANTIVIPERINO'  in d: return 'ANTIVIPERINO'
    if 'BCG'           in d: return 'BCG'
    if 'COVID' in d or ('ARNM' in d and 'SARS' in d): return 'COVID-19'
    if 'DOBLE VIRAL'   in d: return 'DOBLE VIRAL (SR)'
    if ('ANTIPERTUSSIS' in d and 'DIFTERICO' in d and 'TETANICO' in d
            and 'HEXAVALENTE' not in d and 'HEPATITIS' not in d): return 'DPT'
    if 'ANTITETANICA'  in d and 'INMUNOGLOBULINA' in d: return 'GAMA ANITETA...'
    if 'ANTIRRABICA'   in d and 'INMUNOGLOBULINA' in d: return 'GAMA ANITRA...'
    if 'HEPATITIS A'   in d and ('ADULTO' in d or '1.0 ML' in d): return 'HEPATITIS A AD...'
    if 'HEPATITIS A'   in d: return 'HEPATITIS A'
    if 'HEPATITIS B'   in d and 'DIFTERIA' not in d: return 'HEPATITIS B'
    if 'DIFTERIA'      in d and 'HEPATITIS B' in d: return 'HEXAVALENTE'
    if 'INFLUENZA'     in d: return 'INFLUENZA TET...'
    if '13-VALENTE'    in d: return 'NEUMO 13 V'
    if '20-VALENTE'    in d: return 'NEUMO 20 V'
    if 'ANTINEUMOCOCCICA' in d or 'NEUMOCOCICA' in d: return 'NEUMO 23 V'
    if 'ROTAVIRUS'     in d: return 'ROTAVIRUS'
    if 'TOXOIDES TETANICO Y DIFTERICO' in d: return 'TD'
    if 'TOSFERINA ACELULAR' in d: return 'TDPA'
    if 'TRIPLE VIRAL'  in d: return 'TRIPLE VIRAL (S...)'
    if 'PAPILOMA' in d or 'VPH' in d: return 'VHP 9 V'
    if 'VITAMINA A'    in d: return 'VITAMINA A'
    if 'ANTIRRABICA'   in d: return 'VACUNA ANTIR...'
    if 'VARICELA'      in d: return 'VARICELA'
    return d[:20]


# ── 3. Procesar Excel → zonas + unidades ──────────────────────
def process(xlsx_path: Path):
    print(f"  Leyendo: {xlsx_path.name}")
    wb = openpyxl.load_workbook(xlsx_path, data_only=True)
    all_rows = list(wb.active.iter_rows(values_only=True))
    wb.close()

    zones_data = {z: {'vacunas': {}, 'unidades': []} for z in ZONES}
    units_data: dict = {}
    n = 0

    for row in all_rows[2:]:
        try:
            unit  = str(row[COL_UNIT] or '').strip()
            desc  = str(row[COL_DESC] or '').strip()
            ex_u  = float(row[COL_EX_U] or 0)
            ex_a  = float(row[COL_EX_A] or 0)
        except Exception:
            continue
        if not unit or not desc or unit == 'None':
            continue

        zone = get_zone(unit)
        vac  = short_name(desc)

        # — por zona —
        if unit not in zones_data[zone]['unidades']:
            zones_data[zone]['unidades'].append(unit)
        if vac not in zones_data[zone]['vacunas']:
            zones_data[zone]['vacunas'][vac] = {'unidad': 0, 'almacen': 0, 'total': 0}
        zones_data[zone]['vacunas'][vac]['unidad']  += ex_u
        zones_data[zone]['vacunas'][vac]['almacen'] += ex_a
        zones_data[zone]['vacunas'][vac]['total']   += ex_u + ex_a

        # — por unidad —
        if unit not in units_data:
            units_data[unit] = {'zona': zone, 'vacunas': {}}
        if vac not in units_data[unit]['vacunas']:
            units_data[unit]['vacunas'][vac] = {'unidad': 0, 'almacen': 0, 'total': 0}
        units_data[unit]['vacunas'][vac]['unidad']  += ex_u
        units_data[unit]['vacunas'][vac]['almacen'] += ex_a
        units_data[unit]['vacunas'][vac]['total']   += ex_u + ex_a
        n += 1

    # Ordenar
    for z in ZONES:
        zones_data[z]['unidades'].sort()
        zones_data[z]['vacunas'] = {
            k: {kk: int(vv) for kk, vv in v.items()}
            for k, v in sorted(zones_data[z]['vacunas'].items())
        }
    units_data = dict(sorted(
        units_data.items(),
        key=lambda x: (ZONES.index(x[1]['zona']) if x[1]['zona'] in ZONES else 99, x[0])
    ))
    for u in units_data:
        units_data[u]['vacunas'] = {
            k: {kk: int(vv) for kk, vv in v.items()}
            for k, v in sorted(units_data[u]['vacunas'].items())
        }

    print(f"  Filas procesadas: {n} · Unidades: {len(units_data)}")
    return zones_data, units_data


# ── 4. Construir índice por vacuna ─────────────────────────────
def build_vaccines(zones_data: dict, units_data: dict) -> dict:
    vaccines: dict = {}

    for z, zd in zones_data.items():
        for vac, vals in zd['vacunas'].items():
            if vac not in vaccines:
                vaccines[vac] = {'zonas': {}, 'unidades': []}
            vaccines[vac]['zonas'][z] = {k: int(v) for k, v in vals.items()}

    for uname, ud in units_data.items():
        for vac, vals in ud['vacunas'].items():
            if vac not in vaccines:
                vaccines[vac] = {'zonas': {}, 'unidades': []}
            vaccines[vac]['unidades'].append({
                'nombre':  uname,
                'zona':    ud['zona'],
                'unidad':  int(vals['unidad']),
                'almacen': int(vals['almacen']),
                'total':   int(vals['total']),
            })

    # Ordenar unidades dentro de cada vacuna
    for vac in vaccines:
        vaccines[vac]['unidades'].sort(
            key=lambda x: (
                ZONES.index(x['zona']) if x['zona'] in ZONES else 99,
                x['nombre']
            )
        )

    return dict(sorted(vaccines.items()))


# ── Main ───────────────────────────────────────────────────────
if __name__ == '__main__':
    xlsx_path = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_XLSX

    if not xlsx_path.exists():
        print(f"\nERROR: No se encontró {xlsx_path}")
        print("Coloca 'Control_Existencias_por_Unidad.xlsx' en esta carpeta.\n")
        sys.exit(1)

    print("① Cargando diccionario de zonas...")
    load_dict()

    print("② Procesando existencias...")
    zones_data, units_data = process(xlsx_path)

    print("③ Generando índice por vacuna...")
    vaccines_data = build_vaccines(zones_data, units_data)

    print("④ Guardando archivos...")
    with open(OUT_ZONES,    'w', encoding='utf-8') as f:
        json.dump(zones_data,    f, ensure_ascii=False, indent=2)
    with open(OUT_UNITS,    'w', encoding='utf-8') as f:
        json.dump(units_data,    f, ensure_ascii=False, indent=2)
    with open(OUT_VACCINES, 'w', encoding='utf-8') as f:
        json.dump(vaccines_data, f, ensure_ascii=False, indent=2)

    print(f"\n✅  Tres archivos generados:")
    print(f"    {OUT_ZONES}")
    print(f"    {OUT_UNITS}")
    print(f"    {OUT_VACCINES}")

    print("\nResumen por zona:")
    print(f"  {'ZONA':<14} {'UMED':>5}  {'VAC':>4}  {'EN UNIDAD':>10}  {'ALMACÉN':>10}  {'TOTAL':>10}")
    print(f"  {'-'*14} {'-'*5}  {'-'*4}  {'-'*10}  {'-'*10}  {'-'*10}")
    for z in ZONES:
        tu = sum(v['unidad']  for v in zones_data[z]['vacunas'].values())
        ta = sum(v['almacen'] for v in zones_data[z]['vacunas'].values())
        tt = sum(v['total']   for v in zones_data[z]['vacunas'].values())
        print(f"  {z:<14} {len(zones_data[z]['unidades']):>5}  "
              f"{len(zones_data[z]['vacunas']):>4}  "
              f"{tu:>10,}  {ta:>10,}  {tt:>10,}")

    print("\n→ Recarga el tablero en tu navegador.")
