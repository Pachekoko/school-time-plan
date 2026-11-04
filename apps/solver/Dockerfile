FROM python:3.12-slim

COPY --from=ghcr.io/astral-sh/uv:0.13.7 /uv /uvx /bin/

WORKDIR /app
ENV UV_COMPILE_BYTECODE=1 \
    UV_LINK_MODE=copy \
    UV_PYTHON_DOWNLOADS=never

# Primero las dependencias (capa que casi no cambia), luego el código.
COPY pyproject.toml uv.lock README.md ./
RUN uv sync --frozen --no-dev --no-install-project
COPY src ./src
RUN uv sync --frozen --no-dev

RUN useradd --create-home --uid 10001 motor
USER motor
ENV PATH="/app/.venv/bin:$PATH"

EXPOSE 8000
CMD ["sh", "-c", "exec uvicorn solver.api:create_app --factory --host 0.0.0.0 --port ${PORT:-8000}"]
