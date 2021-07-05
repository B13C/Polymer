<?php
/**
 * User: macro chen <macro_fengye@163.com>
 * Date: 16-10-26
 * Time: 上午11:44
 */

namespace Polymer\Middleware;

use Exception;
use Polymer\Utils\Constants;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IpFilterMiddleware
{
    protected array $addresses = [];
    protected ?int $mode = null;
    protected $allowed = null;
    protected $handler = null;

    /**
     * IpFilterMiddleware constructor.
     *
     * @param array $addresses
     * @param int $mode
     */
    public function __construct(array $addresses = [], int $mode = Constants::ALLOW)
    {
        foreach ($addresses as $address) {
            if (is_array($address)) {
                $this->addIpRange($address[0], $address[1]);
            } else {
                $this->addIp($address);
            }
        }
        $this->patterns = $addresses;
        $this->mode = $mode;
        $this->handler = function (ServerRequestInterface $request, ResponseInterface $response) {
            try {
                $response = $response->withStatus(403);
                $response->getBody()->write(' 403 Forbidden');
                return $response;
            } catch (Exception $e) {
                return null;
            }
        };
    }

    /**
     * 添加IP段
     *
     * @param $start
     * @param $end
     * @return $this
     */
    public function addIpRange($start, $end)
    {
        foreach (range(ip2long($start), ip2long($end)) as $address) {
            $this->addresses[] = $address;
        }
        return $this;
    }

    /**
     * 添加IP地址
     *
     * @param $ip
     * @return $this
     */
    public function addIp($ip): IpFilterMiddleware
    {
        $this->addresses[] = ip2long($ip);
        return $this;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $next
     * @return mixed|Response
     */
    public function __invoke(Request $request, Response $response, $next)
    {
        if ($this->mode === Constants::ALLOW) {
            $this->allowed = $this->allow($request);
        }
        if ($this->mode === Constants::DENY) {
            $this->allowed = $this->deny($request);
        }
        if (!$this->allowed) {
            $handler = $this->handler;
            return $handler($request, $response);
        }
        $response = $next($request, $response);
        return $response;
    }

    /**
     * 允许访问的请求
     *
     * @param Request $request
     * @return bool
     */
    public function allow(Request $request)
    {
        $clientAddress = ip2long($request->getServerParam('REMOTE_ADDR'));
        if (in_array($clientAddress, $this->addresses, true)) {
            return true;
        }
        return false;
    }

    /**
     * 拒绝访问的请求
     *
     * @param Request $request
     * @return bool
     */
    public function deny(Request $request)
    {
        $clientAddress = ip2long($request->getServerParam('REMOTE_ADDR'));
        if (in_array($clientAddress, $this->addresses, true)) {
            return false;
        }
        return true;
    }

    /**
     * 设置处理器
     *
     * @param $handler
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;
    }
}
