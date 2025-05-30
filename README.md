# Laravel Prism

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abe/laravel-prism.svg?style=flat-square)](https://packagist.org/packages/abe/laravel-prism)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abrahamgreyson/laravel-prism/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abrahamgreyson/laravel-prism/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abrahamgreyson/laravel-prism/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abrahamgreyson/laravel-prism/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abe/laravel-prism.svg?style=flat-square)](https://packagist.org/packages/abe/laravel-prism)

A Laravel package that sets up new projects with opinionated defaults and useful traits.

## Quick Start

1. Install the package:

```bash
composer require abe/laravel-prism
```

2. Run the installation command:

```bash
php artisan prism:install
```

This will guide you through configuring Laravel behaviors and optionally installing development tools like Telescope.

## What's Included

### Laravel Configuration
- **Immutable Dates**: Use immutable date objects for better consistency
- **Model Strict Mode**: Prevent lazy loading and mass assignment issues
- **Unified Responses**: Consistent API response format
- **Prohibit Destructive Commands**: Prevent accidental data loss in production

### Useful Traits

#### HasSnowflake
Automatically generates snowflake IDs for your models:

```php
use Abe\Prism\Traits\HasSnowflake;

class Product extends Model
{
    use HasSnowflake;
    
    // Snowflake ID will be automatically generated
}
```

#### HasResponse
Provides consistent response methods for controllers:

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
            : $this->fail('Product not found', 404);
    }
}
```

### Optional Development Tools
- **Laravel Telescope**: Debug and monitor your application (optional)

## Documentation

- [中文文档 (Chinese)](README_HANS.md)
- [Detailed Documentation](docs/)

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
