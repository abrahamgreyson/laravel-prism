# Laravel Prism

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abe/laravel-prism.svg?style=flat-square)](https://packagist.org/packages/abe/laravel-prism)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abrahamgreyson/laravel-prism/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abrahamgreyson/laravel-prism/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abrahamgreyson/laravel-prism/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abrahamgreyson/laravel-prism/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abe/laravel-prism.svg?style=flat-square)](https://packagist.org/packages/abe/laravel-prism)

一个 Laravel 扩展包，为新项目提供固化的默认配置和实用的 Trait。

## 快速开始

1. 安装扩展包：

```bash
composer require abe/laravel-prism
```

2. 运行安装命令：

```bash
php artisan prism:install
```

该命令会引导你配置 Laravel 行为并可选择安装开发工具（如 Telescope）。

## 包含功能

### Laravel 配置优化
- **不可变日期**：使用不可变日期对象提高一致性
- **模型严格模式**：防止懒加载和批量赋值问题
- **统一响应格式**：一致的 API 响应格式
- **禁止破坏性命令**：防止生产环境意外数据丢失

### 实用 Trait

#### HasSnowflake（雪花 ID）
为模型自动生成雪花 ID：

```php
use Abe\Prism\Traits\HasSnowflake;

class Product extends Model
{
    use HasSnowflake;
    
    // 会自动生成雪花 ID
}
```

#### HasResponse（响应处理）
为控制器提供一致的响应方法：

```php
use Abe\Prism\Traits\HasResponse;

class ProductController extends Controller
{
    use HasResponse;

    public function show($id)
    {
        $product = Product::find($id);
        
        return $product 
            ? $this->success($product)
            : $this->fail('产品未找到', 404);
    }
}
```

### 可选开发工具
- **Laravel Telescope**：调试和监控应用程序（可选）

## 文档

- [English Documentation](README.md)
- [详细文档](docs/)

## 测试

```bash
composer test
```

## 贡献

详情请查看 [CONTRIBUTING](CONTRIBUTING.md)。

## 许可证

MIT 许可证。详情请查看 [License File](LICENSE.md)。
