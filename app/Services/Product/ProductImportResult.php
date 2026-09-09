<?php

namespace App\Services\Product;

class ProductImportResult
{
    /**
     * @param int $totalProcessed
     * @param int $createdCount
     * @param int $updatedCount
     * @param array<int, array{row: int, errors: array<string, list<string>>, data: array<string, mixed>}> $errors
     */
    public function __construct(
        public int $totalProcessed = 0,
        public int $createdCount = 0,
        public int $updatedCount = 0,
        public array $errors = []
    ) {}

    /**
     * Determine if there were any validation or processing errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Determine if any products were imported or updated.
     */
    public function hasSuccess(): bool
    {
        return ($this->createdCount + $this->updatedCount) > 0;
    }

    /**
     * Get a user-friendly summary message.
     */
    public function getSummaryMessage(): string
    {
        $parts = [];
        if ($this->createdCount > 0) {
            $parts[] = "{$this->createdCount} producto(s) creado(s)";
        }
        if ($this->updatedCount > 0) {
            $parts[] = "{$this->updatedCount} producto(s) actualizado(s)";
        }

        if (empty($parts)) {
            if ($this->hasErrors()) {
                return 'No se importó ningún producto debido a errores en los datos del archivo.';
            }

            return 'El archivo no contiene registros de productos para importar.';
        }

        $summary = 'Importación completada con éxito: ' . implode(' y ', $parts) . '.';
        if ($this->hasErrors()) {
            $summary .= ' Sin embargo, se encontraron errores en ' . count($this->errors) . ' fila(s).';
        }

        return $summary;
    }
}
