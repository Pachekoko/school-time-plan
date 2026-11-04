"""Genera los JSON Schema de `contracts/` desde los modelos del contrato.

Uso: `uv run python -m solver.export_schema`
"""

import json
from pathlib import Path

from pydantic import BaseModel

from solver.contract import SolveRequest, SolveResponse, ValidateRequest, ValidateResponse

CONTRACTS = Path(__file__).resolve().parents[4] / "contracts"

ESQUEMAS: dict[str, type[BaseModel]] = {
    "solve-request.schema.json": SolveRequest,
    "solve-response.schema.json": SolveResponse,
    "validate-request.schema.json": ValidateRequest,
    "validate-response.schema.json": ValidateResponse,
}


def write_all(dest: Path) -> None:
    dest.mkdir(parents=True, exist_ok=True)
    for nombre, modelo in ESQUEMAS.items():
        texto = json.dumps(modelo.model_json_schema(), indent=2, ensure_ascii=False)
        (dest / nombre).write_text(texto + "\n", encoding="utf-8")


def main() -> None:
    write_all(CONTRACTS)
    print(f"Esquemas escritos en {CONTRACTS}")


if __name__ == "__main__":
    main()
