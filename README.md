PublishSubscribe
================

A simple and flexible publish-subscribe pattern implementation for PHP, Python, Node/XPCOM/JS


![PublishSubscribe](/publishsubscribe.jpg)


Supports *nested* topics, *tagged* topics and *namespaced* topics.


* `PublishSubscribe` is also a `XPCOM JavaScript Component` (Firefox) (e.g to be used in firefox browser addons/plugins)


[PublishSubscribe.js](https://raw.githubusercontent.com/foo123/PublishSubscribe/master/src/js/PublishSubscribe.js),  [PublishSubscribe.min.js](https://raw.githubusercontent.com/foo123/PublishSubscribe/master/src/js/PublishSubscribe.min.js)


**see also:**

* [ModelView](https://github.com/foo123/modelview.js) a simple, fast, powerful and flexible MVVM framework for JavaScript
* [tico](https://github.com/foo123/tico) a tiny, super-simple MVC framework for PHP
* [LoginManager](https://github.com/foo123/LoginManager) a simple, barebones agnostic login manager for PHP, JavaScript, Python
* [SimpleCaptcha](https://github.com/foo123/simple-captcha) a simple, image-based, mathematical captcha with increasing levels of difficulty for PHP, JavaScript, Python
* [Dromeo](https://github.com/foo123/Dromeo) a flexible, and powerful agnostic router for PHP, JavaScript, Python
* [PublishSubscribe](https://github.com/foo123/PublishSubscribe) a simple and flexible publish-subscribe pattern implementation for PHP, JavaScript, Python
* [Importer](https://github.com/foo123/Importer) simple class &amp; dependency manager and loader for PHP, JavaScript, Python
* [Contemplate](https://github.com/foo123/Contemplate) a fast and versatile isomorphic template engine for PHP, JavaScript, Python
* [HtmlWidget](https://github.com/foo123/HtmlWidget) html widgets, made as simple as possible, both client and server, both desktop and mobile, can be used as (template) plugins and/or standalone for PHP, JavaScript, Python (can be used as [plugins for Contemplate](https://github.com/foo123/Contemplate/blob/master/src/js/plugins/plugins.txt))
* [Paginator](https://github.com/foo123/Paginator)  simple and flexible pagination controls generator for PHP, JavaScript, Python
* [Formal](https://github.com/foo123/Formal) a simple and versatile (Form) Data validation framework based on Rules for PHP, JavaScript, Python
* [Dialect](https://github.com/foo123/Dialect) a cross-vendor &amp; cross-platform SQL Query Builder, based on [GrammarTemplate](https://github.com/foo123/GrammarTemplate), for PHP, JavaScript, Python
* [DialectORM](https://github.com/foo123/DialectORM) an Object-Relational-Mapper (ORM) and Object-Document-Mapper (ODM), based on [Dialect](https://github.com/foo123/Dialect), for PHP, JavaScript, Python
* [Unicache](https://github.com/foo123/Unicache) a simple and flexible agnostic caching framework, supporting various platforms, for PHP, JavaScript, Python
* [Xpresion](https://github.com/foo123/Xpresion) a simple and flexible eXpression parser engine (with custom functions and variables support), based on [GrammarTemplate](https://github.com/foo123/GrammarTemplate), for PHP, JavaScript, Python
* [Regex Analyzer/Composer](https://github.com/foo123/RegexAnalyzer) Regular Expression Analyzer and Composer for PHP, JavaScript, Python


**Topic/Event structure:**

```text
[Topic1[/SubTopic11/SubTopic111 ...]][#Tag1[#Tag2 ...]][@NAMESPACE1[@NAMESPACE2 ...]]
```

* A topic can be **nested** with one or more levels, all matching levels will be notified (in order of specific to general)
* A topic can (also) be **tagged** with one or more tags, only matching levels whose registered tags are matched will be notified
* A topic can (also) be **namespaced** with one or more namespaces, all matching levels will be notified if no namespace given when event triggered, else only the levels whose namespace(s) are matched (this is similar to tags above)
* All/Any of the above can be used simultaneously, at least one topic OR tag OR namespace should be given for an event to be triggered

Namespaces work similarly to (for example) jQuery namespaces (so handlers can be un-binded based on namespaces etc..).

The difference between tags and namespaces is that when just a topic is triggered (without tags and namespaces), 
handlers which match the topic **will** be called regardless if they have namespaces or not, 
while handlers that match the topic but also have tags **will not** be called.

All topic separators (i.e "/", "#", "@") are configurable per instance.

During the publishing process, an event can be stopped and/or cancell the bubble propagation.



**Methods (javascript)**

```javascript
var pb = new PublishSubscribe( );

// set topic/tag/namespace separators for this pb instance
// defaults are:
// Topic separator = "/"
// Tag separator = "#"
// Namespace separator = "@"
pb.setSeparators(["/", "#", "@"]);

// add/subscribe a handler for a topic with (optional) tags and (optional) namespaces
pb.on( topic_with_tags_namespaces, handlerFunc );

// add/subscribe a handler only once for a topic with (optional) tags and (optional) namespaces
// handler automatically is unsubscribed after it is called once
pb.one( topic_with_tags_namespaces, handlerFunc );

// add/subscribe a handler on top (first) for a topic with (optional) tags and (optional) namespaces
pb.on1( topic_with_tags_namespaces, handlerFunc );

// add/subscribe a handler only once on top (first) for a topic with (optional) tags and (optional) namespaces
// handler automatically is unsubscribed after it is called once
pb.one1( topic_with_tags_namespaces, handlerFunc );

// remove/unsubscribe a specific handler or all handlers matching topic with (optional) tags and (optional) namespaces
pb.off( topic_with_tags_namespaces [, handlerFunc=null ] );

// trigger/publish a topic with (optional) tags and (optional) namespaces and pass any data as well
pb.trigger( topic_with_tags_namespaces, data );

// pipeline allows to call subscribers (of given topic/message) asynchronously via a pipeline
// each subscriber calls next subscriber via the (passed) event's .next() method
// pipeline can be aborted via the (passed) event's .abort() method
// optional finish_callback will be called when the pipeline finishes the chain or event is aborted
pb.pipeline( topic_with_tags_namespaces, data [, abort_callback [, finish_callback]] );

// dispose PublishSubscribe instance
pb.disposePubSub( );

```


**example (javascript)**

```javascript

var PublishSubscribe = require('../src/js/PublishSubscribe.js');

console.log('PublishSubscribe.VERSION = ' + PublishSubscribe.VERSION);

function _log(evt)
{
    console.log({topic: evt.topic, originalTopic: evt.originalTopic, tags: evt.tags, namespaces: evt.namespaces, timestamp: evt.timestamp});
    console.log(evt.data);
}

var handler1 = function(evt){
    console.log('Handler1');
    _log(evt);
    // event abort
    //evt.abort( );
    // stop bubble propagation
    //evt.propagate( false );
    // stop propagation on same event
    //evt.stop( );
    //return false;
};
var handler2 = function(evt){
    console.log('Handler2');
    _log(evt);
};
var handler3 = function(evt){
    console.log('Handler3');
    _log(evt);
};
var handler4 = function(evt){
    console.log('Handler4');
    _log(evt);
};

var pb = new PublishSubscribe( )

    .on('Topic1/SubTopic11#Tag1#Tag2', handler1)
    .on1('Topic1/SubTopic11#Tag1#Tag2@NS1', handler2)
    .on('Topic1/SubTopic11#Tag1#Tag2@NS1@NS2', handler3)
    .off('@NS1@NS2')
    .trigger('Topic1/SubTopic11#Tag2#Tag1', {key1: 'value1'})
    .trigger('Topic1/SubTopic11#Tag2#Tag1@NS1', {key1: 'value1'})
;

```


**output**
```text

PublishSubscribe.VERSION = 1.0.0
Handler2
{ topic: [ 'Topic1', 'SubTopic11' ],
  originalTopic: [ 'Topic1', 'SubTopic11' ],
  tags: [ 'Tag1', 'Tag2' ],
  namespaces: [],
  timestamp: 1413370469838 }
{ key1: 'value1' }
Handler1
{ topic: [ 'Topic1', 'SubTopic11' ],
  originalTopic: [ 'Topic1', 'SubTopic11' ],
  tags: [ 'Tag1', 'Tag2' ],
  namespaces: [],
  timestamp: 1413370469838 }
{ key1: 'value1' }
Handler2
{ topic: [ 'Topic1', 'SubTopic11' ],
  originalTopic: [ 'Topic1', 'SubTopic11' ],
  tags: [ 'Tag1', 'Tag2' ],
  namespaces: [ 'NS1' ],
  timestamp: 1413370469840 }
{ key1: 'value1' }

```