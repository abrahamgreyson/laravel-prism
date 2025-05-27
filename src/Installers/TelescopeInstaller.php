<?php

namespace Abe\Prism\Installers;

use Abe\Prism\Support\AbstractExtensionInstaller;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class TelescopeInstaller extends AbstractExtensionInstaller
{
    /**
     * 获取扩展名称
     */
    public function getName(): string
    {
        return 'telescope';
    }

    /**
     * 获取扩展显示名称
     */
    public function getDisplayName(): string
    {
        return 'Laravel Telescope';
    }

    /**
     * 获取扩展描述
     */
    public function getDescription(): string
    {
        return '调试和性能分析工具（将引导安装过程）';
    }

    /**
     * 获取 Composer 包名
     */
    protected function getComposerPackage(): string
    {
        return 'laravel/telescope';
    }

    /**
     * 获取服务提供者类名
     */
    protected function getServiceProviderClass(): string
    {
        return 'Laravel\\Telescope\\TelescopeServiceProvider';
    }

    /**
     * 获取扩展类名
     */
    public function getExtensionClass(): ?string
    {
        return 'Abe\\Prism\\Extensions\\TelescopeExtension';
    }

    /**
     * 获取安装选项
     */
    public function getInstallOptions(): array
    {
        return [
            'telescope_install' => false,
            'telescope_environment' => 'local',
        ];
    }

    /**
     * 配置安装选项
     */
    public function configureOptions(array $options): array
    {
        if (isset($options['telescope_install']) && $options['telescope_install']) {
            $options['telescope_environment'] = select(
                '请选择 Telescope 的安装环境：',
                [
                    'local' => '仅本地环境 (local)',
                    'production' => '仅生产环境 (production)',
                    'all' => '所有环境',
                ],
                'local'
            );
        }

        return $options;
    }

    /**
     * 获取安装步骤
     */
    protected function getInstallSteps(array $options): array
    {
        $devFlag = $options['telescope_environment'] === 'local' ? ' --dev' : '';
        $steps = [
            "composer require laravel/telescope{$devFlag}",
            'php artisan telescope:install',
        ];

        if ($options['telescope_environment'] === 'local') {
            $steps[] = '移除 bootstrap/providers.php 中的 TelescopeServiceProvider 注册';
            $steps[] = '配置 composer.json 的 dont-discover';
            $steps[] = '由 Prism 控制 Telescope 的环境加载';
        } else {
            $steps[] = 'php artisan migrate';
        }

        return $steps;
    }

    /**
     * 执行安装步骤
     */
    protected function executeInstallSteps(OutputInterface $output, array $options): bool
    {
        // 1. 安装 Telescope 包
        if (! $this->installComposerPackage($output, $options)) {
            return false;
        }

        // 2. 重新加载自动加载器
        $this->reloadComposerAutoloader($output);

        // 3. 运行 telescope:install
        if (! $this->runTelescopeInstall($output)) {
            return false;
        }

        // 4. 根据环境执行不同的配置
        if ($options['telescope_environment'] === 'local') {
            // Local 环境：移除自动注册，配置 dont-discover
            $this->removeTelescopeFromProviders($output);
            $this->configureComposerDontDiscover($output);
            info('✅ Telescope 已配置为仅在本地环境通过 Prism 加载');
        } else {
            // Production 或 All 环境：运行数据库迁移
            if (! $this->runMigrations($output)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 运行 telescope:install 命令
     */
    protected function runTelescopeInstall(OutputInterface $output): bool
    {
        $output->writeln('<info>正在执行 telescope:install...</info>');

        try {
            $command = 'php artisan telescope:install';
            $output->writeln("<comment>执行: {$command}</comment>");

            $success = $this->runCommandWithRealTimeOutput($command, $output, base_path());

            if ($success) {
                info('✅ Telescope 初始化成功！');

                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $output->writeln("<comment>Telescope 初始化失败: {$e->getMessage()}</comment>");

            return false;
        }
    }

    /**
     * 运行数据库迁移
     */
    protected function runMigrations(OutputInterface $output): bool
    {
        $output->writeln('<info>正在执行数据库迁移...</info>');

        if (confirm('是否立即运行数据库迁移？', true)) {
            $migrateCommand = 'php artisan migrate';
            $output->writeln("<comment>执行: {$migrateCommand}</comment>");

            $success = $this->runCommandWithRealTimeOutput($migrateCommand, $output, base_path());

            if ($success) {
                info('✅ 数据库迁移完成！');

                return true;
            } else {
                warning('⚠️ 数据库迁移失败，请手动运行: php artisan migrate');

                return false;
            }
        } else {
            warning('⚠️ 请记得手动运行: php artisan migrate');

            return true; // 用户选择不迁移，但不算失败
        }
    }

    /**
     * 配置 composer.json 的 dont-discover
     */
    protected function configureComposerDontDiscover(OutputInterface $output): void
    {
        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            return;
        }

        try {
            $composer = json_decode(File::get($composerPath), true);

            // 确保 extra.laravel.dont-discover 存在
            if (! isset($composer['extra'])) {
                $composer['extra'] = [];
            }
            if (! isset($composer['extra']['laravel'])) {
                $composer['extra']['laravel'] = [];
            }
            if (! isset($composer['extra']['laravel']['dont-discover'])) {
                $composer['extra']['laravel']['dont-discover'] = [];
            }

            // 添加 Telescope 到 dont-discover 列表
            if (! in_array('laravel/telescope', $composer['extra']['laravel']['dont-discover'])) {
                $composer['extra']['laravel']['dont-discover'][] = 'laravel/telescope';

                File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $output->writeln('<info>已配置 composer.json 的 dont-discover 设置。</info>');
            }
        } catch (\Exception $e) {
            $output->writeln('<comment>无法自动配置 composer.json，请手动添加 Telescope 到 dont-discover 列表。</comment>');
        }
    }

    /**
     * 从 bootstrap/providers.php 中移除 TelescopeServiceProvider
     */
    protected function removeTelescopeFromProviders(OutputInterface $output): void
    {
        $providersPath = base_path('bootstrap/providers.php');

        if (! File::exists($providersPath)) {
            $output->writeln('<comment>bootstrap/providers.php 文件不存在，跳过移除步骤。</comment>');

            return;
        }

        try {
            $providersContent = File::get($providersPath);

            // 检查是否包含 TelescopeServiceProvider
            if (strpos($providersContent, 'TelescopeServiceProvider') === false) {
                $output->writeln('<info>bootstrap/providers.php 中未发现 TelescopeServiceProvider，无需移除。</info>');

                return;
            }

            // 移除 TelescopeServiceProvider 相关行
            $lines = explode("\n", $providersContent);
            $filteredLines = [];

            foreach ($lines as $line) {
                // 跳过包含 TelescopeServiceProvider 的行
                if (strpos($line, 'TelescopeServiceProvider') === false) {
                    $filteredLines[] = $line;
                } else {
                    $output->writeln('<comment>移除行: '.trim($line).'</comment>');
                }
            }

            $newContent = implode("\n", $filteredLines);
            File::put($providersPath, $newContent);
            $output->writeln('<info>已从 bootstrap/providers.php 中移除 TelescopeServiceProvider。</info>');

        } catch (\Exception $e) {
            $output->writeln("<comment>无法自动移除 TelescopeServiceProvider: {$e->getMessage()}</comment>");
            $output->writeln('<comment>请手动从 bootstrap/providers.php 中移除 Laravel\\Telescope\\TelescopeServiceProvider::class</comment>');
        }
    }

    /**
     * 显示手动安装步骤
     */
    public function showManualSteps(OutputInterface $output, array $options): void
    {
        $devFlag = $options['telescope_environment'] === 'local' ? ' --dev' : '';

        // 检查各个步骤的完成状态
        $telescopeInstalled = $this->isInstalled();
        $telescopeConfigExists = File::exists(config_path('telescope.php'));
        $composerJsonConfigured = $this->isComposerJsonConfigured();
        $providersFileClean = $this->isProvidersFileClean();

        warning('请手动完成以下 Telescope 安装步骤：');
        note('');

        // 步骤1: Composer 安装
        $step1Status = $telescopeInstalled ? '✅' : '❌';
        $step1Message = $telescopeInstalled ? '已完成' : '需要执行';
        note("{$step1Status} composer require laravel/telescope{$devFlag} ({$step1Message})");

        if (! $telescopeInstalled) {
            note('   执行此命令安装 Telescope 包');
        }

        // 步骤2: Telescope 初始化
        $step2Status = $telescopeConfigExists ? '✅' : ($telescopeInstalled ? '⏳' : '⏸️');
        $step2Message = $telescopeConfigExists ? '已完成' : ($telescopeInstalled ? '待执行' : '等待上一步完成');
        note("{$step2Status} php artisan telescope:install ({$step2Message})");

        if ($telescopeInstalled && ! $telescopeConfigExists) {
            note('   如果命令无法识别，请先尝试：');
            note('   - php artisan config:clear');
            note('   - php artisan cache:clear');
            note('   然后重新运行 telescope:install');
        }

        // 根据环境显示不同的后续步骤
        if ($options['telescope_environment'] === 'local') {
            // Local 环境的特殊配置
            $step3Status = $providersFileClean ? '✅' : '⏳';
            $step3Message = $providersFileClean ? '已完成' : '待执行';
            note("{$step3Status} 移除 bootstrap/providers.php 中的 TelescopeServiceProvider ({$step3Message})");

            if (! $providersFileClean) {
                note('   从 bootstrap/providers.php 中删除以下行：');
                note('   Laravel\\Telescope\\TelescopeServiceProvider::class,');
            }

            $step4Status = $composerJsonConfigured ? '✅' : '⏳';
            $step4Message = $composerJsonConfigured ? '已完成' : '待执行';
            note("{$step4Status} 配置 composer.json 的 dont-discover ({$step4Message})");

            if (! $composerJsonConfigured) {
                note('   在 composer.json 中添加以下配置：');
                note('   "extra": {');
                note('     "laravel": {');
                note('       "dont-discover": ["laravel/telescope"]');
                note('     }');
                note('   }');
            }

            note('✅ 环境控制：Telescope 将由 Prism 在本地环境自动加载');
        } else {
            // Production 或 All 环境
            $step3Status = $telescopeConfigExists ? '⏳' : '⏸️';
            $step3Message = $telescopeConfigExists ? '待执行' : '等待上述步骤完成';
            note("{$step3Status} php artisan migrate ({$step3Message})");

            if ($telescopeConfigExists) {
                note('   这将创建 Telescope 需要的数据库表');
            }
        }

        note('');
        info('💡 完成所有步骤后，Telescope 将在 /telescope 路径可用');

        // 根据环境给出具体的使用说明
        if ($options['telescope_environment'] === 'local') {
            note('');
            info('🔧 本地环境配置完成后：');
            note('- Telescope 只在本地环境加载（通过 Prism 控制）');
            note('- 生产环境不会加载 Telescope，提高性能');
            note('- 无需担心 Telescope 意外在生产环境运行');
        }

        // 如果有部分步骤已完成，给出更具体的指导
        if ($telescopeInstalled && ! $telescopeConfigExists) {
            note('');
            warning('下一步建议：');
            note('由于 Telescope 包已安装但配置文件缺失，建议先清除缓存：');
            note('php artisan config:clear && php artisan cache:clear');
            note('然后重新运行: php artisan telescope:install');
        }
    }

    /**
     * 检查 composer.json 是否已配置 telescope 的 dont-discover
     */
    protected function isComposerJsonConfigured(): bool
    {
        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            return false;
        }

        try {
            $composer = json_decode(File::get($composerPath), true);
            $dontDiscover = $composer['extra']['laravel']['dont-discover'] ?? [];

            return in_array('laravel/telescope', $dontDiscover);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查 bootstrap/providers.php 是否已移除 TelescopeServiceProvider
     */
    protected function isProvidersFileClean(): bool
    {
        $providersPath = base_path('bootstrap/providers.php');

        if (! File::exists($providersPath)) {
            return true; // 文件不存在认为是干净的
        }

        try {
            $providersContent = File::get($providersPath);

            return strpos($providersContent, 'TelescopeServiceProvider') === false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取配置键前缀
     */
    public function getConfigPrefix(): string
    {
        return 'telescope_';
    }

    /**
     * 获取扩展的默认配置
     */
    protected function getExtensionDefaultConfig(): array
    {
        // 创建 TelescopeExtension 实例并获取其默认配置
        $extension = new \Abe\Prism\Extensions\TelescopeExtension;

        // 使用反射访问 protected 方法 getDefaultConfig
        $reflection = new \ReflectionClass($extension);
        $method = $reflection->getMethod('getDefaultConfig');
        $method->setAccessible(true);

        return $method->invoke($extension);
    }
}
