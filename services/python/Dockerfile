FROM python:3.11-slim AS base
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 PIP_NO_CACHE_DIR=1
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential libpq-dev && rm -rf /var/lib/apt/lists/*
COPY requirements.txt .
RUN pip install --upgrade pip && pip install -r requirements.txt

FROM base AS proto-builder
COPY ../../protobuf /proto
RUN mkdir -p /app/proto && \
    python -m grpc_tools.protoc \
        -I /proto \
        --python_out=/app/proto \
        --grpc_python_out=/app/proto \
        /proto/multimodal.proto

FROM base AS runtime
COPY --from=proto-builder /app/proto /app/proto
COPY . /app
EXPOSE 50051
HEALTHCHECK --interval=30s --timeout=5s \
    CMD python -c "import grpc; ch=grpc.insecure_channel('localhost:50051'); grpc.channel_ready_future(ch).result(timeout=5)"
CMD ["python", "-m", "app.main"]
