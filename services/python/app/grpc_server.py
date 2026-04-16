"""
gRPC servicer implementation.

Design principle: this layer is a thin adapter between the wire protocol
and the RAG chain. It knows nothing about LangChain internals —
it only calls get_chain().run(...) and maps results to proto messages.

Future autonomous agents attach as K8s sidecars and call Process() with
their own session_id. The servicer routes them through the same RAG chain
without modification.
"""
from __future__ import annotations

import time
import uuid
from typing import Iterator

import grpc
import structlog
from prometheus_client import Counter, Histogram

# These imports resolve once protoc has generated the stubs
import proto.multimodal_pb2 as pb2
import proto.multimodal_pb2_grpc as pb2_grpc

from app.rag_chain import get_chain
from app.settings import settings

log = structlog.get_logger()

# ── Prometheus metrics ────────────────────────────────────────
REQUEST_COUNT = Counter(
    "grpc_requests_total",
    "Total gRPC requests",
    ["method", "status"],
)
REQUEST_LATENCY = Histogram(
    "grpc_request_duration_seconds",
    "gRPC request latency",
    ["method"],
)


class AuthInterceptor(grpc.ServerInterceptor):
    """Validates service-to-service bearer tokens."""

    def intercept_service(self, continuation, handler_call_details):
        if not settings.service_token:
            return continuation(handler_call_details)  # token validation disabled

        meta = dict(handler_call_details.invocation_metadata or [])
        auth = meta.get("authorization", "")
        if not auth.startswith("Bearer ") or auth[7:] != settings.service_token:
            def abort(req, ctx):
                ctx.abort(grpc.StatusCode.UNAUTHENTICATED, "Invalid service token")
            return grpc.unary_unary_rpc_method_handler(abort)

        return continuation(handler_call_details)


class MultimodalServicer(pb2_grpc.MultimodalDiagramServiceServicer):

    # ── Unary ────────────────────────────────────────────────
    def AnalyzeText(
        self,
        request: pb2.TextChunk,
        context: grpc.ServicerContext,
    ) -> pb2.DiagramSuggestion:
        start = time.perf_counter()
        try:
            result = get_chain().run(
                query=request.content,
                diagram_id=request.diagram_id,
                tenant_id=request.session_id,  # session carries tenant context
            )
            REQUEST_COUNT.labels(method="AnalyzeText", status="ok").inc()
            return pb2.DiagramSuggestion(
                suggestion_id=str(uuid.uuid4()),
                diagram_id=request.diagram_id,
                mermaid_code=result.mermaid_code,
                explanation=result.explanation,
                confidence=result.confidence,
                sources=result.sources,
            )
        except Exception as exc:
            log.error("analyze_text_error", error=str(exc), diagram_id=request.diagram_id)
            REQUEST_COUNT.labels(method="AnalyzeText", status="error").inc()
            context.abort(grpc.StatusCode.INTERNAL, str(exc))
        finally:
            REQUEST_LATENCY.labels(method="AnalyzeText").observe(time.perf_counter() - start)

    # ── File upload streaming ─────────────────────────────────
    def AnalyzeFile(
        self,
        request_iterator: Iterator[pb2.FileChunk],
        context: grpc.ServicerContext,
    ) -> Iterator[pb2.StreamResponse]:
        buffer = bytearray()
        diagram_id = ""
        session_id = ""
        mime_type  = ""

        for chunk in request_iterator:
            diagram_id = chunk.diagram_id
            session_id = chunk.session_id
            mime_type  = chunk.mime_type
            buffer.extend(chunk.data)

        # Decode text-based files (SVG, XML, JSON, plain text)
        if mime_type in ("text/plain", "text/markdown", "image/svg+xml", "application/json"):
            content = buffer.decode("utf-8", errors="replace")
        else:
            content = f"[Binary file: {mime_type}, {len(buffer)} bytes — describe its structure]"

        try:
            result = get_chain().run(content, diagram_id=diagram_id)
            yield pb2.StreamResponse(
                request_id=session_id,
                done=True,
                suggestion=pb2.DiagramSuggestion(
                    suggestion_id=str(uuid.uuid4()),
                    diagram_id=diagram_id,
                    mermaid_code=result.mermaid_code,
                    explanation=result.explanation,
                    confidence=result.confidence,
                    sources=result.sources,
                ),
            )
        except Exception as exc:
            log.error("analyze_file_error", error=str(exc))
            yield pb2.StreamResponse(request_id=session_id, done=True, error=str(exc))

    # ── Voice streaming ───────────────────────────────────────
    def AnalyzeVoice(
        self,
        request_iterator: Iterator[pb2.VoiceChunk],
        context: grpc.ServicerContext,
    ) -> Iterator[pb2.StreamResponse]:
        """
        Placeholder — real implementation pipes audio to Whisper (OpenAI)
        then runs the RAG chain on the transcript.
        Future: a WhisperAgent sidecar handles transcription autonomously.
        """
        audio_buffer = bytearray()
        session_id = ""
        diagram_id = ""

        for chunk in request_iterator:
            session_id = chunk.session_id
            diagram_id = chunk.diagram_id
            audio_buffer.extend(chunk.audio_data)

        # TODO: integrate openai.Audio.transcribe when sidecar is ready
        transcript = "[Voice transcription not yet implemented — attach WhisperAgent sidecar]"
        result = get_chain().run(transcript, diagram_id=diagram_id)

        yield pb2.StreamResponse(
            request_id=session_id,
            done=True,
            suggestion=pb2.DiagramSuggestion(
                suggestion_id=str(uuid.uuid4()),
                diagram_id=diagram_id,
                mermaid_code=result.mermaid_code,
                explanation=result.explanation,
            ),
        )

    # ── Generic multimodal bidi-stream ───────────────────────
    def Process(
        self,
        request_iterator: Iterator[pb2.MultimodalRequest],
        context: grpc.ServicerContext,
    ) -> Iterator[pb2.StreamResponse]:
        """
        Generic entry-point. Routes each request to the appropriate handler
        based on modality. Autonomous agent sidecars use this endpoint.
        """
        from proto.multimodal_pb2 import Modality

        for req in request_iterator:
            try:
                if req.modality == Modality.MODALITY_TEXT:
                    result = get_chain().run(
                        req.text.content,
                        diagram_id=req.diagram_id,
                        tenant_id=req.metadata.get("tenant_id"),
                    )
                    yield pb2.StreamResponse(
                        request_id=req.session_id,
                        done=False,
                        suggestion=pb2.DiagramSuggestion(
                            suggestion_id=str(uuid.uuid4()),
                            diagram_id=req.diagram_id,
                            mermaid_code=result.mermaid_code,
                            explanation=result.explanation,
                            confidence=result.confidence,
                            sources=result.sources,
                        ),
                    )
                else:
                    yield pb2.StreamResponse(
                        request_id=req.session_id,
                        done=False,
                        error=f"Modality {req.modality} not yet handled in Process(). Attach a dedicated agent sidecar.",
                    )
            except Exception as exc:
                log.error("process_error", error=str(exc), session_id=req.session_id)
                yield pb2.StreamResponse(request_id=req.session_id, done=True, error=str(exc))
