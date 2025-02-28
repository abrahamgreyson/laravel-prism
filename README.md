# This is my package laravel-prism

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abrahamgreyson/laravel-prism.svg?style=flat-square)](https://packagist.org/packages/abrahamgreyson/laravel-prism)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abrahamgreyson/laravel-prism/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abrahamgreyson/laravel-prism/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abrahamgreyson/laravel-prism/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abrahamgreyson/laravel-prism/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abrahamgreyson/laravel-prism.svg?style=flat-square)](https://packagist.org/packages/abrahamgreyson/laravel-prism)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-prism.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-prism)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require abe/laravel-prism
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-prism-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-prism-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-prism-views"
```

## Usage

```php
$Prism = new Abe\Prism();
echo $Prism->echoPhrase('Hello, Abe!');
```

# Laravel Prism

## 功能

### 雪花ID (Snowflake ID)

使用 `HasSnowflake` trait 可以让你的模型在创建时自动生成并填充雪花ID。

#### 使用方法

1. 在模型中引入 trait

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Abe\Prism\Traits\HasSnowflake;

class Product extends Model
{
    use HasSnowflake;
    
    /**
     * 需要自动生成雪花ID的字段列表
     * 如果不设置，默认使用模型主键
     */
    protected $snowflakeColumns = ['id', 'another_snowflake_field'];
}
```

2. 创建模型时会自动生成雪花ID

```php
$product = new Product();
$product->name = '测试产品';
$product->save();

```

#### 前后端雪花ID处理

由于JavaScript对大整数的限制（最大安全整数为2^53-1），雪花ID在前端可能会出现精度丢失问题。`HasSnowflake` trait自动处理了这一问题：

1. 自动将雪花ID字段添加到模型的`$casts`数组，确保在JSON序列化时转为字符串
2. 提供了处理前端提交数据的helper方法

```php
// 控制器中处理前端提交的数据
public function store(Request $request)
{
    $product = new Product();
    
    // 将请求中的字符串ID转为整数
    $data = $product->convertSnowflakeFromRequest($request->all());
    
    $product->fill($data);
    $product->save();
    
    return $product;
}
```

前端处理示例（使用JavaScript）：

```javascript
// 显示ID时保持字符串形式
console.log("产品ID: " + product.id); // 不要进行数值运算

// 提交时直接发送字符串形式的ID
axios.post('/api/products', {
  id: productId, // 已经是字符串形式，不需要转换
  // 其他字段...
});
```

这种方式确保雪花ID在前后端传递过程中不会丢失精度。

#### 注意事项

- 本功能基于 [godruoyi/php-snowflake](https://github.com/godruoyi/php-snowflake) 包实现
- 确保你的数据库字段类型足够大以存储雪花ID（推荐使用 BIGINT UNSIGNED）
- 雪花ID生成器配置在 PrismServiceProvider 中注册，可根据需要调整起始时间等参数

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [abrahamgreyson](https://github.com/abe)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
