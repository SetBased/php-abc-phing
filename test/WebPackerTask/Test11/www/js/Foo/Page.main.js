requirejs.config({
  baseUrl: '/js',
  paths: {
    'jquery': 'jquery/jquery'
  },
  shim: {
    tinyMCE: {
      exports: 'tinyMCE',
      init: function () {
        'use strict';
        this.tinyMCE.DOM.events.domLoaded = true;
        return this.tinyMCE;
      }
    }
  }
});

require(['Foo/Page'], function (page) {
  page.init();
});

/* ID: Page.main.js */
