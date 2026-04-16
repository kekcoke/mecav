"""
LangChain RAG pipeline.

Architecture (decoupled):
  1. Embed incoming text with OpenAI embeddings
  2. ANN search against pgvector for relevant diagram chunks
  3. Stuff retrieved docs into a prompt with the user query
  4. Return a DiagramSuggestion (mermaid_code + explanation)

Future agents can swap the retriever or the LLM without touching the
gRPC servicer layer — they simply produce the same DiagramSuggestion output.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

import structlog
from langchain.chains import RetrievalQA
from langchain.prompts import ChatPromptTemplate
from langchain_community.vectorstores.pgvector import PGVector
from langchain_openai import ChatOpenAI, OpenAIEmbeddings
from sqlalchemy.orm import Session
from tenacity import retry, stop_after_attempt, wait_exponential

from app.settings import settings

log = structlog.get_logger()

DIAGRAM_PROMPT = ChatPromptTemplate.from_messages(
    [
        (
            "system",
            """You are an expert software architect and diagramming assistant.
Your ONLY job is to output valid Mermaid.js diagram code and a brief explanation.

Context from the knowledge base:
{context}

Rules:
- Output valid Mermaid.js syntax ONLY for the diagram block.
- Wrap the diagram in ```mermaid ... ``` fences.
- After the diagram, write a plain-English explanation prefixed with 'EXPLANATION:'.
- Never include any other text before the diagram block.
""",
        ),
        ("human", "{question}"),
    ]
)


@dataclass
class DiagramSuggestionResult:
    mermaid_code: str
    explanation: str
    sources: list[str] = field(default_factory=list)
    confidence: float = 0.9
    metadata: dict[str, Any] = field(default_factory=dict)


class RAGChain:
    """
    Singleton-style chain — constructed once at server startup.
    Thread-safe: LangChain components are stateless per invocation.
    """

    def __init__(self) -> None:
        self._embeddings = OpenAIEmbeddings(
            model=settings.embedding_model,
            openai_api_key=settings.openai_api_key,
        )
        self._llm = ChatOpenAI(
            model=settings.openai_model,
            temperature=0.2,
            openai_api_key=settings.openai_api_key,
        )
        self._vector_store = PGVector(
            collection_name="diagram_embeddings",
            connection_string=settings.database_url,
            embedding_function=self._embeddings,
            distance_strategy="cosine",
        )
        log.info("rag_chain_initialized", model=settings.openai_model)

    @retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=1, max=8))
    def run(
        self,
        query: str,
        diagram_id: str | None = None,
        tenant_id: str | None = None,
    ) -> DiagramSuggestionResult:
        """
        Execute the RAG chain for a given text query.

        Optionally filter the vector store by diagram_id or tenant_id so
        suggestions stay scoped to the correct tenant's knowledge.
        """
        retriever_kwargs: dict = {"k": settings.rag_top_k}
        if tenant_id:
            # pgvector metadata filter
            retriever_kwargs["filter"] = {"tenant_id": tenant_id}

        retriever = self._vector_store.as_retriever(search_kwargs=retriever_kwargs)

        chain = RetrievalQA.from_chain_type(
            llm=self._llm,
            chain_type="stuff",
            retriever=retriever,
            chain_type_kwargs={"prompt": DIAGRAM_PROMPT},
            return_source_documents=True,
        )

        result = chain.invoke({"query": query})
        raw_text: str = result["result"]
        source_docs = result.get("source_documents", [])

        mermaid_code, explanation = self._parse_response(raw_text)
        sources = [doc.metadata.get("source", str(i)) for i, doc in enumerate(source_docs)]

        log.info("rag_chain_completed", diagram_id=diagram_id, sources_count=len(sources))

        return DiagramSuggestionResult(
            mermaid_code=mermaid_code,
            explanation=explanation,
            sources=sources,
            confidence=0.9,
        )

    @staticmethod
    def _parse_response(raw: str) -> tuple[str, str]:
        """Extract mermaid code block and explanation from LLM output."""
        mermaid = ""
        explanation = ""

        if "```mermaid" in raw:
            start = raw.index("```mermaid") + len("```mermaid")
            end   = raw.index("```", start)
            mermaid = raw[start:end].strip()

        if "EXPLANATION:" in raw:
            explanation = raw.split("EXPLANATION:", 1)[1].strip()

        if not mermaid:
            # Graceful fallback — return raw text as a note diagram
            mermaid = f"graph TD\n    A[\"{raw[:100]}...\"]"

        return mermaid, explanation


# Module-level singleton — instantiated when the gRPC server starts
_chain: RAGChain | None = None


def get_chain() -> RAGChain:
    global _chain
    if _chain is None:
        _chain = RAGChain()
    return _chain
