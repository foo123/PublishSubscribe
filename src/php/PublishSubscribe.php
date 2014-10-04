<?php
/**
*  PublishSubscribe
*  A simple publish-subscribe implementation for PHP, Python, Node/JS
*
*  @version: 0.3.3
*  https://github.com/foo123/PublishSubscribe
*
**/
if ( !class_exists('PublishSubscribe') )
{

interface PublishSubscribeInterface
{
    public function on($message, $callback);
    public function one($message, $callback);
    public function off($message, $callback=null);
    public function trigger($message, $data=null/*, $delay=0*/);
}

class PublishSubscribeEvent
{
    public $topic = null;
    public $originalTopic = null;
    public $tags = null;
    public $namespaces = null;
    public $data = null;
    private $_stopPropagation = false;
    private $_stopEvent = false;
    
    public function __construct($topic=null, $original=null, $tags=null, $namespaces=null)
    {
        if ( $topic ) $this->topic = (array)$topic;
        else  $this->topic = array();
        if ( $original ) $this->originalTopic = (array)$original;
        else $this->originalTopic = array();
        if ( $tags ) $this->tags = (array)$tags;
        else $this->tags = array();
        if ( $namespaces )  $this->namespaces = (array)$namespaces;
        else  $this->namespaces = array();
        $this->data = array();
        $this->_stopPropagation = false;
        $this->_stopEvent = false;
    }
    
    public function dispose( ) 
    {
        $this->topic = null;
        $this->originalTopic = null;
        $this->tags = null;
        $this->namespaces = null;
        $this->data = null;
        $this->_stopPropagation = true;
        $this->_stopEvent = true;
        return $this;
    }
    
        
    public function propagate( $enable=true ) 
    {
        $this->_stopPropagation = !((bool)$enable);
        return $this;
    }
    
    public function stop( $enable=true ) 
    {
        $this->_stopEvent = (bool)$enable;
        return $this;
    }
    
    public function propagationStopped( ) 
    {
        return $this->_stopPropagation;
    }
    
    public function eventStopped( ) 
    {
        return $this->_stopEvent;
    }
}

class PublishSubscribe implements PublishSubscribeInterface
{
    const VERSION = "0.3.3";
    const TOPIC_SEP = '/';
    const TAG_SEP = '#';
    const NS_SEP = '@';
    const OTOPIC_SEP = '/';
    const OTAG_SEP = '#';
    const ONS_SEP = '@';
    
    private static function getPubSub( ) 
    { 
        return array( 'notopics'=> array( 'notags'=> array('namespaces'=> array(), 'list'=> array()), 'tags'=> array() ), 'topics'=> array() );
    }
    
    private static function parseTopic( $seps, $topic )
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
    
    private static function getAllTopics( $seps, $topic ) 
    { 
        $topics = array();
        $tags = array(); 
        //$namespaces = array();
        
        $topic = self::parseTopic( $seps, $topic );
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
    
    private static function updateNamespaces( &$pbns, &$namespaces, $nl=0 )
    {
        foreach ($namespaces as $ns)
        {
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
    
    private static function removeNamespaces( &$pbns, &$namespaces, $nl=0 )
    {
        foreach ($namespaces as $ns)
        {
            if ( isset($pbns[$ns]) )
            {
                $pbns[ $ns ]--;
                if ( $pbns[ $ns ] <=0 )
                    unset($pbns[ $ns ]);
            }
        }
    }
    
    private static function matchNamespace( &$pbns, &$namespaces, $nl=0 )
    {
        foreach ($namespaces as $ns)
        {
            if ( !isset($pbns[ $ns ]) || (0 >= $pbns[ $ns ]) ) return false;
        }
        return true;
    }
    
    private static function checkIsSubscribed( &$pubsub, &$subscribedTopics, $topic, $tag, &$namespaces, $nl )
    {
        if ( $topic )
        {
            if ( $tag )
            {
                if ( $nl > 0 )
                {
                    if ( isset($pubsub['topics'][ $topic ]['tags'][ $tag ]) && 
                        !empty($pubsub['topics'][ $topic ]['tags'][ $tag ]['list']) &&
                        self::matchNamespace( $pubsub['topics'][ $topic ]['tags'][ $tag ]['namespaces'], $namespaces, $nl ) )
                    {
                        array_push($subscribedTopics, array($topic, $tag, true, &$pubsub['topics'][ $topic ]['tags'][ $tag ]));
                        return true;
                    }
                }
                else
                {
                    if ( isset($pubsub['topics'][ $topic ]['tags'][$tag]) && !empty($pubsub['topics'][ $topic ]['tags'][$tag]['list']) )
                    {
                        array_push($subscribedTopics, array($topic, $tag, null, &$pubsub['topics'][ $topic ]['tags'][$tag]));
                        return true;
                    }
                }
            }
            else
            {
                if ( $nl > 0 )
                {
                    if ( !empty($pubsub['topics'][ $topic ]['notags']['list']) &&
                        self::matchNamespace( $pubsub['topics'][ $topic ]['notags']['namespaces'], $namespaces, $nl ) )
                    {
                        array_push($subscribedTopics, array($topic, null, true, &$pubsub['topics'][ $topic ]['notags']));
                        return true;
                    }
                }
                else
                {
                    if ( !empty($pubsub['topics'][ $topic ]['notags']['list']) )
                    {
                        array_push($subscribedTopics, array($topic, null, null, &$pubsub['topics'][ $topic ]['notags']));
                        return true;
                    }
                }
            }
        }
        else
        {
            if ( $tag )
            {
                if ( $nl > 0 )
                {
                    if ( isset($pubsub['notopics']['tags'][$tag]) && 
                        !empty($pubsub['notopics']['tags'][$tag]['list']) &&
                        self::matchNamespace( $pubsub['notopics']['tags'][$tag]['namespaces'], $namespaces, $nl ) )
                    {
                        array_push($subscribedTopics, array(null, $tag, true, &$pubsub['notopics']['tags'][$tag]));
                        return true;
                    }
                }
                else
                {
                    if ( isset($pubsub['notopics']['tags'][$tag]) && !empty($pubsub['notopics']['tags'][$tag]['list']) )
                    {
                        array_push($subscribedTopics, array(null, $tag, null, &$pubsub['notopics']['tags'][$tag]));
                        return true;
                    }
                }
            }
            else
            {
                if ( $nl > 0 )
                {
                    if ( !empty($pubsub['notopics']['notags']['list']) &&
                        self::matchNamespace( $pubsub['notopics']['notags']['namespaces'], $namespaces, $nl ) )
                    {
                        array_push($subscribedTopics, array(null, null, true, &$pubsub['notopics']['notags']));
                        return true;
                    }
                }
                else
                {
                    /* no topics no tags no namespaces, do nothing */
                }
            }
        }
        return false;
    }
    
    private static function getSubscribedTopics( $seps, &$pubsub, $atopic )
    {
        $all = self::getAllTopics( $seps, $atopic );
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
                if ( isset($pubsub['topics'][ $topic ]) ) 
                {
                    if ( $tl > 0 )
                    {
                        foreach ($tags as $tag)
                        {
                            self::checkIsSubscribed( $pubsub, $subscribedTopics, $topic, $tag, $namespaces, $nl );
                        }
                    }
                    else
                    {
                        self::checkIsSubscribed( $pubsub, $subscribedTopics, $topic, null, $namespaces, $nl );
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
                self::checkIsSubscribed( $pubsub, $subscribedTopics, null, $tag, $namespaces, $nl );
            }
        }
        self::checkIsSubscribed( $pubsub, $subscribedTopics, null, null, $namespaces, $nl );
        
        return array($topTopic, $subscribedTopics, $namespaces);
    }
    
    private static function publish( $seps, &$pubsub, $topic, $data )
    {
        if ( !empty($pubsub) )
        {
            $topics = self::getSubscribedTopics( $seps, $pubsub, $topic );
            $topTopic = $topics[ 0 ];
            $namespaces = $topics[ 2 ];
            $topics = $topics[ 1 ];
            $evt = null;
            
            if ( !empty($topics) )
            {
                $evt = new PublishSubscribeEvent( );
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
                //$oneOffs = array();
                $sl = count($subscribers['list']);
                for ($s=0; $s<$sl; $s++)
                {
                    if ( !$hasNamespace || ($subscribers['list'][ $s ][ 2 ] && self::matchNamespace($subscribers['list'][ $s ][ 2 ], $namespaces)) ) 
                    {
                        //if ($subscribers['list'][ $s ][ 1 ]) $oneOffs[] = $s;
                        $subs[] =& $subscribers['list'][ $s ];
                    }
                }
                
                // unsubscribeOneOffs
                /*while ( !empty($oneOffs) )
                {
                    $pos = array_pop($oneOffs);
                    if ( $subscribers['list'][$pos][2] )
                    {
                        $nskeys = array_keys($subscribers['list'][$pos][2]);
                        self::removeNamespaces( $subscribers['namespaces'], $nskeys );
                    }
                    array_splice( $subscribers['list'], $pos, 1 );
                }*/
                
                foreach ($subs as $subscriber)
                {
                    if ( $hasNamespace ) $evt->namespaces = array_merge(array(), $subscriber[ 3 ]);
                    else $evt->namespaces = array( );
                    $subscriber[ 4 ] = 1; // subscriber called
                    $res = call_user_func( $subscriber[ 0 ], $evt, $data );
                    // stop event propagation
                    if ( (false === $res) || $evt->eventStopped() ) break;
                }
                
                // unsubscribeOneOffs
                if ( isset($subscribers['list']) && count($subscribers['list']) > 0 )
                {
                    $subs =& $subscribers['list'];
                    $sl = count($subs);
                    for ($s=$sl-1; $s>=0; $s--)
                    {
                        $subscriber =& $subs[ $s ];
                        if ( $subscriber[1] && $subscriber[4] > 0 )
                            array_splice( $subs, $s, 1 );
                    }
                }
                
                // stop event bubble propagation
                if ( $evt->propagationStopped() ) break;
            }
            
            if ( $evt )
            {
                $evt->dispose( );
                $evt = null;
            }
        }
    }
    
    private static function subscribe( $seps, &$pubsub, $topic, $subscriber, $oneOff=false, $on1=false )
    {
        if ( !empty($pubsub) && is_callable($subscriber) )
        {
            $topic = self::parseTopic( $seps, $topic );
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
                    $nshash[$namespaces[$n]] = 1;
                }
            }
            $namespaces_ref = array_merge(array(), $namespaces);
            
            if ( strlen($topic) )
            {
                if ( !isset($pubsub['topics'][ $topic ]) ) 
                    $pubsub['topics'][ $topic ] = array( 'notags'=> array('namespaces'=> array(), 'list'=> array()), 'tags'=> array() );
                if ( $tagslen )
                {
                    if ( !isset($pubsub['topics'][ $topic ]['tags'][$tags]) ) 
                        $pubsub['topics'][ $topic ]['tags'][ $tags ] = array('namespaces'=> array(), 'list'=> array());
                    if ( $nslen )
                    {
                        $entry = array($subscriber, $oneOff, $nshash, $namespaces_ref, 0);
                        if ( $on1 )
                            array_unshift($pubsub['topics'][ $topic ]['tags'][ $tags ]['list'], $entry);
                        else
                            array_push($pubsub['topics'][ $topic ]['tags'][ $tags ]['list'], $entry);
                        self::updateNamespaces( $pubsub['topics'][ $topic ]['tags'][ $tags ]['namespaces'], $namespaces, $nslen );
                    }
                    else
                    {
                        $entry = array($subscriber, $oneOff, false, array(), 0);
                        if ( $on1 )
                            array_unshift($pubsub['topics'][ $topic ]['tags'][ $tags ]['list'], $entry);
                        else
                            array_push($pubsub['topics'][ $topic ]['tags'][ $tags ]['list'], $entry);
                    }
                }
                else
                {
                    if ( $nslen )
                    {
                        $entry = array($subscriber, $oneOff, $nshash, $namespaces_ref, 0);
                        if ( $on1 )
                            array_unshift($pubsub['topics'][ $topic ]['notags']['list'], $entry);
                        else
                            array_push($pubsub['topics'][ $topic ]['notags']['list'], $entry);
                        self::updateNamespaces( $pubsub['topics'][ $topic ]['notags']['namespaces'], $namespaces, $nslen );
                    }
                    else
                    {
                        $entry = array($subscriber, $oneOff, false, array(), 0);
                        if ( $on1 )
                            array_unshift($pubsub['topics'][ $topic ]['notags']['list'], $entry);
                        else
                            array_push($pubsub['topics'][ $topic ]['notags']['list'], $entry);
                    }
                }
            }
            else
            {
                if ( $tagslen )
                {
                    if ( !isset($pubsub['notopics']['tags'][$tags]) ) 
                        $pubsub['notopics']['tags'][ $tags ] = array('namespaces'=> array(), 'list'=> array());
                    if ( $nslen )
                    {
                        $entry = array($subscriber, $oneOff, $nshash, $namespaces_ref, 0);
                        if ( $on1 )
                            array_unshift($pubsub['notopics']['tags'][ $tags ]['list'], $entry);
                        else
                            array_push($pubsub['notopics']['tags'][ $tags ]['list'], $entry);
                        self::updateNamespaces( $pubsub['notopics']['tags'][ $tags ]['namespaces'], $namespaces, $nslen );
                    }
                    else
                    {
                        $entry = array($subscriber, $oneOff, false, array(), 0);
                        if ( $on1 )
                            array_unshift($pubsub['notopics']['tags'][ $tags ]['list'], $entry);
                        else
                            array_push($pubsub['notopics']['tags'][ $tags ]['list'], $entry);
                    }
                }
                elseif ( $nslen )
                {
                    $entry = array($subscriber, $oneOff, $nshash, $namespaces_ref, 0);
                    if ( $on1 )
                        array_unshift($pubsub['notopics']['notags']['list'], $entry);
                    else
                        array_push($pubsub['notopics']['notags']['list'], $entry);
                    self::updateNamespaces( $pubsub['notopics']['notags']['namespaces'], $namespaces, $nslen );
                }
            }
        }
    }
    
    private static function removeSubscriber( &$pb, $hasSubscriber, $subscriber, &$namespaces, $nslen )
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
                        if ( $nslen && $pb['list'][$pos][2] && self::matchNamespace( $pb['list'][$pos][2], $namespaces, $nslen ) )
                        {
                            $nskeys = array_keys($pb['list'][$pos][2]);
                            self::removeNamespaces( $pb['namespaces'], $nskeys );
                            array_splice( $pb['list'], $pos, 1 );
                        }
                        elseif ( !$nslen )
                        {
                            if ( $pb['list'][$pos][2] ) 
                            {
                                $nskeys = array_keys($pb['list'][$pos][2]);
                                self::removeNamespaces( $pb['namespaces'], $nskeys );
                            }
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
                if ( $pb['list'][$pos][2] && self::matchNamespace( $pb['list'][$pos][2], $namespaces, $nslen ) )
                {
                    $nskeys = array_keys($pb['list'][$pos][2]);
                    self::removeNamespaces( $pb['namespaces'], $nskeys );
                    array_splice( $pb['list'], $pos, 1 );
                }
            }
        }
        elseif ( !$hasSubscriber && ($pos > 0) )
        {
            $pb['list'] = array( );
            $pb['namespaces'] = array( );
        }
    }
    
    private static function unsubscribe( $seps, &$pubsub, $topic, $subscriber=null )
    {
        if ( !empty($pubsub) )
        {
            $topic = self::parseTopic( $seps, $topic );
            $tags = implode(self::OTAG_SEP, $topic[1]); 
            $namespaces = $topic[2];
            $tagslen = strlen($tags); 
            $nslen = count($namespaces);
            $hasSubscriber = (bool)($subscriber && is_callable( $subscriber ));
            if ( !$hasSubscriber ) $subscriber = null;

            $topic = implode(self::OTOPIC_SEP, $topic[0]);
            $topiclen = strlen($topic);
            
            if ( $topiclen && isset($pubsub['topics'][$topic]) )
            {
                if ( $tagslen && isset($pubsub['topics'][ $topic ]['tags'][$tags]) ) 
                {
                    self::removeSubscriber( $pubsub['topics'][ $topic ]['tags'][ $tags ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                    if ( empty($pubsub['topics'][ $topic ]['tags'][ $tags ]['list']) )
                        unset($pubsub['topics'][ $topic ]['tags'][ $tags ]);
                }
                elseif ( !$tagslen )
                {
                    self::removeSubscriber( $pubsub['topics'][ $topic ]['notags'], $hasSubscriber, $subscriber, $namespaces, $nslen );
                }
                if ( empty($pubsub['topics'][ $topic ]['notags']['list']) && empty($pubsub['topics'][ $topic ]['tags']) )
                    unset($pubsub['topics'][ $topic ]);
            }
            elseif ( !$topiclen && ($tagslen || $nslen) )
            {
                if ( $tagslen )
                {
                    if ( isset($pubsub['notopics']['tags'][$tags]) )
                    {
                        self::removeSubscriber( $pubsub['notopics']['tags'][ $tags ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                        if ( empty($pubsub['notopics']['tags'][ $tags ]['list']) )
                            unset($pubsub['notopics']['tags'][ $tags ]);
                    }
                    
                    // remove from any topics as well
                    foreach ( array_keys($pubsub['topics']) as $t )
                    {
                        if ( isset($pubsub['topics'][ $t ]['tags'][$tags]) )
                        {
                            self::removeSubscriber( $pubsub['topics'][ $t ]['tags'][ $tags ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                            if ( empty($pubsub['topics'][ $t ]['tags'][ $tags ]['list']) )
                                unset($pubsub['topics'][ $t ]['tags'][ $tags ]);
                        }
                    }
                }
                else
                {
                    self::removeSubscriber( $pubsub['notopics']['notags'], $hasSubscriber, $subscriber, $namespaces, $nslen );
                    
                    // remove from any tags as well
                    foreach ( array_keys($pubsub['notopics']['tags']) as $t2 )
                    {
                        self::removeSubscriber( $pubsub['notopics']['tags'][ $t2 ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                        if ( empty($pubsub['notopics']['tags'][ $t2 ]['list']) )
                            unset($pubsub['notopics']['tags'][ $t2 ]);
                    }
                    
                    // remove from any topics and tags as well
                    foreach ( array_keys($pubsub['topics']) as $t )
                    {
                        self::removeSubscriber( $pubsub['topics'][ $t ]['notags'], $hasSubscriber, $subscriber, $namespaces, $nslen );
                        
                        foreach ( array_keys($pubsub['topics'][ $t ]['tags']) as $t2 )
                        {
                            self::removeSubscriber( $pubsub['topics'][ $t ]['tags'][ $t2 ], $hasSubscriber, $subscriber, $namespaces, $nslen );
                            if ( empty($pubsub['topics'][ $t ]['tags'][ $t2 ]['list']) )
                                unset($pubsub['topics'][ $t ]['tags'][ $t2 ]);
                        }
                    }
                }
            }
        }
    }
    
    private $_seps = null;
    private $_pubsub = null;
        
    //
    // PublishSubscribe (Interface)
    public function __construct( )
    {
        $this->initPubSub( ); 
    }
        
    public function initPubSub( ) 
    {
        $this->_seps = array(self::TOPIC_SEP, self::TAG_SEP, self::NS_SEP);
        $this->_pubsub = self::getPubSub( );
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
    
    public function trigger( $message, $data=null/*, $delay=0*/ ) 
    {
        //$delay = intval($delay);
        if ( !$data ) $data = array();
        //print_r($this->_pubsub);
        self::publish( $this->_seps, $this->_pubsub, $message, $data );
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