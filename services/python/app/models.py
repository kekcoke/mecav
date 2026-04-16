"""
SQLAlchemy ORM models for the Python service.
These are read-only projections of data produced by the Laravel service,
plus the vector store tables owned exclusively by Python.
"""
import uuid
from datetime import datetime

from pgvector.sqlalchemy import Vector
from sqlalchemy import (
    BigInteger, Boolean, DateTime, ForeignKey,
    String, Text, func, text,
)
from sqlalchemy.dialects.postgresql import JSONB, UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.db import Base


# ── Diagram (read projection) ────────────────────────────────
class Diagram(Base):
    __tablename__ = "diagrams"

    id: Mapped[int]            = mapped_column(BigInteger, primary_key=True)
    ulid: Mapped[str]          = mapped_column(String(26), unique=True, nullable=False)
    user_id: Mapped[int]       = mapped_column(BigInteger, ForeignKey("users.id"))
    tenant_id: Mapped[str]     = mapped_column(String(255), index=True)
    title: Mapped[str]         = mapped_column(String(255))
    mermaid_code: Mapped[str]  = mapped_column(Text)
    diagram_type: Mapped[str]  = mapped_column(String(64), default="flowchart")
    ai_enabled: Mapped[bool]   = mapped_column(Boolean, default=False)
    tags: Mapped[dict]         = mapped_column(JSONB, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), onupdate=func.now())

    embeddings: Mapped[list["DiagramEmbedding"]] = relationship(back_populates="diagram")


# ── Vector store for RAG ─────────────────────────────────────
class DiagramEmbedding(Base):
    """
    pgvector store — each row is a chunk of diagram knowledge
    (e.g., a mermaid node description, doc paragraph, or snapshot diff).
    Dimension 1536 matches text-embedding-3-small.
    """
    __tablename__ = "diagram_embeddings"

    id: Mapped[uuid.UUID]      = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    diagram_id: Mapped[int]    = mapped_column(BigInteger, ForeignKey("diagrams.id"), nullable=True)
    tenant_id: Mapped[str]     = mapped_column(String(255), index=True)
    chunk_type: Mapped[str]    = mapped_column(String(64))  # diagram_code|doc|snapshot_diff
    content: Mapped[str]       = mapped_column(Text)
    embedding: Mapped[Vector]  = mapped_column(Vector(1536))
    metadata_: Mapped[dict]    = mapped_column("metadata", JSONB, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())

    diagram: Mapped["Diagram"] = relationship(back_populates="embeddings")


# ── Agent run log (future autonomous agents write here) ──────
class AgentRun(Base):
    __tablename__ = "agent_runs"

    id: Mapped[uuid.UUID]    = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    agent_name: Mapped[str]  = mapped_column(String(128))
    diagram_id: Mapped[int]  = mapped_column(BigInteger, ForeignKey("diagrams.id"), nullable=True)
    session_id: Mapped[str]  = mapped_column(String(128), index=True)
    status: Mapped[str]      = mapped_column(String(32), default="running")  # running|done|failed
    input_: Mapped[dict]     = mapped_column("input", JSONB, nullable=True)
    output: Mapped[dict]     = mapped_column(JSONB, nullable=True)
    error: Mapped[str]       = mapped_column(Text, nullable=True)
    started_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())
    finished_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=True)
