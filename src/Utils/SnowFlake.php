<?php
/**
 * User: macro chen <chen_macro@163.com>
 * Date: 2016/10/16
 * Time: 18:40
 */

namespace Polymer\Utils;

use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;

class SnowFlake extends AbstractIdGenerator
{
    /**
     * 用于SnowFlake产生ID
     *
     * @var string
     */
    protected $initialEpoch = 1476614506000;

    /**
     * EntityManager
     *
     * @var EntityManager
     */
    protected $em = null;

    /**
     * Generates an identifier for an entity.
     *
     * @param EntityManager|EntityManager $em
     * @param \Doctrine\ORM\Mapping\Entity $entity
     * @throws \Exception
     * @return mixed
     */
    public function generate(EntityManager $em, $entity)
    {
        $this->em = $em;
        return $this->generateID();
    }

    /**
     * Generate the 64bit unique ID.
     * @throws \Exception
     * @return mixed
     */
    public function generateID()
    {
        /**
         * Current Timestamp - 41 bits
         */
        $curr_timestamp = floor(microtime(true) * 1000);
        /**
         * Subtract custom epoch from current time
         */
        $curr_timestamp -= app()->getConfig('app.initial_epoch', $this->initialEpoch);
        /**
         * Create a initial base for ID
         */
        $base = decbin(pow(2, 40) - 1 + $curr_timestamp);
        /**
         * Get ID of database server (10 bits)
         * Up to 512 machines
         */
        $shard_id = decbin(pow(2, 9) - 1 + $this->getServerShardId());
        /**
         * Generate a random number (12 bits)
         * Up to 2048 random numbers per db server
         */
        $random_part = mt_rand(1, pow(2, 11) - 1);
        $random_part = decbin(pow(2, 11) - 1 + $random_part);
        /**
         * Concatenate the final ID
         */
        $final_id = bindec($base) . bindec($shard_id) . bindec($random_part);
        /**
         * Return unique 64bit ID
         */
        return $final_id;
    }

    /**
     * Identify the database and get the ID.
     * Only MySQL.
     * @throws \Exception
     * @return \Exception|int|\PDOException
     */
    private function getServerShardId()
    {
        try {
            $databaseType = $this->em->getConnection()->getDatabasePlatform()->getName();
        } catch (\PDOException $e) {
            return $e;
        }
        if ('mysql' === $databaseType) {
            return (int)$this->getMySqlServerId();
        }
        return (int)1;
    }

    /**
     * Get server-id from mysql cluster or replication server.
     *
     * @throws \Exception
     * @return mixed
     */
    private function getMySqlServerId()
    {
        if ($this->em->getConnection() instanceof PoolingShardConnection) {
            return $this->em->getConnection()->getActiveShardId();
        }
        try {
            $result = $this->em->getConnection()->query('SELECT @@server_id as server_id LIMIT 1')->fetch();
            return $result['server_id'];
        } catch (\Exception $e) {
            throw  $e;
        }
    }

    /**
     * Return time from 64bit ID.
     * @param $id
     * @throws \Exception
     * @return number
     */
    public function getTimeFromID($id)
    {
        $initialEpoch = app()->getConfig('app.initial_epoch', $this->initialEpoch);
        return bindec(substr(decbin($id), 0, 41)) - pow(2, 40) + 1 + $initialEpoch;
    }
}