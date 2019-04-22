<?php
require_once("utils.php");

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



class NamedNodeMap
{
        public $qname_to_attr = array(); /* qname => Attr */
        public $lname_to_attr = array(); /* ns|lname => Attr */
        public $lname_to_index = array(); /* ns|lname => N */
        public $index_to_attr = array(); /* N => Attr */

        public function __construct(?Element $element=NULL)
        {
                $this->element = $element;
        }

        private function _append(Attr $attr)
        {
                $qname = $attr->name;

                if (isset($this->qname_to_attr[$qname])) {
                        /* Slow branch to handle collisions */
                        if (is_array($this->qname_to_attr[$qname])) {
                                $this->qname_to_attr[$qname][] = $attr;
                        } else {
                                $this->qname_to_attr[$qname] = array($this->qname_to_attr[$qname], $attr);
                        }
                } else {
                        /* Fast branch (should be majority of time) */
                        $this->qname_to_attr[$qname] = $attr;
                }

                $this->lname_to_attr[$attr->ns.'|'.$attr->lname] = $attr;
                $this->lname_to_index[$attr->ns.'|'.$attr->lname] = count($this->index_to_attr);
                $this->index_to_attr[] = $attr;
        }

        private function _replace(Attr $attr)
        {
                $qname = $attr->name;

                if (isset($this->qname_to_attr[$qname])) {
                        /* Slow branch to handle collisions */
                        if (is_array($this->qname_to_attr[$qname])) {
                                $this->qname_to_attr[$qname][] = $attr;
                        } else {
                                $this->qname_to_attr[$qname] = array($this->qname_to_attr[$qname], $attr);
                        }
                } else {
                        /* Fast branch (should be majority of time) */
                        $this->qname_to_attr[$qname] = $attr;
                }

                $this->lname_to_attr[$attr->ns.'|'.$attr->lname] = $attr;
                $this->index_to_attr[$this->lname_to_index[$attr->ns.'|'.$attr->lname]] = $attr;
        }

        private function _remove(Attr $a)
        {
                $key = $a->namespace.'|'.$a->lname;

                unset($this->lname_to_attr[$key]);
                $i = $this->lname_to_index[$key]);
                unset($this->lname_to_index[$key]);

                array_splice($this->index_to_attr, $i, 1);

                if (isset($this->qname_to_attr[$a->name])) {
                        if (is_array($this->qname_to_attr[$a->name])) {
                                $i = array_search($a, $this->qname_to_attr[$a->name]);
                                if ($i !== false) {
                                        array_splice($this->qname_to_attr[$a->name], $i, 1);
                                }
                        } else {
                                unset($this->qname_to_attr[$a->name]);
                        }
                }
        }

        public function length(): int
        {
                return count($this->_attrKeys);
        }

        public function item(int $index): ?Attr
        {
                if (!isset($this->element->_attrKeys[$index])) {
                        return NULL;
                }

                return $this->element->_attrsByLName[$this->element->_attrKeys[$index]];
        }

        /* MY EXTENSION */
        public function hasNamedItem(string $qname): boolean
        {
                /*
                 * Per HTML spec, we normalize qname before lookup,
                 * even though XML itself is case-sensitive.
                 */
                if (!ctype_lower($qname) && $this->element->isHTMLElement()) {
                        $qname = utils\toASCIILowerCase($qname);
                }

                return isset($this->qname_to_attr[$qname];
        }

        /* MY EXTENSION */
        public function hasNamedItemNS(?string $ns, string $lname): boolean
        {
                $ns = $ns ?? "";
                return isset($this->lname_to_attr["$ns|$lname"]);
        }

        public function getNamedItem(string $qname): ?Attr
        {
                /*
                 * Per HTML spec, we normalize qname before lookup,
                 * even though XML itself is case-sensitive.
                 */
                if (!ctype_lower($qname) && $this->element->isHTMLElement()) {
                        $qname = utils\toASCIILowerCase($qname);
                }

                if (!isset($this->qname_to_attr[$qname])) {
                        return NULL;
                }

                /*
                 * BEWARE: assignment will make a copy if the values
                 * are arrays.
                 */
                if (is_array($this->qname_to_attr[$qname])) {
                        return $this->qname_to_attr[$qname][0];
                } else {
                        return $this->qname_to_attr[$qname];
                }
        }

        public function getNamedItemNS(?string $ns, string $lname): ?Attr
        {
                $ns = $ns ?? "";
                $key = "$ns|$lname";

                return $this->lname_to_attr[$key] ?? NULL;
        }

        public function setNamedItem(Attr $attr): ?Attr
        {
                if ($attr->ownerElement !== NULL && $attr->ownerElement !== $this->element) {
                        throw DOMException("InUseAttributeError");
                }

                $oldAttr = $this->getNamedItem($attr->name);

                if ($oldAttr == $attr) {
                        return $attr;
                }

                if ($oldAttr !== NULL) {
                        $this->_replace($attr);
                } else {
                        $this->_append($attr);
                }

                return $oldAttr;
        }

        public function setNamedItemNS(Attr $attr): ?Attr
        {
                if ($attr->ownerElement !== NULL && $attr->ownerElement !== $this->element) {
                        throw DOMException("InUseAttributeError");
                }

                $oldAttr = $this->getNamedItemNS($attr->namespace, $attr->lname);

                if ($oldAttr == $attr) {
                        return $attr;
                }

                if ($oldAttr !== NULL) {
                        $this->_replace($attr);
                } else {
                        $this->_append($attr);
                }

                return $oldAttr;
        }


        /* NOTE: qname may be lowercase or normalized in various ways */
        public function removeNamedItem(string $qname): ?Attr
        {
                $attr = $this->getNamedItem($qname);
                if ($attr !== NULL) {
                        $this->_remove($attr);
                } else {
                        throw DOMException("NotFoundError");
                }
                return $attr;
        }

        /* NOTE: qname may be lowercase or normalized in various ways */
        public function removeNamedItemNS(?string $ns, string $lname): ?Attr
        {
                $attr = $this->getNamedItemNS($ns, $lname);
                if ($attr !== NULL) {
                        $this->_remove($attr);
                } else {
                        throw DOMException("NotFoundError");
                }
                return $attr;
        }
}

?>
