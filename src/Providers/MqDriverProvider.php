<?php
/**
 * User: macro chen <macro_fengye@163.com>
 * Date: 17-5-26
 * Time: 上午11:30
 */

namespace Polymer\Providers;

use DI\Container;
use Redis;

class MqDriverProvider
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimpleContainer A container instance
     */
    public function register(Container $pimpleContainer)
    {
        $pimpleContainer['mq_driver'] = function (Container $container) {
            $redis = $container['application']->component('redis');
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
            $redis->setOption(Redis::OPT_PREFIX, 'bernard:');
            return new PhpRedisDriver($redis);
        };
    }
}
