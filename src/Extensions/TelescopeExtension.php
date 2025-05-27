<?php

namespace Abe\Prism\Extensions;

use Illuminate\Console\Scheduling\Schedule;

class TelescopeExtension extends AbstractExtension
{
    /**
     * 获取 Telescope 服务提供者类名
     */
    protected function getServiceProviderClass(): string
    {
        return 'Laravel\\Telescope\\TelescopeServiceProvider';
    }

    /**
     * 获取 Telescope 的默认配置
     */
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'auto_register' => true,
            'environment' => 'local',
            'auto_prune' => true,
            'prune_hours' => 24,
        ]);
    }

    /**
     * 获取额外的服务提供者
     */
    protected function getAdditionalServiceProviders(): array
    {
        return [
            'App\\Providers\\TelescopeServiceProvider',
        ];
    }

    /**
     * 注册 Telescope 清理计划任务
     */
    public function schedule(Schedule $schedule): void
    {
        $config = $this->getConfig();

        // 检查是否启用自动清理
        if ($config['auto_prune']) {
            $hours = $config['prune_hours'];
            $schedule->command("telescope:prune --hours={$hours}")
                ->daily()
                ->at('02:00');
        }
    }

    /**
     * 获取扩展名称
     */
    public function getName(): string
    {
        return 'telescope';
    }

    /**
     * 获取扩展配置键
     */
    public function getConfigKey(): string
    {
        return 'prism.telescope';
    }
}
