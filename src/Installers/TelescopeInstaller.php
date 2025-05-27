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
     * 获取安装选项
     */
    public function getInstallOptions(): array
    {
        return [
            'telescope_install' => false,
            'telescope_environment' => 'dev',
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
                    'dev' => '仅开发环境 (--dev)',
                    'all' => '所有环境',
                ],
                'dev'
            );
        }

        return $options;
    }

    /**
     * 获取安装步骤
     */
    protected function getInstallSteps(array $options): array
    {
        $devFlag = $options['telescope_environment'] === 'dev' ? ' --dev' : '';
        $steps = [
            "composer require laravel/telescope{$devFlag}",
            'php artisan telescope:install',
            'php artisan migrate',
        ];

        if ($options['telescope_environment'] === 'dev') {
            $steps[] = '配置 composer.json 的 dont-discover（生产环境时自动禁用）';
        }

        return $steps;
    }

    /**
     * 执行安装步骤
     */
    protected function executeInstallSteps(OutputInterface $output, array $options): bool
    {
        // 1. 安装 Telescope 包
        if (!$this->installComposerPackage($output, $options)) {
            return false;
        }

        // 2. 重新加载自动加载器
        $this->reloadComposerAutoloader($output);

        // 3. 运行 telescope:install
        if (!$this->runTelescopeInstall($output)) {
            return false;
        }

        // 4. 运行数据库迁移
        if (!$this->runMigrations($output)) {
            return false;
        }

        // 5. 配置 composer.json (如果是 dev 环境)
        if ($options['telescope_environment'] === 'dev') {
            $this->configureComposerDontDiscover($output);
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

        if (!File::exists($composerPath)) {
            return;
        }

        try {
            $composer = json_decode(File::get($composerPath), true);

            // 确保 extra.laravel.dont-discover 存在
            if (!isset($composer['extra'])) {
                $composer['extra'] = [];
            }
            if (!isset($composer['extra']['laravel'])) {
                $composer['extra']['laravel'] = [];
            }
            if (!isset($composer['extra']['laravel']['dont-discover'])) {
                $composer['extra']['laravel']['dont-discover'] = [];
            }

            // 添加 Telescope 到 dont-discover 列表
            if (!in_array('laravel/telescope', $composer['extra']['laravel']['dont-discover'])) {
                $composer['extra']['laravel']['dont-discover'][] = 'laravel/telescope';

                File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $output->writeln('<info>已配置 composer.json 的 dont-discover 设置。</info>');
            }
        } catch (\Exception $e) {
            $output->writeln("<comment>无法自动配置 composer.json，请手动添加 Telescope 到 dont-discover 列表。</comment>");
        }
    }

    /**
     * 显示手动安装步骤
     */
    public function showManualSteps(OutputInterface $output, array $options): void
    {
        $devFlag = $options['telescope_environment'] === 'dev' ? ' --dev' : '';

        // 检查各个步骤的完成状态
        $telescopeInstalled = $this->isInstalled();
        $telescopeConfigExists = File::exists(config_path('telescope.php'));
        $composerJsonConfigured = $this->isComposerJsonConfigured();

        warning('请手动完成以下 Telescope 安装步骤：');
        note('');

        // 步骤1: Composer 安装
        $step1Status = $telescopeInstalled ? '✅' : '❌';
        $step1Message = $telescopeInstalled ? '已完成' : '需要执行';
        note("{$step1Status} composer require laravel/telescope{$devFlag} ({$step1Message})");

        if (!$telescopeInstalled) {
            note('   执行此命令安装 Telescope 包');
        }

        // 步骤2: Telescope 初始化
        $step2Status = $telescopeConfigExists ? '✅' : ($telescopeInstalled ? '⏳' : '⏸️');
        $step2Message = $telescopeConfigExists ? '已完成' : ($telescopeInstalled ? '待执行' : '等待上一步完成');
        note("{$step2Status} php artisan telescope:install ({$step2Message})");

        if ($telescopeInstalled && !$telescopeConfigExists) {
            note('   如果命令无法识别，请先尝试：');
            note('   - php artisan config:clear');
            note('   - php artisan cache:clear');
            note('   然后重新运行 telescope:install');
        }

        // 步骤3: 数据库迁移
        $step3Status = $telescopeConfigExists ? '⏳' : '⏸️';
        $step3Message = $telescopeConfigExists ? '待执行' : '等待上述步骤完成';
        note("{$step3Status} php artisan migrate ({$step3Message})");

        if ($telescopeConfigExists) {
            note('   这将创建 Telescope 需要的数据库表');
        }

        // 步骤4: Composer 配置（仅开发环境）
        if ($options['telescope_environment'] === 'dev') {
            $step4Status = $composerJsonConfigured ? '✅' : '⏳';
            $step4Message = $composerJsonConfigured ? '已完成' : '待执行';
            note("{$step4Status} 配置 composer.json 的 dont-discover ({$step4Message})");

            if (!$composerJsonConfigured) {
                note('   在 composer.json 中添加以下配置：');
                note('   "extra": {');
                note('     "laravel": {');
                note('       "dont-discover": ["laravel/telescope"]');
                note('     }');
                note('   }');
            }
        }

        note('');
        info('💡 完成所有步骤后，Telescope 将在 /telescope 路径可用');

        // 如果有部分步骤已完成，给出更具体的指导
        if ($telescopeInstalled && !$telescopeConfigExists) {
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

        if (!File::exists($composerPath)) {
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
     * 获取配置键前缀
     */
    public function getConfigPrefix(): string
    {
        return 'telescope_';
    }
}
