<?php
/******************************************************************************
 * NodeList.php
 * ------------
 ******************************************************************************/
namespace Dodo;

/* Played fairly straight. Used for Node::childNodes when in "array mode". */
class NodeList extends \ArrayObject
{
        public function __construct($input=NULL)
        {
                parent::__construct($input);
        }

        public function item($i)
        {
                return $this[$i] ?? NULL;
        }
}

?>
