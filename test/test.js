var PublishSubscribe = require('../src/js/PublishSubscribe.js');

console.log('PublishSubscribe.VERSION = ' + PublishSubscribe.VERSION);

function _log(evt)
{
    console.log({topic: evt.topic, originalTopic: evt.originalTopic, tags: evt.tags, namespaces: evt.namespaces, timestamp: evt.timestamp});
    console.log(evt.data.data);
    //console.log(evt.non_local);
}

var handler1 = function(evt){
    console.log('Handler1');
    _log(evt);
    evt.next();
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
    evt.next();
};
var handler3 = function(evt){
    console.log('Handler3');
    _log(evt);
    evt.next();
};
var handler4 = function(evt){
    console.log('Handler4');
    _log(evt);
    evt.next();
};

var pb = new PublishSubscribe( )

    .on('Topic1/SubTopic11#Tag1#Tag2', handler1)
    .on1('Topic1/SubTopic11#Tag1#Tag2@NS1', handler2)
    .on('Topic1/SubTopic11#Tag1#Tag2@NS1@NS2', handler3)
    .off('@NS1@NS2')
    .pipeline('Topic1/SubTopic11#Tag2#Tag1', {key1: 'value1'})
    .pipeline('Topic1/SubTopic11#Tag2#Tag1@NS1', {key1: 'value1'})
;
