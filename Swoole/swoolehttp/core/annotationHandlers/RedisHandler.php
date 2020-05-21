<?php


namespace Core\annotationHandlers;


use Core\annotations\Redis;
use Core\BeanFactory;
use Core\init\DecoratorCollector;
use Core\lib\RedisHelper;


function getKey(string $key, array $params)
{
    $pattern = "/^#(\d+)/i";
    if (preg_match($pattern, $key, $matches)) {
        return $params[$matches[1]];
    }
    return $key;
}


function redisByStaring(Redis $self, array $params, $function)
{
    $cacheKey = $self->prefix . getKey($self->key, $params);
    $getFromRedis = RedisHelper::get($cacheKey);
    if ($getFromRedis) {
//        echo '从缓存取' . PHP_EOL;
        return $getFromRedis;
    } else {
//        echo '从数据库取，且插入缓存' . PHP_EOL;
        $getData = call_user_func($function, ...$params);
        // 缓存时间
        if ($self->timeout > 0) {
            RedisHelper::setex($cacheKey, $self->timeout, json_encode($getData));
        } else {
            RedisHelper::set($cacheKey, json_encode($getData));
        }
        return $getData;
    }
}

function getDataKey(string $key, array $array)
{
    $pattern = "/^#(\w+)/i";
    if (preg_match($pattern, $key, $matches)) {
        if (isset($array[$matches[1]])) {
            return $array[$matches[1]];
        }
    }
    return $key;
}

function redisByHash(Redis $self, array $params, $function)
{
    $cacheKey = $self->prefix . getKey($self->key, $params);
    $getFromRedis = RedisHelper::hgetall($cacheKey);
    // 从缓存取
    if ($getFromRedis) {
        if ($self->incr != "") {
            RedisHelper::hIncrBy($cacheKey, $self->incr, 1);
        }
        return $getFromRedis;
    } else {
        $getData = call_user_func($function, ...$params);
        if (is_object($getData)) {
            $getData = json_decode(json_encode($getData), 1);
        }
        $dataKeys = implode("", array_keys($getData));
        if (preg_match("/^\d+$/", $dataKeys)) {
            foreach ($getData as $getDatum) {
                RedisHelper::hmset($self->prefix . getDataKey($self->key, $getDatum), $getDatum);
            }
        } else {
            RedisHelper::hmset($cacheKey, $getData);
        }

        return $getData;
    }
}

return [
    Redis::class => function (\ReflectionMethod $method, $instance, $self) {
        $decoratorCollector = BeanFactory::getBean(DecoratorCollector::class);
        $key = get_class($instance) . "::" . $method->getName();
        // 收集装饰器 放入 装饰器收集类
        $decoratorCollector->dSet[$key] = function ($function) use ($self) {
            return function ($params) use ($function, $self) {
                if ($self->key != '') { // 处理缓存
                    switch ($self->type) {
                        case "string":
                            return redisByStaring($self, $params, $function);
                        case "hash":
                            return redisByHash($self, $params, $function);
                        default:
                            return call_user_func($function, ...$params);
                    }
                }
                return call_user_func($function, ...$params);
            };
        };
        return $instance;
    }
];