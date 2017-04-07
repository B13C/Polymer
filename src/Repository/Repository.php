<?php
/**
 * User: macro chen <chen_macro@163.com>
 * Date: 16-12-12
 * Time: 上午8:55
 */
namespace Polymer\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping;
use Exception;
use Polymer\Boot\Application;
use Polymer\Exceptions\EntityValidateErrorException;
use Polymer\Exceptions\PresenterException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Repository extends EntityRepository
{
    /**
     * View presenter instance
     *
     * @var mixed
     */
    protected $presenterInstance;

    /**
     * 全局应用实例
     *
     * @var Application
     */
    protected $app = null;

    /**
     * Initializes a new <tt>EntityRepository</tt>.
     *
     * @param EntityManager $em The EntityManager to use.
     * @param Mapping\ClassMetadata $class The class descriptor.
     */
    public function __construct(EntityManager $em, Mapping\ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->app = app();
    }

    /**
     * 验证查询字段的值
     *
     * @param array $data 需要验证的数据
     * @param array $rules 验证数据的规则
     * @param string $key 存储错误信息的键
     * @throws Exception
     * @return $this
     */
    public function validate(array $data = [], array $rules = [], $key = 'error')
    {
        if($rules) {
            $validateRet = $this->app->component('biz_validator')->verifyField($data, $rules, $key);
            if (!$validateRet) {
                throw new EntityValidateErrorException('数据验证错误!');
            }
        }
        return $this;
    }

    /**
     * Prepare a new or cached presenter instance
     *
     * @param $entity
     * @return mixed
     * @throws PresenterException
     */
    public function present($entity)
    {
        if (!$this->getProperty('presenter') || !class_exists($this->getProperty('presenter'))) {
            throw new PresenterException('Please set the $presenter property to your presenter path.');
        }
        if (!$this->presenterInstance) {
            $cls = $this->getProperty('presenter');
            $this->presenterInstance = new $cls($entity);
        }
        return $this->presenterInstance;
    }

    /**
     * 给对象新增属性
     *
     * @param $propertyName
     * @param $value
     * @return $this
     */
    protected function setProperty($propertyName, $value)
    {
        $this->$propertyName = $value;
        return $this;
    }

    /**
     * 获取对象属性
     *
     * @param $propertyName
     * @return mixed
     */
    protected function getProperty($propertyName)
    {
        if (isset($this->$propertyName)) {
            return $this->$propertyName;
        }
        return null;
    }
}
