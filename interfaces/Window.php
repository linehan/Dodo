<?php

use \domo\interfaces\DOMImplementation;
use \domo\interfaces\EventTarget;
use \domo\interfaces\Location;
use \domo\sloppy;
use \domo\utils;

require_once("interfaces/DOMImplementation.php");
require_once("interfaces/EventTarget.php");
require_once("interfaces/whatwg/Location.php");
//require_once("sloppy.php");
//require_once("utils.php");

class History {
        public function back() { return utils\nyi(); }
        public function forward() { return utils\nyi(); }
        public function go() { return utils\nyi(); }
        public function pushState() { return utils\nyi(); }
        public function replaceState() { return utils\nyi(); }
}

class Console {
        public function assert() { return utils\nyi(); }
        public function clear() { return utils\nyi(); }
        public function count() { return utils\nyi(); }
        public function countReset() { return utils\nyi(); }
        public function debug() { return utils\nyi(); }
        public function dir() { return utils\nyi(); }
        public function dirxml() { return utils\nyi(); }
        public function error() { return utils\nyi(); }
        public function group() { return utils\nyi(); }
        public function groupCollapsed() { return utils\nyi(); }
        public function groupEnd() { return utils\nyi(); }
        public function info() { return utils\nyi(); }
        public function log() { return utils\nyi(); }
        public function profile() { return utils\nyi(); }
        public function profileEnd() { return utils\nyi(); }
        public function table() { return utils\nyi(); }
        public function time() { return utils\nyi(); }
        public function timeEnd() { return utils\nyi(); }
        public function timeLog() { return utils\nyi(); }
        public function timeStamp() { return utils\nyi(); }
        public function trace() { return utils\nyi(); }
        public function warn() { return utils\nyi(); }
}

class Window extends EventTarget
{
        public $console = NULL;
        public $history = NULL;
        public $navigator = NULL;

        public
        function __construct(Document $doc=NULL)
        {
                if ($doc == NULL) {
                        $impl = new DOMImplementation(NULL);
                        $doc = $impl->createHTMLDocument("");
                }

                $this->document = $doc;
                $this->document->_scripting_enabled = true;
                $this->document->defaultView = $this;

                if (!$this->document->_address) {
                        $this->document->_address = "about:blank";
                }

                /* Instantiate sub-objects */
                $this->location = new Location($this, $this->document->_address);
                $this->console = new Console(); /* not implemented */
                $this->history = new History(); /* not implemented */
                $this->navigator = new NavigatorID();

                /* Self-referential properties; moved from prototype in port */
                $this->window = $this;
                $this->self = $this;
                $this->frames = $this;

                /* Self-referential properties for a top-level window */
                $this->parent = $this;
                $this->top = $this;

                /* We don't support any other windows for now */
                $this->length = 0;              // no frames
                $this->frameElement = NULL;     // not part of a frame
                $this->opener = NULL;           // not opened by another window
        }

        public function _run($code, $file)
        {
                /* Original JS: */
                /*
                 * This was used only for the testharness, I think we
                 * will have another way to do this here.
                 */
                /*
                if ($file) {
                        $code += '\n//@ sourceURL=' + $file;
                }
                with($this) eval($code);
                */
        }


        /*
         * The onload event handler.
         * TODO: Need to support a bunch of other event types, too, and have
         * them interoperate with document.body.
         */
        public function onload($handler=NULL)
        {
                if ($handler == NULL) {
                        /* From the EventTarget parent class */
                        return $this->_getEventHandler("load");
                } else {
                        return $this->_setEventHandler("load", $handler);
                }
        }

        /* TODO: This is a completely broken implementation */
        public function getComputedStyle($elt)
        {
                return $elt->style;
        }
}

/* TODO: Make this work with Window properly */
//utils.expose(require('./WindowTimers'), Window);
//utils.expose(require('./impl'), Window);
