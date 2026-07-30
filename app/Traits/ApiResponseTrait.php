<?php

namespace App\Traits;

trait ApiResponseTrait
{
    public function successResponse($data = null, string $message = 'Success', int $status = 200, array $meta = [])
    {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $meta ? ['meta' => $meta] : []), $status);
    }

    public function errorResponse(string $message = 'Error', int $status = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
