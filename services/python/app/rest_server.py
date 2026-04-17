"""
FastAPI REST server for HTTP/REST client compatibility.
Provides REST endpoints that map to gRPC methods for Laravel PHP client.
"""
from __future__ import annotations

import uuid
from typing import Optional

from fastapi import FastAPI, HTTPException, Header, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field
import structlog

from app.rag_chain import get_chain
from app.settings import settings

log = structlog.get_logger()

app = FastAPI(
    title="Mecav AI Service REST API",
    description="HTTP/REST endpoints for AI diagram suggestions",
    version="1.0.0",
)


# ── Request/Response Models ──────────────────────────────────

class TextAnalyzeRequest(BaseModel):
    diagram_id: str = Field(..., description="Diagram identifier")
    session_id: Optional[str] = Field(None, description="Session identifier")
    content: str = Field(..., description="Mermaid code or text to analyze")


class TextAnalyzeResponse(BaseModel):
    suggestion_id: str
    diagram_id: str
    mermaid_code: str
    explanation: str
    confidence: float
    sources: list[str] = []


class ErrorResponse(BaseModel):
    error: str
    detail: Optional[str] = None


# ── Auth ───────────────────────────────────────────────────

async def verify_token(authorization: Optional[str] = Header(None)) -> bool:
    """Verify service-to-service bearer token."""
    if not settings.service_token:
        return True  # Token validation disabled
    
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Missing or invalid Authorization header")
    
    token = authorization[7:]  # Strip "Bearer "
    if token != settings.service_token:
        raise HTTPException(status_code=401, detail="Invalid service token")
    
    return True


# ── REST Endpoints ─────────────────────────────────────────

@app.get("/health")
async def health_check():
    """Health check endpoint."""
    return {"status": "ok", "service": "mecav-ai"}


@app.post("/api/analyze/text", response_model=TextAnalyzeResponse)
async def analyze_text(
    request: TextAnalyzeRequest,
    _auth: bool = None,
) -> TextAnalyzeResponse:
    """
    Analyze text/Mermaid code and return AI diagram suggestion.
    
    Maps to gRPC: AnalyzeText(TextChunk) -> DiagramSuggestion
    """
    try:
        result = get_chain().run(
            query=request.content,
            diagram_id=request.diagram_id,
            tenant_id=request.session_id,
        )
        
        return TextAnalyzeResponse(
            suggestion_id=str(uuid.uuid4()),
            diagram_id=request.diagram_id,
            mermaid_code=result.mermaid_code,
            explanation=result.explanation,
            confidence=result.confidence,
            sources=result.sources or [],
        )
        
    except Exception as exc:
        log.error("rest_analyze_text_error", error=str(exc), diagram_id=request.diagram_id)
        raise HTTPException(status_code=500, detail=str(exc))


@app.post("/api/analyze/file")
async def analyze_file(request: Request, authorization: Optional[str] = Header(None)):
    """
    Analyze uploaded file content.
    
    Maps to gRPC: AnalyzeFile(stream FileChunk) -> stream StreamResponse
    
    Accepts multipart/form-data or JSON with base64-encoded content.
    """
    # Verify token
    await verify_token(authorization)
    
    # Get headers
    diagram_id = request.headers.get("X-Diagram-Id", "")
    session_id = request.headers.get("X-Session-Id", str(uuid.uuid4()))
    
    # Get body content
    body = await request.body()
    
    # Try to decode as text
    try:
        content = body.decode("utf-8")
    except UnicodeDecodeError:
        content = f"[Binary file, {len(body)} bytes — describe its structure]"
    
    try:
        result = get_chain().run(content, diagram_id=diagram_id)
        
        return JSONResponse(content={
            "suggestion_id": str(uuid.uuid4()),
            "diagram_id": diagram_id,
            "mermaid_code": result.mermaid_code,
            "explanation": result.explanation,
            "confidence": result.confidence,
            "sources": result.sources or [],
        })
        
    except Exception as exc:
        log.error("rest_analyze_file_error", error=str(exc))
        raise HTTPException(status_code=500, detail=str(exc))


@app.get("/api/diagrams/{diagram_id}/suggest")
async def suggest_for_diagram(
    diagram_id: str,
    content: str = "",
    authorization: Optional[str] = Header(None),
):
    """
    Get AI suggestions for an existing diagram.
    
    Convenience endpoint that wraps analyze_text with diagram context.
    """
    await verify_token(authorization)
    
    if not content:
        raise HTTPException(status_code=400, detail="content parameter is required")
    
    try:
        result = get_chain().run(
            query=content,
            diagram_id=diagram_id,
        )
        
        return {
            "suggestion_id": str(uuid.uuid4()),
            "diagram_id": diagram_id,
            "mermaid_code": result.mermaid_code,
            "explanation": result.explanation,
            "confidence": result.confidence,
            "sources": result.sources or [],
        }
        
    except Exception as exc:
        log.error("rest_suggest_error", error=str(exc), diagram_id=diagram_id)
        raise HTTPException(status_code=500, detail=str(exc))
