<?php

namespace Abe\Prism\Commands;

use Abe\Prism\Support\ExtensionInstallerManager;
use Abe\Prism\Support\ExtensionStateManager;
use Carbon\Carbon;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class StatusCommand extends Command
{
    protected $signature = 'prism:status {extension? : 扩展名称，留空显示所有扩展概览}';

    protected $description = '显示扩展的详细状态信息';

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

        if ($extension) {
            return $this->showExtensionStatus($extension);
        } else {
            return $this->showOverview();
        }
    }

    /**
     * 显示特定扩展的状态
     */
    protected function showExtensionStatus(string $extension): int
    {
        $installer = $this->installerManager->getInstaller($extension);
        if (! $installer) {
            error("扩展 '{$extension}' 不存在");
            $this->suggestAvailableExtensions();

            return self::FAILURE;
        }

        $state = $this->stateManager->getState($extension);
        $isInstalled = $installer->isInstalled();

        $this->displayExtensionHeader($installer->getDisplayName(), $extension);
        $this->displayBasicInfo($installer, $state, $isInstalled);
        $this->displayConfigurationInfo($extension, $state);
        $this->displayInstallationInfo($state);
        $this->displayHealthStatus($extension, $installer, $state);
        $this->displayAvailableActions($extension, $state, $isInstalled);

        return self::SUCCESS;
    }

    /**
     * 显示所有扩展概览
     */
    protected function showOverview(): int
    {
        $this->line('');
        info('🎯 Prism 扩展概览');
        $this->line('');

        $managedExtensions = $this->stateManager->getManagedExtensions();
        $enabledExtensions = $this->stateManager->getEnabledExtensions();

        // 统计信息
        $this->line('<fg=cyan>📊 统计信息</>');
        $this->line('   Prism 管理的扩展: <fg=green>'.count($managedExtensions).'</>');
        $this->line('   已启用的扩展: <fg=green>'.count($enabledExtensions).'</>');
        $this->line('');

        // 已启用的扩展
        if (! empty($enabledExtensions)) {
            $this->line('<fg=green>✅ 已启用的扩展</>');
            foreach ($enabledExtensions as $name => $state) {
                $installer = $this->installerManager->getInstaller($name);
                $displayName = $installer ? $installer->getDisplayName() : $name;
                $version = $state['version'] ? " (v{$state['version']})" : '';
                $this->line("   • {$displayName}{$version}");
            }
            $this->line('');
        }

        // 已禁用但由 Prism 管理的扩展
        $disabledManagedExtensions = array_filter($managedExtensions, function ($state) {
            return ($state['status'] ?? 'disabled') === 'disabled';
        });

        if (! empty($disabledManagedExtensions)) {
            $this->line('<fg=yellow>⏸️ 已禁用的扩展</>');
            foreach ($disabledManagedExtensions as $name => $state) {
                $installer = $this->installerManager->getInstaller($name);
                $displayName = $installer ? $installer->getDisplayName() : $name;
                $this->line("   • {$displayName}");
            }
            $this->line('');
        }

        // 手动安装的扩展
        $this->displayManuallyInstalledExtensions();

        $this->line('<fg=cyan>💡 提示</>');
        $this->line('   使用 <fg=green>prism:status <扩展名></> 查看详细信息');
        $this->line('   使用 <fg=green>prism:list</> 查看所有扩展');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * 显示扩展头部信息
     */
    protected function displayExtensionHeader(string $displayName, string $extension): void
    {
        $this->line('');
        info("🔍 {$displayName} ({$extension})");
        $this->line('');
    }

    /**
     * 显示基本信息
     */
    protected function displayBasicInfo($installer, array $state, bool $isInstalled): void
    {
        $this->line('<fg=cyan>📋 基本信息</>');
        $this->line("   描述: {$installer->getDescription()}");
        $this->line('   安装状态: '.($isInstalled ? '<fg=green>已安装</>' : '<fg=red>未安装</>'));

        $managedByPrism = $state['managed_by_prism'] ?? false;
        $this->line('   管理方式: '.($managedByPrism ? '<fg=green>Prism 管理</>' : '<fg=yellow>手动安装</>'));

        $status = $state['status'] ?? ($isInstalled ? 'manual' : 'not_installed');
        $this->line('   运行状态: '.$this->formatDetailedStatus($status));

        if ($version = $state['version'] ?? null) {
            $this->line("   版本: {$version}");
        }

        $this->line('');
    }

    /**
     * 显示配置信息
     */
    protected function displayConfigurationInfo(string $extension, array $state): void
    {
        $config = config("prism.{$extension}", []);

        if (empty($config)) {
            return;
        }

        $this->line('<fg=cyan>⚙️ 配置信息</>');

        foreach ($config as $key => $value) {
            $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            $this->line("   {$key}: <fg=yellow>{$valueStr}</>");
        }

        $this->line('');
    }

    /**
     * 显示安装信息
     */
    protected function displayInstallationInfo(array $state): void
    {
        if (empty($state) || ! ($state['managed_by_prism'] ?? false)) {
            return;
        }

        $this->line('<fg=cyan>📦 安装信息</>');

        if ($installedAt = $state['installed_at'] ?? null) {
            $date = Carbon::parse($installedAt)->format('Y-m-d H:i:s');
            $this->line("   安装时间: {$date}");
        }

        if ($method = $state['installation_method'] ?? null) {
            $this->line("   安装方式: {$method}");
        }

        if ($lastUpdated = $state['last_updated'] ?? null) {
            $date = Carbon::parse($lastUpdated)->format('Y-m-d H:i:s');
            $this->line("   最后更新: {$date}");
        }

        $this->line('');
    }

    /**
     * 显示健康状态
     */
    protected function displayHealthStatus(string $extension, $installer, array $state): void
    {
        $this->line('<fg=cyan>🏥 健康状态</>');

        $issues = [];

        // 检查安装状态一致性
        $isInstalled = $installer->isInstalled();
        $managedByPrism = $state['managed_by_prism'] ?? false;
        $recordExists = ! empty($state);

        if ($managedByPrism && ! $isInstalled) {
            $issues[] = '扩展在记录中显示已安装，但实际未找到';
        }

        if ($isInstalled && $recordExists && ! $managedByPrism) {
            $issues[] = '扩展已安装但不在 Prism 管理范围内';
        }

        // 检查配置状态
        $configExists = ! empty(config("prism.{$extension}"));
        if ($managedByPrism && ! $configExists) {
            $issues[] = '缺少配置信息';
        }

        if (empty($issues)) {
            $this->line('   <fg=green>✅ 状态正常</>');
        } else {
            $this->line('   <fg=red>❌ 发现问题:</>');
            foreach ($issues as $issue) {
                $this->line("      • {$issue}");
            }
        }

        $this->line('');
    }

    /**
     * 显示可用操作
     */
    protected function displayAvailableActions(string $extension, array $state, bool $isInstalled): void
    {
        $managedByPrism = $state['managed_by_prism'] ?? false;
        $status = $state['status'] ?? 'unknown';

        $this->line('<fg=cyan>🛠️ 可用操作</>');

        if (! $isInstalled) {
            $this->line('   <fg=green>prism:install</> - 安装扩展');
        } elseif ($managedByPrism) {
            if ($status === 'enabled') {
                $this->line("   <fg=yellow>prism:disable {$extension}</> - 禁用扩展");
            } elseif ($status === 'disabled') {
                $this->line("   <fg=green>prism:enable {$extension}</> - 启用扩展");
            }
            $this->line("   <fg=red>prism:uninstall {$extension}</> - 卸载扩展");
            $this->line("   <fg=blue>prism:reset {$extension}</> - 重置配置");
        } else {
            $this->line('   <fg=yellow>此扩展不在 Prism 管理范围内</>');
            $this->line('   如需 Prism 管理，请先手动卸载后通过 prism:install 重新安装');
        }

        $this->line('');
    }

    /**
     * 显示手动安装的扩展
     */
    protected function displayManuallyInstalledExtensions(): void
    {
        $manualExtensions = [];

        foreach ($this->installerManager->getInstallers() as $installer) {
            $name = $installer->getName();
            if ($installer->isInstalled() && ! $this->stateManager->isManagedByPrism($name)) {
                $manualExtensions[] = $installer;
            }
        }

        if (! empty($manualExtensions)) {
            $this->line('<fg=blue>🔧 手动安装的扩展</>');
            foreach ($manualExtensions as $installer) {
                $this->line("   • {$installer->getDisplayName()}");
            }
            $this->line('');
        }
    }

    /**
     * 建议可用的扩展
     */
    protected function suggestAvailableExtensions(): void
    {
        $available = array_keys($this->installerManager->getInstallers());
        $this->line('');
        $this->line('<fg=cyan>可用的扩展:</>');
        foreach ($available as $name) {
            $this->line("   • {$name}");
        }
    }

    /**
     * 格式化详细状态
     */
    protected function formatDetailedStatus(string $status): string
    {
        return match ($status) {
            'enabled' => '<fg=green>已启用</>',
            'disabled' => '<fg=yellow>已禁用</>',
            'manual' => '<fg=blue>手动安装</>',
            'not_installed' => '<fg=gray>未安装</>',
            default => "<fg=red>{$status}</>",
        };
    }
}
