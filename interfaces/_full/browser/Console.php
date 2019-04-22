<?php
namespace domo\interfaces\browser

class Console
{
        public function __construct()
        {
                /* Nothing to do */
        }

        public function __call($method_name, $arguments)
        {
                throw Exception("Console->$method_name is not implemented");
        }
}

//class Console {
        //public function assert() { return utils\nyi(); }
        //public function clear() { return utils\nyi(); }
        //public function count() { return utils\nyi(); }
        //public function countReset() { return utils\nyi(); }
        //public function debug() { return utils\nyi(); }
        //public function dir() { return utils\nyi(); }
        //public function dirxml() { return utils\nyi(); }
        //public function error() { return utils\nyi(); }
        //public function group() { return utils\nyi(); }
        //public function groupCollapsed() { return utils\nyi(); }
        //public function groupEnd() { return utils\nyi(); }
        //public function info() { return utils\nyi(); }
        //public function log() { return utils\nyi(); }
        //public function profile() { return utils\nyi(); }
        //public function profileEnd() { return utils\nyi(); }
        //public function table() { return utils\nyi(); }
        //public function time() { return utils\nyi(); }
        //public function timeEnd() { return utils\nyi(); }
        //public function timeLog() { return utils\nyi(); }
        //public function timeStamp() { return utils\nyi(); }
        //public function trace() { return utils\nyi(); }
        //public function warn() { return utils\nyi(); }
//}

?>
