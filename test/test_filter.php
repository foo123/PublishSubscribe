<?php
require('../src/php/PublishSubscribe.php');

echo('PublishSubscribe.VERSION = ' . PublishSubscribe::VERSION . PHP_EOL);

function filter($pb, $hook, $value=null, $args=null)
{
	$data = new stdClass;
	if ( !empty($args) )
	{
		foreach((array)$args as $k=>$v)
			$data->{$k} = $v;
	}
	$data->value = $value;
	$pb->trigger($hook, $data);
	return $data->value;
}

function filter_pipeline($pb, $hook, $value=null, $args=null)
{
	$data = new stdClass;
	if ( !empty($args) )
	{
		foreach((array)$args as $k=>$v)
			$data->{$k} = $v;
	}
	$data->value = $value;
	$pb->pipeline($hook, $data, null, function($evt){
        echo($evt->data->value . PHP_EOL);
    });
}

$pb = new PublishSubscribe( );
$pb->on('filter_value', function($evt){
	print_r($evt->data);
	$evt->data->value++;
});
$pb->on('filter_value_pipeline', function($evt){
	print_r($evt->data);
	$evt->data->value++;
    $evt->next();
});
$pb->on('filter_value_pipeline', function($evt){
	print_r($evt->data);
	$evt->data->value++;
    $evt->next();
});
$pb->on('filter_value_pipeline', function($evt){
	print_r($evt->data);
	$evt->data->value++;
    $evt->next();
});

echo filter($pb, 'filter_value', 2) . PHP_EOL;
filter_pipeline($pb, 'filter_value_pipeline', 2);