"use strict";
var PublishSubscribe = require('../src/js/PublishSubscribe.js'), echo = console.log;

echo('PublishSubscribe.VERSION = ' + PublishSubscribe.VERSION);

function filter(pb, hook, value, args)
{
	var data = {};
	if ( args )
	{
		for(var k in args)
			data[k] = args[k];
	}
	data.value = value;
    return new Promise(function(resolve,reject){
        pb.pipeline(hook, data, null, function(evt){
            echo('FINISH');
            resolve(evt.data.value);
        });
    });
}

var pb = new PublishSubscribe( );
pb.on('filter_value', function(evt){
	echo(evt.data);
	evt.data.value++;
    setTimeout(function(){evt.next();}, 100);
});
pb.on('filter_value', function(evt){
	echo(evt.data);
	evt.data.value++;
    evt.next();
});
pb.on('filter_value', function(evt){
	echo(evt.data);
	evt.data.value++;
    evt.next();
});

filter(pb, 'filter_value', 2).then(function(value){echo(value);});