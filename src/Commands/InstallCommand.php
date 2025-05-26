<?php

namespace Abe\Prism\Commands;

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
        info('开始安装 Prism 包...');

        // 配置选项
        $options = $this->configureOptions($input, $output);

        // 发布自身配置
        $this->publishPrismConfig($input, $output, $options);

        // 发布依赖包资源
        $this->publishDependencies($input, $output, $options);

        info('Prism 安装完成！');
        note('您可以在 config/prism.php 文件中修改配置选项。');

        return self::SUCCESS;
    }

    /**
     * 配置选项
     */
    protected function configureOptions(InputInterface $input, OutputInterface $output): array
    {
        // 默认选项
        $options = [
            'immutable_date' => true,
            'unified_response' => true,
            'model_strict' => true,
            'unguard_models' => true,
            'prohibit_destructive_commands' => true,
        ];

        // 如果是非交互模式，直接返回默认选项
        if (! $input->isInteractive()) {
            return $options;
        }

        // 定义功能选项
        $features = [
            'immutable_date' => '不可变日期 (Immutable Date) - 使模型日期字段和 Date Facade 返回 Carbon 不可变实例',
            'unified_response' => '统一格式的响应 (Unified Response) - 提供标准化的 API 响应格式',
            'model_strict' => '模型严格模式 (Model Strict) - 防止懒加载、静默丢弃属性等问题',
            'unguard_models' => '解除模型保护 (Unguard Models) - 无需定义 $fillable 数组',
            'prohibit_destructive_commands' => '禁止破坏性命令 (Prohibit Destructive Commands) - 在生产环境禁止危险的数据库命令',
        ];

        // 使用 Laravel Prompts 的 multiselect
        $selectedKeys = multiselect(
            '请选择要启用的功能：',
            $features,
            array_keys($options)
        );

        // 更新选项
        foreach ($features as $key => $description) {
            $options[$key] = in_array($key, $selectedKeys);
        }

        return $options;
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

            // 修改各项配置选项
            foreach ($options as $key => $value) {
                $boolValue = $value ? 'true' : 'false';
                $configContent = preg_replace(
                    "/('$key'\s*=>\s*)(true|false)/",
                    "$1$boolValue",
                    $configContent
                );
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
