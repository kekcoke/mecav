# SKILLS.md — Technology Stack

## Laravel Service (`services/laravel`)

| Skill | Version | Purpose |
|---|---|---|
| PHP | 8.3 | Runtime |
| Laravel | 11.x | Web framework, ORM, routing, migrations |
| Sanctum | 4.x | API token authentication |
| Eloquent ORM | — | Models: User, Diagram, DiagramSnapshot, AuditLog |
| grpc/grpc (ext) | 1.63 | gRPC PHP extension + generated client stubs |
| google/protobuf | 4.x | Proto message serialisation |
| Mermaid.js | 10.x | Client-side diagram rendering (async, debounced) |
| Vite | 5.x | Asset bundling |
| PostgreSQL | 16 | Primary relational database |
| PHPUnit | 11.x | Unit + feature testing |

### Key Patterns
- **HasUlids** on all models — URL-safe, time-sortable public identifiers
- **SoftDeletes** on User + Diagram — recovery window before hard deletion
- **AuditLog** — generic morph-based event trail recording all mutations with old/new values
- **DiagramSnapshot** — append-only versioned table; auto-pruned by storage/age thresholds
- **MultimodalClient** — single-responsibility gRPC adapter; the only class aware of the Python service host; fully replaceable without touching business logic
- **Config-driven thresholds** — `config/diagrams.php` exposes all tunable limits via env vars

---

## Python AI Service (`services/python`)

| Skill | Version | Purpose |
|---|---|---|
| Python | 3.11 | Runtime |
| grpcio | 1.63 | gRPC server, interceptors, reflection |
| grpcio-tools | 1.63 | `protoc` stub compiler |
| protobuf | 5.x | Proto message deserialisation |
| LangChain | 0.2.x | RAG chain orchestration |
| langchain-openai | 0.1.x | OpenAI LLM + embedding adapters |
| langchain-postgres | 0.0.4 | PGVector retriever integration |
| SQLAlchemy | 2.0 | ORM with async-ready engine |
| Alembic | 1.13 | Schema migrations |
| pgvector | 0.3 | Vector column type + SQLAlchemy integration |
| psycopg3 | 3.1 | PostgreSQL driver (binary + pool) |
| Pydantic v2 | 2.7 | Settings management, data validation |
| structlog | 24.x | Structured JSON logging |
| tenacity | 8.x | Retry with exponential back-off on LLM calls |
| prometheus-client | 0.20 | `grpc_requests_total` + latency histograms on `:9090` |

### Key Patterns
- **RAGChain singleton** — constructed once at startup; stateless and thread-safe per invocation
- **AuthInterceptor** — validates `Authorization: Bearer` service token before every RPC
- **IVFFlat index** — `lists=100` cosine ANN search at launch; upgrade to HNSW at scale
- **AgentRun table** — full audit trail for every autonomous agent invocation
- **Modality routing in `Process()`** — single bidi-stream entry-point; routes `MODALITY_TEXT / FILE / VOICE` to the correct handler without modifying the servicer
- **Graceful shutdown** — `SIGTERM` → 10-second drain on the gRPC server before exit

---

## Shared / Infrastructure

| Skill | Purpose |
|---|---|
| Protocol Buffers v3 | Single source of truth for the gRPC contract |
| Terraform 1.8 | IaC for EKS cluster, RDS instance, Helm releases |
| AWS EKS 1.30 | Managed Kubernetes — app node group + GPU agent node group |
| AWS RDS Aurora PostgreSQL 16 | Managed DB; custom parameter group enables `pgvector` |
| AWS ECR | Container image registry |
| AWS IAM OIDC | Keyless GitHub Actions → AWS auth (no long-lived secrets) |
| Helm 3 | K8s release management + rollback |
| GitHub Actions | CI: lint → test → build/push → migrate → deploy |
| Docker multi-stage | `base` → `proto-builder` → `runtime`; minimal final image |
