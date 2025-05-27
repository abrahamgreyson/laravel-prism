<?php

namespace Abe\Prism\Commands;

use Abe\Prism\Support\ExtensionInstallerManager;
use Abe\Prism\Support\ExtensionStateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class EnableCommand extends Command
{
    protected $signature = 'prism:enable {extension : 要启用的扩展名称}';

    protected $description = '启用 Prism 管理的扩展';

    protected ExtensionStateManager $stateManager;

    protected ExtensionInstallerManager $installerManager;

    public function __construct()
    {
        parent::__construct();
        $this->stateManager = new ExtensionStateManager;
        $this->installerManager = new ExtensionInstallerManager;
    }

    public function handle(): int
    {
        $extension = $this->argument('extension');

        // 验证扩展是否存在
        $installer = $this->installerManager->getInstaller($extension);
        if (! $installer) {
            error("扩展 '{$extension}' 不存在");

            return self::FAILURE;
        }

        // 检查是否由 Prism 管理
        if (! $this->stateManager->isManagedByPrism($extension)) {
            error("扩展 '{$extension}' 不在 Prism 管理范围内");
            $this->line('只有通过 Prism 安装的扩展才能被启用');

            return self::FAILURE;
        }

        // 检查扩展是否仍然安装
        if (! $installer->isInstalled()) {
            error("扩展 '{$extension}' 似乎已被卸载");
            $this->line('请使用 <fg=green>prism:install</> 重新安装');

            return self::FAILURE;
        }

        // 检查当前状态
        if ($this->stateManager->isEnabled($extension)) {
            warning("扩展 '{$extension}' 已经是启用状态");

            return self::SUCCESS;
        }

        // 确认操作
        $displayName = $installer->getDisplayName();
        if (! confirm("确定要启用 {$displayName} 吗？")) {
            $this->line('操作已取消');

            return self::SUCCESS;
        }

        try {
            $this->enableExtension($extension);
            info("✅ 已成功启用 {$displayName}");

            $this->line('');
            $this->line('<fg=cyan>💡 说明:</>');
            $this->line('   • 扩展已在配置中启用，将自动注册服务提供者');
            $this->line('   • 配置将在下次请求时生效');
            $this->line("   • 使用 <fg=yellow>prism:disable {$extension}</> 可以禁用");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error('启用扩展失败: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * 启用扩展
     */
    protected function enableExtension(string $extension): void
    {
        // 更新状态
        $this->stateManager->updateStatus($extension, 'enabled');

        // 更新配置文件
        $this->updateConfigFile($extension);

        // 清除相关缓存
        $this->clearCaches();
    }

    /**
     * 更新配置文件
     */
    protected function updateConfigFile(string $extension): void
    {
        $configPath = config_path('prism.php');
        if (! File::exists($configPath)) {
            return;
        }

        $configContent = File::get($configPath);

        // 查找并更新 auto_register 配置
        $pattern = "/('$extension'\s*=>\s*\[.*?)('auto_register'\s*=>\s*)(true|false)/s";

        if (preg_match($pattern, $configContent)) {
            $configContent = preg_replace(
                $pattern,
                '$1$2true',
                $configContent
            );

            File::put($configPath, $configContent);
        }
    }

    /**
     * 清除缓存
     */
    protected function clearCaches(): void
    {
        try {
            \Artisan::call('config:clear');
            \Artisan::call('cache:clear');
        } catch (\Exception $e) {
            // 静默处理缓存清除错误
        }
    }
}
