import os

laravel_env_local = """\
# ============================================================
# Mecav — Laravel .env.local
# Local development overrides — DO NOT COMMIT
# ============================================================

APP_NAME="Mecav Local"
APP_ENV=local
APP_KEY=base64:REPLACE_WITH_php_artisan_key_generate_OUTPUT
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=single
LOG_LEVEL=debug

# ── Database ─────────────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=diagrams_local
DB_USERNAME=diagrams
DB_PASSWORD=secret

# ── gRPC (Python service running locally) ────────────────────
GRPC_PYTHON_SERVICE_HOST=127.0.0.1:50051
GRPC_INSECURE=true
GRPC_SERVICE_TOKEN=dev-local-token-changeme
GRPC_TIMEOUT_MS=30000

# ── Snapshot thresholds ───────────────────────────────────────
SNAPSHOT_STORAGE_BYTES=10485760
SNAPSHOT_MAX_AGE_DAYS=90
EXPORT_QUOTA_BYTES=104857600

# ── Queue / Cache (sync = no worker needed locally) ──────────
QUEUE_CONNECTION=sync
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120

# ── Mail (log driver = no real email locally) ─────────────────
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@diagramai.local"
MAIL_FROM_NAME="Mecav"

# ── Broadcasting (disabled locally) ──────────────────────────
BROADCAST_DRIVER=log

# ── Filesystem (local disk, not S3) ──────────────────────────
FILESYSTEM_DISK=local

# ── Vite HMR ─────────────────────────────────────────────────
VITE_APP_NAME="${APP_NAME}"
"""

python_env_local = """\
# ============================================================
# Mecav — Python service .env.local
# Local development overrides — DO NOT COMMIT
# ============================================================

# ── Database ─────────────────────────────────────────────────
DATABASE_URL=postgresql+psycopg://diagrams:secret@127.0.0.1:5432/diagrams_local

# ── LLM ──────────────────────────────────────────────────────
OPENAI_API_KEY=sk-REPLACE_WITH_YOUR_KEY
OPENAI_MODEL=gpt-4o
EMBEDDING_MODEL=text-embedding-3-small

# ── gRPC server ───────────────────────────────────────────────
GRPC_HOST=0.0.0.0
GRPC_PORT=50051
GRPC_MAX_WORKERS=4
GRPC_SERVICE_TOKEN=dev-local-token-changeme

# ── RAG retrieval ─────────────────────────────────────────────
RAG_TOP_K=5
RAG_SIMILARITY_THRESHOLD=0.75

# ── Observability (disabled locally) ─────────────────────────
PROMETHEUS_PORT=9090
OTEL_EXPORTER_OTLP_ENDPOINT=
"""

out_dir = os.path.expanduser("~/diagramai-env-local")
os.makedirs(out_dir, exist_ok=True)

with open(os.path.join(out_dir, "laravel.env.local"), "w") as f:
    f.write(laravel_env_local)

with open(os.path.join(out_dir, "python.env.local"), "w") as f:
    f.write(python_env_local)

print("Files written:")
for fname in os.listdir(out_dir):
    size = os.path.getsize(os.path.join(out_dir, fname))
    print(f"  {fname}  ({size} bytes)")