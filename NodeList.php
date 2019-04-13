<?php

class NodeList extends ArrayObject
{
        function __construct($input = NULL)
        {
                parent::__construct($input);
        }

        function item(i)
        {
                return (self[i] || NULL);
        }
}

?>
