<?php

namespace App\Grpc;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP/REST client for Python AI Service
 * 
 * Note: gRPC PHP extension has compilation issues with PHP 8.3/8.4 on Alpine.
 * This HTTP client provides equivalent functionality via REST endpoints.
 * 
 * The Python service exposes REST endpoints at:
 *   POST /api/analyze/text -> AnalyzeText
 *   POST /api/analyze/file -> AnalyzeFile (streaming)
 *   GET  /health          -> Health check
 */
final class MultimodalClient
{
    private string $baseUrl;
    private string $token;
    private int $timeout;

    public function __construct()
    {
        $host = config('grpc.python_service_host', 'python-service:50051');
        
        // Convert gRPC host to HTTP URL for REST API (port 8001)
        if (str_starts_with($host, 'http')) {
            // Remove any existing port and use 8001 for REST
            $host = preg_replace('/:\d+$/', '', $host);
            $this->baseUrl = 'http://' . $host . ':8001';
        } else {
            // Remove port if present, add http:// prefix and REST port
            $host = preg_replace('/:\d+$/', '', $host);
            $this->baseUrl = 'http://' . $host . ':8001';
        }
        
        $this->token = config('grpc.service_token', '');
        $this->timeout = (int) config('grpc.timeout_ms', 30000) / 1000;
    }

    /**
     * Send mermaid source text and receive an AI diagram suggestion.
     * Maps to gRPC: AnalyzeText(TextChunk) -> DiagramSuggestion
     */
    public function analyzeText(string $diagramId, string $sessionId, string $content): DiagramSuggestionResponse
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/api/analyze/text', [
                'diagram_id' => $diagramId,
                'session_id' => $sessionId,
                'content' => $content,
            ]);

            if (!$response->successful()) {
                Log::error('HTTP AnalyzeText failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException("HTTP error [{$response->status()}]: {$response->body()}");
            }

            $data = $response->json();
            
            return new DiagramSuggestionResponse(
                suggestionId: $data['suggestion_id'] ?? uniqid('suggestion_'),
                diagramId: $data['diagram_id'] ?? $diagramId,
                mermaidCode: $data['mermaid_code'] ?? $content,
                explanation: $data['explanation'] ?? '',
                confidence: $data['confidence'] ?? 0.0,
                sources: $data['sources'] ?? []
            );
            
        } catch (\Exception $e) {
            Log::error('HTTP AnalyzeText exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Stream a file to the Python service and collect streamed responses.
     * Maps to gRPC: AnalyzeFile(stream FileChunk) -> stream StreamResponse
     * 
     * @return \Generator<DiagramSuggestionResponse>
     */
    public function analyzeFile(string $diagramId, string $sessionId, string $filePath, string $mimeType): \Generator
    {
        $chunkSize = 64 * 1024; // 64 KB
        $handle = fopen($filePath, 'rb');
        
        if (!$handle) {
            throw new \RuntimeException("Cannot open file: $filePath");
        }
        
        try {
            $index = 0;
            while (!feof($handle)) {
                $data = fread($handle, $chunkSize);
                $isLast = feof($handle);
                
                // For streaming, we'd use chunked transfer encoding
                // This is a simplified version - real implementation would use
                // a streaming HTTP client or Server-Sent Events
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/octet-stream',
                    'X-Diagram-Id' => $diagramId,
                    'X-Session-Id' => $sessionId,
                    'X-Chunk-Index' => (string) $index,
                    'X-Is-Last' => $isLast ? 'true' : 'false',
                    'X-Filename' => basename($filePath),
                    'X-Mime-Type' => $mimeType,
                ])
                ->timeout($this->timeout)
                ->withBody($data, 'application/octet-stream')
                ->post($this->baseUrl . '/api/analyze/file');

                if ($response->successful()) {
                    $data = $response->json();
                    yield new DiagramSuggestionResponse(
                        suggestionId: $data['suggestion_id'] ?? '',
                        diagramId: $data['diagram_id'] ?? $diagramId,
                        mermaidCode: $data['mermaid_code'] ?? '',
                        explanation: $data['explanation'] ?? '',
                        confidence: $data['confidence'] ?? 0.0,
                        sources: $data['sources'] ?? []
                    );
                }
                
                $index++;
            }
        } finally {
            fclose($handle);
        }
    }
}

/**
 * Value object for diagram suggestions (replaces gRPC's DiagramSuggestion)
 */
class DiagramSuggestionResponse
{
    public function __construct(
        public readonly string $suggestionId,
        public readonly string $diagramId,
        public readonly string $mermaidCode,
        public readonly string $explanation,
        public readonly float $confidence,
        public readonly array $sources
    ) {}

    public function getSuggestionId(): string { return $this->suggestionId; }
    public function getDiagramId(): string { return $this->diagramId; }
    public function getMermaidCode(): string { return $this->mermaidCode; }
    public function getExplanation(): string { return $this->explanation; }
    public function getConfidence(): float { return $this->confidence; }
    public function getSources(): array { return $this->sources; }
}
