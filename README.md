PublishSubscribe
================

A simple and flexible publish-subscribe pattern implementation for PHP, Python, Node/JS, ActionScript(TODO)

Supports *nested* topics, *tagged* topics and *namespaced* topics.


[PublishSubscribe.js](https://raw.githubusercontent.com/foo123/PublishSubscribe/master/src/js/PublishSubscribe.js),  [PublishSubscribe.min.js](https://raw.githubusercontent.com/foo123/PublishSubscribe/master/src/js/PublishSubscribe.min.js)


**see also:**  

* [Contemplate](https://github.com/foo123/Contemplate) a light-weight template engine for Node/JS, PHP, Python, ActionScript
* [ModelView](https://github.com/foo123/modelview.js) a light-weight and flexible MVVM framework for JavaScript/HTML5
* [ModelView MVC jQueryUI Widgets](https://github.com/foo123/modelview-widgets) plug-n-play, state-full, full-MVC widgets for jQueryUI using modelview.js (e.g calendars, datepickers, colorpickers, tables/grids, etc..) (in progress)
* [Dromeo](https://github.com/foo123/Dromeo) a flexible, agnostic router for Node/JS, PHP, Python, ActionScript
* [Regex Analyzer/Composer](https://github.com/foo123/RegexAnalyzer) Regular Expression Analyzer and Composer for Node/JS, PHP, Python, ActionScript
* [Xpresion](https://github.com/foo123/Xpresion) a simple and flexible eXpression parser engine (with custom functions and variables support) for PHP, Python, Node/JS, ActionScript
* [Dialect](https://github.com/foo123/Dialect) a simple cross-platform SQL construction for PHP, Python, Node/JS, ActionScript (in progress)
* [Simulacra](https://github.com/foo123/Simulacra) a simulation, algebraic, probability and combinatorics PHP package for scientific computations
* [Asynchronous](https://github.com/foo123/asynchronous.js) a simple manager for async, linearised, parallelised, interleaved and sequential tasks for JavaScript


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
pb.pipeline( topic_with_tags_namespaces, data );

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
    console.log(evt.data['data']);
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

PublishSubscribe.VERSION = 0.4
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