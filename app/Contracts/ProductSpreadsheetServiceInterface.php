<?php

namespace App\Contracts;

use App\Services\Product\ProductImportResult;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

interface ProductSpreadsheetServiceInterface
{
    /**
     * Export registered products to a downloadable spreadsheet response.
     *
     * @param string $format 'xlsx' or 'csv'
     * @return Response
     */
    public function export(string $format = 'xlsx'): Response;

    /**
     * Import products from an uploaded spreadsheet file (.xlsx or .csv).
     *
     * @param UploadedFile $file
     * @return ProductImportResult
     */
    public function import(UploadedFile $file): ProductImportResult;
}
