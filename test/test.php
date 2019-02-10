<?php
require('../src/php/PublishSubscribe.php');

echo('PublishSubscribe.VERSION = ' . PublishSubscribe::VERSION . PHP_EOL);

function _log(&$evt)
{
    echo(print_r(array('topic'=>$evt->topic, 'originalTopic'=>$evt->originalTopic, 'tags'=>$evt->tags, 'namespaces'=>$evt->namespaces, 'timestamp'=>$evt->timestamp), true). PHP_EOL);
    echo(print_r($evt->data, true). PHP_EOL);
}

function handler1($evt)
{
    echo('Handler1' . PHP_EOL);
    _log($evt);
    $evt->next();
    // event abort
    //$evt->abort( );
    // stop bubble propagation
    //$evt->propagate( false );
    // stop propagation on same event
    //$evt->stop( );
    //return false;
}
function handler2($evt)
{
    echo('Handler2' . PHP_EOL);
    _log($evt);
    $evt->next();
}
function handler3($evt)
{
    echo('Handler3' . PHP_EOL);
    _log($evt);
    $evt->next();
}
function handler4($evt)
{
    echo('Handler4' . PHP_EOL);
    _log($evt);
    $evt->next();
}

$pb = new PublishSubscribe( );
$pb
    ->on('Topic1/SubTopic11#Tag1#Tag2', 'handler1')
    ->on1('Topic1/SubTopic11#Tag1#Tag2@NS1', 'handler2')
    ->on('Topic1/SubTopic11#Tag1#Tag2@NS1@NS2', 'handler3')
    ->off('@NS1@NS2')
    ->pipeline('Topic1/SubTopic11#Tag2#Tag1', array('key1'=> 'value1'))
    ->pipeline('Topic1/SubTopic11#Tag2#Tag1@NS1', array('key1'=> 'value1'))
;
