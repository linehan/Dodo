<?php
namespace domo\interfaces\browser

class History
{
        public function __construct()
        {
                /* Nothing to do */
        }

        public function __call($method_name, $arguments)
        {
                throw Exception("History->$method_name is not implemented");
        }
}

//class History {
        //public function back() { return utils\nyi(); }
        //public function forward() { return utils\nyi(); }
        //public function go() { return utils\nyi(); }
        //public function pushState() { return utils\nyi(); }
        //public function replaceState() { return utils\nyi(); }
//}

?>
