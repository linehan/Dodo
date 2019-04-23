<?php
/*
 * PORT NOTES
 *
 * Event.js                             Event.php
 *
 * module.exports = Event               class Event
 *
 * Event.EVENT_CAPTURING_PHASE          define("EVENT_CAPTURING_PHASE")
 * Event.EVENT_AT_TARGET                define("EVENT_AT_TARGET")
 * Event.EVENT_BUBBLING_PHASE           define("EVENT_BUBBLING_PHASE")
 *
 * Event._propagationStopped            private $_propagationStopped
 * Event._immediatePropagationStopped   private $_immediatePropagationStopped
 * Event._initialized                   private $_initialized
 * Event._dispatching                   private $_dispatching
 *
 * this.timeStamp = Date.now()          $this->timeStamp = time()
 */

/*
 * PORT: Originally Event.EVENT_CAPTURING_PHASE, etc.
 */
define("EVENT_CAPTURING_PHASE", 1);
define("EVENT_AT_TARGET", 2);
define("EVENT_BUBBLING_PHASE", 3);

class Event
{
        public $type = '';
        public $target = NULL;
        public $currentTarget = NULL;
        public $eventPhase = EVENT_AT_TARGET;
        public $bubbles = false;
        public $cancelable = false;
        public $isTrusted = false;
        public $defaultPrevented = false;
        public $timeStamp = NULL; /* PORT: yes, orig. was also 'timeStamp' */

        private $_propagationStopped = false;
        private $_immediatePropagationStopped = false;
        private $_initialized = true;
        private $_dispatching = false;

        public
        function __construct(string $type, array $properties)
        {
                /*
                 * PORT: Replaces this.timeStamp = Date.now()
                 * TODO: This is Unix time. Is that in the W3C spec?
                 */
                $this->timeStamp = time();

                if ($type) {
                        $this->type = $type;
                }

                if ($dictionary) {
                        foreach ($properties as $p) {
                                $this[$p] = $properties[$p];
                        }
                }
        }

        public
        function stopPropagation()
        {
                $this->_propagationStopped = true;
        }

        public
        function stopImmediatePropagation()
        {
                $this->_propagationStopped = true;
                $this->_immediatePropagationStopped = true;
        }

        public
        function preventDefault()
        {
                if ($this->cancelable) {
                        $this->defaultPrevented = true;
                }
        }

        public
        function initEvent(string $type, boolean $bubbles, boolean $cancelable)
        {
                $this->_initialized = true;

                if ($this->_dispatching) {
                        return;
                }

                $this->_propagationStopped = false;
                $this->_immediatePropagationStopped = false;
                $this->defaultPrevented = false;
                $this->isTrusted = false;

                $this->target = NULL;
                $this->type = $type;
                $this->bubbles = $bubbles;
                $this->cancelable = $cancelable;
        }
}

?>
