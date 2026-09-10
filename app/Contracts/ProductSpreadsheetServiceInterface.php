<?php

namespace App\Contracts;

use App\Models\ProductImport;
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
     * Import products from an uploaded spreadsheet file or stored file path (.xlsx or .csv).
     *
     * @param UploadedFile|string $file
     * @param ProductImport|null $import
     * @return ProductImportResult
     */
    public function import(UploadedFile|string $file, ?ProductImport $import = null): ProductImportResult;
}
