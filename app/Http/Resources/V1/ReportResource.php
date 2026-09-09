<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasPdf = $this->hasPdf();
        $hasExcel = $this->hasExcel();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'format' => $this->format,
            'status' => $this->status,
            'is_completed' => $this->isCompleted(),
            'is_processing' => $this->isProcessing(),
            'is_pending' => $this->isPending(),
            'is_failed' => $this->isFailed(),
            'has_pdf' => $hasPdf,
            'has_excel' => $hasExcel,
            'parameters' => $this->parameters ?? [],
            'summary_data' => $this->summary_data,
            'error_message' => $this->error_message,
            'downloads' => [
                'pdf' => $hasPdf ? route('api.v1.reports.download', ['report' => $this->id, 'format' => 'pdf']) : null,
                'excel' => $hasExcel ? route('api.v1.reports.download', ['report' => $this->id, 'format' => 'xlsx']) : null,
            ],
            'user_identification' => $this->user_identification,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
