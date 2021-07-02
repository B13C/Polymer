<?php
/**
 * User: macro chen <macro_fengye@163.com>
 *
 * 所有控制器必须集成该类
 *
 * @author macro chen <macro_fengye@163.com>
 */

namespace Polymer\Controller;

use Exception;
use JsonException;
use Polymer\Boot\Application;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

class Controller
{
    /**
     * Slim框架自动注册的Container
     * @var ContainerInterface
     */
    protected ContainerInterface $ci;

    /**
     * 整个框架的应用
     *
     * @var Application
     */
    protected Application $application;

    /**
     * Controller constructor.
     *
     * @param ContainerInterface $ci
     * @throws ContainerExceptionInterface
     */

    public function __construct(ContainerInterface $ci)
    {
        $this->application = $ci->get('application');
    }

    /**
     * 模板渲染
     *
     * @param string $template 模板文件
     * @param array $data 传递到模板的数据
     * @return ResponseInterface
     * @throws Exception
     * @author macro chen <macro_fengye@163.com>
     */
    protected function render(string $template, ResponseInterface $response, array $data = []): ResponseInterface
    {
        $response->getBody()->write("");
        return $response;
    }

    /**
     * Json.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * This method prepares the response object to return an HTTP Json
     * response to the client.
     *
     * @param mixed $data The data
     * @param ResponseInterface $response
     * @param int|null $status The HTTP status code.
     * @param int $encodingOptions Json encoding options
     * @return ResponseInterface
     */
    protected function withJson(mixed $data, ResponseInterface $response, int $status = null, int $encodingOptions = 0): ResponseInterface
    {
        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR | $encodingOptions);
        } catch (JsonException $e) {
            $body = '{"code":500 , "msg":' . $e->getMessage() . ' , "data":null}';
        }
        $response->getBody()->write($body);
        return $response;
    }
}
