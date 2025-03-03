<?php

namespace Abe\Prism\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;

class InstallCommand extends Command
{
    protected $signature = 'prism:install
                            {--force : 强制覆盖已存在的配置文件}
                            {--no-interaction : 不进行交互式问答，使用默认选项}';

    protected $description = 'Install the Prism package';

    public function handle()
    {
        info('开始安装 Prism 包...');

        // 配置选项
        $options = $this->configureOptions();

        // 发布自身配置
        $this->publishPrismConfig($options);

        // 发布依赖包资源
        $this->publishDependencies();

        info('Prism 安装完成！');

        if (! $this->option('no-interaction')) {
            note('您可以在 config/prism.php 文件中修改配置选项。');
        }

        return self::SUCCESS;
    }

    /**
     * 配置选项
     *
     * @return array
     */
    protected function configureOptions()
    {
        $options = [
            'immutable_date' => true,
        ];

        if ($this->option('no-interaction')) {
            return $options;
        }

        // 使用多选框让用户选择需要启用的功能
        $selectedOptions = multiselect(
            label: '请选择要启用的功能：',
            options: [
                'immutable_date' => '不可变日期 (Immutable Date) - 使模型日期字段和 Date Facade 返回Carbon不可变实例',
                // 在此处可以添加更多选项
                'unified_response' => '统一格式的响应',
            ],
            default: ['immutable_date'],
            required: false,
            hint: '按空格选择/取消选择选项，按回车确认'
        );

        $options['immutable_date'] = in_array('immutable_date', $selectedOptions);
        $options['unified_response'] = in_array('unified_response', $selectedOptions);

        return $options;
    }

    /**
     * 发布 Prism 配置文件
     *
     * @return void
     */
    protected function publishPrismConfig(array $options)
    {
        info('发布 Prism 配置文件...');

        $this->call('vendor:publish', [
            '--tag' => 'prism-config',
            '--force' => $this->option('force'),
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

            // 写回配置文件
            File::put($configPath, $configContent);
        }
    }

    /**
     * 发布依赖包的资源
     *
     * @return void
     */
    protected function publishDependencies()
    {
        // 发布 jiannei/laravel-response 配置
        if ($this->option('unified_response')) {
            info('发布统一格式的响应配置...');
            $this->call('vendor:publish', [
                '--provider' => 'Jiannei\Response\Laravel\Providers\LaravelServiceProvider',
                '--force' => $this->option('force'),
            ]);
        }
    }
}
