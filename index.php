<?php

namespace src\Integration;

class DataProvider
{
    private string $host;
    private string $user;
    private string $password;

    //оформил PHPDoc согласно стандартам
    /**
     * @param string $host
     * @param string $user
     * @param string $password
     */
    public function __construct(string $host, string $user, string $password) // добавил строгую типизацию
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * @param array $request
     *
     * @return array
     */
    public function getResponse(array $request) // поменял название функции
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

class DecoratorManager // убрал наследование
{
    // сделал приватными свойства класса, чтобы извне нельзя было их изменить - инкапсуляция
    private DataProvider $dataProvider;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;

    /**
     * @param DataProvider $dataProvider
     * @param CacheItemPoolInterface $cache
     * @param LoggerInterface $logger
     */
    // дата провайдер будем прокидывать в констуктор, это упростит тестирование и избавит от ошибок в случае, если $dataProvider поменяет функциональность
    public function __construct(DataProvider $dataProvider, CacheItemPoolInterface $cache, LoggerInterface $logger) // перенес логгер в конструктор
    {
        $this->dataProvider = $dataProvider;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Получение и кеширование данных.
     *
     * @param array $input
     */
    // разбил большой метод на насколько мелких согласное прицнипу разделения единственной ответственности
    public function getResponse(array $input)
    {
        $cachedResponse = $this->getCachedResponse($input);

        if ($cachedResponse !== null) {
            return $cachedResponse;
        }

        return $this->fetchAndCacheData($input);
    }

    private function getCachedResponse(array $input)
    {
        $cacheKey = $this->getCacheKey($input);
        $cachedItem = $this->cache->getItem($cacheKey);

        if ($cachedItem->isHit()) {
            return $cachedItem->get();
        }

        return null;
    }

    private function fetchAndCacheData(array $input)
    {
        try {
            $response = $this->dataProvider->getResponse($input);
            $this->cacheResponse($input, $response);
            return $response;
        } catch (Exception $e) {
            throw new Exception('Error fetching data: ' . $e->getMessage());
        }
    }

    private function cacheResponse($input, $response)
    {
        $cacheKey = $this->getCacheKey($input);
        $cachedItem = $this->cache->getItem($cacheKey);
        $cachedItem
            ->set($response)
            ->expiresAt(
            (new DateTime())->modify('+1 day')
        );
    }

    private function getCacheKey(array $input)
    {
        return md5(serialize($input));
    }
}
