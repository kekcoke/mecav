# ── Stage 1: Builder — install Python deps with build tools available ─────────
FROM python:3.11-slim AS builder

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1

# build-essential and libpq-dev are compile-time only.
# They are intentionally absent from the final runtime image.
RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        libpq-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /build

COPY services/python/requirements.txt .

# Install into an isolated prefix so only the installed packages are copied
# into the runtime image — the compiler toolchain stays behind in this stage.
RUN pip install --no-cache-dir --prefix=/install -r requirements.txt


# ── Stage 2: Protobuf compiler ────────────────────────────────────────────────
# Compile .proto stubs at build time so the runtime image carries ready-to-use
# generated code. In development the volume mount at /app/proto overlays these
# stubs and the dev command recompiles them on each start.
FROM python:3.11-slim AS proto-builder

# grpc_tools is needed here; pull it from the builder prefix.
COPY --from=builder /install /usr/local
COPY protobuf /proto

RUN mkdir -p /app/proto \
    && python -m grpc_tools.protoc \
        -I /proto \
        --python_out=/app/proto \
        --grpc_python_out=/app/proto \
        /proto/multimodal.proto \
    # Fix the generated absolute import → relative package import so the module
    # resolves correctly when proto/ is used as a package inside /app.
    && sed -i \
        's/^import multimodal_pb2/from proto import multimodal_pb2/' \
        /app/proto/multimodal_pb2_grpc.py \
    # Make proto/ a proper Python package so `from proto import ...` works.
    && touch /app/proto/__init__.py


# ── Stage 3: Final runtime image ──────────────────────────────────────────────
FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1

# libpq5 is the runtime PostgreSQL client library (not the -dev headers).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq5 \
    && rm -rf /var/lib/apt/lists/*

# Copy only the installed Python packages — no compiler, no dev headers.
COPY --from=builder /install /usr/local

WORKDIR /app

# Copy application source before proto stubs so the subsequent COPY cannot be
# shadowed by a proto/ subdirectory that may exist inside services/python/.
# Previously the order was reversed, causing the compiled stubs to be silently
# overwritten by whatever proto/ contained in the source tree.
COPY services/python/app ./app

# Proto stubs go in last, guaranteed to land on top of any source-tree proto/.
COPY --from=proto-builder /app/proto ./proto

# gRPC server: 50051 | FastAPI/uvicorn REST server: 8001.
# app.main is responsible for starting both processes.
EXPOSE 50051 8001

# Inner timeout is 4s, leaving 1s headroom against the Docker --timeout=5s.
# Previously both were 5s, creating a race where Docker could kill the check
# before Python finished its own timeout handling.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD python -c "\
import grpc; \
ch = grpc.insecure_channel('localhost:50051'); \
grpc.channel_ready_future(ch).result(timeout=4)"

# watchfiles is a dev-only hot-reload wrapper and must not be the production
# entrypoint. Use the bare module invocation so the process is PID 1 and
# receives signals correctly (SIGTERM for graceful shutdown).
CMD ["python", "-m", "app.main"]
