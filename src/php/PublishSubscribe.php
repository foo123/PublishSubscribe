<?php
/**
*  PublishSubscribe
*  A simple publish-subscribe implementation for PHP, Python, Node/JS
*
*  @version: 1.1.0
*  https://github.com/foo123/PublishSubscribe
*
**/
if ( !class_exists('PublishSubscribe', false) )
{

interface PublishSubscribeInterface
{
    public function on($message, $callback);
    public function one($message, $callback);
    public function off($message, $callback=null);
    public function trigger($message, $data=array()/*, $delay=0*/);
    public function pipeline($message, $data=array(), $abort=null/*, $delay=0*/);
}

class PublishSubscribeData
{
    public function __construct($props=null)
    {
        if ( $props )
        {
            foreach ($props as $k=>$v)
            {
                $this->{$k} = $v;
            }
        }
    }
    
    public function __destruct()
    {
        $this->dispose();
    }
    
    public function dispose($props=null)
    {
        if ( $props )
        {
            foreach ($props as $k)
            {
                $this->{$k} = null;
            }
        }
        return $this;
    }
}

class PublishSubscribeEvent
{
    public $target = null;
    public $topic = null;
    public $originalTopic = null;
    public $tags = null;
    public $namespaces = null;
    public $data = null;
    public $timestamp = 0;
    protected $_propagates = true;
    protected $_stopped = false;
    protected $_aborted = false;
    public $is_pipelined = false;
    protected $_next = null;
    
    public function __construct(&$target=null, $topic=null, $original=null, $tags=null, $namespaces=null)
    {
        $this->target = $target;
        if ( $topic ) $this->topic = (array)$topic;
        else  $this->topic = array();
        if ( $original ) $this->originalTopic = (array)$original;
        else $this->originalTopic = array();
        if ( $tags ) $this->tags = (array)$tags;
        else $this->tags = array();
        if ( $namespaces )  $this->namespaces = (array)$namespaces;
        else  $this->namespaces = array();
        $this->data = null;//new PublishSubscribeData();
        $this->timestamp = round(microtime(true) * 1000);
        $this->_propagates = true;
        $this->_stopped = false;
        $this->_aborted = false;
    }
    
    public function __destruct()
    {
        $this->dispose();
    }
    
    public function dispose( ) 
    {
        $this->target = null;
        $this->topic = null;
        $this->originalTopic = null;
        $this->tags = null;
        $this->namespaces = null;
        if ($this->data instanceof PublishSubscribeData) $this->data->dispose();
        $this->data = null;
        $this->timestamp = null;
        $this->is_pipelined = false;
        $this->_propagates = false;
        $this->_stopped = true;
        $this->_aborted = false;
        $this->_next = null;
        return $this;
    }
    
    public function next( ) 
    {
        if ( is_callable($this->_next) ) call_user_func($this->_next, $this);
        return $this;
    }
    
    public function pipeline( $next=null ) 
    {
        if ( is_callable($next) )
        {
            $this->_next = $next;
            $this->is_pipelined = true;
        }
        else
        {
            $this->_next = null;
            $this->is_pipelined = false;
        }
        return $this;
    }
        
    public function propagate( $enable=true ) 
    {
        $this->_propagates = (bool)$enable;
        return $this;
    }
    
    public function stop( $enable=true ) 
    {
        $this->_stopped = (bool)$enable;
        return $this;
    }
    
    public function abort( $enable=true ) 
    {
        $this->_aborted = (bool)$enable;
        return $this;
    }
    
    public function propagates( ) 
    {
        return $this->_propagates;
    }
    
    public function aborted( ) 
    {
        return $this->_aborted;
    }
    
    public function stopped( ) 
    {
        return $this->_stopped;
    }
}

class PublishSubscribe implements PublishSubscribeInterface
{
    const VERSION = "1.1.0";
    const TOPIC_SEP = '/';
    const TAG_SEP = '#';
    const NS_SEP = '@';
    const OTOPIC_SEP = '/';
    const OTAG_SEP = '#';
    const ONS_SEP = '@';
    
    protected static function get_pubsub( ) 
    { 
        return array( 'notopics'=> array( 'notags'=> array('namespaces'=> array(), 'list'=> array(), 'oneOffs'=> 0), 'tags'=> array() ), 'topics'=> array() );
    }
    
    protected static function parse_topic( $seps, $topic )
    {
        $nspos = strpos( $topic, $seps[2] );
        $tagspos = strpos( $topic, $seps[1] );
        if ( false !== $nspos )
        {
            $namespaces = array_filter( explode( $seps[2], substr($topic, $nspos) ), 'strlen' );
            sort( $namespaces );
            $topic = substr( $topic, 0, $nspos );
        }
        else
        {
            $namespaces = array();
        }
        if ( false !== $tagspos )
        {
            $tags = array_filter( explode( $seps[1], substr($topic, $tagspos) ), 'strlen' );
            sort( $tags );
            $topic = substr( $topic, 0, $tagspos );
        }
        else
        {
            $tags = array();
        }
        $topic = array_filter( explode( $seps[0], $topic ), 'strlen' );
        return array($topic, $tags, $namespaces);
    }
    
    protected static function get_all_topics( $seps, $topic ) 
    { 
        $topics = array();
        $tags = array(); 
        //$namespaces = array();
        
        $topic = self::parse_topic( $seps, $topic );
        //$tns = $topic[2];
        $namespaces = $topic[2];
        $ttags = $topic[1];
        $topic = $topic[0];
        
        $l = count($topic);
        while ( $l )
        {
            $topics[] = implode( self::OTOPIC_SEP, $topic );
            array_pop($topic);
            $l--;
        }
        
        $l = count($ttags);
        if ( $l > 1 )
        {
            $combinations = (1 << $l);
            for ($i=$combinations-1; $i>=1; $i--)
            {
                $tmp = array();
                for ($j=0; $j<$l; $j++)
                {
                    $jj = (1 << $j);
                    if ( ($i !== $jj) && ($i & $jj) )
                        $tmp[] = $ttags[ $j ];
                }
                if ( !empty($tmp) )
                    $tags[] = implode( self::OTAG_SEP, $tmp );
            }
            $tags = array_merge( $tags, $ttags );
        }
        else if ( $l ) $tags[] = $ttags[ 0 ];
        
        /*$l = count($tns);
        if ( $l > 1 )
        {
            $combinations = (1 << $l);
            for ($i=$combinations-1; $i>=1; $i--)
            {
                $tmp = array();
                for ($j=0; $j<$l; $j++)
                {
                    $jj = (1 << $j);
                    if ( ($i !== $jj) && ($i & $jj) )
                        $tmp[] = $tns[ $j ];
                }
                if ( !empty($tmp) )
                    $namespaces[] = implode( self::ONS_SEP, $tmp );
            }
            $namespaces = array_merge( $namespaces, $tns );
        }
        else if ( $l && strlen($tns[0]) ) $namespaces[] = $tns[ 0 ];*/
        
        return array(count($topics) ? $topics[0] : '', $topics, $tags, $namespaces);
    }
    
    protected static function update_namespaces( &$pbns, &$namespaces, $nl=0 )
    {
        foreach ($namespaces as $ns)
        {
            $ns = 'ns_' . $ns;
            if ( !isset($pbns[$ns]) )
            {
                $pbns[ $ns ] = 1;
            }
            else
            {
                $pbns[ $ns ]++;
            }
        }
    }
    
    protected static function remove_namespaces( &$pbns, &$namespaces, $nl=0 )
    {
        foreach ($namespaces as $ns)
        {
            $ns = 'ns_' . $ns;
            if ( isset($pbns[$ns]) )
            {
                $pbns[ $ns ]--;
                if ( $pbns[ $ns ] <=0 )
                    unset($pbns[ $ns ]);
            }
        }
    }
    
    protected static function match_namespace( &$pbns, &$namespaces, $nl=0 )
    {
        foreach ($namespaces as $ns)
        {
            $ns = 'ns_' . $ns;
            if ( !isset($pbns[ $ns ]) || (0 >= $pbns[ $ns ]) ) return false;
        }
        return true;
    }
    
    protected static function check_is_subscribed( &$pubsub, &$subscribedTopics, $topic, $tag, &$namespaces, $nl )
    {
        $_topic = $topic ? ('tp_'.$topic) : false;
        $_tag = $tag ? ('tg_'.$tag) : false;
        
        if ( $_topic && isset($pubsub['topics'][ $_topic ]) )
        {
            if ( $_tag && isset($pubsub['topics'][ $_topic ]['tags'][ $_tag ]) )
            {
                if ( !empty($pubsub['topics'][ $_topic ]['tags'][ $_tag ]['list']) &&
                    ($nl <= 0 || self::match_namespace( $pubsub['topics'][ $_topic ]['tags'][ $_tag ]['namespaces'], $namespaces, $nl )) )
                {
                    array_push($subscribedTopics, array($topic, $tag, $nl > 0, &$pubsub['topics'][ $_topic ]['tags'][ $_tag ]));
                    return true;
                }
            }
            else
            {
                if ( !empty($pubsub['topics'][ $_topic ]['notags']['list']) &&
                    ($nl <= 0 || self::match_namespace( $pubsub['topics'][ $_topic ]['notags']['namespaces'], $namespaces, $nl )) )
                {
                    array_push($subscribedTopics, array($topic, null, $nl > 0, &$pubsub['topics'][ $_topic ]['notags']));
                    return true;
                }
            }
        }
        else
        {
            if ( $_tag && isset($pubsub['notopics']['tags'][$_tag]) )
            {
                if ( !empty($pubsub['notopics']['tags'][$_tag]['list']) &&
                    ($nl <= 0 || self::match_namespace( $pubsub['notopics']['tags'][$_tag]['namespaces'], $namespaces, $nl )) )
                {
                    array_push($subscribedTopics, array(null, $tag, $nl > 0, &$pubsub['notopics']['tags'][$_tag]));
                    return true;
                }
            }
            else
            {
                if ( !empty($pubsub['notopics']['notags']['list']) &&
                    ($nl > 0 && self::match_namespace( $pubsub['notopics']['notags']['namespaces'], $namespaces, $nl )) )
                {
                    array_push($subscribedTopics, array(null, null, true, &$pubsub['notopics']['notags']));
                    return true;
                }
                /* else no topics no tags no namespaces, do nothing */
            }
        }
        return false;
    }
    
    protected static function get_subscribed_topics( $seps, &$pubsub, $atopic )
    {
        $all = self::get_all_topics( $seps, $atopic );
        $topics = $all[ 1 ];
        $tags = $all[ 2 ]; 
        $namespaces = $all[ 3 ];
        $topTopic = $all[ 0 ]; 
        $subscribedTopics = array( );
        $tl = count($tags);
        $nl = count($namespaces);
        $l = count($topics);
        
        if ( $l )
        {
            while ( $l )
            {
                $topic = $topics[ 0 ];
                if ( isset($pubsub['topics'][ 'tp_'.$topic ]) ) 
                {
                    if ( $tl > 0 )
                    {
                        foreach ($tags as $tag)
                        {
                            self::check_is_subscribed( $pubsub, $subscribedTopics, $topic, $tag, $namespaces, $nl );
                        }
                    }
                    else
                    {
                        self::check_is_subscribed( $pubsub, $subscribedTopics, $topic, null, $namespaces, $nl );
                    }
                }
                array_shift($topics);
                $l--;
            }
        }
        if ( $tl > 0 )
        {
            foreach ($tags as $tag)
            {
                self::check_is_subscribed( $pubsub, $subscribedTopics, null, $tag, $namespaces, $nl );
            }
        }
        self::check_is_subscribed( $pubsub, $subscribedTopics, null, null, $namespaces, $nl );
        
        return array($topTopic, $subscribedTopics, $namespaces);
    }
    
    protected static function &unsubscribe_oneoffs( &$subscribers )
    {
        // unsubscribeOneOffs
        if ( $subscribers && isset($subscribers['list']) && count($subscribers['list']) > 0 )
        {
            if ( $subscribers['oneOffs'] > 0 )
            {
                $subs =& $subscribers['list'];
                $sl = count($subs);
                for ($s=$sl-1; $s>=0; $s--)
                {
                    $subscriber =& $subs[ $s ];
                    if ( $subscriber[1] && $subscriber[4] > 0 )
                    {
                        array_splice( $subs, $s, 1 );
                        $subscribers['oneOffs'] = $subscribers['oneOffs'] > 0 ? ($subscribers['oneOffs']-1) : 0;
                    }
                }
            }
            else
            {
                $subscribers['oneOffs'] = 0;
            }
        }
        return $subscribers;
    }
    
    protected static function publish( &$target, $seps, &$pubsub, $topic, &$data )
    {
        if ( !empty($pubsub) )
        {
            $topics = self::get_subscribed_topics( $seps, $pubsub, $topic );
            $topTopic = $topics[ 0 ];
            $namespaces = $topics[ 2 ];
            $topics = $topics[ 1 ];
            $evt = null;
            $res = false;
            
            if ( !empty($topics) )
            {
                $evt = new PublishSubscribeEvent( $target );
                $evt->data =& $data;
                if ( $topTopic ) $evt->originalTopic = explode( self::OTOPIC_SEP, $topTopic );
                else $evt->originalTopic = array( );
            }
            
            foreach ($topics as &$t)
            {
                $subTopic = $t[ 0 ];
                $tags = $t[ 1 ];
                if ( $subTopic ) $evt->topic = explode( self::OTOPIC_SEP, $subTopic );
                else $evt->topic = array( );
                if ( $tags ) $evt->tags = explode( self::OTAG_SEP, $tags );
                else $evt->tags = array( );
                $hasNamespace = $t[ 2 ];
                $subscribers =& $t[ 3 ];
                // create a copy avoid mutation of pubsub during notifications
                $subs = array();
                $sl = count($subscribers['list']);
                for ($s=0; $s<$sl; $s++)
                {
                    $subscriber =& $subscribers['list'][ $s ];
                    if ( (!$subscriber[ 1 ] || !$subscriber[ 4 ]) && 
                        (!$hasNamespace || 
                        ($subscriber[ 2 ] && self::match_namespace($subscriber[ 2 ], $namespaces))) 
                    ) 
                    {
                        $subs[] = $subscriber;
                    }
                }
                
                foreach ($subs as $subscriber)
                {
                    //if ( $subscriber[ 1 ] && $subscriber[ 4 ] > 0 ) continue; // oneoff subscriber already called
                    
                    if ( $hasNamespace ) $evt->namespaces = array_merge(array(), $subscriber[ 3 ]);
                    else $evt->namespaces = array( );
                    
                    $subscriber[ 4 ] = 1; // subscriber called
                    
                    $res = call_user_func( $subscriber[ 0 ], $evt );
                    
                    // stop event propagation
                    if ( (false === $res) || $evt->stopped() || $evt->aborted() ) break;
                }
                
                // unsubscribeOneOffs
                self::unsubscribe_oneoffs( $subscribers );
                
                // stop event bubble propagation
                if ( $evt->aborted() || !$evt->propagates() ) break;
            }
            
            if ( $evt )
            {
                $evt->dispose( );
                $evt = null;
            }
        }
    }
    
    protected static function create_pipeline_loop( &$evt, &$topics, $abort=null, $finish=null )
    {
        $topTopic = $topics[ 0 ];
        $namespaces = $topics[ 2 ];
        $topics = $topics[ 1 ];
        $evt->non_local = new PublishSubscribeData(array(
        't' => 0,
        's' => 0,
        'start_topic' => true,
        'subscribers' => null,
        'topics' =>& $topics,
        'namespaces' =>& $namespaces,
        'hasNamespace' => false,
        'abort'=> $abort,
        'finish'=> $finish
        ));
        
        if ( $topTopic ) $evt->originalTopic = explode( self::OTOPIC_SEP, $topTopic );
        else $evt->originalTopic = array( );
        
        return array(__CLASS__, 'pipeline_loop');
    }
    
    public static function pipeline_loop( $evt )
    {
        if ( !$evt->non_local ) return;
        
        $non_local =& $evt->non_local;
        
        if ($non_local->t < count($non_local->topics))
        {
            if ($non_local->start_topic)
            {
                // unsubscribeOneOffs
                self::unsubscribe_oneoffs( $non_local->subscribers );
        
                // stop event bubble propagation
                if ( $evt->aborted() || !$evt->propagates() )
                {
                    if ( $evt->aborted() && is_callable($non_local->abort) )
                    {
                        $abort = $non_local->abort;
                        $non_local->abort = null;
                        call_user_func( $abort, $evt );
                        if ( is_callable($non_local->finish) )
                        {
                            $finish = $non_local->finish;
                            $non_local->finish = null;
                            call_user_func( $finish, $evt );
                        }
                    }
                    return false;
                }
                
                $subTopic = $non_local->topics[$non_local->t][ 0 ];
                $tags = $non_local->topics[$non_local->t][ 1 ];
                if ( $subTopic ) $evt->topic = explode( self::OTOPIC_SEP, $subTopic );
                else $evt->topic = array( );
                if ( $tags ) $evt->tags = explode( self::OTAG_SEP, $tags );
                else $evt->tags = array( );
                $non_local->hasNamespace = $non_local->topics[$non_local->t][ 2 ];
                $non_local->subscribers =& $non_local->topics[$non_local->t][ 3 ];
                $non_local->s = 0;
                $non_local->start_topic = false;
            }
            
            //if ($non_local->subscribers) $non_local->sl = count($non_local->subscribers['list']);
            if ($non_local->s<count($non_local->subscribers['list']))
            {
                // stop event bubble propagation
                if ( $evt->aborted() || $evt->stopped() )
                {
                    // unsubscribeOneOffs
                    self::unsubscribe_oneoffs( $non_local->subscribers );
                    
                    if ( $evt->aborted() && is_callable($non_local->abort) )
                    {
                        $abort = $non_local->abort;
                        $non_local->abort = null;
                        call_user_func( $abort, $evt );
                        if ( is_callable($non_local->finish) )
                        {
                            $finish = $non_local->finish;
                            $non_local->finish = null;
                            call_user_func( $finish, $evt );
                        }
                    }
                    return false;
                }
                
                $done = false;
                while ($non_local->s<count($non_local->subscribers['list']) && !$done)
                {
                    $subscriber =& $non_local->subscribers['list'][ $non_local->s ];
                    
                    if ( (!$subscriber[ 1 ] || !$subscriber[ 4 ]) && 
                        (!$non_local->hasNamespace || 
                        ($subscriber[ 2 ] && self::match_namespace($subscriber[ 2 ], $non_local->namespaces))) 
                    ) 
                    {
                        $done = true;
                    }
                    $non_local->s += 1;
                }
                
                if ($non_local->s>=count($non_local->subscribers['list']))
                {
                    $non_local->t += 1;
                    $non_local->start_topic = true;
                }
                
                if ( $done )
                {
                    if ( $non_local->hasNamespace ) $evt->namespaces = array_merge(array(), $subscriber[ 3 ]);
                    else $evt->namespaces = array( );
                    
                    $subscriber[ 4 ] = 1; // subscriber called
                    $res = call_user_func( $subscriber[ 0 ], $evt );
                }
            }
            else
            {
                $non_local->t += 1;
                $non_local->start_topic = true;
            }
        }
        
        if ( !$evt->non_local ) return;
        
        if ($non_local->t >= count($non_local->topics))
        {
            // unsubscribeOneOffs
            self::unsubscribe_oneoffs( $non_local->subscribers );
            
            if ( is_callable($non_local->finish) )
            {
                $finish = $non_local->finish;
                $non_local->finish = null;
                call_user_func( $finish, $evt );
            }
            
            if ( $evt )
            {
                $evt->non_local->dispose(array(
                    't',
                    's',
                    'start_topic',
                    'subscribers',
                    'topics',
                    'namespaces',
                    'hasNamespace',
                    'abort',
                    'finish'
                ));
                $evt->non_local = null;
                $evt->dispose();
                $evt = null;
            }
        }
    }
    
    protected static function static_pipeline( &$target, $seps, &$pubsub, $topic, &$data, $abort=null, $finish=null )
    {
        if ( !empty($pubsub) )
        {
            $topics = self::get_subscribed_topics( $seps, $pubsub, $topic );
            $evt = null;
            
            if ( !empty($topics[ 1 ]) )
            {
                $evt = new PublishSubscribeEvent( $target );
                $evt->data =& $data;
                $evt->pipeline( self::create_pipeline_loop($evt, $topics, $abort, $finish) );
                self::pipeline_loop( $evt );
            }
        }
    }
    
    protected static function subscribe( $seps, &$pubsub, $topic, $subscriber, $oneOff=false, $on1=false )
    {
        if ( !empty($pubsub) && is_callable($subscriber) )
        {
            $topic = self::parse_topic( $seps, $topic );
            $tags = implode(self::OTAG_SEP, $topic[1]); 
            $tagslen = strlen($tags);
            $namespaces = $topic[2]; 
            $nslen = count($namespaces);
            $topic = implode(self::OTOPIC_SEP, $topic[0]);
            $oneOff = (true === $oneOff);
            $on1 = (true === $on1);
            
            $nshash = array();
            if ( $nslen )
            {
                for ($n=0; $n<$nslen; $n++)
                {
                    $nshash['ns_'.$namespaces[$n]] = 1;
                }
            }
            $namespaces_ref = array_merge(array(), $namespaces);
            
            $queue = null;
            if ( strlen($topic) )
            {
                $_topic = 'tp_'.$topic;
                if ( !isset($pubsub['topics'][ $_topic ]) ) 
                    $pubsub['topics'][ $_topic ] = array( 'notags'=> array('namespaces'=> array(), 'list'=> array(), 'oneOffs'=> 0), 'tags'=> array() );
                
                if ( $tagslen )
                {
                    $_tag = 'tg_'.$tags;
                    if ( !isset($pubsub['topics'][ $_topic ]['tags'][$_tag]) ) 
                        $pubsub['topics'][ $_topic ]['tags'][ $_tag ] = array('namespaces'=> array(), 'list'=> array(), 'oneOffs'=> 0);
                    
                    $queue =& $pubsub['topics'][ $_topic ]['tags'][ $_tag ];
                }
                else
                {
                    $queue =& $pubsub['topics'][ $_topic ]['notags'];
                }
            }
            else
            {
                if ( $tagslen )
                {
                    $_tag = 'tg_'.$tags;
                    if ( !isset($pubsub['notopics']['tags'][$_tag]) ) 
                        $pubsub['notopics']['tags'][ $_tag ] = array('namespaces'=> array(), 'list'=> array(), 'oneOffs'=> 0);
                    
                    $queue =& $pubsub['notopics']['tags'][ $_tag ];
                }
                elseif ( $nslen )
                {
                    $queue =& $pubsub['notopics']['notags'];
                }
            }
            if ( null !== $queue )
            {
                if ( $nslen ) $entry = array($subscriber, $oneOff, $nshash, $namespaces_ref, 0);
                else $entry = array($subscriber, $oneOff, false, array(), 0);
                if ( $on1 ) array_unshift($queue['list'], $entry);
                else array_push($queue['list'], $entry);
                if ( $oneOff ) $queue['oneOffs']++;
                if ( $nslen ) self::update_namespaces( $queue['namespaces'], $namespaces, $nslen );
            }
        }
    }
    
    protected static function remove_subscriber( &$pb, $hasSubscriber, $subscriber, &$namespaces, $nslen )
    {
        $pos = count($pb['list']);
        
        if ( $hasSubscriber )
        {
            if ( (null != $subscriber) && ($pos > 0) )
            {
                while ( --$pos >= 0 )
                {
                    if ( $subscriber == $pb['list'][$pos][0] )  
                    {
                        if ( $nslen && $pb['list'][$pos][2] && self::match_namespace( $pb['list'][$pos][2], $namespaces, $nslen ) )
                        {
                            $nskeys = array_keys($pb['list'][$pos][2]);
                            self::remove_namespaces( $pb['namespaces'], $nskeys );
                            if ( $pb['list'][$pos][1] ) $pb['oneOffs'] = $pb['oneOffs'] > 0 ? ($pb['oneOffs']-1) : 0;
                            array_splice( $pb['list'], $pos, 1 );
                        }
                        elseif ( !$nslen )
                        {
                            if ( $pb['list'][$pos][2] ) 
                            {
                                $nskeys = array_keys($pb['list'][$pos][2]);
                                self::remove_namespaces( $pb['namespaces'], $nskeys );
                            }
                            if ( $pb['list'][$pos][1] ) $pb['oneOffs'] = $pb['oneOffs'] > 0 ? ($pb['oneOffs']-1) : 0;
                            array_splice( $pb['list'], $pos, 1 );
                        }
                    }
                }
            }
        }
        elseif ( !$hasSubscriber && ($nslen > 0) && ($pos > 0) )
        {
            while ( --$pos >= 0 )
            {
                if ( $pb['list'][$pos][2] && self::match_namespace( $pb['list'][$pos][2], $namespaces, $nslen ) )
                {
                    $nskeys = array_keys($pb['list'][$pos][2]);
                    self::remove_namespaces( $pb['namespaces'], $nskeys );
                    if ( $pb['list'][$pos][1] ) $pb['oneOffs'] = $pb['oneOffs'] > 0 ? ($pb['oneOffs']-1) : 0;
                    array_splice( $pb['list'], $pos, 1 );
                }
            }
        }
        elseif ( !$hasSubscriber && ($pos > 0) )
        {
            $pb['list'] = array( );
            $pb['oneOffs'] = 0;
            $pb['namespaces'] = array( );
        }
    }
    
    protected static function unsubscribe( $seps, &$pubsub, $topic, $subscriber=null )
    {
        if ( !empty($pubsub) )
        {
            $topic = self::parse_topic( $seps, $topic );
            $tags = implode(self::OTAG_SEP, $topic[1]); 
            $namespaces = $topic[2];
            $tagslen = strlen($tags); 
            $nslen = count($namespaces);
            $hasSubscriber = (bool)($subscriber && is_callable( $subscriber ));
            if ( !$hasSubscriber ) $subscriber = null;

            $topic = implode(self::OTOPIC_SEP, $topic[0]);
            $topiclen = strlen($topic);
            $_topic = $topiclen ? ('tp_' . $topic) : false; $_tag = $tagslen ? ('tg_' . $tags) : false;
            
            if ( $topiclen && isset($pubsub['topics'][$_topic]) )
            {
                if ( $tagslen && isset($pubsub['topics'][ $_topic ]['tags'][$_tag]) ) 
                {
                    self::remove_subscriber( $pubsub['topics'][ $_topic ]['tags'][ $_tag ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                    if ( empty($pubsub['topics'][ $_topic ]['tags'][ $_tag ]['list']) )
                        unset($pubsub['topics'][ $_topic ]['tags'][ $_tag ]);
                }
                elseif ( !$tagslen )
                {
                    self::remove_subscriber( $pubsub['topics'][ $_topic ]['notags'], $hasSubscriber, $subscriber, $namespaces, $nslen );
                }
                if ( empty($pubsub['topics'][ $_topic ]['notags']['list']) && empty($pubsub['topics'][ $_topic ]['tags']) )
                    unset($pubsub['topics'][ $_topic ]);
            }
            elseif ( !$topiclen && ($tagslen || $nslen) )
            {
                if ( $tagslen )
                {
                    if ( isset($pubsub['notopics']['tags'][$_tag]) )
                    {
                        self::remove_subscriber( $pubsub['notopics']['tags'][ $_tag ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                        if ( empty($pubsub['notopics']['tags'][ $_tag ]['list']) )
                            unset($pubsub['notopics']['tags'][ $_tag ]);
                    }
                    
                    // remove from any topics as well
                    foreach ( array_keys($pubsub['topics']) as $t )
                    {
                        if ( isset($pubsub['topics'][ $t ]['tags'][$_tag]) )
                        {
                            self::remove_subscriber( $pubsub['topics'][ $t ]['tags'][ $_tag ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                            if ( empty($pubsub['topics'][ $t ]['tags'][ $_tag ]['list']) )
                                unset($pubsub['topics'][ $t ]['tags'][ $_tag ]);
                        }
                    }
                }
                else
                {
                    self::remove_subscriber( $pubsub['notopics']['notags'], $hasSubscriber, $subscriber, $namespaces, $nslen );
                    
                    // remove from any tags as well
                    foreach ( array_keys($pubsub['notopics']['tags']) as $t2 )
                    {
                        self::remove_subscriber( $pubsub['notopics']['tags'][ $t2 ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                        if ( empty($pubsub['notopics']['tags'][ $t2 ]['list']) )
                            unset($pubsub['notopics']['tags'][ $t2 ]);
                    }
                    
                    // remove from any topics and tags as well
                    foreach ( array_keys($pubsub['topics']) as $t )
                    {
                        self::remove_subscriber( $pubsub['topics'][ $t ]['notags'], $hasSubscriber, $subscriber, $namespaces, $nslen );
                        
                        foreach ( array_keys($pubsub['topics'][ $t ]['tags']) as $t2 )
                        {
                            self::remove_subscriber( $pubsub['topics'][ $t ]['tags'][ $t2 ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                            if ( empty($pubsub['topics'][ $t ]['tags'][ $t2 ]['list']) )
                                unset($pubsub['topics'][ $t ]['tags'][ $t2 ]);
                        }
                    }
                }
            }
        }
    }
    
    public static function Data($props=null)
    {
        return new PublishSubscribeData($props);
    }
    
    protected $_seps = null;
    protected $_pubsub = null;
        
    //
    // PublishSubscribe (Interface)
    public function __construct( )
    {
        $this->initPubSub( ); 
    }
        
    public function __destruct()
    {
        $this->disposePubSub();
    }
    
    public function initPubSub( ) 
    {
        $this->_seps = array(self::TOPIC_SEP, self::TAG_SEP, self::NS_SEP);
        $this->_pubsub = self::get_pubsub( );
        return $this;
    }
    
    public function disposePubSub( ) 
    {
        $this->_seps = null;
        $this->_pubsub = null;
        return $this;
    }
    
    public function setSeparators( $seps ) 
    {
        if ( $seps )
        {
            $l = count($seps);
            if ( $l > 0 && $seps[0] ) $this->_seps[0] = $seps[0];
            if ( $l > 1 && $seps[1] ) $this->_seps[1] = $seps[1];
            if ( $l > 2 && $seps[2] ) $this->_seps[2] = $seps[2];
        }
        return $this;
    }
    
    public function trigger( $message, $data=array()/*, $delay=0*/ ) 
    {
        //$delay = intval($delay);
        //if ( !$data ) $data = array();
        //print_r($this->_pubsub);
        self::publish( $this, $this->_seps, $this->_pubsub, $message, $data );
        //print_r($this->_pubsub);
        return $this;
    }
    
    public function pipeline( $message, $data=array(), $abort=null, $finish=null/*, $delay=0*/ ) 
    {
        //$delay = intval($delay);
        //if ( !$data ) $data = array();
        //print_r($this->_pubsub);
        self::static_pipeline( $this, $this->_seps, $this->_pubsub, $message, $data, $abort, $finish );
        //print_r($this->_pubsub);
        return $this;
    }
    
    public function on( $message, $callback ) 
    {
        if ( $callback && is_callable($callback) )
        {
            //print_r($this->_pubsub);
            self::subscribe( $this->_seps, $this->_pubsub, $message, $callback );
            //print_r($this->_pubsub);
        }
        return $this;
    }
    
    public function one( $message, $callback ) 
    {
        if ( $callback && is_callable($callback) )
        {
            //print_r($this->_pubsub);
            self::subscribe( $this->_seps, $this->_pubsub, $message, $callback, true );
            //print_r($this->_pubsub);
        }
        return $this;
    }
    
    public function on1( $message, $callback ) 
    {
        if ( $callback && is_callable($callback) )
        {
            //print_r($this->_pubsub);
            self::subscribe( $this->_seps, $this->_pubsub, $message, $callback, false, true );
            //print_r($this->_pubsub);
        }
        return $this;
    }
    
    public function one1( $message, $callback ) 
    {
        if ( $callback && is_callable($callback) )
        {
            //print_r($this->_pubsub);
            self::subscribe( $this->_seps, $this->_pubsub, $message, $callback, true, true );
            //print_r($this->_pubsub);
        }
        return $this;
    }
    
    public function off( $message, $callback=null ) 
    {
        //print_r($this->_pubsub);
        self::unsubscribe( $this->_seps, $this->_pubsub, $message, $callback );
        //print_r($this->_pubsub);
        return $this;
    }
}    
}