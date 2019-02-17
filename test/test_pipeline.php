<?php

require('../src/php/PublishSubscribe.php');

echo('PublishSubscribe.VERSION = ' . PublishSubscribe::VERSION . PHP_EOL);

$pbs = new PublishSubscribe( );
$pbs->on('topic', function($evt){
	echo('Sync Handler 1 on topic'.PHP_EOL);
    $evt->next();
});
$pbs->on('topic', function($evt){
	echo('Sync Handler 2 on topic'.PHP_EOL);
    $evt->next();
});
$pbs->on('topic/subtopic', function($evt){
	echo('Sync Handler 1 on topic/subtopic'.PHP_EOL);
    $evt->next();
});
$pbs->on('topic/subtopic', function($evt){
	echo('Sync Handler 2 on topic/subtopic'.PHP_EOL);
    $evt->next();
});

$pbs->pipeline('topic/subtopic', array(), null, function($evt){
	echo('Sync pipeline finished'.PHP_EOL);
});
