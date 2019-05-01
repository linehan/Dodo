<?php
namespace domo;

/*
 * Element::children will be an instance of this class.
 * It defines length, item() and namedItem().
 *
 * Compare the similar NamedNodeMap, used to implement
 * Element::attributes.
 *
 * TODO: Clean up to bring in line with style guidelines used elsewhere.
 */
class HTMLCollection extends \ArrayObject
{
        private $name_to_item = array();
        private $lastModTime;
        private $owner;

        public function __construct(Element $owner)
        {
                $this->owner = $owner;
                $this->updateCache();
        }

        public function length()
        {
                $this->updateCache();
                return count($this);
        }

        public function item(integer $i): ?Element
        {
                $this->updateCache();
                return $this[$i] ?? NULL;
        }

        public function namedItem(string $name): ?Element
        {
                $this->updateCache();
                return $this->name_to_item[$name] ?? NULL;
        }

        public function namedItems()
        {
                $this->updateCache();
                return $this->name_to_item;
        }

        public function updateCache()
        {
                if ($this->_lastModTime !== $this->owner->__lastmod_update()) {
                        $this->_lastModTime = $this->owner->__lastmod_update();

                        foreach ($this as $i => $v) {
                                unset($this[$i]);
                        }
                        $this->name_to_item = array();

                        for ($n=$this->owner->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === ELEMENT_NODE) {
                                        $this[] = $n;

                                        /* XXX Are there any requirements about the namespace of the id property? */
                                        $id = $n->getAttribute("id");

                                        /* If there is an id that is not already in use... */
                                        if ($id && !$this->name_to_item[$id]) {
                                                $this->name_to_item[$id] = $n;
                                        }

                                        /* For certain HTML elements we check the name attribute */
                                        $name = $n->getAttribute("name");
                                        if ($name
                                        && $this->owner->namespaceURI() === NAMESPACE_HTML
                                        && preg_match('/^(a|applet|area|embed|form|frame|frameset|iframe|img|object)$/', $this->owner->localName())
                                        && !$this->name_to_item[$name]) {
                                                $this->name_to_item[$id] = $n;
                                        }
                                }
                        }
                }
        }
}

?>
