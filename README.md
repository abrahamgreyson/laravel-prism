# Laravel Prism

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abe/laravel-prism.svg?style=flat-square)](https://packagist.org/packages/abe/laravel-prism)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abrahamgreyson/laravel-prism/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abrahamgreyson/laravel-prism/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abrahamgreyson/laravel-prism/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abrahamgreyson/laravel-prism/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abe/laravel-prism.svg?style=flat-square)](https://packagist.org/packages/abe/laravel-prism)

New Laravel project setup.

## Installation

You can install the package via composer:

```bash
composer require abe/laravel-prism
```

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

1. 自动将雪花ID字段添加到模型的`$casts`数组，确保在 JSON 序列化时转为字符串

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

这种方式确保雪花 ID 在前后端传递过程中不会丢失精度。

#### 注意事项

- 本功能基于 [godruoyi/php-snowflake](https://github.com/godruoyi/php-snowflake) 包实现
- 确保你的数据库字段类型足够大以存储雪花 ID（推荐使用 BIGINT UNSIGNED）
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
