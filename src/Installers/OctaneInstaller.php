<?php

namespace Abe\Prism\Installers;

use Abe\Prism\Support\AbstractExtensionInstaller;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

class OctaneInstaller extends AbstractExtensionInstaller
{
    /**
     * 获取扩展名称
     */
    public function getName(): string
    {
        return 'octane';
    }

    /**
     * 获取扩展显示名称
     */
    public function getDisplayName(): string
    {
        return 'Laravel Octane';
    }

    /**
     * 获取扩展描述
     */
    public function getDescription(): string
    {
        return '高性能应用服务器，支持 Swoole 和 RoadRunner';
    }

    /**
     * 获取 Composer 包名
     */
    protected function getComposerPackage(): string
    {
        return 'laravel/octane';
    }

    /**
     * 获取服务提供者类名
     */
    protected function getServiceProviderClass(): string
    {
        return 'Laravel\\Octane\\OctaneServiceProvider';
    }

    /**
     * 获取扩展类名
     */
    public function getExtensionClass(): string
    {
        return 'Laravel\\Octane\\Octane';
    }

    /**
     * 获取安装选项
     */
    public function getInstallOptions(): array
    {
        return [
            'octane_install' => false,
            'octane_server' => 'swoole',
        ];
    }

    /**
     * 配置安装选项
     */
    public function configureOptions(array $options): array
    {
        if (isset($options['octane_install']) && $options['octane_install']) {
            $options['octane_server'] = select(
                '请选择 Octane 服务器类型：',
                [
                    'swoole' => 'Swoole（推荐）',
                    'roadrunner' => 'RoadRunner',
                ],
                'swoole'
            );
        }

        return $options;
    }

    /**
     * 获取安装步骤
     */
    protected function getInstallSteps(array $options): array
    {
        $serverType = $options['octane_server'] ?? 'swoole';

        return [
            'composer require laravel/octane',
            'php artisan octane:install',
            $serverType === 'swoole' ? '安装 Swoole 扩展' : '安装 RoadRunner 二进制文件',
            '配置 Octane 设置',
        ];
    }

    /**
     * 执行安装步骤
     */
    protected function executeInstallSteps(OutputInterface $output, array $options): bool
    {
        // 1. 安装 Octane 包
        if (! $this->installComposerPackage($output, $options)) {
            return false;
        }

        // 2. 重新加载自动加载器
        $this->reloadComposerAutoloader($output);

        // 3. 运行 octane:install
        if (! $this->runOctaneInstall($output, $options)) {
            return false;
        }

        return true;
    }

    /**
     * 运行 octane:install 命令
     */
    protected function runOctaneInstall(OutputInterface $output, array $options): bool
    {
        $output->writeln('<info>正在执行 octane:install...</info>');

        $serverType = $options['octane_server'] ?? 'swoole';
        $command = "php artisan octane:install --server={$serverType}";

        $output->writeln("<comment>执行: {$command}</comment>");

        try {
            $success = $this->runCommandWithRealTimeOutput($command, $output, base_path());

            if ($success) {
                info('✅ Octane 安装成功！');

                if ($serverType === 'swoole') {
                    info('💡 请确保已安装 Swoole PHP 扩展');
                    info('💡 可以使用 "php artisan octane:start" 启动服务器');
                } else {
                    info('💡 可以使用 "php artisan octane:start" 启动服务器');
                }

                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $output->writeln("<comment>Octane 安装失败: {$e->getMessage()}</comment>");

            return false;
        }
    }

    /**
     * 显示手动安装步骤
     */
    public function showManualSteps(OutputInterface $output, array $options): void
    {
        $serverType = $options['octane_server'] ?? 'swoole';

        info('请手动完成以下 Octane 安装步骤：');
        info('');
        info('1. ✅ composer require laravel/octane (已完成)');
        info("2. ❌ php artisan octane:install --server={$serverType} (需要执行)");

        if ($serverType === 'swoole') {
            info('3. ⏳ 安装 Swoole PHP 扩展:');
            info('   - Ubuntu/Debian: sudo apt-get install php-swoole');
            info('   - macOS: brew install swoole');
            info('   - 或使用 PECL: pecl install swoole');
        } else {
            info('3. ⏳ RoadRunner 二进制文件将在执行 octane:install 时自动下载');
        }

        info('');
        info('💡 完成后使用 "php artisan octane:start" 启动高性能服务器');
    }

    /**
     * 获取配置键前缀
     */
    public function getConfigPrefix(): string
    {
        return 'octane_';
    }
}
