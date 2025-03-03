<?php

namespace Abe\Prism\Tests;

use Abe\Prism\PrismServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PrismServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        // 创建测试表结构
        $this->app['db']->connection()->getSchemaBuilder()->create('test_models', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('test_custom_models', function ($table) {
            $table->id();
            $table->unsignedBigInteger('snowflake_id');
            $table->unsignedBigInteger('another_snowflake_id');
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        // 使用内存数据库进行测试
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // 确保 Laravel\Telescope\TelescopeServiceProvider 类可用于测试
        if (! class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            eval('namespace Laravel\Telescope { class TelescopeServiceProvider extends \Illuminate\Support\ServiceProvider {} }');
        }

        // 确保 App\Providers\TelescopeServiceProvider 类可用于测试
        if (! class_exists(\App\Providers\TelescopeServiceProvider::class)) {
            eval('namespace App\Providers { class TelescopeServiceProvider extends \Illuminate\Support\ServiceProvider {} }');
        }
    }

    /**
     * 定义环境
     *
     * @return $this
     */
    protected function withEnvironment(string $environment)
    {
        $this->app['env'] = $environment;

        return $this;
    }
}
