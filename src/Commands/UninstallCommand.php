<?php

namespace Abe\Prism\Commands;

use Abe\Prism\Support\ExtensionInstallerManager;
use Abe\Prism\Support\ExtensionStateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class UninstallCommand extends Command
{
    protected $signature = 'prism:uninstall {extension : 要卸载的扩展名称} {--force : 强制卸载，跳过确认}';

    protected $description = '卸载 Prism 管理的扩展';

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
        $force = $this->option('force');

        // 验证扩展是否存在
        $installer = $this->installerManager->getInstaller($extension);
        if (! $installer) {
            error("扩展 '{$extension}' 不存在");

            return self::FAILURE;
        }

        // 检查是否由 Prism 管理
        if (! $this->stateManager->isManagedByPrism($extension)) {
            error("扩展 '{$extension}' 不在 Prism 管理范围内");
            $this->line('只有通过 Prism 安装的扩展才能被卸载');
            $this->line('如需卸载手动安装的扩展，请使用 composer remove 命令');

            return self::FAILURE;
        }

        $displayName = $installer->getDisplayName();

        // 显示卸载信息
        $this->displayUninstallInfo($extension, $installer);

        // 确认操作
        if (! $force) {
            $this->line('');
            warning('⚠️  这将完全移除扩展及其配置');
            if (! confirm("确定要卸载 {$displayName} 吗？")) {
                $this->line('操作已取消');

                return self::SUCCESS;
            }
        }

        try {
            $this->uninstallExtension($extension, $installer);
            info("✅ 已成功卸载 {$displayName}");

            $this->line('');
            $this->line('<fg=cyan>💡 说明:</>');
            $this->line('   • 扩展包已从项目中移除');
            $this->line('   • 相关配置已清理');
            $this->line('   • 状态记录已删除');
            $this->line("   • 使用 <fg=green>prism:install {$extension}</> 可重新安装");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error('卸载扩展失败: '.$e->getMessage());
            $this->line('');
            $this->line('<fg=yellow>建议:</>');
            $this->line('   • 检查是否有其他依赖此扩展的包');
            $this->line('   • 尝试手动运行 composer remove 命令');
            $this->line('   • 使用 <fg=green>prism:doctor</> 检查系统状态');

            return self::FAILURE;
        }
    }

    /**
     * 显示卸载信息
     */
    protected function displayUninstallInfo(string $extension, $installer): void
    {
        $state = $this->stateManager->getState($extension);

        $this->line('');
        info("🗑️  准备卸载 {$installer->getDisplayName()}");
        $this->line('');

        $this->line('<fg=cyan>📋 扩展信息</>');
        $this->line("   名称: {$installer->getDisplayName()}");
        $this->line("   描述: {$installer->getDescription()}");
        if ($version = $state['version'] ?? null) {
            $this->line("   版本: {$version}");
        }
        $this->line('   状态: '.($this->stateManager->isEnabled($extension) ? '<fg=green>已启用</>' : '<fg=yellow>已禁用</>'));

        if ($installedAt = $state['installed_at'] ?? null) {
            $date = \Carbon\Carbon::parse($installedAt)->format('Y-m-d H:i:s');
            $this->line("   安装时间: {$date}");
        }
    }

    /**
     * 卸载扩展
     */
    protected function uninstallExtension(string $extension, $installer): void
    {
        $this->line('');
        $this->line('<fg=cyan>🔄 正在卸载...</>');

        // 1. 禁用扩展（如果启用）
        if ($this->stateManager->isEnabled($extension)) {
            $this->line('   • 禁用扩展服务');
            $this->stateManager->updateStatus($extension, 'disabled');
            $this->updateConfigAutoRegister($extension, false);
        }

        // 2. 运行扩展自定义的卸载逻辑
        $this->line('   • 执行扩展卸载逻辑');
        if (method_exists($installer, 'uninstall')) {
            $installer->uninstall();
        }

        // 3. 移除 Composer 包
        $this->line('   • 移除 Composer 包');
        $this->removeComposerPackage($extension);

        // 4. 清理配置
        $this->line('   • 清理配置文件');
        $this->cleanupConfiguration($extension);

        // 5. 移除状态记录
        $this->line('   • 清理状态记录');
        $this->stateManager->removeState($extension);

        // 6. 清除缓存
        $this->line('   • 清除缓存');
        $this->clearCaches();
    }

    /**
     * 移除 Composer 包
     */
    protected function removeComposerPackage(string $extension): void
    {
        $packageMap = [
            'telescope' => 'laravel/telescope',
            // 添加其他扩展的包名映射
        ];

        $packageName = $packageMap[$extension] ?? null;
        if (! $packageName) {
            return;
        }

        // 执行 composer remove
        $process = new Process(['composer', 'remove', $packageName], base_path());
        $process->setTimeout(300); // 5 分钟超时

        $process->run(function ($type, $buffer) {
            // 静默执行，避免在 Artisan 命令中输出过多信息
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('移除 Composer 包失败: '.$process->getErrorOutput());
        }
    }

    /**
     * 清理配置
     */
    protected function cleanupConfiguration(string $extension): void
    {
        $configPath = config_path('prism.php');
        if (! File::exists($configPath)) {
            return;
        }

        $configContent = File::get($configPath);

        // 移除整个扩展配置块
        $pattern = "/\s*'$extension'\s*=>\s*\[.*?\],?/s";
        $configContent = preg_replace($pattern, '', $configContent);

        // 清理可能的连续逗号
        $configContent = preg_replace('/,(\s*,)+/', ',', $configContent);
        $configContent = preg_replace('/,(\s*\])/', '$1', $configContent);

        File::put($configPath, $configContent);
    }

    /**
     * 更新配置文件的 auto_register 设置
     */
    protected function updateConfigAutoRegister(string $extension, bool $enabled): void
    {
        $configPath = config_path('prism.php');
        if (! File::exists($configPath)) {
            return;
        }

        $configContent = File::get($configPath);

        $pattern = "/('$extension'\s*=>\s*\[.*?)('auto_register'\s*=>\s*)(true|false)/s";

        if (preg_match($pattern, $configContent)) {
            $configContent = preg_replace(
                $pattern,
                '$1$2'.($enabled ? 'true' : 'false'),
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
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
        } catch (\Exception $e) {
            // 静默处理缓存清除错误
        }
    }
}
