<?php

namespace Abe\Prism\Tests\Traits;

use Abe\Prism\Tests\TestCase;
use Abe\Prism\Traits\HasSnowflake;
use Godruoyi\Snowflake\Snowflake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

beforeEach(function () {
    // 创建一个模拟的雪花 ID 生成器
    $snowflake = new Snowflake();
    App::instance('snowflake', $snowflake);
});

// 创建测试模型类
class TestModel extends Model
{
    use HasSnowflake;
    
    protected $table = 'test_models';
    protected $guarded = [];
    
    // 模拟了数据库交互，不需要实际连接数据库
    public function save(array $options = [])
    {
        $this->fireModelEvent('creating');
        return true;
    }
}

// 创建带有自定义雪花 ID 字段的测试模型类
class TestModelWithCustomColumns extends Model
{
    use HasSnowflake;
    
    protected $table = 'test_custom_models';
    protected $guarded = [];
    protected $snowflakeColumns = ['snowflake_id', 'another_snowflake_id'];
    
    public function save(array $options = [])
    {
        $this->fireModelEvent('creating');
        return true;
    }
}

test('模型创建时自动生成雪花 ID', function () {
    $model = new TestModel();
    $model->save();
   
    expect($model->{$model->getKeyName()})
        ->not->toBeNull()
        ->toBeNumeric();
});

test('自动设置模型的自增属性为 false', function () {
    $model = new TestModel();
    
    expect($model->incrementing)->toBeFalse();
});

test('支持自定义雪花 ID 字段', function () {
    $model = new TestModelWithCustomColumns();
    $model->save();
    
    expect($model->snowflake_id)
        ->not->toBeNull()
        ->toBeNumeric();
        
    expect($model->another_snowflake_id)
        ->not->toBeNull()
        ->toBeNumeric();
});

test('雪花 ID 字段自动添加到 casts 数组', function () {
    $model = new TestModel();
    
    // 初始化会触发 initializeHasSnowflake 方法
    // 我们可以检查 casts 数组是否包含主键
    
    expect($model->getCasts())
        ->toHaveKey($model->getKeyName())
        ->and($model->getCasts()[$model->getKeyName()])
        ->toBe('string');
});

test('getSnowflakeColumns 方法返回正确的字段', function () {
    $model = new TestModel();
    expect($model->getSnowflakeColumns())->toBe([$model->getKeyName()]);
    
    $customModel = new TestModelWithCustomColumns();
    expect($customModel->getSnowflakeColumns())
        ->toBe(['snowflake_id', 'another_snowflake_id']);
});
