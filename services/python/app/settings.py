from pydantic_settings import BaseSettings, SettingsConfigDict
from pydantic import Field


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    # gRPC
    grpc_host: str = Field("0.0.0.0", alias="GRPC_HOST")
    grpc_port: int = Field(50051, alias="GRPC_PORT")
    grpc_max_workers: int = Field(10, alias="GRPC_MAX_WORKERS")
    service_token: str = Field("", alias="GRPC_SERVICE_TOKEN")

    # Database
    database_url: str = Field(..., alias="DATABASE_URL")
    # e.g. postgresql+psycopg://user:pass@host:5432/diagrams

    # LLM
    openai_api_key: str = Field("", alias="OPENAI_API_KEY")
    openai_model: str = Field("gpt-4o", alias="OPENAI_MODEL")
    embedding_model: str = Field("text-embedding-3-small", alias="EMBEDDING_MODEL")

    # RAG
    rag_top_k: int = Field(5, alias="RAG_TOP_K")
    rag_similarity_threshold: float = Field(0.75, alias="RAG_SIMILARITY_THRESHOLD")

    # Observability
    otel_exporter_otlp_endpoint: str = Field("", alias="OTEL_EXPORTER_OTLP_ENDPOINT")
    prometheus_port: int = Field(9090, alias="PROMETHEUS_PORT")


settings = Settings()
