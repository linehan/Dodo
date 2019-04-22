<?php
namespace domo\interfaces\browser

class Location
{
        public function __construct()
        {
                /* Nothing to do */
        }

        public function __call($method_name, $arguments)
        {
                throw Exception("Location->$method_name is not implemented");
        }
}

?>
