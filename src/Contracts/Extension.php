<?php

namespace Abe\Prism\Contracts;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;

interface Extension
{
    /**
     * 检查扩展是否已安装
     */
    public function isInstalled(): bool;

    /**
     * 检查是否应该注册该扩展
     */
    public function shouldRegister(Application $app): bool;

    /**
     * 注册扩展服务
     */
    public function register(Application $app): void;

    /**
     * 启动扩展
     */
    public function boot(Application $app): void;

    /**
     * 注册计划任务
     */
    public function schedule(Schedule $schedule): void;

    /**
     * 获取扩展名称
     */
    public function getName(): string;

    /**
     * 获取扩展配置键
     */
    public function getConfigKey(): string;
}
