<?php


use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class ShippingDataService
{

    private const DURATION = 5 * 60;
    private $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function get(ShippingRateParams $params)
    {
        $key = serialize($params);
        $value = $this->cache->get($key);
        if ($value == null) {
            $client = new Client(['verify' => false]);

            $query = $client->request('POST', 'https://api.printful.com/shipping/rates', [
                'auth' => [
                    '77qn9aax-qrrm-idki',
                    'lnh0-fm2nhmp0yca7'
                ],
                RequestOptions::JSON => $params
            ]);

            $value = $query->getBody()->getContents();

            $this->cache->set($key, $value, self::DURATION);
            return $value;
        } else {
            return $value;
        }
    }
}