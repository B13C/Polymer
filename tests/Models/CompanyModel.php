<?php
/**
 * User: macro chen <chen_macro@163.com>
 * Date: 17-3-30
 * Time: 下午2:12
 */

namespace Polymer\Tests\Models;

use Doctrine\ORM\Events;
use Polymer\Model\Model;
use Polymer\Tests\Listener\TestListener;
use Polymer\Utils\Constants;

class CompanyModel extends Model
{
    /**
     * 数据库表名
     * @var string
     */
    protected $table = 'company';

    /**
     * 验证规则
     *
     * @var array
     */
    protected $rules = [
        'name' => [
            'Length' => [
                'min' => 20,
                'max' => 50,
                'minMessage' => 'Your first name must be at least {{ limit }} characters long',
                'maxMessage' => 'Your first name cannot be longer than {{ limit }} characters',
                'groups' => ['registration'],
            ],
            'NotBlank' => ['groups' => ['add'], 'message' => 'aaaaa']
        ]
    ];

    /**
     * 数据库配置
     * @var string
     */
    protected $schema = 'db1';

    /**
     * 实体命名空间
     *
     * @var string
     */
    protected $entityNamespace = 'Polymer\Tests\Entity\Models';

    /**
     * 实体文件的文件系统路径
     *
     * @var string
     */
    protected $entityFolder = ROOT_PATH . '/test/Entity/Models';

    /**
     * Repository的命名空间
     *
     * @var string
     */
    protected $repositoryNamespace = 'Polymer\Tests\Entity\Repositories';

    /**
     * 保存数据
     *
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function save(array $data = [])
    {
        try {
            $this->app->addEvent([Events::prePersist => ['class_name' => TestListener::class]]);
            $obj = $this->make($data)->validate($this->rules, Constants::MODEL_OBJECT, ['add']);
            $this->em->persist($obj);
            $this->em->flush();
            return $obj->getId();
        } catch (\Exception $e) {
            throw $e;
        }
    }


    /**
     * 更新数据
     *
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function update(array $data = [])
    {
        try {
            $this->app->addEvent([
                Events::preUpdate => [
                    'class_name' => TestListener::class,
                    'data' => ['address' => 'aaaaa']
                ]
            ]);
            $obj = $this->make($data, ['id' => 5], true);
            $this->em->persist($obj);
            $this->em->flush();
            return $obj->getId();
        } catch (\Exception $e) {
            throw $e;
        }
    }
}