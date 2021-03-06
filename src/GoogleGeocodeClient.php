<?php

namespace Rezzza\GoogleGeocoder;

use Ivory\HttpAdapter\HttpAdapterInterface;
use Ivory\HttpAdapter\HttpAdapterException;

/**
 * @author Sébastien HOUZÉ <sebastien.houze@verylastroom.com>
 *
 * @see https://developers.google.com/maps/documentation/geocoding
 *
 */
class GoogleGeocodeClient
{
    const ENDPOINT_URL_GEOCODE = 'https://maps.googleapis.com/maps/api/geocode/json';
    const ENDPOINT_URL_PLACE_DETAILS = 'https://maps.googleapis.com/maps/api/place/details/json';
    const STATUS_OK = 'OK';

    private $apiKey;

    private $adapter;

    public function __construct(HttpAdapterInterface $adapter, $apiKey = null)
    {
        $this->adapter = $adapter;
        $this->apiKey = $apiKey;
    }

    public function executeQuery(array $queryParams)
    {
        $url = $this->buildUrl($queryParams);
        try {
            $response = $this->adapter->get($url);

            if ($response->getStatusCode() >= 400) {
                throw new Exception\GoogleGeocodeProtocolException(
                    $response->getReasonPhrase(),
                    $response->getStatusCode()
                );
            }

            $content = (string) $response->getBody();
            $json = json_decode($content, true);

            if (null === $json) {
                throw new Exception\GoogleGeocodeResponseDecodeException(
                    json_last_error_msg(), json_last_error()
                );
            }

            if (!array_key_exists('status', $json) || 'OK' !== $json['status']) {
                throw Exception\GoogleGeocodeException::fromStatusAndErrorMessage(
                    $json['status'],
                    array_key_exists('error_message', $json) ? $json['error_message'] : null
                );
            }

            $factory = new Model\AddressFactory();

            return $factory->createFromDecodedResultCollection(
                array_key_exists('results', $json) ? $json['results'] : [$json['result']]
            );
        } catch (HttpAdapterException $e) {
            throw new Exception\GoogleGeocodeProtocolException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function buildUrl(array $queryParams)
    {
        $queryString = http_build_query(
            array_merge(
                array_filter(
                    [
                        'key' => $this->apiKey
                    ]
                ),
                $queryParams
            )
        );

        return http_build_url(
            $this->guessEndpointUrl($queryParams),
            ['query' => $queryString]
        );
    }

    private function guessEndpointUrl($queryParams)
    {
        // Because google provide much more translated info
        // on placeDetails endpoint we switch to
        // Google place details compatible payload for
        // search by place id
        if (array_key_exists('placeid', $queryParams)) {
            return self::ENDPOINT_URL_PLACE_DETAILS;
        }

        return self::ENDPOINT_URL_GEOCODE;
    }
}
