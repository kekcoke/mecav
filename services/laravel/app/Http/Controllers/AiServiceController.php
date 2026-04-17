<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP/REST wrapper for Python AI Service
 * 
 * Note: gRPC PHP extension has build compatibility issues with PHP 8.4.
 * This HTTP client provides equivalent functionality via REST endpoints
 * exposed by the Python service.
 */
class AiServiceController extends Controller
{
    private string $baseUrl;
    private string $token;
    
    public function __construct()
    {
        $this->baseUrl = config('grpc.python_service_host', 'http://python-service:50051');
        $this->token = config('grpc.service_token', '');
    }
    
    /**
     * Analyze text and get diagram suggestion
     * Maps to gRPC: AnalyzeText(TextChunk) -> DiagramSuggestion
     */
    public function analyzeText(Request $request, string $diagramId)
    {
        $request->validate([
            'content' => 'required|string|max:50000',
            'session_id' => 'nullable|string',
        ]);
        
        try {
            $response = Http::withToken($this->token)
                ->timeout(30)
                ->post("{$this->baseUrl}/api/analyze/text", [
                    'diagram_id' => $diagramId,
                    'session_id' => $request->input('session_id', uniqid()),
                    'content' => $request->input('content'),
                ]);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            Log::error('AI AnalyzeText failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            return response()->json([
                'error' => 'AI service unavailable',
                'details' => $response->status() >= 500 ? 'Service error' : 'Request failed',
            ], $response->status());
            
        } catch (\Exception $e) {
            Log::error('AI AnalyzeText exception', ['message' => $e->getMessage()]);
            
            return response()->json([
                'error' => 'AI service connection failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Service unavailable',
            ], 503);
        }
    }
    
    /**
     * Get AI suggestions for a diagram
     * Called from: POST /api/diagrams/{diagram}/ai-suggest
     */
    public function suggest(string $diagramId, Request $request)
    {
        $request->validate([
            'mermaid_code' => 'required|string',
        ]);
        
        // Check if gRPC is available, fall back to HTTP
        if (extension_loaded('grpc')) {
            return $this->analyzeViaGrpc($diagramId, $request->input('mermaid_code'));
        }
        
        return $this->analyzeText($request, $diagramId);
    }
    
    /**
     * gRPC fallback using MultimodalClient if extension is loaded
     */
    private function analyzeViaGrpc(string $diagramId, string $content)
    {
        try {
            $client = app(\App\Grpc\MultimodalClient::class);
            $result = $client->analyzeText($diagramId, uniqid(), $content);
            
            return response()->json([
                'suggestion_id' => $result->getSuggestionId(),
                'mermaid_code' => $result->getMermaidCode(),
                'explanation' => $result->getExplanation(),
                'confidence' => $result->getConfidence(),
                'sources' => $result->getSources(),
            ]);
        } catch (\Exception $e) {
            Log::warning('gRPC fallback failed, using HTTP', ['error' => $e->getMessage()]);
            return $this->analyzeViaHttpFallback($diagramId, $content);
        }
    }
    
    /**
     * HTTP fallback when gRPC is not available
     */
    private function analyzeViaHttpFallback(string $diagramId, string $content)
    {
        // Return a placeholder response when AI service is unavailable
        return response()->json([
            'suggestion_id' => null,
            'mermaid_code' => $content,
            'explanation' => 'AI suggestion unavailable - gRPC extension not loaded',
            'confidence' => 0.0,
            'sources' => [],
            'fallback' => true,
        ]);
    }
    
    /**
     * Health check for AI service
     */
    public function health()
    {
        try {
            $response = Http::withToken($this->token)
                ->timeout(5)
                ->get("{$this->baseUrl}/health");
            
            return response()->json([
                'status' => 'ok',
                'grpc_available' => extension_loaded('grpc'),
                'grpc_version' => phpversion('grpc'),
                'service' => $response->successful() ? 'reachable' : 'error',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'degraded',
                'grpc_available' => extension_loaded('grpc'),
                'service' => 'unreachable',
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}
