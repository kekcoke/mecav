<?php

namespace App\Grpc;

use Multimodal\V1\MultimodalDiagramServiceClient;
use Multimodal\V1\TextChunk;
use Multimodal\V1\FileChunk;
use Multimodal\V1\DiagramSuggestion;
use Grpc\Channel;
use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;

/**
 * Thin gRPC client — Laravel only knows the contract, not the AI implementation.
 *
 * Generated stubs are produced by running:
 *   protoc --php_out=app/Grpc/Generated --grpc_out=app/Grpc/Generated \
 *          --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
 *          ../../protobuf/multimodal.proto
 */
final class MultimodalClient
{
    private MultimodalDiagramServiceClient $client;

    public function __construct()
    {
        $host     = config('grpc.python_service_host', 'python-service:50051');
        $insecure = config('grpc.insecure', true);

        $this->client = new MultimodalDiagramServiceClient(
            $host,
            [
                'credentials' => $insecure
                    ? ChannelCredentials::createInsecure()
                    : ChannelCredentials::createSsl(),
                'update_metadata' => function (array $metadata) {
                    // Inject service-to-service auth token
                    $metadata['authorization'] = ['Bearer ' . config('grpc.service_token')];
                    return $metadata;
                },
            ]
        );
    }

    /**
     * Send mermaid source text and receive an AI diagram suggestion (unary).
     */
    public function analyzeText(string $diagramId, string $sessionId, string $content): DiagramSuggestion
    {
        $request = new TextChunk();
        $request->setDiagramId($diagramId);
        $request->setSessionId($sessionId);
        $request->setContent($content);

        [$response, $status] = $this->client->AnalyzeText($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            Log::error('gRPC AnalyzeText failed', [
                'code'    => $status->code,
                'details' => $status->details,
            ]);
            throw new \RuntimeException("gRPC error [{$status->code}]: {$status->details}");
        }

        return $response;
    }

    /**
     * Stream a file to the Python service and collect streamed responses.
     *
     * @return \Generator<\Multimodal\V1\StreamResponse>
     */
    public function analyzeFile(string $diagramId, string $sessionId, string $filePath, string $mimeType): \Generator
    {
        $call = $this->client->AnalyzeFile();

        $chunkSize = 64 * 1024; // 64 KB
        $handle    = fopen($filePath, 'rb');
        $index     = 0;

        while (!feof($handle)) {
            $data  = fread($handle, $chunkSize);
            $isLast = feof($handle);

            $chunk = new FileChunk();
            $chunk->setDiagramId($diagramId);
            $chunk->setSessionId($sessionId);
            $chunk->setFilename(basename($filePath));
            $chunk->setMimeType($mimeType);
            $chunk->setData($data);
            $chunk->setChunkIndex($index++);
            $chunk->setIsLast($isLast);

            $call->write($chunk);
        }

        fclose($handle);
        $call->writesDone();

        foreach ($call->responses() as $response) {
            yield $response;
        }
    }
}
