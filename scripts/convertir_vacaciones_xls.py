#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Convierte el informe de vacaciones de RRHH (.xls binario) a JSON para
scripts/import_vacaciones.php.

Formato esperado (hoja única):
  Período | Desde | Hasta | Comentario | Asignados | Otorgados | Saldo
  "{legajo} - {Nombre} ({dd/mm/yyyy ingreso})"           ← cabecera de empleado
  0    | ... | Saldo Inicial            | X | 0 | ...
  2025 | ... | Vacaciones Período 2025  | N | 0 | ...
  2025 | desde | hasta |               | 0 | D | saldo   ← período tomado
  "Total {legajo} - {Nombre} (...)" | saldo_final

Uso:
  python scripts/convertir_vacaciones_xls.py "informe.xls" vacaciones_2025.json

Requiere xlrd 1.2 (pip install xlrd==1.2.0). El JSON de salida contiene datos
personales: NO commitearlo (está gitignored).
"""
import json
import re
import sys

import xlrd

if len(sys.argv) < 3:
    sys.exit("Uso: convertir_vacaciones_xls.py <informe.xls> <salida.json>")

wb = xlrd.open_workbook(sys.argv[1])
sh = wb.sheet_by_index(0)

def celda(r, c):
    cell = sh.cell(r, c)
    if cell.ctype == xlrd.XL_CELL_DATE:
        return xlrd.xldate.xldate_as_datetime(cell.value, wb.datemode).strftime("%Y-%m-%d")
    return str(cell.value).strip()

empleados = []
actual = None
cab = re.compile(r"^0*(\d+)\s*-\s*(.+?)\s*\((\d{2}/\d{2}/\d{4})\)$")
for r in range(1, sh.nrows):
    a = celda(r, 0)
    if a.startswith("Total"):
        if actual is not None:
            saldo = celda(r, 1)
            actual["saldo_final"] = float(saldo) if saldo else 0.0
            empleados.append(actual)
            actual = None
        continue
    m = cab.match(a)
    if m:
        d, mth, y = m.group(3).split("/")
        actual = {
            "legajo": m.group(1),
            "nombre": m.group(2),
            "ingreso": f"{y}-{mth}-{d}",
            "periodo": None,
            "saldo_inicial": 0.0,
            "asignados": 0.0,
            "tomados": [],
            "saldo_final": None,
        }
        continue
    if actual is None:
        continue
    comentario = celda(r, 3)
    if comentario == "Saldo Inicial":
        actual["saldo_inicial"] = float(celda(r, 4) or 0)
        continue
    if comentario.startswith("Vacaciones Período"):
        actual["asignados"] = float(celda(r, 4) or 0)
        actual["periodo"] = comentario.split()[-1]
        continue
    desde, hasta = celda(r, 1), celda(r, 2)
    if desde and hasta:
        actual["tomados"].append({
            "desde": desde,
            "hasta": hasta,
            "dias": float(celda(r, 5) or 0),
        })

with open(sys.argv[2], "w", encoding="utf-8") as f:
    json.dump({"empleados": empleados}, f, ensure_ascii=False, indent=2)

print(f"{len(empleados)} empleado(s) -> {sys.argv[2]}")
for e in empleados:
    tomado = sum(t["dias"] for t in e["tomados"])
    esperado = e["saldo_inicial"] + e["asignados"] - tomado
    marca = "OK " if abs(esperado - e["saldo_final"]) < 0.01 else "AVISO: saldo no cierra"
    print(f"  [{marca}] legajo {e['legajo']} {e['nombre']}: inicial {e['saldo_inicial']} + "
          f"{e['asignados']} - {tomado} = {esperado} (informe: {e['saldo_final']})")
