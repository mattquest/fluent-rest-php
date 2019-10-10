<?php

namespace App\Connections;

use ErrorException;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Class BaseConnection
 * @package App\Connections
 * @property String $resource;
 * @method bool hasBearerAuth;
 * @method \GuzzleHttp\HandlerStack stickyHeaderStack;
 */
abstract class Connection extends Client
{
    use GenericEndpoints;
    protected static $resourceMethods = [
        'each',
        'get',
        'delete',
        'post',
        'postMany',
        'put',
        'putMany',
        'list',
    ];
    protected $resource = null;
    protected $nextRequestData = [];
    protected $resources = [];
    protected $headers = null;
    
    abstract protected function baseUri():string;
    abstract protected function listName():string;
    abstract protected function totalPages($response):int;
    
    public function __construct()
    {
        parent::__construct($this->initialConfig());
    }
    
    /**
     * set resource & return $this
     * @param $property
     * @return $this
     * creates a fluent interface for setting
     * the current resource and then using it
     * in one call
     * ie: ShipStation->orders->get();
     * @throws \ErrorException
     */
    public function __get($property)
    {
        if (is_array($this->resourceNames()) &&
            ! in_array($property, $this->resourceNames())) {
            throw new ErrorException("$property resource not found, did you mean one of these? " . implode(', ', $this->resourceNames()));
        }
        logger('setting resource to '.$property);
        $this->resource = $property;
        if ($resourceObject = $this->resourceObject($property)) {
            logger('resource object found');
            return $resourceObject;
        } else {
            logger('returning this');
            return $this;
        }
    }
    /**
     * @param $method
     * @param $args
     * @return bool
     * used as a catch all for "$this->hasTrait()" calls
     * if said trait is used, it will contain a function
     * to return true, otherwise this returns false
     */
    public function __call($method, $args)
    {
        if (substr($method, 0, 3) === 'has') {
            return false;
        }
        
        return parent::__call($method, $args);
    }
    
    public function each(callable $callable)
    {
        $page = 1;
        while ($proceed ?? true === true) {
            $response = $this->list([$this->pageName() => $page]);
            $totalPages = $this->totalPages($response);
            if ($page++ >= $totalPages) {
                $proceed = false;
            }
            if (!is_array($response->{$this->listName()} ?? null)) {
                break;
            }
            foreach ($response->{$this->listName()} as $item) {
                $callable($item);
            }
        }
    }
    public function get($id)
    {
        return $this->processResponse(
            parent::get($this->endpointForGet($id))
        );
    }
    public function list($parameters = [])
    {
        if ( count($parameters) === 0 ) {
            $pageName = $this->pageName();
            $parameters = [$pageName => 1];
        }
        if ($this->nextRequestData) {
            $parameters = array_merge($parameters, $this->nextRequestData);
            $this->nextRequestData = null;
        }
        return $this->processResponse(
            parent::get(
                $this->endpointForList(),
                ['query' => $parameters]
            )
        );
    }
    public function post($data)
    {
        logger("endpoint is {$this->endpointForPost()}");
        return $this->processResponse(
            parent::post(
                $this->endpointForPost(),
                ['json' => $data]
            )
        );
    }
    public function postMany($data)
    {
        return $this->processResponse(
            parent::post(
                $this->endpointForPostMany(),
                ['json' => $data]
            )
        );
    }
    public function put($data)
    {
        return $this->processResponse(
            parent::put(
                $this->endpointForPut(),
               ['json' => $data]
            )
        );
    }
    public function putMany($data)
    {
        return $this->processResponse(
            parent::put(
                $this->endpointForPutMany(),
                ['json' => $data]
            )
        );
    }
    public function delete($id)
    {
        return $this->processResponse(
            parent::delete($this->endpointForDelete($id))
        );
    }
    
    protected function pageName()
    {
        return 'page';
    }
    protected function initialConfig($config = [])
    {
        $config['base_uri'] = $config['base_uri'] ?? $this->baseUri();
        if (!is_null($this->headers) && !isset($config['headers'])) {
            $config['headers'] = $this->headers;
        }
        if ($this->hasBearerAuth() && !isset($config['handler'])) {
            $config['handler'] = $this->stickyHeaderStack();
        }
        
        return $config;
    }
    protected function processResponse(ResponseInterface $response)
    {
        $this->nextRequestData = null;
        // use randomSleep() if rateLimit() not extended
        is_null($this->rateLimit($response)) ? $this->randomSleep():null;
        
        $body = json_decode($response->getBody());

        if (isset($body->data)) {
            if (is_array($body->data) && count($body->data) === 1) {
                return $body->data[0];
            } else {
                return $body->data;
            }
        }

        return $body;
    }
    protected function randomSleep()
    {
        $microSeconds = rand(500000, 2000000);
        logger("randomly sleeping $microSeconds micro seconds");
        usleep($microSeconds);
        logger('done sleeping');
    }
    
    protected function rateLimit(ResponseInterface $response)
    {
        return $response ? null: null;
    }
    /**
     * resourceNames
     * optionally extend and set whitelist for resource names
     * @return array
     */
    protected function resourceNames()
    {
        return null;
    }
    protected function resourceNamespace()
    {
        return null;
    }
    protected function resourceObject($property)
    {
        if ($namespace = $this->resourceNamespace()) {
            $classPath = $namespace.'\\'.ucfirst($property);
            if (class_exists($classPath)) {
                return new $classPath($this);
            }
        }
        return null;
    }
    protected function requestOptions($options = [])
    {
        if (!is_null($this->headers)) {
            $options['headers'] = $this->headers;
        }
        
        return $options;
    }
}
