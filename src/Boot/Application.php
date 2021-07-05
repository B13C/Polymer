<?php
/**
 * User: macro chen <macro_fengye@163.com>
 * Date: 2016/9/21
 * Time: 18:02
 */

namespace Polymer\Boot;

use Composer\Autoload\ClassLoader;
use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Source\DefinitionArray;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\EventManager;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\NoopWordInflector;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Setup;
use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use Noodlehaus\Config;
use Noodlehaus\Exception\EmptyDirectoryException;
use Polymer\Providers\InitApplicationProvider;
use Polymer\Repository\Repository;
use Polymer\Utils\Constants;
use ReflectionClass;
use Slim\App;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;
use Throwable;

final class Application
{
    /**
     * 应用实例
     *
     * @var ?Application
     */
    protected static ?Application $instance = null;

    /**
     * Slim APP
     *
     * @var App
     */
    protected App $app;

    /**
     * The loaded service providers.
     *
     * @var array
     */
    protected array $loadedProviders = [];

    /**
     * 应用的服务容器
     *
     * @var Container
     */
    private Container $diContainer;

    /**
     * 配置文件对象
     *
     * @var ?Config $configObject
     */
    private ?Config $configObject = null;

    /**
     * 配置文件缓存
     *
     * @var ?Cache
     */
    private ?Cache $configCache = null;

    /**
     * @var ClassLoader
     */
    private ClassLoader $classLoader;

    /**
     * Application constructor.
     * @throws Exception
     */
    public function __construct()
    {
        self::setInstance($this);
        $this->initEnvironment();
    }

    /**
     * 初始化应用环境
     *
     * @throws Exception
     * @author macro chen <macro_fengye@163.com>
     */
    public function initEnvironment(): void
    {
        try {
            set_error_handler('handleError');
            set_exception_handler(static function (Throwable $throwable) {
                print_r($throwable);
            });
            register_shutdown_function('handleShutdown');
            $this->configCache = new DoctrineProvider(new ArrayAdapter());
            $builder = new ContainerBuilder();
            $builder->useAnnotations(true)->addDefinitions(new DefinitionArray($this->initConfigObject()));
            $this->diContainer = $builder->build();
            $initAppFile = ROOT_PATH . DS . 'app' . DS . APP_NAME . DS . 'Providers' . DS . 'InitApplicationProvider.php';
            $initAppClass = file_exists($initAppFile) ? APP_NAME . DS . 'Providers' . DS . 'InitApplicationProvider' : InitApplicationProvider::class;
            $this->diContainer->set('application', $this);
            $this->register($initAppClass);
            $this->component('aop');
            self::setInstance($this);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 将配置文件合并为一个数组
     *
     * @return array
     */
    public function initConfigObject(): array
    {
        $configPaths = $this->getConfigPaths();
        if (null === $this->configObject) {
            $this->configObject = new Config($configPaths);
            $this->configCache->save('configCache', $this->configObject);
        }
        return $this->configObject->all();
    }

    /**
     * 获取项目的配置文件位置
     *
     *
     * @return array
     */
    public function getConfigPaths(): array
    {
        $configPaths = [dirname(__DIR__) . DS . 'Config'];
        if (defined('ROOT_PATH') && file_exists(ROOT_PATH . DS . 'config') && is_dir(ROOT_PATH . DS . 'config')) {
            $configPaths[] = ROOT_PATH . DS . 'config';
        }
        if (defined('APP_PATH') && file_exists(APP_PATH . DS . 'Config') && is_dir(APP_PATH . DS . 'Config')) {
            $configPaths[] = APP_PATH . DS . 'Config';
        }
        return $configPaths;
    }

    /**
     * 注册应用配置的Provider
     */
    public function register($provider): void
    {
        if (!array_key_exists($provider, $this->loadedProviders)) {
            $this->diContainer->call([new $provider(), 'register'], [$this->diContainer]);
            $this->loadedProviders[$provider] = true;
        }
    }

    /**
     * 获取指定组件名字的对象
     *
     * @param string $componentName
     * @param array $param
     * @return mixed
     */
    public function component(string $componentName, array $param = []): mixed
    {
        try {
            if (!$this->diContainer->has($componentName)) {
                $providersPath = array_merge($this->config('app.providers_path', []), $this->config('providers_path'));
                foreach ($providersPath as $namespace) {
                    $className = $namespace . '\\' . $this->getInflector()->classify($componentName) . 'Provider';
                    if (class_exists($className)) {
                        $param[0] = $this->diContainer;
                        $this->diContainer->call([new $className(), 'register'], $param);
                        break;
                    }
                }
            }
            $componentObj = $this->diContainer->get($componentName);
            if ($componentName === Constants::REDIS) {
                $database = (isset($param['database']) && is_numeric($param['database'])) ? $param['database'] & 15 : 0;
                $componentObj->select($database);
            }
            return $componentObj;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 获取指定键的配置文件
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     * @throws Exception
     * @throws EmptyDirectoryException
     * @author macro chen <macro_fengye@163.com>
     */
    public function config(string $key, mixed $default = null): mixed
    {
        try {
            if ($this->configCache->fetch('configCache') && $this->configCache->fetch('configCache')->get($key)) {
                return $this->configCache->fetch('configCache')->get($key, $default);
            }
            return $default;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 将下划线转为驼峰
     * table_name =>  tableName
     *
     * @return Inflector
     */
    #[Pure] public function getInflector(): Inflector
    {
        return new Inflector(new NoopWordInflector(), new NoopWordInflector());
    }

    /**
     * 获取全局应用实例
     *
     * @return static
     */
    public static function getInstance(): Application
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * 设置全局可用的应用实例
     *
     * @param Application|null $application
     * @return Application|null
     */
    public static function setInstance(Application $application = null): ?Application
    {
        return self::$instance = $application;
    }

    /**
     * @return ClassLoader
     */
    public function getClassLoader(): ClassLoader
    {
        return $this->classLoader;
    }

    /**
     * @param ClassLoader $classLoader
     */
    public function setClassLoader(ClassLoader $classLoader): void
    {
        $this->classLoader = $classLoader;
    }

    /**
     * 启动WEB应用
     *
     * @throws Exception
     * @author macro chen <macro_fengye@163.com>
     */
    public function start(): void
    {
        try {
            $this->component('routerFile');
            $this->component('app')->run();
        } catch (Exception $e) {
            print_r($e);
            throw $e;
        }
    }

    /**
     * 启动控制台，包括单元测试及其他的控制台程序(定时任务等...)
     *
     * @throws Exception
     * @author macro chen <macro_fengye@163.com>
     */
    public function startConsole(): void
    {
        try {
            $this->initEnvironment();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 添加自定义监听器
     *
     * @param array $params
     * @return EventManager|null
     * @throws Exception
     * @author macro chen <macro_fengye@163.com>
     */
    public function addEvent(array $params = []): ?EventManager
    {
        try {
            return $this->addEventOrSubscribe($params, 1);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 添加事件监听器或者订阅器
     *
     * @param array $params
     * @param int $listener 0 添加事件订阅器 1 添加事件监听器
     * @return mixed
     */
    private function addEventOrSubscribe(array $params, int $listener): mixed
    {
        $methods = ['addEventSubscriber', 'addEventListener'];
        $eventManager = $this->component('eventManager');
        foreach ($params as $key => $value) {
            if (!isset($value['class_name'])) {
                throw new InvalidArgumentException('class_name必须设置');
            }
            $className = $value['class_name'];
            $data = $value['params'] ?? [];
            $listener === 1 ? $eventManager->{$methods[$listener]}($key, new $className($data)) : $eventManager->{$methods[$listener]}(new $className($data));
        }
        return $eventManager;
    }

    /**
     * 添加自定义订阅器
     *
     * @param array $params
     * @return EventManager|null
     * @author macro chen <macro_fengye@163.com>
     */
    public function addSubscriber(array $params = []): ?EventManager
    {
        try {
            return $this->addEventOrSubscribe($params, 0);
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * 获取业务模型实例
     *
     * @param string $modelName 模型的名字
     * @param array $params 实例化时需要的参数
     * @param string|null $modelNamespace 模型命名空间
     * @return mixed
     */
    public function model(string $modelName, array $params = [], string $modelNamespace = null)
    {
        try {
            $modelNamespace = $modelNamespace ?: (defined('DEPEND_NAMESPACE') ? DEPEND_NAMESPACE : APP_NAME) . '\\Models';
            $className = $modelNamespace . '\\' . $this->getInflector()->classify($modelName) . 'Model';
            $key = str_replace('\\', '', $className);
            if (!$this->diContainer->has($key) && class_exists($className)) {
                $this->diContainer->set($key, new $className($params));
            }
            return $this->diContainer->get($key);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 获取实体模型实例
     *
     * @param $entityName
     * @param string|null $entityNamespace 实体的命名空间
     * @return bool|null
     */
    public function entity($entityName, string $entityNamespace = null): ?bool
    {
        try {
            $entityNamespace = $entityNamespace ?: 'Entity\\Models';
            $className = $entityNamespace . '\\' . $this->getInflector()->classify($entityName);
            return new $className;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 获取EntityRepository
     *
     * @param string $entityName 实体的名字
     * @param string $dbName 数据库的名字
     * @param null $entityFolder 实体文件的路径
     * @param null $entityNamespace 实体的命名空间
     * @param null $repositoryNamespace Repository的命名空间
     * @return EntityRepository | Repository | NULL
     */
    public function repository(string $entityName, string $dbName = '', $entityFolder = null, $entityNamespace = null, $repositoryNamespace = null)
    {
        $entityNamespace = $entityNamespace ?: APP_NAME . '\\Entity\\Mapping';
        $repositoryNamespace = $repositoryNamespace ?: APP_NAME . '\\Entity\\Repositories';
        $repositoryClassName = $repositoryNamespace . '\\' . $this->getInflector()->classify($entityName) . 'Repository';
        try {
            $dbName = $dbName ?: current(array_keys($this->config('db.' . APPLICATION_ENV)));
            $key = str_replace('\\', '', $repositoryClassName);
            if (!$this->diContainer->has($key) && class_exists($repositoryClassName)) {
                $this->diContainer->set($key, $this->db($dbName, $entityFolder)->getRepository($entityNamespace . '\\' . $this->getInflector()->classify($entityName)));
            }
            return $this->diContainer->get($key);
        } catch (Exception $e) {
            print_r($e);
            return null;
        }
    }

    /**
     * 实例化数据库链接对象
     *
     * @param string $dbName
     * @param mixed|null $entityFolder 实体文件夹的名字
     * @return EntityManager
     * @throws ORMException | InvalidArgumentException | Exception
     */
    public function db(string $dbName = '', mixed $entityFolder = null): EntityManager
    {
        try {
            $dbName = $dbName ?: current(array_keys($this->config('db.' . APPLICATION_ENV)));
            $cacheKey = 'em' . '.' . $this->config('db.' . APPLICATION_ENV . '.' . $dbName . '.emCacheKey', str_replace([':', DIRECTORY_SEPARATOR], ['', ''], APP_PATH)) . '.' . $dbName;
            if ($this->config('db.' . APPLICATION_ENV . '.' . $dbName) && !$this->diContainer->has($cacheKey)) {
                $entityFolder = $entityFolder ?: ROOT_PATH . DIRECTORY_SEPARATOR . 'entity' . DIRECTORY_SEPARATOR . 'Models';
                $cache = APPLICATION_ENV === 'development' ? null : new DoctrineProvider(new ArrayAdapter());
                $configuration = Setup::createAnnotationMetadataConfiguration([
                    $entityFolder,
                ], APPLICATION_ENV === 'development',
                    ROOT_PATH . DIRECTORY_SEPARATOR . 'entity' . DIRECTORY_SEPARATOR . 'Proxies' . DIRECTORY_SEPARATOR,
                    $cache,
                    $this->config('db.' . APPLICATION_ENV . '.' . $dbName . '.' . 'useSimpleAnnotationReader'));
                $entityManager = EntityManager::create($this->config('db.' . APPLICATION_ENV . '.' . $dbName), $configuration, $this->component('eventManager'));
                $this->diContainer->set($cacheKey, $entityManager);
            }
            return $this->diContainer->get($cacheKey);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 获取服务组件
     *
     * @param string $serviceName
     * @param string|null $serviceNamespace
     * @param array $params
     * @return null | Object
     */
    public function service(string $serviceName, string $serviceNamespace = null, ...$params): ?object
    {
        try {
            $serviceNamespace = $serviceNamespace ?: (defined('DEPEND_NAMESPACE') ? DEPEND_NAMESPACE : APP_NAME) . '\\Services';
            $className = $serviceNamespace . '\\' . $this->getInflector()->classify($serviceName) . 'Service';
            $key = str_replace('\\', '', $className);
            if (!$this->diContainer->has($key) && class_exists($className)) {
                $class = new ReflectionClass($className);
                $instance = $class->newInstanceArgs($params);
                $this->diContainer->set($key, $instance);
            }
            return $this->diContainer->get($key);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 向Container里面设置值
     *
     * @param $key
     * @param $value
     * @throws Exception
     */
    public function offSetValueToContainer($key, $value): void
    {
        try {
            !$this->diContainer->has($key) && $this->diContainer->set($key, $value);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 获取Slim APP对象
     *
     * @return App
     */
    public function getSlimApp(): App
    {
        return $this->app;
    }
}
