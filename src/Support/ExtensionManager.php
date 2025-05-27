<?php

namespace Abe\Prism\Support;

use Abe\Prism\Contracts\Extension;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;

class ExtensionManager
{
    /**
     * @var Extension[]
     */
    protected array $extensions = [];

    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 注册扩展
     */
    public function register(Extension $extension): self
    {
        $this->extensions[$extension->getName()] = $extension;

        return $this;
    }

    /**
     * 注册所有扩展的服务
     */
    public function registerAll(): void
    {
        foreach ($this->extensions as $extension) {
            if ($extension->shouldRegister($this->app)) {
                $extension->register($this->app);
            }
        }
    }

    /**
     * 启动所有扩展
     */
    public function bootAll(): void
    {
        foreach ($this->extensions as $extension) {
            if ($extension->shouldRegister($this->app)) {
                $extension->boot($this->app);
            }
        }
    }

    /**
     * 注册所有扩展的计划任务
     */
    public function scheduleAll(Schedule $schedule): void
    {
        foreach ($this->extensions as $extension) {
            if ($extension->shouldRegister($this->app)) {
                $extension->schedule($schedule);
            }
        }
    }

    /**
     * 获取已注册的扩展
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * 检查扩展是否已注册
     */
    public function hasExtension(string $name): bool
    {
        return isset($this->extensions[$name]);
    }

    /**
     * 获取指定扩展
     */
    public function getExtension(string $name): ?Extension
    {
        return $this->extensions[$name] ?? null;
    }
}
