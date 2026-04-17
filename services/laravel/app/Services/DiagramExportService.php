<?php

namespace App\Services;

use App\Models\Diagram;
use App\Models\DiagramSnapshot;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiagramExportService
{
    public function export(Diagram $diagram, string $format, ?DiagramSnapshot $snapshot = null): StreamedResponse
    {
        return new StreamedResponse(function () use ($diagram, $format, $snapshot) {
            echo $snapshot ? $snapshot->mermaid_code : $diagram->mermaid_code;
        }, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="diagram.'.$format.'"',
        ]);
    }
}
