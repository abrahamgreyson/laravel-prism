<?php

namespace Abe\Prism\Commands;

use Abe\Prism\Support\ExtensionInstallerManager;
use Abe\Prism\Support\ExtensionStateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class DoctorCommand extends Command
{
    protected $signature = 'prism:doctor {--fix : 尝试自动修复发现的问题}';

    protected $description = '检查 Prism 扩展系统的健康状态';

    protected ExtensionStateManager $stateManager;

    protected ExtensionInstallerManager $installerManager;

    protected array $issues = [];

    public function __construct()
    {
        parent::__construct();
        $this->stateManager = new ExtensionStateManager;
        $this->installerManager = new ExtensionInstallerManager;
    }

    public function handle(): int
    {
        $this->displayHeader();

        // 执行各项检查
        $this->checkPrismConfiguration();
        $this->checkStoragePermissions();
        $this->checkExtensionStates();
        $this->checkConfigurationConsistency();
        $this->checkComposerPackages();
        $this->checkServiceProviders();

        // 显示结果
        $this->displayResults();

        // 如果有问题且用户选择修复
        if (! empty($this->issues) && $this->option('fix')) {
            $this->attemptFixes();
        }

        return empty($this->issues) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 显示命令头部
     */
    protected function displayHeader(): void
    {
        $this->line('');
        info('🏥 Prism 健康检查');
        $this->line('');
        $this->line('<fg=cyan>正在检查系统状态...</>');
        $this->line('');
    }

    /**
     * 检查 Prism 配置
     */
    protected function checkPrismConfiguration(): void
    {
        $this->line('🔍 检查 Prism 配置...');

        $configPath = config_path('prism.php');
        if (! File::exists($configPath)) {
            $this->addIssue('critical', 'Prism 配置文件不存在', 'config_missing', [
                'fix' => '运行 php artisan vendor:publish --tag=prism-config',
            ]);

            return;
        }

        try {
            $config = include $configPath;
            if (! is_array($config)) {
                $this->addIssue('critical', 'Prism 配置文件格式错误', 'config_invalid');

                return;
            }

            // 检查必需的配置键
            $requiredKeys = ['enabled', 'auto_register'];
            foreach ($requiredKeys as $key) {
                if (! array_key_exists($key, $config)) {
                    $this->addIssue('warning', "缺少必需的配置键: {$key}", 'config_key_missing', [
                        'key' => $key,
                    ]);
                }
            }

            $this->line('   ✅ Prism 配置检查通过');
        } catch (\Exception $e) {
            $this->addIssue('critical', 'Prism 配置文件无法解析: '.$e->getMessage(), 'config_parse_error');
        }
    }

    /**
     * 检查存储权限
     */
    protected function checkStoragePermissions(): void
    {
        $this->line('🔍 检查存储权限...');

        $storageDir = storage_path('prism');

        if (! File::exists($storageDir)) {
            try {
                File::makeDirectory($storageDir, 0755, true);
                $this->line('   ✅ 已创建 Prism 存储目录');
            } catch (\Exception $e) {
                $this->addIssue('critical', '无法创建 Prism 存储目录', 'storage_create_failed', [
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        if (! is_writable($storageDir)) {
            $this->addIssue('critical', 'Prism 存储目录不可写', 'storage_not_writable', [
                'path' => $storageDir,
                'fix' => "chmod 755 {$storageDir}",
            ]);

            return;
        }

        $this->line('   ✅ 存储权限检查通过');
    }

    /**
     * 检查扩展状态
     */
    protected function checkExtensionStates(): void
    {
        $this->line('🔍 检查扩展状态...');

        $states = $this->stateManager->getAllStates();
        $installers = $this->installerManager->getInstallers();

        foreach ($states as $extension => $state) {
            // 检查是否有对应的安装器
            if (! isset($installers[$extension])) {
                $this->addIssue('warning', "状态记录中的扩展 '{$extension}' 没有对应的安装器", 'state_orphaned', [
                    'extension' => $extension,
                ]);

                continue;
            }

            $installer = $installers[$extension];
            $isInstalled = $installer->isInstalled();
            $managedByPrism = $state['managed_by_prism'] ?? false;

            // 检查状态一致性
            if ($managedByPrism && ! $isInstalled) {
                $this->addIssue('error', "扩展 '{$extension}' 记录为已安装但实际未找到", 'state_inconsistent', [
                    'extension' => $extension,
                    'fix' => "运行 prism:uninstall {$extension} 或重新安装",
                ]);
            }

            if (! $managedByPrism && $isInstalled && ! empty($state)) {
                $this->addIssue('info', "扩展 '{$extension}' 已安装但不在 Prism 管理范围内", 'state_unmanaged', [
                    'extension' => $extension,
                ]);
            }
        }

        // 检查已安装但没有状态记录的扩展
        foreach ($installers as $extension => $installer) {
            if ($installer->isInstalled() && ! isset($states[$extension])) {
                $this->addIssue('info', "扩展 '{$extension}' 已安装但没有状态记录", 'state_missing', [
                    'extension' => $extension,
                    'fix' => '这可能是手动安装的扩展',
                ]);
            }
        }

        $this->line('   ✅ 扩展状态检查完成');
    }

    /**
     * 检查配置一致性
     */
    protected function checkConfigurationConsistency(): void
    {
        $this->line('🔍 检查配置一致性...');

        $states = $this->stateManager->getAllStates();
        $config = config('prism', []);

        foreach ($states as $extension => $state) {
            if (! ($state['managed_by_prism'] ?? false)) {
                continue;
            }

            $extensionConfig = $config[$extension] ?? [];
            $autoRegister = $extensionConfig['auto_register'] ?? null;
            $status = $state['status'] ?? 'unknown';

            // 检查 auto_register 与状态的一致性
            if ($status === 'enabled' && $autoRegister === false) {
                $this->addIssue('warning', "扩展 '{$extension}' 状态为启用但配置中 auto_register 为 false", 'config_state_mismatch', [
                    'extension' => $extension,
                    'fix' => "运行 prism:enable {$extension}",
                ]);
            }

            if ($status === 'disabled' && $autoRegister === true) {
                $this->addIssue('warning', "扩展 '{$extension}' 状态为禁用但配置中 auto_register 为 true", 'config_state_mismatch', [
                    'extension' => $extension,
                    'fix' => "运行 prism:disable {$extension}",
                ]);
            }
        }

        $this->line('   ✅ 配置一致性检查完成');
    }

    /**
     * 检查 Composer 包
     */
    protected function checkComposerPackages(): void
    {
        $this->line('🔍 检查 Composer 包...');

        $composerLock = base_path('composer.lock');
        if (! File::exists($composerLock)) {
            $this->addIssue('warning', 'composer.lock 文件不存在', 'composer_lock_missing');

            return;
        }

        try {
            $lock = json_decode(File::get($composerLock), true);
            $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
            $packageNames = array_column($packages, 'name');

            $packageMap = [
                'telescope' => 'laravel/telescope',
                // 添加其他扩展的包名映射
            ];

            $states = $this->stateManager->getManagedExtensions();

            foreach ($states as $extension => $state) {
                $packageName = $packageMap[$extension] ?? null;
                if ($packageName && ! in_array($packageName, $packageNames)) {
                    $this->addIssue('error', "扩展 '{$extension}' 的 Composer 包 '{$packageName}' 未安装", 'composer_package_missing', [
                        'extension' => $extension,
                        'package' => $packageName,
                        'fix' => "运行 composer require {$packageName}",
                    ]);
                }
            }

            $this->line('   ✅ Composer 包检查完成');
        } catch (\Exception $e) {
            $this->addIssue('error', 'composer.lock 文件解析失败: '.$e->getMessage(), 'composer_lock_parse_error');
        }
    }

    /**
     * 检查服务提供者
     */
    protected function checkServiceProviders(): void
    {
        $this->line('🔍 检查服务提供者...');

        $serviceProviderMap = [
            'telescope' => 'Laravel\\Telescope\\TelescopeServiceProvider',
            // 添加其他扩展的服务提供者映射
        ];

        $enabledExtensions = $this->stateManager->getEnabledExtensions();

        foreach ($enabledExtensions as $extension => $state) {
            $providerClass = $serviceProviderMap[$extension] ?? null;
            if ($providerClass && ! class_exists($providerClass)) {
                $this->addIssue('error', "扩展 '{$extension}' 的服务提供者 '{$providerClass}' 不存在", 'service_provider_missing', [
                    'extension' => $extension,
                    'provider' => $providerClass,
                ]);
            }
        }

        $this->line('   ✅ 服务提供者检查完成');
    }

    /**
     * 添加问题
     */
    protected function addIssue(string $level, string $message, string $code, array $data = []): void
    {
        $this->issues[] = [
            'level' => $level,
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ];
    }

    /**
     * 显示检查结果
     */
    protected function displayResults(): void
    {
        $this->line('');

        if (empty($this->issues)) {
            info('🎉 所有检查均通过，Prism 系统运行正常！');

            return;
        }

        // 按级别分组显示问题
        $levels = ['critical', 'error', 'warning', 'info'];
        $levelColors = [
            'critical' => 'red',
            'error' => 'red',
            'warning' => 'yellow',
            'info' => 'blue',
        ];
        $levelIcons = [
            'critical' => '🚨',
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
        ];

        foreach ($levels as $level) {
            $levelIssues = array_filter($this->issues, fn ($issue) => $issue['level'] === $level);

            if (empty($levelIssues)) {
                continue;
            }

            $color = $levelColors[$level];
            $icon = $levelIcons[$level];
            $count = count($levelIssues);

            $this->line("<fg={$color}>{$icon} ".ucfirst($level)." ({$count})</>");

            foreach ($levelIssues as $issue) {
                $this->line("   • {$issue['message']}");
                if (isset($issue['data']['fix'])) {
                    $this->line("     💡 {$issue['data']['fix']}");
                }
            }
            $this->line('');
        }

        if ($this->option('fix')) {
            $this->line('<fg=cyan>💡 使用 --fix 选项尝试自动修复部分问题</>');
        }
    }

    /**
     * 尝试自动修复
     */
    protected function attemptFixes(): void
    {
        $this->line('');
        info('🔧 尝试自动修复...');
        $this->line('');

        $fixed = 0;

        foreach ($this->issues as $issue) {
            switch ($issue['code']) {
                case 'state_orphaned':
                    $this->line("   • 清理孤立状态记录: {$issue['data']['extension']}");
                    $this->stateManager->removeState($issue['data']['extension']);
                    $fixed++;
                    break;

                case 'config_state_mismatch':
                    $extension = $issue['data']['extension'];
                    $state = $this->stateManager->getState($extension);
                    $status = $state['status'] ?? 'unknown';

                    if ($status === 'enabled') {
                        $this->line("   • 同步启用状态: {$extension}");
                        $this->updateConfigAutoRegister($extension, true);
                    } elseif ($status === 'disabled') {
                        $this->line("   • 同步禁用状态: {$extension}");
                        $this->updateConfigAutoRegister($extension, false);
                    }
                    $fixed++;
                    break;
            }
        }

        if ($fixed > 0) {
            info("✅ 已修复 {$fixed} 个问题");
            $this->clearCaches();
        } else {
            warning('没有可以自动修复的问题');
        }
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
        } catch (\Exception $e) {
            // 静默处理缓存清除错误
        }
    }
}
