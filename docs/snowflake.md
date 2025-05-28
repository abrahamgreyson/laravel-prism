# Snowflake ID Generator

The `HasSnowflake` trait allows your models to automatically generate and populate snowflake IDs when created.

## Usage

### Basic Usage

Add the trait to your model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Abe\Prism\Traits\HasSnowflake;

class Product extends Model
{
    use HasSnowflake;
    
    /**
     * Fields that should receive snowflake IDs
     * If not set, defaults to the model's primary key
     */
    protected $snowflakeColumns = ['id', 'another_snowflake_field'];
}
```

### Automatic Generation

Snowflake IDs are generated automatically when creating models:

```php
$product = new Product();
$product->name = 'Test Product';
$product->save(); // Snowflake ID automatically generated
```

## Frontend Integration

Due to JavaScript's integer limitations (max safe integer is 2^53-1), snowflake IDs may lose precision in the frontend. The `HasSnowflake` trait automatically handles this:

1. Automatically adds snowflake ID fields to the model's `$casts` array as strings
2. Ensures JSON serialization returns strings instead of integers

### Frontend Example

```javascript
// Display ID as string
console.log("Product ID: " + product.id); // Don't perform numeric operations

// Submit string form of ID
axios.post('/api/products', {
  id: productId, // Already a string, no conversion needed
  // other fields...
});
```

This ensures snowflake IDs maintain precision during frontend-backend communication.

## Configuration

The snowflake generator is configured in the PrismServiceProvider and can be customized by adjusting the start time and other parameters.

## Notes

- Based on [godruoyi/php-snowflake](https://github.com/godruoyi/php-snowflake)
- Ensure your database fields are large enough to store snowflake IDs (recommended: BIGINT UNSIGNED)
- Generator configuration is registered in PrismServiceProvider
