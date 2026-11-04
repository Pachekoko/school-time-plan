"""Benchmark del motor con la escuela demo (o con otro problema en JSON).

Uso: `uv run python bench/run.py [--tiempo 60] [--workers 8] [--archivo problema.json]`
"""

import argparse
import json
import sys
from pathlib import Path

from solver.contract import SolveRequest
from solver.instance import build_instance
from solver.solve import solve
from solver.validator import validate_schedule

sys.path.insert(0, str(Path(__file__).resolve().parent))
from generate_demo import build  # noqa: E402


def main(argv: list[str] | None = None) -> int:
    args = argparse.ArgumentParser(description=__doc__)
    args.add_argument("--tiempo", type=float, default=60, help="límite en segundos")
    args.add_argument("--workers", type=int, default=8)
    args.add_argument("--archivo", type=Path, help="SolveRequest en JSON (por defecto: la demo)")
    a = args.parse_args(argv)

    if a.archivo:
        req = SolveRequest.model_validate(json.loads(a.archivo.read_text(encoding="utf-8")))
    else:
        req = build(42)
    req.parametros.tiempo_limite_s = a.tiempo
    req.parametros.num_workers = a.workers

    inst = build_instance(req)
    resp = solve(req)
    e = resp.estadisticas
    print(
        f"Problema: {len(inst.grupos)} grupos, {len(inst.profesores)} profesores, "
        f"{len(inst.cursos)} cursos, {len(inst.bloques)} bloques"
    )
    print(f"Estado:   {resp.estado} (CP-SAT {e.estado_cp_sat}) en {e.tiempo_s:.1f} s")
    print(f"Modelo:   {e.variables} variables, {e.restricciones} restricciones")
    print(f"Puntaje:  {resp.puntaje}")
    print("\n| Regla | Crudo | Peso | Penalización |\n|---|---|---|---|")
    for regla, p in resp.desglose.items():
        print(f"| {regla} | {p.crudo} | {p.peso} | {p.penalizacion} |")
    for d in resp.diagnostico:
        print(f"- [{d.codigo}] {d.mensaje}")
    if resp.estado == "ok":
        violaciones = validate_schedule(inst, resp.sesiones)
        print(f"\nValidador independiente: {len(violaciones)} violaciones")
        return 1 if violaciones else 0
    return 0 if resp.estado == "factible_parcial" else 1


if __name__ == "__main__":
    raise SystemExit(main())
