<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseApiController extends Controller
{
    /**
     * Return a standardized success JSON response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  int  $status
     * @param  array<string, string>  $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operación realizada con éxito.',
        int $status = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        return response()->json($payload, $status, $headers);
    }

    /**
     * Return a standardized 201 Created JSON response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  string|null  $location
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'Recurso creado exitosamente.',
        ?string $location = null
    ): JsonResponse {
        $headers = [];
        if ($location) {
            $headers['Location'] = $location;
        }

        return $this->successResponse($data, $message, Response::HTTP_CREATED, $headers);
    }

    /**
     * Return a standardized 202 Accepted JSON response for asynchronous tasks.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function acceptedResponse(
        mixed $data = null,
        string $message = 'Solicitud aceptada y encolada para su procesamiento.'
    ): JsonResponse {
        return $this->successResponse($data, $message, Response::HTTP_ACCEPTED);
    }

    /**
     * Return a standardized 204 No Content response for deletions.
     *
     * @return \Illuminate\Http\Response
     */
    protected function noContentResponse(): Response
    {
        return response()->noContent();
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param  string  $message
     * @param  int  $status
     * @param  mixed  $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        mixed $errors = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a standardized paginated JSON response with metadata and navigation links.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator  $paginator
     * @param  string  $resourceClass
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        string $message = 'Listado obtenido exitosamente.'
    ): JsonResponse {
        $transformedData = $resourceClass::collection($paginator->items());

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $transformedData,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ], Response::HTTP_OK);
    }
}
