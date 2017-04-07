<?php
/**
 * User: macro chen <chen_macro@163.com>
 * Date: 2016/9/1
 * Time: 20:12
 */

namespace Polymer\Providers;

use Doctrine\Common\Cache\MemcachedCache;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class MemcachedCacheProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {
        $pimple['memcachedCache'] = function (Container $container) {
            $namespace = 'memcachedCache';
            if ($container['application']->component('namespace')) {
                $namespace = $container['application']->component('namespace');
            }
            $memcachedCache = new MemcachedCache();
            $memcachedCache->setNamespace($namespace);
            $memcachedCache->setMemcached($container['application']->component('memcached'));
            return $memcachedCache;
        };
    }
}