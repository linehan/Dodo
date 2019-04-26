<?php
namespace domo;

require_once(__DIR__.'/../lib/util.php');

/*
 * History: NamedNodeMap has nothing to do with Nodes, it's a collection
 * of Attrs. But once upon a time, an Attr was a type of Node called a
 * NamedNode. But then DOM4 came along and changed that, but it lives on
 * in this interface's name!
 */

/*
 * Originally in Domino this was just to satisfy type checking, so
 * some of the DOM methods are still stubbed. We only really use construct(),
 * length(), and item().
 */


/*
 * The main function of NamedNodeMap is to implement the
 * Element::attribute() array. Thus, we have baked in
 * logic that is specific to an Element, including access
 * of its internal storage caches.
 */

class NamedNodeMap extends \ArrayObject
{
        private $__qname_to_attr = array(); /* qname => Attr */
        private $__lname_to_attr = array(); /* ns|lname => Attr */
        private $__lname_to_index = array(); /* ns|lname => N */
        /* NOW IMPLEMENTED AS $this[], the default */
        //public $index_to_attr = array(); [> N => Attr <]

        /* DOM-LS associated element, defined in spec but not given property. */
        public $_element= NULL;

        public function __construct(?Element $element=NULL)
        {
                $this->_element = $element;
        }

        /**********************************************************************
         * DOMO INTERNAL BOOK-KEEPING
         **********************************************************************/
        private function __append(Attr $a)
        {
                $qname = $a->name();

                /* NO COLLISION */
                if (!isset($this->__qname_to_attr[$qname])) {
                        $this->__qname_to_attr[$qname] = $a;
                /* COLLISION */
                } else {
                        if (is_array($this->__qname_to_attr[$qname])) {
                                $this->__qname_to_attr[$qname][] = $a;
                        } else {
                                $this->__qname_to_attr[$qname] = array(
                                        $this->__qname_to_attr[$qname], 
                                        $a
                                );
                        }
                }

                $key = $a->namespaceURI() . '|' . $a->localName();

                $this->__lname_to_attr[$key] = $a;
                $this->__lname_to_index[$key] = count($this);
                $this[] = $a;
        }

        private function __replace(Attr $a)
        {
                $qname = $a->name();

                /* NO COLLISION */
                if (!isset($this->__qname_to_attr[$qname])) {
                        $this->__qname_to_attr[$qname] = $a;
                /* COLLISION */
                } else {
                        if (is_array($this->__qname_to_attr[$qname])) {
                                $this->__qname_to_attr[$qname][] = $a;
                        } else {
                                $this->__qname_to_attr[$qname] = array(
                                        $this->__qname_to_attr[$qname], 
                                        $a
                                );
                        }
                }

                $key = $a->namespaceURI() . '|' . $a->localName();

                $this->__lname_to_attr[$key] = $a;
                $this[$this->__lname_to_index[$key]] = $a;
        }

        private function __remove(Attr $a)
        {
                $qname = $a->name();
                $key = $a->namespaceURI() . '|' . $a->localName();

                unset($this->__lname_to_attr[$key]);
                $i = $this->__lname_to_index[$key];
                unset($this->__lname_to_index[$key]);

                array_splice($this, $i, 1);

                if (isset($this->__qname_to_attr[$qname])) {
                        if (is_array($this->__qname_to_attr[$qname])) {
                                $i = array_search($a, $this->__qname_to_attr[$qname]);
                                if ($i !== false) {
                                        array_splice($this->__qname_to_attr[$qname], $i, 1);
                                }
                        } else {
                                unset($this->__qname_to_attr[$qname]);
                        }
                }
        }

        /**********************************************************************
         * DOM-LS Methods
         **********************************************************************/

        public function length(): int
        {
                return count($this);
        }

        public function item(int $index): ?Attr
        {
                return $this[$index] ?? NULL;
        }

        /* DOMO */
        public function hasNamedItem(string $qname): bool 
        {
                /*
                 * Per HTML spec, we normalize qname before lookup,
                 * even though XML itself is case-sensitive.
                 */
                if (!ctype_lower($qname) && $this->_element->isHTMLElement()) {
                        $qname = \domo\to_ascii_lower_case($qname);
                }

                return isset($this->__qname_to_attr[$qname]);
        }

        /* DOMO */
        public function hasNamedItemNS(?string $ns, string $lname): bool 
        {
                $ns = $ns ?? "";
                return isset($this->__lname_to_attr["$ns|$lname"]);
        }

        public function getNamedItem(string $qname): ?Attr
        {
                /*
                 * Per HTML spec, we normalize qname before lookup,
                 * even though XML itself is case-sensitive.
                 */
                if (!ctype_lower($qname) && $this->_element->isHTMLElement()) {
                        $qname = \domo\to_ascii_lower_case($qname);
                }

                if (!isset($this->__qname_to_attr[$qname])) {
                        return NULL;
                }

                if (is_array($this->__qname_to_attr[$qname])) {
                        return $this->__qname_to_attr[$qname][0];
                } else {
                        return $this->__qname_to_attr[$qname];
                }
        }

        public function getNamedItemNS(?string $ns, string $lname): ?Attr
        {
                $ns = $ns ?? "";
                return $this->__lname_to_attr["$ns|$lname"] ?? NULL;
        }

        public function setNamedItem(Attr $attr): ?Attr
        {
                $owner = $attr->ownerElement();

                if ($owner !== NULL && $owner !== $this->_element) {
                        \domo\error("InUseAttributeError");
                }

                $oldAttr = $this->getNamedItem($attr->name());

                if ($oldAttr == $attr) {
                        return $attr;
                }

                if ($oldAttr !== NULL) {
                        $this->__replace($attr);
                } else {
                        $this->__append($attr);
                }

                return $oldAttr;
        }

        public function setNamedItemNS(Attr $attr): ?Attr
        {
                $owner = $attr->ownerElement();

                if ($owner !== NULL && $owner !== $this->_element) {
                        \domo\error("InUseAttributeError");
                }

                $oldAttr = $this->getNamedItemNS($attr->namespaceURI(), $attr->localName());

                if ($oldAttr == $attr) {
                        return $attr;
                }

                if ($oldAttr !== NULL) {
                        $this->__replace($attr);
                } else {
                        $this->__append($attr);
                }

                return $oldAttr;
        }

        /* NOTE: qname may be lowercase or normalized in various ways */
        public function removeNamedItem(string $qname): ?Attr
        {
                $attr = $this->getNamedItem($qname);
                if ($attr !== NULL) {
                        $this->__remove($attr);
                } else {
                        \domo\error("NotFoundError");
                }
                return $attr;
        }

        /* NOTE: qname may be lowercase or normalized in various ways */
        public function removeNamedItemNS(?string $ns, string $lname): ?Attr
        {
                $attr = $this->getNamedItemNS($ns, $lname);
                if ($attr !== NULL) {
                        $this->__remove($attr);
                } else {
                        \domo\error("NotFoundError");
                }
                return $attr;
        }
}

?>
