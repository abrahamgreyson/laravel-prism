<?php

namespace Abe\Prism\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class LaravelConfigurator
{
    /**
     * 配置 Laravel 的默认行为
     */
    public static function configure(): void
    {
        static::configureJsonResources();
        static::configureDates();
        static::configureModels();
        static::configureDatabase();
    }

    /**
     * 配置 JSON 资源
     */
    protected static function configureJsonResources(): void
    {
        // 根据配置决定是否禁用资源包装
        if (config('prism.json_resource_without_wrapping', true)) {
            JsonResource::withoutWrapping();
        }
    }

    /**
     * 配置日期处理
     */
    protected static function configureDates(): void
    {
        // 根据配置决定是否使用不可变日期
        if (config('prism.immutable_date', true)) {
            Date::use(CarbonImmutable::class);
        }
    }

    /**
     * 配置模型行为
     */
    protected static function configureModels(): void
    {
        // 根据配置决定是否启用模型严格模式
        if (config('prism.model_strict', true)) {
            // 在非生产环境启用所有严格检查，生产环境不启用懒加载检查
            Model::shouldBeStrict(!app()->isProduction());
        }

        // 根据配置决定是否解除模型保护
        if (config('prism.unguard_models', true)) {
            Model::unguard();
        }
    }

    /**
     * 配置数据库行为
     */
    protected static function configureDatabase(): void
    {
        // 根据配置决定是否禁止破坏性命令（仅在生产环境）
        if (config('prism.prohibit_destructive_commands', true)) {
            DB::prohibitDestructiveCommands(app()->isProduction());
        }
    }
}
