<?php

namespace Abe\Prism\Tests\Traits;

use Abe\Prism\Traits\HasResponse;
use Illuminate\Http\JsonResponse;
use Jiannei\Response\Laravel\Support\Facades\Response;
use Mockery;

beforeEach(function () {
    // 重置 Response 门面
    Response::shouldReceive()->andReturn(null);
});

// 创建测试类使用 HasResponse trait
class TestController
{
    use HasResponse;
}

test('success 方法返回正确的成功响应', function () {
    $data = ['key' => 'value'];
    $message = 'Success message';
    $code = 200;
    
    $expectedResponse = new JsonResponse(['data' => $data]);
    
    // 设置 Response 门面的期望
    Response::shouldReceive('success')
        ->once()
        ->with($data, $message, $code)
        ->andReturn($expectedResponse);
    
    // 使用 trait 方法
    $controller = new TestController();
    $response = $controller->success($data, $message, $code);
    
    // 验证返回值
    expect($response)->toBe($expectedResponse);
    expect($response)->toBeInstanceOf(JsonResponse::class);
});

test('success 方法使用默认参数', function () {
    $expectedResponse = new JsonResponse(['data' => []]);
    
    // 设置 Response 门面的期望
    Response::shouldReceive('success')
        ->once()
        ->with([], '', 200)
        ->andReturn($expectedResponse);
    
    $controller = new TestController();
    $response = $controller->success();
    
    expect($response)->toBe($expectedResponse);
});

test('error 方法返回正确的错误响应', function () {
    $data = ['error' => 'Invalid input'];
    $code = 422;
    $message = 'Validation error';
    
    $expectedResponse = new JsonResponse(['errors' => $data], $code);
    
    // 设置 Response 门面的期望
    Response::shouldReceive('fail')
        ->once()
        ->with($message, $code, $data)  // 注意：参数顺序已更改
        ->andReturn($expectedResponse);
    
    // 使用 trait 方法
    $controller = new TestController();
    $response = $controller->error($message, $code, $data);  // 注意：参数顺序已更改
    
    // 验证返回值
    expect($response)->toBe($expectedResponse);
    expect($response)->toBeInstanceOf(JsonResponse::class);
});

test('error 方法使用默认参数', function () {
    $expectedResponse = new JsonResponse(['errors' => []], 500);
    
    // 设置 Response 门面的期望
    Response::shouldReceive('fail')
        ->once()
        ->with('', 500, [])  // 注意：参数顺序已更改
        ->andReturn($expectedResponse);
    
    $controller = new TestController();
    $response = $controller->error();
    
    expect($response)->toBe($expectedResponse);
});

test('success 方法和 error 方法返回不同的响应对象', function () {
    $successResponse = new JsonResponse(['data' => ['status' => 'ok']]);
    $errorResponse = new JsonResponse(['errors' => ['status' => 'error']], 400);
    
    Response::shouldReceive('success')
        ->once()
        ->andReturn($successResponse);
        
    Response::shouldReceive('fail')
        ->once()
        ->andReturn($errorResponse);
    
    $controller = new TestController();
    $success = $controller->success(['status' => 'ok']);
    $error = $controller->error('Error occurred', 400, ['status' => 'error']);  // 注意：参数顺序已更改
    
    expect($success)->not->toBe($error);
});

afterEach(function () {
    Mockery::close();
});
