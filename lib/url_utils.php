<?php

/*
 * TODO: PORT NOTE: Uh, even in the original, this function isn't actually
 * failing if two things aren't equal. I guess we want to test that
 * certain parts are equal?
 *
 * I also dont' see why we need to be so damn weird. This function has problems.
 */
function assertURIEquals($assertID, $scheme, $path, $host, $file, $name, $query, $fragment=NULL, $isAbsolute, $actual)
{
        /*
         * URI must be non-NULL
         */
        assertNotNull($assertID, $actual);

        /*
         * Get all the parts
         */
        $uri = parse_url($actual);

        /*
         * Test all the parts
         */
        if ($fragment != NULL) {
                assertEquals($assertID, $fragment, $uri["fragment"]);
        }
        if ($query != NULL) {
                assertEquals($assertID, $query, $uri["query"]);
        }
        if ($scheme != NULL) {
                assertEquals($assertID, $scheme, $uri["scheme"]);
        }
        if ($path != NULL) {
                assertEquals($assertID, $path, $uri["path"]);
        }
        if ($host != NULL) {
                assertEquals($assertID, $host, $uri["host"]);
        }

        /*
         * If there's a file path, get its parts
         */
        if ($uri["path"] != NULL) {
                $path = pathinfo($uri["path"]);
        } else {
                $path = NULL;
        }

        if ($file != NULL && $path != NULL) {
                assertEquals($assertID, $file, $path["basename"]);
        }
        if ($name != NULL && $path != NULL) {
                assertEquals($assertID, $name, $path["filename"]);
        }

        if ($isAbsolute != NULL && $path != NULL) {
                assertEquals($assertID, $isAbsolute, substr($uri["path"], 0, 1) == "/");
        }
}

abstract class URLUtils
{
        abstract function href(string $val=NULL);

        private function _url()
        {
                /*
                 * XXX: this should do the "Reinitialize url" steps,
                 * and "null" should be a valid return value.
                 */
                return new URL($this->href());
        }

        public function protocol(string $val=NULL)
        {
                if ($val == NULL) {
                        /* GET */
                        $url = $this->_url();
                        if ($url && $url->scheme) {
                                return $url->scheme . ":";
                        } else {
                                return ":";
                        }
                } else {
                        /* SET */
                        $out = $this->href();
                        $url = new URL($out);
                        if ($url->isAbsolute()) {
                                $val = $val


                }
        }
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);
      if (url.isAbsolute()) {
        v = v.replace(/:+$/, "");
        v = v.replace(/[^-+\.a-zA-Z0-9]/g, URL.percentEncode);
        if (v.length > 0) {
          url.scheme = v;
          output = url.toString();
        }
      }
      this.href = output;
    },
  },

  host: {
    get: function() {
      var url = this._url;
      if (url.isAbsolute() && url.isAuthorityBased())
        return url.host + (url.port ? (":" + url.port) : "");
      else
        return "";
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);
      if (url.isAbsolute() && url.isAuthorityBased()) {
        v = v.replace(/[^-+\._~!$&'()*,;:=a-zA-Z0-9]/g, URL.percentEncode);
        if (v.length > 0) {
          url.host = v;
          delete url.port;
          output = url.toString();
        }
      }
      this.href = output;
    },
  },

  hostname: {
    get: function() {
      var url = this._url;
      if (url.isAbsolute() && url.isAuthorityBased())
        return url.host;
      else
        return "";
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);
      if (url.isAbsolute() && url.isAuthorityBased()) {
        v = v.replace(/^\/+/, "");
        v = v.replace(/[^-+\._~!$&'()*,;:=a-zA-Z0-9]/g, URL.percentEncode);
        if (v.length > 0) {
          url.host = v;
          output = url.toString();
        }
      }
      this.href = output;
    },
  },

  port: {
    get: function() {
      var url = this._url;
      if (url.isAbsolute() && url.isAuthorityBased() && url.port!==undefined)
        return url.port;
      else
        return "";
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);
      if (url.isAbsolute() && url.isAuthorityBased()) {
        v = '' + v;
        v = v.replace(/[^0-9].*$/, "");
        v = v.replace(/^0+/, "");
        if (v.length === 0) v = "0";
        if (parseInt(v, 10) <= 65535) {
          url.port = v;
          output = url.toString();
        }
      }
      this.href = output;
    },
  },

  pathname: {
    get: function() {
      var url = this._url;
      if (url.isAbsolute() && url.isHierarchical())
        return url.path;
      else
        return "";
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);
      if (url.isAbsolute() && url.isHierarchical()) {
        if (v.charAt(0) !== "/")
          v = "/" + v;
        v = v.replace(/[^-+\._~!$&'()*,;:=@\/a-zA-Z0-9]/g, URL.percentEncode);
        url.path = v;
        output = url.toString();
      }
      this.href = output;
    },
  },

  search: {
    get: function() {
      var url = this._url;
      if (url.isAbsolute() && url.isHierarchical() && url.query!==undefined)
        return "?" + url.query;
      else
        return "";
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);
      if (url.isAbsolute() && url.isHierarchical()) {
        if (v.charAt(0) === "?") v = v.substring(1);
        v = v.replace(/[^-+\._~!$&'()*,;:=@\/?a-zA-Z0-9]/g, URL.percentEncode);
        url.query = v;
        output = url.toString();
      }
      this.href = output;
    },
  },

  hash: {
    get: function() {
      var url = this._url;
      if (url == null || url.fragment == null || url.fragment === '') {
        return "";
      } else {
        return "#" + url.fragment;
      }
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);

      if (v.charAt(0) === "#") v = v.substring(1);
      v = v.replace(/[^-+\._~!$&'()*,;:=@\/?a-zA-Z0-9]/g, URL.percentEncode);
      url.fragment = v;
      output = url.toString();

      this.href = output;
    },
  },

  username: {
    get: function() {
      var url = this._url;
      return url.username || '';
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);
      if (url.isAbsolute()) {
        v = v.replace(/[\x00-\x1F\x7F-\uFFFF "#<>?`\/@\\:]/g, URL.percentEncode);
        url.username = v;
        output = url.toString();
      }
      this.href = output;
    },
  },

  password: {
    get: function() {
      var url = this._url;
      return url.password || '';
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);
      if (url.isAbsolute()) {
        if (v==='') {
          url.password = null;
        } else {
          v = v.replace(/[\x00-\x1F\x7F-\uFFFF "#<>?`\/@\\]/g, URL.percentEncode);
          url.password = v;
        }
        output = url.toString();
      }
      this.href = output;
    },
  },

  origin: { get: function() {
    var url = this._url;
    if (url == null) { return ''; }
    var originForPort = function(defaultPort) {
      var origin = [url.scheme, url.host, +url.port || defaultPort];
      // XXX should be "unicode serialization"
      return origin[0] + '://' + origin[1] +
        (origin[2] === defaultPort ? '' : (':' + origin[2]));
    };
    switch (url.scheme) {
    case 'ftp':
      return originForPort(21);
    case 'gopher':
      return originForPort(70);
    case 'http':
    case 'ws':
      return originForPort(80);
    case 'https':
    case 'wss':
      return originForPort(443);
    default:
      // this is what chrome does
      return url.scheme + '://';
    }
  } },

  /*
  searchParams: {
    get: function() {
      var url = this._url;
      // XXX
    },
    set: function(v) {
      var output = this.href;
      var url = new URL(output);
      // XXX
      this.href = output;
    },
  },
  */
});

?>
