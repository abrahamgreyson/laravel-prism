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

    public function error(string $message = '', $code = 500, array $data = []): JsonResponse
    {
        return Response::fail($message, $code, $data);
    }

    /**
     * Returns a failure response.
     * 
     * @alias $self::error
     *
     * @param string $message
     * @param int $code
     * @param array $data
     * @return JsonResponse
     */
    public function fail(string $message = '', int $code = 500, array $data = []): JsonResponse
    {
        return Response::fail($message, $code, $data);
    }
}
