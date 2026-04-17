"""
Unit tests for REST server endpoints.
"""
import pytest
from unittest.mock import patch, MagicMock

from fastapi.testclient import TestClient

from app.rest_server import app, analyze_text, health_check


class TestHealthEndpoint:
    """Tests for /health endpoint."""

    def test_health_returns_ok(self):
        """Health endpoint should return status ok."""
        client = TestClient(app)
        response = client.get("/health")
        
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "ok"
        assert data["service"] == "mecav-ai"


class TestAnalyzeTextEndpoint:
    """Tests for /api/analyze/text endpoint."""

    @patch("app.rest_server.get_chain")
    def test_analyze_text_success(self, mock_get_chain):
        """Should return suggestion on successful analysis."""
        mock_result = MagicMock()
        mock_result.mermaid_code = "graph TD; A --> B"
        mock_result.explanation = "Simple flow"
        mock_result.confidence = 0.95
        mock_result.sources = ["doc1"]
        mock_get_chain.return_value.run.return_value = mock_result
        
        client = TestClient(app)
        response = client.post("/api/analyze/text", json={
            "diagram_id": "test-123",
            "session_id": "session-456",
            "content": "flowchart TD; A --> B",
        })
        
        assert response.status_code == 200
        data = response.json()
        assert data["diagram_id"] == "test-123"
        assert data["mermaid_code"] == "graph TD; A --> B"
        assert data["explanation"] == "Simple flow"
        assert data["confidence"] == 0.95
        assert "suggestion_id" in data

    @patch("app.rest_server.get_chain")
    def test_analyze_text_error(self, mock_get_chain):
        """Should return 500 on chain error."""
        mock_get_chain.return_value.run.side_effect = Exception("Chain failed")
        
        client = TestClient(app)
        response = client.post("/api/analyze/text", json={
            "diagram_id": "test-123",
            "content": "some content",
        })
        
        assert response.status_code == 500

    def test_analyze_text_missing_content(self):
        """Should return 422 on missing content."""
        client = TestClient(app)
        response = client.post("/api/analyze/text", json={
            "diagram_id": "test-123",
        })
        
        assert response.status_code == 422


class TestSuggestEndpoint:
    """Tests for /api/diagrams/{diagram_id}/suggest endpoint."""

    @patch("app.rest_server.get_chain")
    def test_suggest_success(self, mock_get_chain):
        """Should return suggestion for existing diagram."""
        mock_result = MagicMock()
        mock_result.mermaid_code = "improved code"
        mock_result.explanation = "Better structure"
        mock_result.confidence = 0.88
        mock_result.sources = []
        mock_get_chain.return_value.run.return_value = mock_result
        
        client = TestClient(app)
        response = client.get(
            "/api/diagrams/diag-123/suggest",
            params={"content": "original code"},
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data["diagram_id"] == "diag-123"
        assert data["mermaid_code"] == "improved code"

    def test_suggest_missing_content(self):
        """Should return 400 when content is missing."""
        client = TestClient(app)
        response = client.get("/api/diagrams/diag-123/suggest")
        
        assert response.status_code == 400


class TestAnalyzeFileEndpoint:
    """Tests for /api/analyze/file endpoint."""

    @patch("app.rest_server.get_chain")
    def test_analyze_file_text(self, mock_get_chain):
        """Should analyze text file content."""
        mock_result = MagicMock()
        mock_result.mermaid_code = "file graph"
        mock_result.explanation = "Structure detected"
        mock_result.confidence = 0.75
        mock_result.sources = []
        mock_get_chain.return_value.run.return_value = mock_result
        
        client = TestClient(app)
        response = client.post(
            "/api/analyze/file",
            content=b"flowchart LR; A --> B",
            headers={
                "X-Diagram-Id": "file-diag-1",
                "X-Session-Id": "file-session",
            },
        )
        
        assert response.status_code == 200
        data = response.json()
        assert data["diagram_id"] == "file-diag-1"

    @patch("app.rest_server.get_chain")
    def test_analyze_file_binary(self, mock_get_chain):
        """Should handle binary files gracefully."""
        mock_result = MagicMock()
        mock_result.mermaid_code = "[Binary file]"
        mock_result.explanation = "Could not parse"
        mock_result.confidence = 0.0
        mock_result.sources = []
        mock_get_chain.return_value.run.return_value = mock_result
        
        client = TestClient(app)
        # Send binary data that can't be decoded as UTF-8
        binary_content = bytes([0x00, 0x01, 0x02, 0xff, 0xfe])
        response = client.post(
            "/api/analyze/file",
            content=binary_content,
            headers={"X-Diagram-Id": "bin-diag"},
        )
        
        assert response.status_code == 200
