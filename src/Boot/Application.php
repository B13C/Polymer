<?php
/**
 * User: macro chen <macro_fengye@163.com>
 * Date: 2016/9/21
 * Time: 18:02
 */

namespace Polymer\Boot;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\ORMException;
use Noodlehaus\Config;
use Noodlehaus\Exception\EmptyDirectoryException;
use Polymer\Exceptions\ModelClassNotExistException;
use Polymer\Exceptions\ModelInstanceErrorException;
use Polymer\Providers\InitAppProvider;
use Polymer\Repository\Repository;
use Polymer\Utils\Constants;
use Polymer\Utils\DoctrineExtConfigLoader;
use Slim\Container;
use Doctrine\ORM\EntityManager;
use Doctrine\Common\EventManager;
use Doctrine\ORM\Tools\Setup;
use Interop\Container\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

final class Application
{
    /**
     * 应用实例
     *
     * @var $this
     */
    protected static $instance;

    /**
     * 应用的服务容器
     *
     * @var Container
     */
    private $container;

    /**
     * 配置文件对象
     *
     * @var Config $configObject
     */
    private $configObject = null;

    /**
     * 启动WEB应用
     *
     * @author macro chen <macro_fengye@163.com>
     * @throws \Exception
     */
    public function start()
    {
        try {
            $this->initEnvironment();
            $this->component('routerFile');
            $this->component('app')->run();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 启动控制台，包括单元测试及其他的控制台程序(定时任务等...)
     *
     * @author macro chen <macro_fengye@163.com>
     * @throws \Exception
     */
    public function startConsole()
    {
        try {
            $this->initEnvironment();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 初始化应用环境
     *
     * @author macro chen <macro_fengye@163.com>
     * @throws \Exception
     */
    private function initEnvironment()
    {
        try {
            set_error_handler('handleError');
            set_exception_handler('handleException');
            register_shutdown_function('handleShutdown');
            $this->container = new Container($this->config('slim'));
            $this->container->register(new InitAppProvider());
            $this->container['application'] = $this;
            static::setInstance($this);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 实例化数据库链接对象
     *
     * @param string $dbName
     * @param string $entityFolder 实体文件夹的名字
     * @throws \Doctrine\ORM\ORMException | \InvalidArgumentException | \Exception
     * @return EntityManager
     */
    public function db($dbName = '', $entityFolder = ROOT_PATH . '/entity/Models')
    {
        try {
            $dbConfig = $this->config('db.' . APPLICATION_ENV);
            $dbName = $dbName ?: current(array_keys($dbConfig));
            if (isset($dbConfig[$dbName]) && $dbConfig[$dbName] && !$this->component('entityManager-' . $dbName)) {
                $configuration = Setup::createAnnotationMetadataConfiguration([
                    $entityFolder,
                ], APPLICATION_ENV === 'development', ROOT_PATH . '/entity/Proxies/', null,
                    $dbConfig[$dbName]['useSimpleAnnotationReader']);
                try {
                    $entityManager = EntityManager::create($dbConfig[$dbName], $configuration,
                        $this->component('eventManager'));
                    $this->container['entityManager-' . $dbName] = $entityManager;
                } catch (\InvalidArgumentException $e) {
                    throw $e;
                }
            }
            return $this->container['entityManager-' . $dbName];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 获取指定键的配置文件
     *
     * @author macro chen <macro_fengye@163.com>
     * @param string $key
     * @param mixed | array $default
     * @throws EmptyDirectoryException
     * @throws \Exception
     * @return mixed
     */
    public function config($key, $default = null)
    {
        $configPaths = [dirname(__DIR__) . '/Config'];
        if (defined('ROOT_PATH') && file_exists(ROOT_PATH . '/config') && is_dir(ROOT_PATH . '/config')) {
            $configPaths[] = ROOT_PATH . '/config';
        }
        if (defined('APP_PATH') && file_exists(APP_PATH . '/Config') && is_dir(APP_PATH . '/Config')) {
            $configPaths[] = APP_PATH . '/Config';
        }
        try {
            if (null === $this->configObject) {
                $this->configObject = new Config($configPaths);
            }
            return $this->configObject->get($key, $default);
        } catch (EmptyDirectoryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 添加自定义监听器
     *
     * @author macro chen <macro_fengye@163.com>
     * @param array $params
     * @throws \Exception
     * @return EventManager
     */
    public function addEvent(array $params = [])
    {
        try {
            return $this->addEventOrSubscribe($params, 1);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 添加自定义订阅器
     *
     * @author macro chen <macro_fengye@163.com>
     * @param array $params
     * @throws \Exception
     * @return EventManager
     */
    public function addSubscriber(array $params = [])
    {
        try {
            return $this->addEventOrSubscribe($params, 0);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * 添加事件监听器或者订阅器
     *
     * @param array $params
     * @param int $listener 0 添加事件订阅器 1 添加事件监听器
     * @return mixed|null
     * @throws ORMException | \InvalidArgumentException | \Exception
     */
    private function addEventOrSubscribe(array $params, $listener)
    {
        $method = $listener ? 'addEventListener' : 'addEventSubscriber';
        $eventManager = $this->component('eventManager');
        foreach ($params as $key => $value) {
            if (!isset($value['class_name'])) {
                throw new \InvalidArgumentException('class_name必须设置');
            }
            $className = $value['class_name'];
            $data = isset($value['params']) ? $value['params'] : [];
            $listener === 1 ? $eventManager->{$method}($key,
                new $className($data)) : $eventManager->{$method}(new $className($data));
        }
        return $eventManager;
    }

    /**
     * 获取指定组件名字的对象
     *
     * @param $componentName
     * @param array $param
     * @throws \Exception
     * @return mixed|null
     */
    public function component($componentName, array $param = [])
    {
        if (!$this->container->has($componentName)) {
            $tmpClassName = ucfirst(str_replace(' ', '', lcfirst(ucwords(str_replace('_', ' ', $componentName)))));
            $providersPath = array_merge($this->config('providersPath'), $this->config('app.providersPath') ?: []);
            $classExist = 0;
            foreach ($providersPath as $namespace) {
                $className = $namespace . '\\' . $tmpClassName . 'Provider';
                if (class_exists($className)) {
                    $this->container->register(new $className(), $param);
                    $classExist = 1;
                    break;
                }
            }
            if (!$classExist) {
                return null;
            }
        }
        try {
            $retObj = $this->container->get($componentName);
            if ($componentName === Constants::REDIS) {
                $database = (isset($param['database']) && is_numeric($param['database'])) ? $param['database'] : 0;
                $retObj->select($database);
            }
            return $retObj;
        } catch (ContainerValueNotFoundException $e) {
            throw $e;
        } catch (ContainerException $e) {
            throw $e;
        }
    }

    /**
     * 获取全局应用实例
     *
     * @return static
     */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static;
        }
        return static::$instance;
    }

    /**
     * 设置全局可用的应用实例
     *
     * @param Application $application
     * @return static
     */
    public static function setInstance($application = null)
    {
        return static::$instance = $application;
    }

    /**
     * 获取业务模型实例
     *
     * @param string $modelName 模型的名字
     * @param array $parameters 实例化时需要的参数
     * @param string $modelNamespace 模型命名空间
     * @throws ModelClassNotExistException
     * @return mixed
     */
    public function model($modelName, array $parameters = [], $modelNamespace = APP_NAME . '\\Models')
    {
        $className = ucfirst(str_replace(' ', '', lcfirst(ucwords(str_replace('_', ' ', $modelName)))));
        $className = $modelNamespace . '\\' . ucfirst($className) . 'Model';
        if (class_exists($className)) {
            return new $className($parameters);
        }
        throw new ModelClassNotExistException($className . '不存在');
    }

    /**
     * 获取实体模型实例
     *
     * @param $tableName
     * @param string $entityNamespace 实体的命名空间
     * @throws EntityNotFoundException
     * @return bool
     */
    public function entity($tableName, $entityNamespace = 'Entity\\Models')
    {
        $className = ucfirst(str_replace(' ', '', lcfirst(ucwords(str_replace('_', ' ', $tableName)))));
        $className = $entityNamespace . '\\' . ucfirst($className);
        if (class_exists($className)) {
            return new $className;
        }
        throw new EntityNotFoundException($className . '类不存在');
    }

    /**
     * 获取EntityRepository
     *
     * @param string $entityName 实体的名字
     * @param string $dbName 数据库的名字
     * @param null $entityFolder 实体文件的路径
     * @param string $entityNamespace 实体的命名空间
     * @param string $repositoryNamespace Repository的命名空间
     * @throws \Exception
     * @return \Doctrine\ORM\EntityRepository | Repository | NULL
     */
    public function repository($entityName, $dbName = '', $entityFolder = null, $entityNamespace = 'Entity\\Models', $repositoryNamespace = 'Entity\\Repositories')
    {
        $className = ucfirst(str_replace(' ', '', lcfirst(ucwords(str_replace('_', ' ', $entityName)))));
        $repositoryClassName = $repositoryNamespace . '\\' . ucfirst($className) . 'Repository';
        if (class_exists($repositoryClassName)) {
            try {
                $dbConfig = $this->config('db.' . APPLICATION_ENV);
                $dbName = $dbName ?: current(array_keys($dbConfig));
                return $this->db($dbName, $entityFolder)->getRepository($entityNamespace . '\\' . ucfirst($className));
            } catch (ORMException $e) {
                throw $e;
            } catch (\InvalidArgumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                throw $e;
            }
        }
        throw new EntityNotFoundException($repositoryClassName . '类不存在');
    }

    /**
     * 获取服务组件
     *
     * @param string $serviceName
     * @param array $params
     * @param string $serviceNamespace
     * @return null | Object
     */
    public function service($serviceName, array $params = [], $serviceNamespace = APP_NAME . '\\Services')
    {
        $className = ucfirst(str_replace(' ', '', lcfirst(ucwords(str_replace('_', ' ', $serviceName)))));
        $className = $serviceNamespace . '\\' . ucfirst($className) . 'Service';
        if (class_exists($className)) {
            return new $className($params);
        }
        return null;
    }

    /**
     * 向Container里面设置值
     *
     * @param $key
     * @param $value
     * @throws \Exception
     */
    public function offSetValueToContainer($key, $value)
    {
        try {
            !$this->container->has($key) && $this->container->offsetSet($key, $value);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
