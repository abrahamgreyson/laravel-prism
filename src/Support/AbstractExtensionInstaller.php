<?php

namespace Abe\Prism\Support;

use Abe\Prism\Contracts\ExtensionInstaller;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

abstract class AbstractExtensionInstaller implements ExtensionInstaller
{
    /**
     * 获取 Composer 包名
     */
    abstract protected function getComposerPackage(): string;

    /**
     * 获取服务提供者类名（用于检查是否安装）
     */
    abstract protected function getServiceProviderClass(): string;

    /**
     * 获取安装步骤
     */
    abstract protected function getInstallSteps(array $options): array;

    /**
     * 执行安装步骤
     */
    abstract protected function executeInstallSteps(OutputInterface $output, array $options): bool;

    /**
     * 检查扩展是否已安装
     */
    public function isInstalled(): bool
    {
        return class_exists($this->getServiceProviderClass());
    }

    /**
     * 安装扩展
     */
    public function install(OutputInterface $output, array $options): bool
    {
        $output->writeln("<info>开始安装 {$this->getDisplayName()}...</info>");

        // 检查是否已经安装
        if ($this->isInstalled()) {
            $output->writeln("<comment>{$this->getDisplayName()} 已经安装，跳过安装步骤。</comment>");
            $this->updateConfig($options);
            return true;
        }

        // 显示即将执行的操作
        $this->showInstallSteps($output, $options);

        if (!confirm("是否继续安装 {$this->getDisplayName()}？", true)) {
            $output->writeln("<comment>跳过 {$this->getDisplayName()} 安装。</comment>");
            return false;
        }

        try {
            $success = $this->executeInstallSteps($output, $options);

            if ($success) {
                $this->updateConfig($options);
                info("🎉 {$this->getDisplayName()} 安装完成！");
                return true;
            } else {
                warning("⚠️ {$this->getDisplayName()} 安装可能需要手动完成");
                $this->showManualSteps($output, $options);
                return false;
            }
        } catch (\Exception $e) {
            $output->writeln("<error>❌ {$this->getDisplayName()} 安装失败: {$e->getMessage()}</error>");
            $this->handleInstallError($output, $options, $e);
            return false;
        }
    }

    /**
     * 显示安装步骤
     */
    protected function showInstallSteps(OutputInterface $output, array $options): void
    {
        warning("即将执行以下 {$this->getDisplayName()} 安装步骤：");
        
        $steps = $this->getInstallSteps($options);
        foreach ($steps as $i => $step) {
            note(($i + 1) . ". {$step}");
        }
    }

    /**
     * 运行命令并显示实时输出
     */
    protected function runCommandWithRealTimeOutput(string $command, OutputInterface $output, ?string $workingPath = null): bool
    {
        $process = Process::fromShellCommandline($command, $workingPath, null, null, null);

        // 尝试启用 TTY 模式来实现流式输出
        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $output->writeln('<comment>无法启用 TTY 模式: ' . $e->getMessage() . '</comment>');
            }
        }

        // 运行命令并实时输出
        $process->run(function ($type, $line) use ($output) {
            $output->write('    ' . $line);
        });

        return $process->isSuccessful();
    }

    /**
     * 安装 Composer 包
     */
    protected function installComposerPackage(OutputInterface $output, array $options): bool
    {
        $packageName = $this->getComposerPackage();
        $devFlag = ($options['environment'] ?? '') === 'dev' ? ' --dev' : '';
        $composerCommand = "composer require {$packageName}{$devFlag}";

        $output->writeln("<info>正在安装 {$packageName} 包...</info>");
        $output->writeln("<comment>执行: {$composerCommand}</comment>");

        $success = $this->runCommandWithRealTimeOutput($composerCommand, $output, base_path());

        if ($success) {
            info("✅ {$packageName} 包安装成功！");
            return true;
        } else {
            throw new \Exception("Composer 安装失败");
        }
    }

    /**
     * 清除应用缓存
     */
    protected function clearApplicationCache(OutputInterface $output): void
    {
        $output->writeln('<info>清除应用缓存...</info>');
        
        $commands = [
            'config:clear',
            'cache:clear',
            'route:clear',
            'view:clear'
        ];

        foreach ($commands as $command) {
            try {
                \Artisan::call($command);
            } catch (\Exception $e) {
                // 静默处理错误
            }
        }
    }

    /**
     * 重新加载 Composer 自动加载器
     */
    protected function reloadComposerAutoloader(OutputInterface $output): void
    {
        $output->writeln('<info>重新加载 Composer autoloader...</info>');

        $dumpAutoloadCommand = 'composer dump-autoload --optimize';
        $output->writeln("<comment>执行: {$dumpAutoloadCommand}</comment>");

        $success = $this->runCommandWithRealTimeOutput($dumpAutoloadCommand, $output, base_path());

        if ($success) {
            $output->writeln('<info>✓ Composer autoload 已重新生成</info>');
        } else {
            $output->writeln('<comment>⚠ Composer dump-autoload 执行失败，但不影响后续操作</comment>');
        }

        // 清除 Laravel 缓存
        $this->clearApplicationCache($output);

        // 尝试重新发现包
        try {
            \Artisan::call('package:discover');
            $output->writeln('<info>✓ 包发现已重新执行</info>');
        } catch (\Exception $e) {
            $output->writeln('<comment>⚠ package:discover 不可用，跳过此步骤</comment>');
        }

        // 给系统一点时间来处理文件系统变化
        usleep(500000); // 0.5秒

        $output->writeln('<info>✅ Composer 自动加载器刷新完成</info>');
    }

    /**
     * 更新配置文件
     */
    public function updateConfig(array $options): void
    {
        $configPath = config_path('prism.php');
        if (!File::exists($configPath)) {
            return;
        }

        try {
            $configContent = File::get($configPath);
            $configContent = $this->updateConfiguration($configContent, $options);
            File::put($configPath, $configContent);
        } catch (\Exception $e) {
            // 静默失败，配置可以稍后手动调整
        }
    }

    /**
     * 更新配置内容
     */
    public function updateConfiguration(string $configContent, array $options): string
    {
        $prefix = $this->getConfigPrefix();
        $extensionName = $this->getName();
        
        // 获取扩展的默认配置
        $defaultConfig = $this->getExtensionDefaultConfig();
        
        // 合并用户选择的选项和默认配置
        $configToUpdate = [];
        
        // 首先添加默认配置
        foreach ($defaultConfig as $key => $value) {
            $configToUpdate[$key] = $value;
        }
        
        // 然后用用户选择的选项覆盖默认配置
        foreach ($options as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $configKey = str_replace($prefix, '', $key);
                $configToUpdate[$configKey] = $value;
            }
        }
        
        // 更新配置文件内容
        foreach ($configToUpdate as $configKey => $value) {
            $valueString = is_bool($value) ? ($value ? 'true' : 'false') : "'{$value}'";
            
            // 使用更精确的正则表达式来匹配嵌套配置
            $pattern = "/('$configKey'\s*=>\s*)[^,\n\]]+/";
            $replacement = "'$configKey' => $valueString";
            
            // 只在扩展配置块内进行替换
            $sectionPattern = "/('$extensionName'\s*=>\s*\[[^\]]*?)('$configKey'\s*=>\s*[^,\n\]]+)([^\]]*\])/s";
            if (preg_match($sectionPattern, $configContent)) {
                $configContent = preg_replace($sectionPattern, "$1'$configKey' => $valueString$3", $configContent);
            } else {
                // 如果没有找到特定的扩展配置块，尝试全局匹配（向后兼容）
                $configContent = preg_replace($pattern, $replacement, $configContent);
            }
        }

        return $configContent;
    }
    
    /**
     * 获取扩展的默认配置（子类可覆盖）
     */
    protected function getExtensionDefaultConfig(): array
    {
        // 默认实现，子类应该覆盖此方法
        return [];
    }

    /**
     * 处理安装错误
     */
    protected function handleInstallError(OutputInterface $output, array $options, \Exception $e): void
    {
        $this->showManualSteps($output, $options);
    }
}
