var PublishSubscribe = require('../src/js/PublishSubscribe.js');

console.log('PublishSubscribe.VERSION = ' + PublishSubscribe.VERSION);

function _log(evt, data)
{
    console.log({topic: evt.topic, originalTopic: evt.originalTopic, tags: evt.tags, namespaces: evt.namespaces});
    console.log(data);
}

var handler1 = function(evt, data){
    console.log('Handler1');
    _log(evt, data);
    // stop bubble propagation
    //evt.propagate( false );
    // stop propagation on same event
    //evt.stop( );
    //return false;
};
var handler2 = function(evt, data){
    console.log('Handler2');
    _log(evt, data);
};
var handler3 = function(evt, data){
    console.log('Handler3');
    _log(evt, data);
};
var handler4 = function(evt, data){
    console.log('Handler4');
    _log(evt, data);
};

var pb = new PublishSubscribe( )

    .on('Topic1/SubTopic11#Tag1#Tag2', handler1)
    .on('Topic1/SubTopic11#Tag1#Tag2@NS1', handler2)
    .on('Topic1/SubTopic11#Tag1#Tag2@NS1@NS2', handler3)
    .off('@NS1@NS2')
    .trigger('Topic1/SubTopic11#Tag2#Tag1', {key1: 'value1'})
    .trigger('Topic1/SubTopic11#Tag2#Tag1@NS1', {key1: 'value1'})
;
