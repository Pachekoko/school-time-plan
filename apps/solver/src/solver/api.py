"""API HTTP del motor.

Uso: `SOLVER_TOKEN=... uvicorn solver.api:create_app --factory`
"""

import os
import secrets

from fastapi import Depends, FastAPI, Header, HTTPException, Request
from fastapi.responses import JSONResponse

from solver.contract import (
    SCHEMA_VERSION,
    SolveRequest,
    SolveResponse,
    ValidateRequest,
    ValidateResponse,
)
from solver.instance import InstanceError
from solver.solve import solve, validate


def create_app(token: str | None = None) -> FastAPI:
    """La app exige `SOLVER_TOKEN` (o `token`): sin él no arranca, no hay modo sin token."""
    token = token if token is not None else os.environ.get("SOLVER_TOKEN")
    if not token:
        raise RuntimeError("Define SOLVER_TOKEN para arrancar el motor")
    esperado = f"Bearer {token}"

    def autorizar(authorization: str | None = Header(default=None)) -> None:
        if authorization is None or not secrets.compare_digest(authorization, esperado):
            raise HTTPException(status_code=401, detail="Token inválido")

    app = FastAPI(title="Motor de horarios", version=SCHEMA_VERSION)

    @app.exception_handler(InstanceError)
    def _instancia_incoherente(_: Request, exc: InstanceError) -> JSONResponse:
        return JSONResponse(status_code=422, content={"detail": [{"msg": m} for m in exc.errores]})

    @app.get("/health")
    def health() -> dict:
        return {"status": "ok", "schema_version": SCHEMA_VERSION}

    # Funciones síncronas: FastAPI las corre en su threadpool (CP-SAT usa CPU).
    @app.post("/solve", response_model=SolveResponse, dependencies=[Depends(autorizar)])
    def resolver(req: SolveRequest) -> SolveResponse:
        return solve(req)

    @app.post("/validate", response_model=ValidateResponse, dependencies=[Depends(autorizar)])
    def validar(req: ValidateRequest) -> ValidateResponse:
        return validate(req)

    return app
