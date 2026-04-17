<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Multimodal\V1;

/**
 * ── Service ──────────────────────────────────────────────────
 */
class MultimodalDiagramServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Unary — quick text query → suggestion
     * @param \Multimodal\V1\TextChunk $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Multimodal\V1\DiagramSuggestion>
     */
    public function AnalyzeText(\Multimodal\V1\TextChunk $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/multimodal.v1.MultimodalDiagramService/AnalyzeText',
        $argument,
        ['\Multimodal\V1\DiagramSuggestion', 'decode'],
        $metadata, $options);
    }

    /**
     * Server-streaming — file upload with streaming AI response
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function AnalyzeFile($metadata = [], $options = []) {
        return $this->_bidiRequest('/multimodal.v1.MultimodalDiagramService/AnalyzeFile',
        ['\Multimodal\V1\StreamResponse','decode'],
        $metadata, $options);
    }

    /**
     * Bidi-streaming — real-time voice transcription + diagram generation
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function AnalyzeVoice($metadata = [], $options = []) {
        return $this->_bidiRequest('/multimodal.v1.MultimodalDiagramService/AnalyzeVoice',
        ['\Multimodal\V1\StreamResponse','decode'],
        $metadata, $options);
    }

    /**
     * Generic multimodal (preferred entry-point)
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function Process($metadata = [], $options = []) {
        return $this->_bidiRequest('/multimodal.v1.MultimodalDiagramService/Process',
        ['\Multimodal\V1\StreamResponse','decode'],
        $metadata, $options);
    }

}
