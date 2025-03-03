<?php

namespace Abe\Prism\Traits;

trait HasSnowflake
{
    /**
     * 初始化HasSnowflake trait
     *
     * @return void
     */
    protected function initializeHasSnowflake()
    {
        // 在初始化时设置模型为非自增，而不是通过属性定义
        $this->incrementing = false;
        
        // 确保雪花ID字段在JSON序列化时转为字符串
        $columns = $this->getSnowflakeColumns();

        // 如果没有自定义$casts数组，初始化一个
        if (! isset($this->casts)) {
            $this->casts = [];
        }

        // 将所有雪花ID字段添加到$casts中，确保它们被序列化为字符串
        foreach ($columns as $column) {
            if (! isset($this->casts[$column])) {
                $this->casts[$column] = 'string';
            }
        }
    }

    /**
     * 初始化HasSnowflake trait
     *
     * @return void
     */
    protected static function bootHasSnowflake()
    {
        static::creating(function ($model) {
            $columns = $model->getSnowflakeColumns();

            // 遍历所有配置的雪花ID字段
            foreach ($columns as $column) {
                // 如果字段值为空，则生成雪花ID
                if (empty($model->{$column})) {
                    $model->{$column} = app('snowflake')->id();
                }
            }
        });
    }
    
    /**
     * 获取所有雪花ID字段
     *
     * @return array
     */
    public function getSnowflakeColumns()
    {
        // 如果定义了 $snowflakeColumns 属性，则使用它
        if (property_exists($this, 'snowflakeColumns') && is_array($this->snowflakeColumns)) {
            return $this->snowflakeColumns;
        }
        
        // 否则默认使用主键
        return [$this->getKeyName()];
    }

    /**
     * 从请求数据中转换雪花ID
     * 可在控制器中调用此方法处理前端提交的数据
     *
     * @param array $data
     * @return array
     */
    public function convertSnowflakeFromRequest(array $data)
    {
        $columns = $this->getSnowflakeColumns();
        
        foreach ($columns as $column) {
            if (isset($data[$column]) && is_string($data[$column])) {
                // 将字符串转为整数
                $data[$column] = (int) $data[$column];
            }
        }
        
        return $data;
    }
}
