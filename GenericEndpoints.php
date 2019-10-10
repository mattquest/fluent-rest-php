<?php

namespace App\Connections;

/**
 * Trait Endpoints
 */
trait GenericEndpoints
{
    protected function endpointForDelete($id)
    {
        return $this->resource.'/'.$id;
    }
    protected function endpointForGet($id)
    {
        return $this->resource.'/'.$id;
    }
    protected function endpointForList()
    {
//        logger("endpoint for list is $this->resource");
        return $this->resource;
    }
    protected function endpointForPost()
    {
        return $this->resource;
    }
    protected function endpointForPut()
    {
        return $this->resource;
    }
    protected function endpointForPostMany()
    {
        return $this->resource;
    }
    protected function endpointForPutMany()
    {
        return $this->resource;
    }
}
