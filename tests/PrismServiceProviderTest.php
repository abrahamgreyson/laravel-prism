<?php

namespace Abe\Prism\Tests;

use Abe\Prism\PrismServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Laravel\Telescope\TelescopeServiceProvider;
use Mockery;
use Spatie\LaravelPackageTools\Package;

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

test('在 local 环境下通过 registerTelescope 方法测试 Telescope 注册', function () {
    // 创建一个模拟应用，设置为 local 环境
    $app = Mockery::mock(Application::class . ',' . CachesConfiguration::class);
    $app->shouldReceive('environment')->with('local')->andReturn(true);
    $app->shouldReceive('register')->with(TelescopeServiceProvider::class)->once();
    $app->shouldReceive('register')->with(\App\Providers\TelescopeServiceProvider::class)->once();
    
    // 创建服务提供者
    $provider = new PrismServiceProvider($app);
    
    // 直接调用 registerTelescope 方法
    $provider->registerTelescope();
});

test('在 production 环境下通过 registerTelescope 方法测试 Telescope 不被注册', function () {
    $app = Mockery::mock(Application::class . ',' . CachesConfiguration::class);
    $app->shouldReceive('environment')->with('local')->andReturn(false);
    $app->shouldNotReceive('register')->with(TelescopeServiceProvider::class);
    $app->shouldNotReceive('register')->with(\App\Providers\TelescopeServiceProvider::class);
    
    // 创建服务提供者
    $provider = new PrismServiceProvider($app);
    
    // 直接调用 registerTelescope 方法
    $provider->registerTelescope();
});

test('通过 registerSnowflake 方法测试 Snowflake 注册', function () {
    $app = Mockery::mock(Application::class . ',' . CachesConfiguration::class);
    $app->shouldReceive('singleton')
        ->withArgs(['snowflake', Mockery::type('Closure')])
        ->once();
    
    $app->shouldReceive('get')
        ->with('cache.store')
        ->andReturn(new class {
            public function remember() { return 0; }
        });
    
    // 创建服务提供者
    $provider = new PrismServiceProvider($app);
    
    // 直接调用 registerSnowflake 方法
    $provider->registerSnowflake();
});

test('registeringPackage 方法正确调用所有注册方法', function () {
    // 创建一个部分 mock，只 mock registerSnowflake 和 registerTelescope 方法
    $provider = Mockery::mock(PrismServiceProvider::class.'[registerSnowflake,registerTelescope]', [$this->app]);
    $provider->shouldAllowMockingProtectedMethods();
    
    // 设置期望
    $provider->shouldReceive('registerSnowflake')->once();
    $provider->shouldReceive('registerTelescope')->once();
    
    // 调用要测试的方法
    $provider->registeringPackage();
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
});

test('bootingPackage 方法正确设置了所有启动项', function () {
    // 模拟 Application 对象
    $app = Mockery::mock(Application::class . ',' . CachesConfiguration::class);
    
    // 需要同时模拟 callAfterResolving 方法
    $app->shouldReceive('afterResolving')
        ->with(Schedule::class, Mockery::type('Closure'))
        ->once();
    
    $app->shouldReceive('resolved')
        ->with(Schedule::class)
        ->andReturn(false)
        ->once();
    
    // 添加配置相关方法的模拟
    $config = Mockery::mock('config');
    $config->shouldReceive('get')
        ->with('prism.immutable_date', true)
        ->andReturn(true);
    $app->shouldReceive('make')->with('config')->andReturn($config);
    
    // 创建一个部分 mock，只 mock registerTelescopePruneCommand 方法
    $provider = new PrismServiceProvider($app);
    
    // 执行要测试的方法
    $provider->bootingPackage();
    
    // 验证 JsonResource 包装被禁用
    expect(\Illuminate\Http\Resources\Json\JsonResource::$wrap)->toBeNull();
    
    // 验证使用的是 CarbonImmutable
    expect(\Illuminate\Support\Facades\Date::now())->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('configurePackage 方法正确配置了包', function () {
    $packageMock = Mockery::mock(Package::class);
    $packageMock->shouldReceive('name')->with('laravel-prism')->once()->andReturnSelf();
    $packageMock->shouldReceive('hasConfigFile')->with('prism')->once()->andReturnSelf();
    $packageMock->shouldReceive('hasCommand')->with(\Abe\Prism\Commands\InstallCommand::class)->once()->andReturnSelf();
    
    $provider = new PrismServiceProvider($this->app);
    $provider->configurePackage($packageMock);
});

afterEach(function () {
    Mockery::close();
});
