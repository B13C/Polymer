<?php
/**
 * User: macro chen <macro_fengye@163.com>
 * Date: 17-7-6
 * Time: 上午11:22
 */

namespace Polymer\Providers;

use Polymer\Boot\ApplicationAspectKernel;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class AopProvider implements ServiceProviderInterface
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
        $pimple['aop'] = function (Container $container) {
            $aspectKernel = ApplicationAspectKernel::getInstance();
            $aspectKernel->init(array_merge($container['application']->config('aop.init', []), $container['application']->config('app.aop.init', [])));
            return $aspectKernel;
        };
    }
}
