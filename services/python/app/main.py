"""
Entrypoint — starts the gRPC server, REST server (FastAPI), and optional metrics server.
Future autonomous agents connect as separate K8s sidecars; they register
themselves via the MultimodalDiagramService.Process() bidi-stream endpoint.
"""
import asyncio
import signal
import sys
import threading

import grpc
import structlog
from concurrent import futures
from prometheus_client import start_http_server

from app.settings import settings
from app.grpc_server import MultimodalServicer

# generated proto stubs (compiled at build time)
import proto.multimodal_pb2_grpc as pb2_grpc

log = structlog.get_logger()


def build_server() -> grpc.Server:
    server = grpc.server(
        futures.ThreadPoolExecutor(max_workers=settings.grpc_max_workers),
        options=[
            ("grpc.max_receive_message_length", 64 * 1024 * 1024),  # 64 MB
            ("grpc.max_send_message_length",    64 * 1024 * 1024),
        ],
    )
    pb2_grpc.add_MultimodalDiagramServiceServicer_to_server(
        MultimodalServicer(), server
    )

    # Enable server reflection for grpcurl / Postman
    from grpc_reflection.v1alpha import reflection
    from proto import multimodal_pb2
    service_names = (
        multimodal_pb2.DESCRIPTOR.services_by_name["MultimodalDiagramService"].full_name,
        reflection.SERVICE_NAME,
    )
    reflection.enable_server_reflection(service_names, server)

    address = f"{settings.grpc_host}:{settings.grpc_port}"
    server.add_insecure_port(address)
    return server


def run_rest_server():
    """Run FastAPI REST server in a separate thread."""
    import uvicorn
    from app.rest_server import app as fastapi_app
    
    uvicorn.run(
        fastapi_app,
        host="0.0.0.0",
        port=settings.rest_port,
        log_level="info",
    )


def main() -> None:
    # Prometheus metrics
    start_http_server(settings.prometheus_port)
    log.info("prometheus_metrics_started", port=settings.prometheus_port)

    # Start REST server in background thread
    rest_thread = threading.Thread(target=run_rest_server, daemon=True)
    rest_thread.start()
    log.info("rest_server_started", host="0.0.0.0", port=settings.rest_port)

    # Start gRPC server
    server = build_server()
    server.start()
    log.info("grpc_server_started", host=settings.grpc_host, port=settings.grpc_port)

    def _shutdown(sig, frame):
        log.info("shutdown_signal_received", signal=sig)
        server.stop(grace=10)
        sys.exit(0)

    signal.signal(signal.SIGTERM, _shutdown)
    signal.signal(signal.SIGINT, _shutdown)
    server.wait_for_termination()


if __name__ == "__main__":
    main()
