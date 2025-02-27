<?php

namespace Abe\Prism\Traits;

use Illuminate\Http\JsonResponse;
use Jiannei\Response\Laravel\Support\Facades\Response;

trait HasResponse
{
    public function success(array $data = [], string $message = '', int $code = 200): JsonResponse
    {
        return Response::success($data, $message, $code);
    }


    public function error(array $data = [], int $code = 500, $message = null): JsonResponse
    {
        return Response::fail($data, $code, $message);
    }
}