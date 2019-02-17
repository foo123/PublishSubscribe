"use strict";
var PublishSubscribe = require('../src/js/PublishSubscribe.js'), echo = console.log;

echo('PublishSubscribe.VERSION = ' + PublishSubscribe.VERSION);

var pbs = new PublishSubscribe( );
pbs.on('topic', function(evt){
	echo('Sync Handler 1 on topic');
    evt.next();
});
pbs.on('topic', function(evt){
	echo('Sync Handler 2 on topic');
    evt.next();
});
pbs.on('topic/subtopic', function(evt){
	echo('Sync Handler 1 on topic/subtopic');
    evt.next();
});
pbs.on('topic/subtopic', function(evt){
	echo('Sync Handler 2 on topic/subtopic');
    evt.next();
});

pbs.pipeline('topic/subtopic', {}, null, function(evt){
	echo('Sync pipeline finished');
});

var pba = new PublishSubscribe( );
pba.on('topic', function(evt){
	echo('Async Handler 1 on topic');
    setTimeout(function(){evt.next();}, 100);
});
pba.on('topic', function(evt){
	echo('Async Handler 2 on topic');
    setTimeout(function(){evt.next();}, 100);
});
pba.on('topic/subtopic', function(evt){
	echo('Async Handler 1 on topic/subtopic');
    setTimeout(function(){evt.next();}, 100);
});
pba.on('topic/subtopic', function(evt){
	echo('Async Handler 2 on topic/subtopic');
    setTimeout(function(){evt.next();}, 100);
});

pba.pipeline('topic/subtopic', {}, null, function(evt){
	echo('Async pipeline finished');
});