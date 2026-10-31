<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Pagination\AbstractPaginator;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Standardize the JSON response structure.
     */
    protected function apiResponse(int $status, string $message, mixed $data = null): JsonResponse
    {
        $response = [
            'status' => $status,
            'message' => $message,
        ];

        if ($data instanceof AbstractPaginator) {
            $dataArray = $data->toArray();
            $response['data'] = $dataArray['data'];
            unset($dataArray['data']);
            $response['pagination'] = $dataArray;
        } else {
            if ($data != null) {
                $response['data'] = $data;
            }
        }

        return response()->json($response, $status);
    }

    /**
     * 200 – Successful retrieval / action.
     */
    protected function success(mixed $data = null, string $message = 'Success'): JsonResponse
    {
        return $this->apiResponse(200, $message, $data);
    }

    /**
     * 201 – Resource created.
     */
    protected function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->apiResponse(201, $message, $data);
    }

    /**
     * 400 – Bad request / validation-level business error.
     */
    protected function badRequest(string $message = 'Bad request', mixed $data = null): JsonResponse
    {
        return $this->apiResponse(400, $message, $data);
    }

    /**
     * 403 – Authenticated but not allowed to perform this action.
     */
    protected function forbidden(string $message = 'You are not allowed to perform this action.', mixed $data = null): JsonResponse
    {
        return $this->apiResponse(403, $message, $data);
    }

    /**
     * 404 – Resource not found.
     */
    protected function notFound(string $message = 'Resource not found', mixed $data = null): JsonResponse
    {
        return $this->apiResponse(404, $message, $data);
    }

    /**
     * 500 – Internal server error.
     */
    protected function serverError(string $message = 'An unexpected error occurred', mixed $data = null): JsonResponse
    {
        return $this->apiResponse(500, $message, $data);
    }
}
