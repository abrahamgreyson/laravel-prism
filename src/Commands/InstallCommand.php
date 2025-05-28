<?php

namespace Abe\Prism\Commands;

use Abe\Prism\Support\ExtensionInstallerManager;
use Abe\Prism\Support\ExtensionStateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;

class InstallCommand extends Command
{
    protected $signature = 'prism:install';

    protected $description = 'Install the Prism package';

    protected ExtensionStateManager $stateManager;

    public function __construct()
    {
        parent::__construct();
        $this->stateManager = new ExtensionStateManager;
    }

    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName($this->name)
            ->setDescription($this->description)
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制覆盖已存在的配置文件');
    }

    /**
     * 执行命令
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        info('🚀 开始安装 Prism 包...');

        // 步骤 1: 配置 Laravel 行为
        $laravelOptions = $this->configureLaravelBehavior($input, $output);

        // 步骤 2: 第三方包安装
        $packageOptions = $this->configureThirdPartyPackages($input, $output);

        // 合并选项
        $options = array_merge($laravelOptions, $packageOptions);

        // 发布自身配置
        $this->publishPrismConfig($input, $output, $options);

        // 发布依赖包资源
        $this->publishDependencies($input, $output, $options);

        // 安装第三方包
        $this->installThirdPartyPackages($input, $output, $options);

        info('🎉 Prism 安装完成！');
        note('您可以在 config/prism.php 文件中修改配置选项。');

        return self::SUCCESS;
    }

    /**
     * 配置 Laravel 行为选项
     */
    protected function configureLaravelBehavior(InputInterface $input, OutputInterface $output): array
    {
        $output->writeln('<fg=blue>📝 步骤 1: 配置 Laravel 行为</>');

        // 默认选项
        $options = [
            'json_resource_without_wrapping' => true,
            'immutable_date' => true,
            'model_strict' => true,
            'unguard_models' => true,
            'prohibit_destructive_commands' => true,
            'unified_response' => true,
        ];

        // 如果是非交互模式，直接返回默认选项
        if (! $input->isInteractive()) {
            return $options;
        }

        // 定义功能选项
        $features = [
            'json_resource_without_wrapping' => 'JSON 资源禁用包装 (JSON Resource Without Wrapping) - 移除 API 响应的 data 包装',
            'immutable_date' => '不可变日期 (Immutable Date) - 使模型日期字段和 Date Facade 返回 Carbon 不可变实例',
            'model_strict' => '模型严格模式 (Model Strict) - 防止懒加载、静默丢弃属性等问题',
            'unguard_models' => '解除模型保护 (Unguard Models) - 无需定义 $fillable 数组',
            'prohibit_destructive_commands' => '禁止破坏性命令 (Prohibit Destructive Commands) - 在生产环境禁止危险的数据库命令',
            'unified_response' => '统一格式的响应 (Unified Response) - 提供标准化的 API 响应格式',
        ];

        // 使用 Laravel Prompts 的 multiselect，设置 scroll 为显示所有选项
        $selectedKeys = multiselect(
            '请选择要启用的 Laravel 行为配置：',
            $features,
            array_keys($options),
            scroll: 10
        );

        // 更新选项
        foreach ($features as $key => $description) {
            $options[$key] = in_array($key, $selectedKeys);
        }

        return $options;
    }

    /**
     * 配置第三方包安装选项
     */
    protected function configureThirdPartyPackages(InputInterface $input, OutputInterface $output): array
    {
        $output->writeln('<fg=blue>📦 步骤 2: 选择第三方包</>');

        // 使用 ExtensionInstallerManager 配置选项
        $installerManager = new ExtensionInstallerManager;

        return $installerManager->configurePackageOptions($input->isInteractive());
    }

    /**
     * 安装第三方包
     */
    protected function installThirdPartyPackages(InputInterface $input, OutputInterface $output, array $options): void
    {
        $installerManager = new ExtensionInstallerManager;

        // 安装 Telescope（如果选择）
        if ($options['telescope_install']) {
            $context = [
                'environment' => $options['environment'], // 使用全局环境配置
                'force' => $input->getOption('force'),
                'interactive' => $input->isInteractive(),
            ];

            $installer = $installerManager->getInstaller('telescope');

            try {
                $installer->install($output, $context);

                // 记录安装状态
                $this->stateManager->recordInstallation('telescope', [
                    'installation_method' => 'prism',
                    'configuration' => [
                        'environment' => $options['environment'], // 使用全局环境配置
                        'auto_register' => true,
                    ],
                ]);

                info('🎉 Telescope 安装完成！');
                note('扩展已被 Prism 管理，可使用 prism:list 查看状态');
            } catch (\Exception $e) {
                $output->writeln("<error>❌ Telescope 安装失败: {$e->getMessage()}</error>");

                // 显示手动安装步骤
                $installer->showManualSteps($output, $context);
            }
        }
    }

    /**
     * 发布 Prism 配置文件
     */
    protected function publishPrismConfig(InputInterface $input, OutputInterface $output, array $options): void
    {
        $output->writeln('<info>发布 Prism 配置文件...</info>');

        $this->call('vendor:publish', [
            '--tag' => 'prism-config',
            '--force' => $input->getOption('force'),
        ]);

        // 根据用户选择的选项修改配置文件
        $configPath = config_path('prism.php');
        if (File::exists($configPath)) {
            $configContent = File::get($configPath);

            // 修改基础配置选项
            $basicOptions = ['json_resource_without_wrapping', 'immutable_date', 'unified_response', 'model_strict', 'unguard_models', 'prohibit_destructive_commands'];
            foreach ($basicOptions as $key) {
                if (isset($options[$key])) {
                    $boolValue = $options[$key] ? 'true' : 'false';
                    $configContent = preg_replace(
                        "/('$key'\s*=>\s*)(true|false)/",
                        "$1$boolValue",
                        $configContent
                    );
                }
            }

            // 使用 ExtensionInstallerManager 更新扩展配置
            $installerManager = new ExtensionInstallerManager;
            foreach ($installerManager->getAvailableInstallers() as $installer) {
                $configContent = $installer->updateConfiguration($configContent, $options);
            }

            // 写回配置文件
            File::put($configPath, $configContent);
        }
    }

    /**
     * 发布依赖包的资源
     */
    protected function publishDependencies(InputInterface $input, OutputInterface $output, array $options): void
    {
        // 发布 jiannei/laravel-response 配置
        if ($options['unified_response']) {
            $output->writeln('<info>发布统一格式的响应配置...</info>');
            $this->call('vendor:publish', [
                '--provider' => 'Jiannei\Response\Laravel\Providers\LaravelServiceProvider',
                '--force' => $input->getOption('force'),
            ]);
        }
    }
}
