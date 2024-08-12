<?php

namespace src\Integration;

class DataProvider
{
    private $host;
    private $user;
    private $password;

    //оформить PHPDoc согласно стандартам
    /**
     * @param $host
     * @param $user
     * @param $password
     */
    public function __construct($host, $user, $password)
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
    }

    //возможно, стоит вынести этот метод в отдельный класс
    /**
     * @param array $request
     *
     * @return array
     */
    public function get(array $request) // название функции не даёт понимания, что мы получаем
    {
        // returns a response from external service
    }
}


namespace src\Decorator;

use DateTime;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use src\Integration\DataProvider;

class DecoratorManager extends DataProvider // наследование создаёт жесткую связь между классами, лучше использовать композицию
{
    public $cache;
    public $logger;

    /**
     * @param string $host
     * @param string $user
     * @param string $password
     * @param CacheItemPoolInterface $cache
     */
    public function __construct($host, $user, $password, CacheItemPoolInterface $cache)
    {
        parent::__construct($host, $user, $password);
        $this->cache = $cache;
    }

    public function setLogger(LoggerInterface $logger) //логгер нужно передавать в конструктор
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse(array $input)
    {
        try {
            // вот это лучше в отдельную функцию переместить
            $cacheKey = $this->getCacheKey($input);
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }

            $result = parent::get($input);

            $cacheItem
                ->set($result)
                ->expiresAt(
                    (new DateTime())->modify('+1 day')
                );

            return $result;
        } catch (Exception $e) {
            $this->logger->critical('Error'); //текст ошибки лучше показывать
        }

        return []; //лучше пустой массив не возвращать, может затруднить определение проблемы, лучше выбрасывать ошибку
    }

    public function getCacheKey(array $input)
    {
        return json_encode($input); //для кэша json_encode не самый лучший вариант, тут скорее md5 надо, ключ будет уникальным
    }
}
