# AGENTS.md — Agentic Vision

## Design Principle

The Python AI service is built as an **agent host**, not a monolithic AI system.
The `Process()` bidi-streaming RPC is intentionally generic: any number of autonomous
agents can connect as **K8s sidecar containers** inside the Python pod and participate
in diagram generation without modifying the host service.

```
┌──────────────────────────────────────────────────────────┐
│  Python Pod  (python-service)                            │
│                                                          │
│  ┌──────────────────────┐  ┌──────────────────────────┐  │
│  │  MultimodalServicer  │  │  RAGChain (LangChain)    │  │
│  │  (gRPC host)         │─▶│  + pgvector retriever    │  │
│  └──────────┬───────────┘  └──────────────────────────┘  │
│             │                                            │
│  ┌──────────▼────────────────────────────────────────┐   │
│  │  Process() bidi-stream — agent multiplexer        │   │
│  └────────┬───────────────────────┬──────────────────┘   │
│           │                       │                      │
│  ┌────────▼──────────┐  ┌─────────▼─────────────────┐    │
│  │  WhisperAgent     │  │  DiagramDiffAgent          │   │
│  │  sidecar          │  │  sidecar                   │   │
│  │  MODALITY_VOICE   │  │  MODALITY_TEXT (diffs)     │   │
│  └───────────────────┘  └────────────────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

## Current Built-in Agent

### `RAGChain` (built-in, always on)
- **Modality**: `MODALITY_TEXT`
- **Entry-point**: `AnalyzeText()` unary RPC or `Process()` stream
- **Action**: Embeds query → ANN search pgvector → GPT-4o with retrieved context → returns `DiagramSuggestion`
- **Observability**: Prometheus counter `grpc_requests_total{method="AnalyzeText"}` + latency histogram

---

## Planned Sidecar Agents

### `WhisperAgent`
| Property | Value |
|---|---|
| **Image** | `mecav/whisper-agent:latest` |
| **Modality** | `MODALITY_VOICE` |
| **Entry-point** | `Process()` — sends `VoiceChunk` payloads |
| **Action** | Streams raw audio to OpenAI Whisper → transcribes → feeds text to RAGChain |
| **Node group** | `workload=app` (no GPU required for Whisper API mode) |
| **Status** | Stub in `AnalyzeVoice()` — awaiting sidecar implementation |

### `DiagramDiffAgent`
| Property | Value |
|---|---|
| **Image** | `mecav/diff-agent:latest` |
| **Modality** | `MODALITY_TEXT` |
| **Trigger** | Periodic CronJob (every 5 min) or SNS webhook on `diagram.updated` |
| **Action** | Computes semantic diff between snapshot versions → proposes minimal update as `DiagramSuggestion` |
| **Node group** | `workload=agents` (GPU Spot node group) |
| **Status** | Planned v1.2 |

### `ReviewAgent` *(v2.0)*
| Property | Value |
|---|---|
| **Trigger** | Diagram `status → published` event |
| **Action** | Validates syntax, checks for orphaned nodes, suggests refactors |
| **Output** | Writes structured feedback to `agent_runs`; optionally posts a Laravel webhook |
| **Status** | Planned v2.0 |

---

## Adding a New Agent — Checklist

1. **Build a gRPC client** that connects to `python-service:50051` using the shared `multimodal.proto` contract
2. **Call `Process()`** as a bidi-stream; set `metadata["agent_name"]` to your agent's name for `AgentRun` correlation
3. **Authenticate** with `Authorization: Bearer <GRPC_SERVICE_TOKEN>` on every call
4. **Write a K8s sidecar container spec** in the Python service `Deployment` — no changes to the servicer code required:

```yaml
# In python-service Helm chart values.yaml
sidecars:
  - name: whisper-agent
    image: mecav/whisper-agent:latest
    env:
      - name: GRPC_HOST
        value: "localhost:50051"
      - name: GRPC_SERVICE_TOKEN
        valueFrom:
          secretKeyRef:
            name: grpc-credentials
            key: token
    resources:
      requests: { cpu: "250m", memory: "512Mi" }
      limits:   { cpu: "1",    memory: "1Gi" }
```

5. **Tolerate the agent taint** if the sidecar needs GPU:
```yaml
tolerations:
  - key: workload
    value: agents
    effect: NoSchedule
```

---

## Agent Contract (Required)

| Rule | Detail |
|---|---|
| **Authentication** | `Authorization: Bearer <token>` on every RPC |
| **Session ID** | Stable UUID per run, set in `session_id` field — used for `AgentRun` correlation |
| **Completion signal** | Send `StreamResponse { done: true }` to mark the stream finished |
| **Logging** | Structured JSON to stdout — collected by Fluent Bit → CloudWatch |
| **No direct DB writes** | Agents must not write to `diagrams` or `diagram_snapshots` directly |
| **No Laravel bypass** | All diagram mutations flow through Laravel REST API |
| **Heartbeat** | Long-running streams must send a `StreamResponse { done: false }` heartbeat every 30 s |
