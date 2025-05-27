<?php

namespace Abe\Prism\Commands;

use Abe\Prism\Support\ExtensionStateManager;
use Abe\Prism\Support\ExtensionInstallerManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class ResetCommand extends Command
{
    protected $signature = 'prism:reset {extension? : 要重置的扩展名称} {--all : 重置所有扩展} {--config-only : 只重置配置，保留状态记录}';
    protected $description = '重置扩展配置到默认状态';

    protected ExtensionStateManager $stateManager;
    protected ExtensionInstallerManager $installerManager;

    public function __construct()
    {
        parent::__construct();
        $this->stateManager = new ExtensionStateManager();
        $this->installerManager = new ExtensionInstallerManager();
    }

    public function handle(): int
    {
        $extension = $this->argument('extension');
        $resetAll = $this->option('all');
        $configOnly = $this->option('config-only');
        
        if ($resetAll) {
            return $this->resetAllExtensions($configOnly);
        }
        
        if (!$extension) {
            return $this->interactiveReset($configOnly);
        }
        
        return $this->resetExtension($extension, $configOnly);
    }

    /**
     * 交互式重置
     */
    protected function interactiveReset(bool $configOnly): int
    {
        $managedExtensions = $this->stateManager->getManagedExtensions();
        
        if (empty($managedExtensions)) {
            warning('没有找到 Prism 管理的扩展');
            return self::SUCCESS;
        }
        
        $choices = [];
        foreach ($managedExtensions as $name => $state) {
            $installer = $this->installerManager->getInstaller($name);
            $displayName = $installer ? $installer->getDisplayName() : $name;
            $status = $state['status'] ?? 'unknown';
            $choices[$name] = "{$displayName} ({$status})";
        }
        
        $extension = select(
            '选择要重置的扩展:',
            $choices
        );
        
        return $this->resetExtension($extension, $configOnly);
    }

    /**
     * 重置所有扩展
     */
    protected function resetAllExtensions(bool $configOnly): int
    {
        $managedExtensions = $this->stateManager->getManagedExtensions();
        
        if (empty($managedExtensions)) {
            warning('没有找到 Prism 管理的扩展');
            return self::SUCCESS;
        }
        
        $this->line('');
        info('🔄 准备重置所有 Prism 管理的扩展');
        $this->line('');
        
        $this->line('<fg=cyan>将要重置的扩展:</>');
        foreach ($managedExtensions as $name => $state) {
            $installer = $this->installerManager->getInstaller($name);
            $displayName = $installer ? $installer->getDisplayName() : $name;
            $status = $state['status'] ?? 'unknown';
            $this->line("   • {$displayName} ({$status})");
        }
        $this->line('');
        
        $action = $configOnly ? '重置配置' : '完全重置';
        if (!confirm("确定要 {$action} 所有这些扩展吗？")) {
            $this->line('操作已取消');
            return self::SUCCESS;
        }
        
        $this->line('');
        $this->line('<fg=cyan>🔄 正在重置...</>');
        
        $success = 0;
        $failed = 0;
        
        foreach ($managedExtensions as $name => $state) {
            try {
                $this->line("   • 重置 {$name}");
                $this->performReset($name, $configOnly);
                $success++;
            } catch (\Exception $e) {
                $this->line("   <fg=red>✗</> 重置 {$name} 失败: " . $e->getMessage());
                $failed++;
            }
        }
        
        $this->line('');
        if ($success > 0) {
            info("✅ 已成功重置 {$success} 个扩展");
        }
        if ($failed > 0) {
            warning("⚠️  {$failed} 个扩展重置失败");
        }
        
        $this->clearCaches();
        
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 重置指定扩展
     */
    protected function resetExtension(string $extension, bool $configOnly): int
    {
        // 验证扩展是否存在
        $installer = $this->installerManager->getInstaller($extension);
        if (!$installer) {
            error("扩展 '{$extension}' 不存在");
            return self::FAILURE;
        }

        // 检查是否由 Prism 管理
        if (!$this->stateManager->isManagedByPrism($extension)) {
            error("扩展 '{$extension}' 不在 Prism 管理范围内");
            return self::FAILURE;
        }

        $displayName = $installer->getDisplayName();
        
        $this->line('');
        info("🔄 准备重置 {$displayName}");
        $this->line('');
        
        // 显示当前状态
        $this->displayCurrentState($extension, $installer);
        
        // 确认操作
        $action = $configOnly ? '重置配置' : '完全重置';
        if (!confirm("确定要 {$action} {$displayName} 吗？")) {
            $this->line('操作已取消');
            return self::SUCCESS;
        }

        try {
            $this->performReset($extension, $configOnly);
            
            $this->line('');
            info("✅ 已成功重置 {$displayName}");
            
            $this->displayResetInfo($configOnly);
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            error("重置扩展失败: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * 显示当前状态
     */
    protected function displayCurrentState(string $extension, $installer): void
    {
        $state = $this->stateManager->getState($extension);
        
        $this->line('<fg=cyan>📋 当前状态</>');
        $this->line("   扩展名: {$installer->getDisplayName()}");
        $this->line("   状态: " . ($this->stateManager->isEnabled($extension) ? '<fg=green>已启用</>' : '<fg=yellow>已禁用</>'));
        
        if ($version = $state['version'] ?? null) {
            $this->line("   版本: {$version}");
        }
        
        if ($installedAt = $state['installed_at'] ?? null) {
            $date = \Carbon\Carbon::parse($installedAt)->format('Y-m-d H:i:s');
            $this->line("   安装时间: {$date}");
        }
        
        $config = config("prism.{$extension}", []);
        if (!empty($config)) {
            $this->line("   配置项: " . count($config) . " 个");
        }
    }

    /**
     * 执行重置
     */
    protected function performReset(string $extension, bool $configOnly): void
    {
        $installer = $this->installerManager->getInstaller($extension);
        
        if (!$configOnly) {
            // 完全重置：重置状态记录
            $currentState = $this->stateManager->getState($extension);
            
            // 保留必要信息，重置其他信息
            $this->stateManager->recordInstallation($extension, [
                'installation_method' => $currentState['installation_method'] ?? 'prism',
                'configuration' => []
            ]);
            
            // 重新启用（如果之前是启用状态）
            $this->stateManager->updateStatus($extension, 'enabled');
        }
        
        // 重置配置文件
        $this->resetConfiguration($extension, $installer);
    }

    /**
     * 重置配置
     */
    protected function resetConfiguration(string $extension, $installer): void
    {
        $configPath = config_path('prism.php');
        if (!File::exists($configPath)) {
            return;
        }

        // 获取扩展的默认配置
        $defaultConfig = [];
        if (method_exists($installer, 'getDefaultConfig')) {
            $defaultConfig = $installer->getDefaultConfig();
        } else {
            // 尝试从扩展类获取默认配置
            $extensionClass = $installer->getExtensionClass();
            if ($extensionClass && method_exists($extensionClass, 'getDefaultConfig')) {
                $defaultConfig = $extensionClass::getDefaultConfig();
            }
        }
        
        // 确保包含必要的配置项
        $defaultConfig = array_merge([
            'auto_register' => true,
            'environments' => ['local', 'production'],
        ], $defaultConfig);

        $configContent = File::get($configPath);
        
        // 查找并替换扩展配置块
        $pattern = "/('$extension'\s*=>\s*)\[.*?\]/s";
        
        $newConfigBlock = $this->arrayToConfigString($defaultConfig, 2);
        $replacement = '$1' . $newConfigBlock;
        
        if (preg_match($pattern, $configContent)) {
            $configContent = preg_replace($pattern, $replacement, $configContent);
        } else {
            // 如果配置块不存在，添加到文件末尾
            $configContent = $this->addConfigurationBlock($configContent, $extension, $defaultConfig);
        }
        
        File::put($configPath, $configContent);
    }

    /**
     * 将数组转换为配置字符串
     */
    protected function arrayToConfigString(array $array, int $indent = 0): string
    {
        $indentStr = str_repeat('    ', $indent);
        $lines = ["["];
        
        foreach ($array as $key => $value) {
            $keyStr = is_string($key) ? "'{$key}'" : $key;
            
            if (is_array($value)) {
                $valueStr = $this->arrayToConfigString($value, $indent + 1);
            } elseif (is_bool($value)) {
                $valueStr = $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                $valueStr = "'{$value}'";
            } else {
                $valueStr = (string) $value;
            }
            
            $lines[] = "{$indentStr}    {$keyStr} => {$valueStr},";
        }
        
        $lines[] = "{$indentStr}]";
        
        return implode("\n", $lines);
    }

    /**
     * 添加配置块
     */
    protected function addConfigurationBlock(string $configContent, string $extension, array $config): string
    {
        $configBlock = $this->arrayToConfigString($config, 1);
        $newEntry = "\n    '{$extension}' => {$configBlock},";
        
        // 在最后一个 ]; 之前插入新配置
        $configContent = preg_replace('/(\s*\];?\s*)$/', $newEntry . '$1', $configContent);
        
        return $configContent;
    }

    /**
     * 显示重置说明信息
     */
    protected function displayResetInfo(bool $configOnly): void
    {
        $this->line('');
        $this->line('<fg=cyan>💡 重置完成说明:</>');
        
        if ($configOnly) {
            $this->line("   • 扩展配置已重置为默认值");
            $this->line("   • 状态记录保持不变");
        } else {
            $this->line("   • 扩展配置已重置为默认值");
            $this->line("   • 状态记录已重置（保留安装信息）");
            $this->line("   • 扩展已设置为启用状态");
        }
        
        $this->line("   • 配置将在下次请求时生效");
        $this->line("   • 使用 <fg=green>prism:status <扩展名></> 查看详细信息");
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
