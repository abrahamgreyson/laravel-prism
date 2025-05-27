<?php

namespace Abe\Prism\Extensions;

use Illuminate\Console\Scheduling\Schedule;

class OctaneExtension extends AbstractExtension
{
    /**
     * 获取 Octane 服务提供者类名
     */
    protected function getServiceProviderClass(): string
    {
        return 'Laravel\\Octane\\OctaneServiceProvider';
    }

    /**
     * 获取 Octane 的默认配置
     */
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'auto_register' => false,
            'environment' => 'production',
            'auto_restart' => false,
            'restart_interval' => 'hourly',
        ]);
    }

    /**
     * 注册 Octane 相关的计划任务
     */
    public function schedule(Schedule $schedule): void
    {
        $config = $this->getConfig();
        
        // 示例：定期重启 Octane 服务器（如果需要）
        if ($config['auto_restart']) {
            $interval = $config['restart_interval'];
            $command = $schedule->command('octane:reload');
            
            match ($interval) {
                'hourly' => $command->hourly(),
                'daily' => $command->daily(),
                'weekly' => $command->weekly(),
                default => $command->hourly(),
            };
        }
    }

    /**
     * 获取扩展名称
     */
    public function getName(): string
    {
        return 'octane';
    }

    /**
     * 获取扩展配置键
     */
    public function getConfigKey(): string
    {
        return 'prism.octane';
    }
}
