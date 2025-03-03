<?php

namespace Abe\Prism\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
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
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
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
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     */
    protected function configureOptions(InputInterface $input, OutputInterface $output): array
    {
        // 默认选项
        $options = [
            'immutable_date' => true,
            'unified_response' => true,
        ];

        // 如果是非交互模式，直接返回默认选项
        if (!$input->isInteractive()) {
            return $options;
        }

        // 定义功能选项
        $features = [
            'immutable_date' => '不可变日期 (Immutable Date) - 使模型日期字段和 Date Facade 返回 Carbon 不可变实例',
            'unified_response' => '统一格式的响应',
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
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $options
     * @return void
     */
    protected function publishPrismConfig(InputInterface $input, OutputInterface $output, array $options): void
    {
        $output->writeln('<info>发布 Prism 配置文件...</info>');

        $this->callSilent('vendor:publish', [
            '--tag' => 'prism-config',
            '--force' => $input->getOption('force'),
        ]);

        // 根据用户选择的选项修改配置文件
        $configPath = config_path('prism.php');
        if (File::exists($configPath)) {
            $configContent = File::get($configPath);

            // 修改 immutable_date 选项
            $immutableValue = $options['immutable_date'] ? 'true' : 'false';
            $configContent = preg_replace(
                "/('immutable_date'\s*=>\s*)(true|false)/",
                "$1$immutableValue",
                $configContent
            );

            // 修改其他选项
            // ...

            // 写回配置文件
            File::put($configPath, $configContent);
        }
    }

    /**
     * 发布依赖包的资源
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $options
     * @return void
     */
    protected function publishDependencies(InputInterface $input, OutputInterface $output, array $options): void
    {
        // 发布 jiannei/laravel-response 配置
        if ($options['unified_response']) {
            $output->writeln('<info>发布统一格式的响应配置...</info>');
            $this->callSilent('vendor:publish', [
                '--provider' => 'Jiannei\Response\Laravel\Providers\LaravelServiceProvider',
                '--force' => $input->getOption('force'),
            ]);
        }
    }
}
