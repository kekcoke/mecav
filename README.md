# Mecav — Multimodal Diagramming Platform

> A production-ready, AI-augmented diagramming platform with real-time
> Mermaid.js editing, versioned snapshots, and a decoupled gRPC-based
> AI inference layer backed by LangChain + pgvector RAG.

## Microservice Architecture

```mermaid
graph TD
    subgraph Browser
        U[User] -->|HTTP| LV[Laravel Blade\nMermaid Editor]
    end

    subgraph K8s Cluster — mecav namespace
        LV -->|REST + Sanctum| LA[Laravel API\nlaravel:8080]
        LA -->|gRPC unary/stream| PS[Python AI Service\npython-service:50051]

        subgraph AI Pod
            PS --> RAG[LangChain RAG Chain]
            RAG --> EMB[OpenAI Embeddings]
            RAG --> LLM[GPT-4o]
        end

        subgraph Agent Sidecars — optional
            WA[WhisperAgent\nVoice to Text]
            DA[DiagramDiffAgent\nAuto-snapshot]
            PS <-->|Process bidi-stream| WA
            PS <-->|Process bidi-stream| DA
        end

        LA --> DB[(PostgreSQL 16\npgvector)]
        PS --> DB
    end

    subgraph AWS
        DB --- RDS[RDS Aurora\nPostgreSQL]
        LA --- S3[S3 Export Storage]
    end
```

## Architecture Overview

| Layer | Technology | Responsibility |
|---|---|---|
| Web UI | Laravel 11 Blade + Mermaid.js | Real-time diagram editing, snapshot management |
| API | Laravel 11 REST + Sanctum | CRUD, auth, gRPC proxy, export |
| AI Service | Python 3.11 + gRPC | RAG inference, LangChain, pgvector retrieval |
| Vector DB | PostgreSQL 16 + pgvector | Embedding storage, ANN search |
| Proto Contract | Protocol Buffers v3 | Shared gRPC contract for text, file, voice |
| Infra | Terraform + EKS + Helm | K8s deployment, managed RDS |

## Quick Start

```bash
# 1. Clone
git clone https://github.com/your-org/mecav && cd mecav

# 2. Configure environment variables
cp services/laravel/.env.example services/laravel/.env
cp services/python/.env.example  services/python/.env
# Edit both .env files — DATABASE_URL, OPENAI_API_KEY, GRPC_SERVICE_TOKEN

# 3. Compile proto stubs (Python)
cd services/python
python -m grpc_tools.protoc \
  -I ../../protobuf \
  --python_out=proto --grpc_python_out=proto \
  ../../protobuf/multimodal.proto

# 4. Laravel setup
cd ../laravel
composer install && php artisan key:generate && php artisan migrate

# 5. Python setup
cd ../python
pip install -r requirements.txt && alembic upgrade head

# 6. Start services
php artisan serve          # Laravel  → localhost:8000
python -m app.main         # gRPC     → localhost:50051
```

## Snapshot Policy

| Setting | Env Var | Default | Behaviour |
|---|---|---|---|
| Storage limit | `SNAPSHOT_STORAGE_BYTES` | 10 MB | Auto-prunes old snapshots when exceeded |
| Age limit | `SNAPSHOT_MAX_AGE_DAYS` | 90 days | Marks snapshots as expiring; 7-day grace period |
| Export lock | — | — | Exported snapshots are never auto-purged |
| Revert | — | — | Creates a new snapshot of current state before reverting |

## Roadmap

### v1.1 — Voice Input
- Wire `WhisperAgent` sidecar to `AnalyzeVoice()` gRPC endpoint
- Real-time audio streaming with Opus codec

### v1.2 — Multi-Agent Collaboration
- `DiagramDiffAgent`: semantic diff + auto-snapshot on change
- `ReviewAgent`: suggestions on diagram publish
- All agents attach as K8s sidecars via `Process()` bidi-stream

### v2.0 — Team Collaboration
- CRDT-based concurrent editing (Yjs)
- Tenant-isolated embedding namespaces
- Role-based diagram sharing (viewer / editor / admin)
- Export queue via Redis + S3 for async large-file delivery

## Scaling Notes

- **Stateless pods**: both Laravel and Python pods are fully stateless — use HPA on CPU/RPS
- **pgvector**: IVFFlat (`lists=100`) at launch; migrate to HNSW at >1 M embeddings
- **gRPC load balancing**: deploy Linkerd or Envoy for L7 gRPC-aware LB between Laravel and Python
- **Agent node group**: dedicated EKS node group with `g4dn.xlarge` Spot instances for GPU agents, tainted `workload=agents`
- **Storage quota enforcement**: run `php artisan diagrams:prune-snapshots` as a K8s CronJob (daily)
