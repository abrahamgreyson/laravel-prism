<?php

namespace Abe\Prism\Tests;

use Abe\Prism\PrismServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Laravel\Telescope\TelescopeServiceProvider;
use Mockery;

beforeEach(function () {
    // 清除任何现有的单例实例
    $this->app->forgetInstance('snowflake');
});

test('snowflake 服务成功注册', function () {
    // 检查 snowflake 是否作为单例被正确注册
    $snowflake1 = app('snowflake');
    $snowflake2 = app('snowflake');

    expect($snowflake1)
        ->toBe($snowflake2)
        ->and($snowflake1)
        ->toBeInstanceOf(\Godruoyi\Snowflake\Snowflake::class);

    // 检查 snowflake 是否使用了正确的序列解析器
    $reflectionProperty = new \ReflectionProperty($snowflake1, 'sequence');
    $reflectionProperty->setAccessible(true);
    $sequenceResolver = $reflectionProperty->getValue($snowflake1);

    expect($sequenceResolver)->toBeInstanceOf(\Godruoyi\Snowflake\LaravelSequenceResolver::class);
});

test('在 local 环境下使用 Mockery 测试 Telescope 注册', function () {
    // 创建一个模拟应用，设置为 local 环境
    $app = Mockery::mock(Application::class . ',' . CachesConfiguration::class);
    $app->shouldReceive('environment')->with('local')->andReturn(true);
    $app->shouldReceive('register')->with(TelescopeServiceProvider::class)->once();
    $app->shouldReceive('register')->with(\App\Providers\TelescopeServiceProvider::class)->once();
    $app->shouldReceive('singleton')->withArgs(['snowflake', Mockery::type('Closure')])->once();
    $app->shouldReceive('get')->with('cache.store')->andReturn(new class
    {
        public function remember()
        {
            return 0;
        }
    });

    // 添加对 configurationIsCached 方法的期望
    $app->shouldReceive('configurationIsCached')->andReturn(false);

    // 添加配置相关方法的模拟
    $config = Mockery::mock('config');
    $config->shouldReceive('get')->andReturn([]);
    $config->shouldReceive('set')->andReturn($config);
    $app->shouldReceive('make')->with('config')->andReturn($config);

    $provider = new PrismServiceProvider($app);
    $provider->register();
});

test('在 production 环境下使用 Mockery 测试 Telescope 不被注册', function () {
    $app = Mockery::mock(Application::class . ',' . CachesConfiguration::class);
    $app->shouldReceive('environment')->with('local')->andReturn(false);
    $app->shouldNotReceive('register')->with(TelescopeServiceProvider::class);
    $app->shouldNotReceive('register')->with(\App\Providers\TelescopeServiceProvider::class);
    $app->shouldReceive('singleton')->withArgs(['snowflake', Mockery::type('Closure')])->once();
    $app->shouldReceive('get')->with('cache.store')->andReturn(new class
    {
        public function remember()
        {
            return 0;
        }
    });

    // 添加对 configurationIsCached 方法的期望
    $app->shouldReceive('configurationIsCached')->andReturn(false);

    // 添加配置相关方法的模拟
    $config = Mockery::mock('config');
    $config->shouldReceive('get')->andReturn([]);
    $config->shouldReceive('set')->andReturn($config);
    $app->shouldReceive('make')->with('config')->andReturn($config);

    $provider = new PrismServiceProvider($app);
    $provider->register();
});

test('在 local 环境下使用 TestCase 测试 Telescope 注册', function () {
    // 使用 TestCase 的环境设置
    $this->withEnvironment('local');

    // 重新创建服务提供者并注册
    $provider = new PrismServiceProvider($this->app);
    $provider->register();

    // 检查 Telescope 服务提供者是否在已注册的提供者列表中
    $registeredProviders = array_keys($this->app->getLoadedProviders());
    expect($registeredProviders)->toContain(TelescopeServiceProvider::class);
});

test('在 production 环境下使用 TestCase 测试 Telescope 不被注册', function () {
    $this->withEnvironment('production');
    $this->app->forgetInstance(TelescopeServiceProvider::class);

    $provider = new PrismServiceProvider($this->app);
    $provider->register();

    $registeredProviders = array_keys($this->app->getLoadedProviders());
    expect($registeredProviders)->not->toContain(TelescopeServiceProvider::class);
});

test('计划任务中 Telescope 清理命令正确注册', function () {
    $schedule = Mockery::mock(Schedule::class);

    $event = Mockery::mock(\Illuminate\Console\Scheduling\Event::class);
    $schedule->shouldReceive('command')
        ->with('telescope:prune --hours=24')
        ->once()
        ->andReturn($event);
    $event->shouldReceive('daily')->once()->andReturnSelf();
    $event->shouldReceive('at')->with('02:00')->once();

    $provider = new PrismServiceProvider($this->app);
    $reflectionMethod = new \ReflectionMethod($provider, 'registerTelescopePruneCommand');
    $reflectionMethod->setAccessible(true);
    $reflectionMethod->invoke($provider, $schedule);

    // 使用 Mockery 的期望检查，不需要访问内部属性
    // Mockery 在测试结束时会自动验证所有期望是否被满足
});

test('服务提供者启动时注册 Schedule 回调', function () {
    // 不测试实际调用，而是测试回调是否正确注册

    // 模拟 Application 对象
    $app = Mockery::mock(Application::class . ',' . CachesConfiguration::class);
    $capturedCallback = null;

    // 需要同时模拟 afterResolving 方法，因为 callAfterResolving 内部会调用它
    $app->shouldReceive('afterResolving')
        ->with(Schedule::class, Mockery::type('Closure'))
        ->once();

    // 模拟 resolved 和 make 方法，因为 callAfterResolving 可能会调用它们
    $app->shouldReceive('resolved')
        ->with(Schedule::class)
        ->andReturn(false)
        ->once();

    // 添加对 configurationIsCached 方法的期望
    $app->shouldReceive('configurationIsCached')->andReturn(false);

    // 对 environment 期望
    $app->shouldReceive('environment')->with('local')->andReturn(true);

    // 添加配置相关方法的模拟
    $config = Mockery::mock('config');
    $config->shouldReceive('get')->withAnyArgs()->andReturn([]);
    $config->shouldReceive('set')->withAnyArgs()->andReturn($config);
    $app->shouldReceive('make')->with('config')->andReturn($config);
    
    // 添加对 register 方法的模拟
    $app->shouldReceive('register')->andReturnSelf();

    // 模拟 runningInConsole 方法
    $app->shouldReceive('runningInConsole')->andReturn(false);
    
    // 为 registerSnowflake 方法添加必要的期望
    $app->shouldReceive('singleton')
        ->withArgs(['snowflake', Mockery::type('Closure')])
        ->once();
    
    $app->shouldReceive('get')
        ->with('cache.store')
        ->andReturn(new class {
            public function remember() { return 0; }
        });

    // 执行启动方法前先注册服务提供者
    $provider = new PrismServiceProvider($app);
    $provider->register();
    $provider->boot();
});

test('JsonResource 包装被禁用', function () {
    expect(\Illuminate\Http\Resources\Json\JsonResource::$wrap)->toBeNull();
});

test('使用 CarbonImmutable 作为默认日期类', function () { expect(\Illuminate\Support\Facades\Date::now())->toBeInstanceOf(\Carbon\CarbonImmutable::class); });

afterEach(function () {
    Mockery::close();
});
