# Response Handling

The `HasResponse` trait provides consistent response methods for your controllers.

## Usage

### Basic Usage

Add the trait to your controller:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Abe\Prism\Traits\HasResponse;

class ProductController extends Controller
{
    use HasResponse;

    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->fail('Product not found', 404);
        }

        return $this->success($product);
    }
}
```

### Available Methods

#### success($data = null, $message = null, $status = 200)

Returns a successful response:

```php
// Simple success
return $this->success();

// Success with data
return $this->success($product);

// Success with data and message
return $this->success($product, 'Product retrieved successfully');

// Success with custom status code
return $this->success($product, 'Product created successfully', 201);
```

#### fail($message = null, $status = 400, $errors = null)

Returns an error response:

```php
// Simple error
return $this->fail('Something went wrong');

// Error with custom status
return $this->fail('Product not found', 404);

// Error with validation errors
return $this->fail('Validation failed', 422, $validator->errors());
```

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... }
}
```

## Configuration

The response format can be customized through the trait's methods or by overriding them in your controller.
