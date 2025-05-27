<?php

namespace Abe\Prism\Extensions;

use Abe\Prism\Contracts\Extension;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;

abstract class AbstractExtension implements Extension
{
    /**
     * 扩展的服务提供者类名
     */
    abstract protected function getServiceProviderClass(): string;

    /**
     * 扩展的默认配置
     */
    protected function getDefaultConfig(): array
    {
        return [
            'auto_register' => false,
            'environment' => 'all',
        ];
    }

    /**
     * 获取扩展配置
     */
    protected function getConfig(): array
    {
        $config = config($this->getConfigKey(), []);
        return array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * 检查扩展是否已安装
     */
    public function isInstalled(): bool
    {
        return class_exists($this->getServiceProviderClass());
    }

    /**
     * 检查是否应该注册扩展
     */
    public function shouldRegister(Application $app): bool
    {
        $config = $this->getConfig();
        
        // 只有在配置了自动注册且扩展存在时才注册
        if (!$config['auto_register'] || !$this->isInstalled()) {
            return false;
        }

        return $this->shouldRegisterForEnvironment($app, $config['environment']);
    }

    /**
     * 根据环境配置决定是否注册
     */
    protected function shouldRegisterForEnvironment(Application $app, string $environment): bool
    {
        return match ($environment) {
            'all' => true,
            'production' => $app->environment('production'),
            'dev' => $app->environment('local', 'testing'),
            default => false,
        };
    }

    /**
     * 注册扩展服务
     */
    public function register(Application $app): void
    {
        $app->register($this->getServiceProviderClass());
        
        // 注册额外的服务提供者
        foreach ($this->getAdditionalServiceProviders() as $provider) {
            if (class_exists($provider)) {
                $app->register($provider);
            }
        }
    }

    /**
     * 获取额外的服务提供者
     */
    protected function getAdditionalServiceProviders(): array
    {
        return [];
    }

    /**
     * 启动扩展（默认实现为空）
     */
    public function boot(Application $app): void
    {
        // 默认情况下不需要特殊的启动逻辑
        // 子类可以覆盖此方法来添加自定义逻辑
    }

    /**
     * 注册计划任务（默认实现为空）
     */
    public function schedule(Schedule $schedule): void
    {
        // 默认情况下不注册计划任务
        // 子类可以覆盖此方法来添加计划任务
    }
}
