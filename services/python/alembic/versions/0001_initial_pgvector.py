"""Initial schema: pgvector extension + diagram_embeddings + agent_runs

Revision ID: 0001
Revises:
Create Date: 2024-01-01 00:00:00.000000
"""
from alembic import op
import sqlalchemy as sa
from pgvector.sqlalchemy import Vector
from sqlalchemy.dialects.postgresql import JSONB, UUID

revision = "0001"
down_revision = None
branch_labels = None
depends_on = None


def upgrade() -> None:
    # Enable pgvector extension (idempotent)
    op.execute("CREATE EXTENSION IF NOT EXISTS vector")
    op.execute("CREATE EXTENSION IF NOT EXISTS pg_trgm")  # for text search

    # diagram_embeddings
    op.create_table(
        "diagram_embeddings",
        sa.Column("id", UUID(as_uuid=True), primary_key=True, server_default=sa.text("gen_random_uuid()")),
        sa.Column("diagram_id", sa.BigInteger, sa.ForeignKey("diagrams.id"), nullable=True),
        sa.Column("tenant_id", sa.String(255), nullable=False),
        sa.Column("chunk_type", sa.String(64), nullable=False),
        sa.Column("content", sa.Text, nullable=False),
        sa.Column("embedding", Vector(1536), nullable=False),
        sa.Column("metadata", JSONB, nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
    )

    # IVFFlat index for approximate nearest-neighbour search (cosine)
    op.execute(
        """
        CREATE INDEX diagram_embeddings_embedding_idx
        ON diagram_embeddings
        USING ivfflat (embedding vector_cosine_ops)
        WITH (lists = 100)
        """
    )
    op.create_index("ix_diagram_embeddings_tenant", "diagram_embeddings", ["tenant_id"])
    op.create_index("ix_diagram_embeddings_diagram", "diagram_embeddings", ["diagram_id"])

    # agent_runs
    op.create_table(
        "agent_runs",
        sa.Column("id", UUID(as_uuid=True), primary_key=True, server_default=sa.text("gen_random_uuid()")),
        sa.Column("agent_name", sa.String(128), nullable=False),
        sa.Column("diagram_id", sa.BigInteger, sa.ForeignKey("diagrams.id"), nullable=True),
        sa.Column("session_id", sa.String(128), nullable=False),
        sa.Column("status", sa.String(32), nullable=False, server_default="running"),
        sa.Column("input", JSONB, nullable=True),
        sa.Column("output", JSONB, nullable=True),
        sa.Column("error", sa.Text, nullable=True),
        sa.Column("started_at", sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column("finished_at", sa.DateTime(timezone=True), nullable=True),
    )
    op.create_index("ix_agent_runs_session", "agent_runs", ["session_id"])


def downgrade() -> None:
    op.drop_table("agent_runs")
    op.drop_table("diagram_embeddings")
