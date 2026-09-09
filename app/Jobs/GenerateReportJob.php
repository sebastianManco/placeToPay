<?php

namespace App\Jobs;

use App\Contracts\ReportServiceInterface;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The report model instance.
     *
     * @var \App\Models\Report
     */
    public Report $report;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Report  $report
     * @return void
     */
    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    /**
     * Execute the job.
     *
     * @param  \App\Contracts\ReportServiceInterface  $reportService
     * @return void
     */
    public function handle(ReportServiceInterface $reportService): void
    {
        $reportService->process($this->report);
    }
}