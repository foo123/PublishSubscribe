/**
*  PublishSubscribe
*  A simple publish-subscribe implementation for PHP, Python, Node/JS
*
*  @version: 0.4.1
*  https://github.com/foo123/PublishSubscribe
*
**/
!function( root, name, factory ) {
"use strict";
var m;
if ( ('undefined'!==typeof Components)&&('object'===typeof Components.classes)&&('object'===typeof Components.classesByID)&&Components.utils&&('function'===typeof Components.utils['import']) ) /* XPCOM */
    (root.EXPORTED_SYMBOLS = [ name ]) && (root[ name ] = factory.call( root ));
else if ( ('object'===typeof module)&&module.exports ) /* CommonJS */
    module.exports = factory.call( root );
else if ( ('function'===typeof(define))&&define.amd&&('function'===typeof(require))&&('function'===typeof(require.specified))&&require.specified(name) ) /* AMD */
    define(name,['require','exports','module'],function( ){return factory.call( root );});
else if ( !(name in root) ) /* Browser/WebWorker/.. */
    (root[ name ] = (m=factory.call( root )))&&('function'===typeof(define))&&define.amd&&define(function( ){return m;} );
}(  /* current root */          this, 
    /* module name */           "PublishSubscribe",
    /* module factory */        function( undef ) {
"use strict";

var __version__ = "0.4.1", 
    PROTO = 'prototype', HAS = 'hasOwnProperty',
    TOPIC_SEP = '/', TAG_SEP = '#', NS_SEP = '@',
    OTOPIC_SEP = '/', OTAG_SEP = '#', ONS_SEP = '@',
    KEYS = Object.keys,
    NOW = Date.now ? Date.now : function( ){ return new Date().getTime(); }
;

function PublishSubscribeData( props ) 
{
    if ( props )
    {
        for (var k in props)
        {
            if ( props[HAS](k) )
                this[ k ] = props[ k ];
        }
    }
}
PublishSubscribeData[PROTO] = {
    constructor: PublishSubscribeData,
    dispose: function( props ) {
        if ( props )
        {
            for (var k=0; k<props.length; k++)
            {
                this[ props[ k ] ] = null;
            }
        }
        return this;
    }
};

function PublishSubscribeEvent(target, topic, original, tags, namespaces)
{
    var self = this;
    self.target = target;
    if ( topic )  self.topic = [].concat( topic );
    else self.topic = [ ];
    if ( original )  self.originalTopic = [].concat( original );
    if ( tags )  self.tags = [].concat( tags );
    else self.tags = [ ];
    if ( namespaces )  self.namespaces = [].concat( namespaces );
    else self.namespaces = [ ];
    self.data = new PublishSubscribeData();
    self.timestamp = NOW( );
    self._propagates = true;
    self._stopped = false;
    self._aborted = false;
}
PublishSubscribeEvent[PROTO] = {
    constructor: PublishSubscribeEvent,
    target: null,
    topic: null,
    originalTopic: null,
    tags: null,
    namespaces: null,
    data: null,
    timestamp: 0,
    is_pipelined: false,
    _next: null,
    _propagates: true,
    _stopped: false,
    _aborted: false,
    
    dispose: function( ) {
        var self = this;
        self.target = null;
        self.topic = null;
        self.originalTopic = null;
        self.tags = null;
        self.namespaces = null;
        if (self.data instanceof PublishSubscribeData) self.data.dispose();
        self.data = null;
        self.timestamp = null;
        self.is_pipelined = false;
        self._propagates = true;
        self._stopped = true;
        self._aborted = false;
        self._next = null;
        return self;
    },
    
    next: function( ) {
        if ('function' === typeof this._next) this._next(this);
        return this;
    },
    
    pipeline: function( next ) {
        if ( !arguments.length ) next = null;
        if ('function' === typeof next)
        {
            this._next = next;
            this.is_pipelined = true;
        }
        else
        {
            this._next = null;
            this.is_pipelined = false;
        }
        return this;
    },
    
    propagate: function( enable ) {
        if ( !arguments.length ) enable = true;
        this._propagates = !!enable;
        return this;
    },
    
    stop: function( enable ) {
        if ( !arguments.length ) enable = true;
        this._stopped = !!enable;
        return this;
    },
    
    abort: function( enable ) {
        if ( !arguments.length ) enable = true;
        this._aborted = !!enable;
        return this;
    },
    
    aborted: function( ) {
        return this._aborted;
    },
    
    propagates: function( ) {
        return this._propagates;
    },
    
    stopped: function( ) {
        return this._stopped;
    }
};

function get_pubsub( ) { return { notopics: { notags: {namespaces: {}, list: [], oneOffs: 0}, tags: {} }, topics: {} }; }

function not_empty( s ) { return s.length > 0; }

function parse_topic( seps, topic )
{
    var nspos, tagspos, tags, namespaces;
    
    topic = String( topic );
    nspos = topic.indexOf( seps[2] );
    tagspos = topic.indexOf( seps[1] );
    if ( -1 < nspos )
    {
        namespaces = topic
            .slice( nspos )
            .split( seps[2] )
            .filter( not_empty )
            .sort( )
        ;
        topic = topic.slice( 0, nspos );
    }
    else
    {
        namespaces = [ ];
    }
    if ( -1 < tagspos )
    {
        tags = topic
                .slice( tagspos )
                .split( seps[1] )
                .filter( not_empty )
                .sort( )
        ;
        topic = topic.slice( 0, tagspos );
    }
    else
    {
        tags = [ ];
    }
    topic = topic.split( seps[0] ).filter( not_empty );
    return [topic, tags, namespaces];
}

function get_all_topics( seps, topic ) 
{ 
    var topics = [ ], tags = [ ], namespaces/* = [ ]*/, 
        ttags, tns, l, i, j, jj, tmp, combinations;
    
    topic = parse_topic( seps, topic );
    //tns = topic[2];
    namespaces = topic[2];
    ttags = topic[1];
    topic = topic[0];
    
    l = topic.length;
    while ( l )
    {
        topics.push( topic.join( OTOPIC_SEP ) );
        topic.pop( );
        l--;
    }
    
    l = ttags.length;
    if ( l > 1 )
    {
        combinations = (1 << l);
        for (i=combinations-1; i>=1; i--)
        {
            tmp = [ ];
            for (j=0,jj=1; j<l; j++,jj=(1 << j))
            {
                if ( (i !== jj) && (i & jj) )
                    tmp.push( ttags[ j ] );
            }
            if ( tmp.length )
                tags.push( tmp.join( OTAG_SEP ) );
        }
        tags = tags.concat( ttags );
    }
    else if ( l ) tags.push( ttags[ 0 ] );
    
    /*l = tns.length;
    if ( l > 1 )
    {
        combinations = (1 << l);
        for (i=combinations-1; i>=1; i--)
        {
            tmp = [ ];
            for (j=0,jj=1; j<l; j++,jj=(1 << j))
            {
                if ( (i !== jj) && (i & jj) )
                    tmp.push( tns[ j ] );
            }
            if ( tmp.length )
                namespaces.push( tmp.join( OMS_SEP ) );
        }
        namespaces = namespaces.concat( tns );
    }
    else if ( l && tns[0].length ) namespaces.push( tns[ 0 ] );*/
    
    return [topics.length ? topics[0] : '', topics, tags, namespaces];
}

function update_namespaces( pbns, namespaces, nl )
{
    var n, ns;
    for (n=0; n<nl; n++)
    {
        ns = 'ns_' + namespaces[n];
        if ( !pbns[HAS](ns) )
        {
            pbns[ ns ] = 1;
        }
        else
        {
            pbns[ ns ]++;
        }
    }
}

function remove_namespaces( pbns, namespaces, nl )
{
    var n, ns;
    for (n=0; n<nl; n++)
    {
        ns = 'ns_' + namespaces[n];
        if ( pbns[HAS](ns) )
        {
            pbns[ ns ]--;
            if ( pbns[ ns ] <=0 ) delete pbns[ ns ];
        }
    }
}

function match_namespace( pbns, namespaces, nl )
{
    var n, ns;
    for (n=0; n<nl; n++)
    {
        ns = 'ns_' + namespaces[n];
        if ( !pbns[HAS](ns) || (0 >= pbns[ ns ]) ) return false;
    }
    return true;
}

function check_is_subscribed( pubsub, subscribedTopics, topic, tag, namespaces, nl )
{
    var _topic = !!topic ? 'tp_' + topic : false, 
        _tag = !!tag ? 'tg_' + tag : false;
        
    if ( _topic && pubsub.topics[HAS](_topic) )
    {
        if ( _tag && pubsub.topics[ _topic ].tags[HAS](_tag) )
        {
            if ( pubsub.topics[ _topic ].tags[ _tag ].list.length &&
                (nl <= 0 || match_namespace( pubsub.topics[ _topic ].tags[ _tag ].namespaces, namespaces, nl )) )
            {
                subscribedTopics.push( [topic, tag, nl > 0, pubsub.topics[ _topic ].tags[ _tag ]] );
                return true;
            }
        }
        else
        {
            if ( pubsub.topics[ _topic ].notags.list.length && 
                (nl <= 0 || match_namespace( pubsub.topics[ _topic ].notags.namespaces, namespaces, nl )) )
            {
                subscribedTopics.push( [topic, null, nl > 0, pubsub.topics[ _topic ].notags] );
                return true;
            }
        }
    }
    else
    {
        if ( _tag && pubsub.notopics.tags[HAS](_tag) )
        {
            if ( pubsub.notopics.tags[ _tag ].list.length &&
                (nl <= 0 || match_namespace( pubsub.notopics.tags[ _tag ].namespaces, namespaces, nl )) )
            {
                subscribedTopics.push( [null, tag, nl > 0, pubsub.notopics.tags[ _tag ]] );
                return true;
            }
        }
        else
        {
            if ( pubsub.notopics.notags.list.length &&
                (nl <= 0 || match_namespace( pubsub.notopics.notags.namespaces, namespaces, nl )) )
            {
                subscribedTopics.push( [null, null, true, pubsub.notopics.notags] );
                return true;
            }
            /* else no topics no tags no namespaces, do nothing */
        }
    }
    return false;
}

function get_subscribed_topics( seps, pubsub, atopic )
{
    var all = get_all_topics( seps, atopic ), l, topic, tag, ns,
        //_topic, _tag,
        t, n, tl, nl, 
        topics = all[ 1 ], tags = all[ 2 ], namespaces = all[ 3 ], 
        topTopic = all[ 0 ], subscribedTopics = [ ]
    ;
    tl = tags.length;
    nl = namespaces.length;
    l = topics.length;
    
    if ( l )
    {
        while ( l )
        {
            topic = topics[ 0 ]; //_topic = 'tp_' + topic;
            if ( pubsub.topics[HAS]( 'tp_' + topic ) ) 
            {
                if ( tl > 0 )
                {
                    for (t=0; t<tl; t++)
                    {
                        tag = tags[ t ]; //_tag = 'tg_' + tag;
                        check_is_subscribed( pubsub, subscribedTopics, topic, tag, namespaces, nl );
                    }
                }
                else
                {
                    check_is_subscribed( pubsub, subscribedTopics, topic, null, namespaces, nl );
                }
            }
            topics.shift( );
            l--;
        }
    }
    if ( tl > 0 )
    {
        for (t=0; t<tl; t++)
        {
            tag = tags[ t ];
            check_is_subscribed( pubsub, subscribedTopics, null, tag, namespaces, nl );
        }
    }
    check_is_subscribed( pubsub, subscribedTopics, null, null, namespaces, nl );
    /*nss = { };
    if ( nl > 0 )
    {
        for (n=0; n<nl; n++)
        {
            nss[ namespaces[ n ] ] = 1;
        }
    }*/
    
    return [topTopic, subscribedTopics, namespaces];
}

function unsubscribe_oneoffs( subscribers )
{
    if ( subscribers && subscribers[HAS]("list") )
    {
        // unsubscribeOneOffs
        var s, sl, subs, subscriber;
        if ( (subs=subscribers.list) && (sl=subs.length) )
        {
            if ( subscribers.oneOffs > 0 )
            {
                for (s=sl-1; s>=0; s--)
                {
                    subscriber = subs[ s ];
                    if ( subscriber[1] && subscriber[4] > 0 )
                    {
                        subs.splice( s, 1 );
                        subscribers.oneOffs = subscribers.oneOffs > 0 ? (subscribers.oneOffs-1) : 0;
                    }
                }
            }
            else
            {
                subscribers.oneOffs = 0;
            }
        }
    }
    return subscribers;
}

function publish( target, seps, pubsub, topic, data )
{
    if ( pubsub )
    {
        var topics = get_subscribed_topics( seps, pubsub, topic ), 
            t, s, tl, sl, subs, subscribers, subscriber, topTopic, subTopic,
            tags, namespaces, hasNamespace, nl, evt, res = false, pos, nskeys
        ;
        topTopic = topics[ 0 ];
        namespaces = topics[ 2 ];
        nl = namespaces.length;
        topics = topics[ 1 ];
        tl = topics.length;
        evt = null;
        
        if ( tl > 0 ) 
        {
            evt = new PublishSubscribeEvent( target );
            evt.data.data = data;
            evt.originalTopic = topTopic ? topTopic.split( OTOPIC_SEP ) : [ ];
        }
        
        for (t=0; t<tl; t++)
        {
            subTopic = topics[ t ][ 0 ];
            tags = topics[ t ][ 1 ];
            evt.topic = subTopic ? subTopic.split( OTOPIC_SEP ) : [ ];
            evt.tags = tags ? tags.split( OTAG_SEP ) : [ ];
            hasNamespace = topics[ t ][ 2 ];
            subscribers = topics[ t ][ 3 ];
            // create a copy avoid mutation of pubsub during notifications
            subs = [ ];
            sl = subscribers.list.length;
            for (s=0; s<sl; s++)
            {
                subscriber = subscribers.list[ s ];
                if ( (!subscriber[ 1 ] || !subscriber[ 4 ]) && 
                    (!hasNamespace || 
                    (subscriber[ 2 ] && match_namespace(subscriber[ 2 ], namespaces, nl))) 
                ) 
                {
                    subs.push( subscriber );
                }
            }
            
            sl = subs.length;
            for (s=0; s<sl; s++)
            {
                subscriber = subs[ s ];
                //if ( subscriber[ 1 ] && subscriber[ 4 ] > 0 ) continue; // oneoff subscriber already called
                
                if ( hasNamespace ) evt.namespaces = subscriber[ 3 ].slice( 0 );
                else evt.namespaces = [ ];
                
                subscriber[ 4 ] = 1; // subscriber called
                
                res = subscriber[ 0 ]( evt );
                
                // stop event propagation
                if ( (false === res) || evt.stopped() || evt.aborted() ) break;
            }
            
            // unsubscribeOneOffs
            unsubscribe_oneoffs( subscribers );
            
            // stop event bubble propagation
            if ( evt.aborted() || !evt.propagates() ) break;
        }
        
        if ( evt ) 
        {
            evt.dispose( );
            evt = null;
        }
    }
}

function create_pipeline_loop( evt, topics, abort )
{
    var topTopic = topics[ 0 ],
        namespaces = topics[ 2 ],
        topics = topics[ 1 ];
    evt.non_local = new PublishSubscribeData({
        't': 0,
        's': 0,
        'start_topic': true,
        'subscribers': null,
        'topics': topics,
        'namespaces': namespaces,
        'hasNamespace': false,
        'abort': abort
    });
    evt.originalTopic = topTopic ? topTopic.split( OTOPIC_SEP ) : [ ];
    var pipeline_loop = function pipeline_loop( evt ) {
        var res, non_local = evt.non_local, subTopic, tags, subscriber, done;
        
        if (non_local.t < non_local.topics.length)
        {
            if (non_local.start_topic)
            {
                // unsubscribeOneOffs
                unsubscribe_oneoffs( non_local.subscribers );
                
                // stop event propagation
                if ( evt.aborted() || !evt.propagates() ) 
                {
                    if ( evt.aborted() && 'function' === typeof non_local.abort ) non_local.abort( evt );
                    return false;
                }
                
                subTopic = non_local.topics[ non_local.t ][ 0 ];
                tags = non_local.topics[ non_local.t ][ 1 ];
                evt.topic = subTopic ? subTopic.split( OTOPIC_SEP ) : [ ];
                evt.tags = tags ? tags.split( OTAG_SEP ) : [ ];
                non_local.hasNamespace = non_local.topics[ non_local.t ][ 2 ];
                non_local.subscribers = non_local.topics[ non_local.t ][ 3 ];
                non_local.s = 0;
                non_local.start_topic = false;
            }
            
            //if (non_local.subscribers) non_local.sl = non_local.subscribers.list.length;
            if (non_local.s < non_local.subscribers.list.length)
            {
                // stop event propagation
                if ( evt.aborted() || evt.stopped() ) 
                {
                    // unsubscribeOneOffs
                    unsubscribe_oneoffs( non_local.subscribers );
                    
                    if ( evt.aborted() && 'function' === typeof non_local.abort ) non_local.abort( evt );
                    return false;
                }
                
                done = false;
                while ( non_local.s < non_local.subscribers.list.length && !done )
                {
                    subscriber = non_local.subscribers.list[ non_local.s ];
                    if ( (!subscriber[ 1 ] || !subscriber[ 4 ]) && 
                        (!non_local.hasNamespace || 
                        (subscriber[ 2 ] && match_namespace(subscriber[ 2 ], non_local.namespaces, non_local.namespaces.length))) 
                    ) 
                    {
                        done = true;
                    }
                    non_local.s += 1;
                }
                if ( done )
                {
                    if ( non_local.hasNamespace ) evt.namespaces = subscriber[ 3 ].slice( 0 );
                    else evt.namespaces = [ ];
                    
                    subscriber[ 4 ] = 1; // subscriber called
                    res = subscriber[ 0 ]( evt );
                }
            }
            
            if (non_local.s >= non_local.subscribers.list.length)
            {
                non_local.t += 1;
                non_local.start_topic = true;
            }
        }
        else 
        {
            // unsubscribeOneOffs
            unsubscribe_oneoffs( non_local.subscribers );
            
            if ( evt )
            {
                evt.non_local.dispose();
                evt.non_local = null;
                evt.dispose();
                evt = null;
            }
        }
    };
    return pipeline_loop;
}

function pipeline( target, seps, pubsub, topic, data, abort )
{
    if ( pubsub )
    {
        var topics = get_subscribed_topics( seps, pubsub, topic ), evt = null, pipeline_loop;
        if ( topics[ 1 ].length > 0 ) 
        {
            evt = new PublishSubscribeEvent( target );
            evt.data.data = data;
            evt.pipeline( pipeline_loop = create_pipeline_loop( evt, topics, abort ) );
            pipeline_loop( evt );
        }
    }
}

function subscribe( seps, pubsub, topic, subscriber, oneOff, on1 )
{
    if ( pubsub && "function" === typeof(subscriber) )
    {
        topic = parse_topic( seps, topic );
        var tags = topic[1].join( OTAG_SEP ), tagslen = tags.length, entry, queue,
            _topic, _tag,
            namespaces = topic[2], nshash, namespaces_ref, n, nslen = namespaces.length;
        topic = topic[0].join( OTOPIC_SEP );
        oneOff = (true === oneOff);
        on1 = (true === on1);
        
        nshash = { };
        if ( nslen )
        {
            for (n=0; n<nslen; n++)
            {
                nshash['ns_'+namespaces[n]] = 1;
            }
        }
        namespaces_ref = namespaces.slice( 0 );
        
        queue = null;
        if ( topic.length )
        {
            _topic = 'tp_' + topic;
            if ( !pubsub.topics[HAS](_topic) ) 
                pubsub.topics[ _topic ] = { notags: {namespaces: {}, list: [], oneOffs: 0}, tags: {} };
            
            if ( tagslen )
            {
                _tag = 'tg_' + tags;
                if ( !pubsub.topics[ _topic ].tags[HAS](_tag) ) 
                    pubsub.topics[ _topic ].tags[ _tag ] = {namespaces: {}, list: [], oneOffs: 0};
                
                queue = pubsub.topics[ _topic ].tags[ _tag ];
            }
            else
            {
                queue = pubsub.topics[ _topic ].notags;
            }
        }
        else
        {
            if ( tagslen )
            {
                _tag = 'tg_' + tags;
                if ( !pubsub.notopics.tags[HAS](_tag) ) 
                    pubsub.notopics.tags[ _tag ] = {namespaces: {}, list: [], oneOffs: 0};
                
                queue = pubsub.notopics.tags[ _tag ];
            }
            else if ( nslen )
            {
                queue = pubsub.notopics.notags;
            }
        }
        if ( null !== queue )
        {
            entry = nslen 
                    ? [subscriber, oneOff, nshash, namespaces_ref, 0]
                    : [subscriber, oneOff, false, [], 0]
                ;
            if ( on1 ) queue.list.unshift( entry );
            else queue.list.push( entry );
            if ( oneOff ) queue.oneOffs++;
            if ( nslen ) update_namespaces( queue.namespaces, namespaces, nslen );
        }
    }
}

function remove_subscriber( pb, hasSubscriber, subscriber, namespaces, nslen )
{
    var pos = pb.list.length, nskeys;
    if ( hasSubscriber )
    {
        if ( (null != subscriber) && (pos > 0) )
        {
            while ( --pos >= 0 )
            {
                if ( subscriber === pb.list[pos][0] )  
                {
                    if ( nslen && pb.list[pos][2] && match_namespace( pb.list[pos][2], namespaces, nslen ) )
                    {
                        nskeys = KEYS(pb.list[pos][2]);
                        remove_namespaces( pb.namespaces, nskeys, nskeys.length );
                        if ( pb.list[pos][1] ) pb.oneOffs = pb.oneOffs > 0 ? (pb.oneOffs-1) : 0;
                        pb.list.splice( pos, 1 );
                    }
                    else if ( !nslen )
                    {
                        if ( pb.list[pos][2] ) 
                        {
                            nskeys = KEYS(pb.list[pos][2]);
                            remove_namespaces( pb.namespaces, nskeys, nskeys.length );
                        }
                        if ( pb.list[pos][1] ) pb.oneOffs = pb.oneOffs > 0 ? (pb.oneOffs-1) : 0;
                        pb.list.splice( pos, 1 );
                    }
                }
            }
        }
    }
    else if ( !hasSubscriber && (nslen > 0) && (pos > 0) )
    {
        while ( --pos >= 0 )
        {
            if ( pb.list[pos][2] && match_namespace( pb.list[pos][2], namespaces, nslen ) )
            {
                nskeys = KEYS(pb.list[pos][2]);
                remove_namespaces( pb.namespaces, nskeys, nskeys.length );
                if ( pb.list[pos][1] ) pb.oneOffs = pb.oneOffs > 0 ? (pb.oneOffs-1) : 0;
                pb.list.splice( pos, 1 );
            }
        }
    }
    else if ( !hasSubscriber && (pos > 0) )
    {
        pb.list = [ ];
        pb.oneOffs = 0;
        pb.namespaces = { };
    }
}

function unsubscribe( seps, pubsub, topic, subscriber )
{
    if ( pubsub )
    {
        topic = parse_topic( seps, topic );
        var t, t2, tags = topic[1].join( OTAG_SEP ), namespaces = topic[2],
            _topic, _tag,
            tagslen = tags.length, nslen = namespaces.length, topiclen,
            hasSubscriber
        ;
        topic = topic[0].join( OTOPIC_SEP );
        topiclen = topic.length;
        _topic = topiclen ? 'tp_' + topic : false; _tag = tagslen ? 'tg_' + tags : false;
        hasSubscriber = !!(subscriber && ("function" === typeof( subscriber )));
        if ( !hasSubscriber ) subscriber = null;
        
        if ( topiclen && pubsub.topics[HAS](_topic) )
        {
            if ( tagslen && pubsub.topics[ _topic ].tags[HAS](_tag) ) 
            {
                remove_subscriber( pubsub.topics[ _topic ].tags[ _tag ], hasSubscriber, subscriber, namespaces, nslen );
                if ( !pubsub.topics[ _topic ].tags[ _tag ].list.length )
                    delete pubsub.topics[ _topic ].tags[ _tag ];
            }
            else if ( !tagslen )
            {
                remove_subscriber( pubsub.topics[ _topic ].notags, hasSubscriber, subscriber, namespaces, nslen );
            }
            if ( !pubsub.topics[ _topic ].notags.list.length && !KEYS(pubsub.topics[ _topic ].tags).length )
                delete pubsub.topics[ _topic ];
        }
        else if ( !topiclen && (tagslen || nslen) )
        {
            if ( tagslen )
            {
                if ( pubsub.notopics.tags[HAS](_tag) )
                {
                    remove_subscriber( pubsub.notopics.tags[ _tag ], hasSubscriber, subscriber, namespaces, nslen );
                    if ( !pubsub.notopics.tags[ _tag ].list.length )
                        delete pubsub.notopics.tags[ _tag ];
                }
                
                // remove from any topics as well
                for ( t in pubsub.topics )
                {
                    if ( pubsub.topics[HAS](t) && pubsub.topics[ t ].tags[HAS](_tag) )
                    {
                        remove_subscriber( pubsub.topics[ t ].tags[ _tag ], hasSubscriber, subscriber, namespaces, nslen );
                        if ( !pubsub.topics[ t ].tags[ _tag ].list.length )
                            delete pubsub.topics[ t ].tags[ _tag ];
                    }
                }
            }
            else
            {
                remove_subscriber( pubsub.notopics.notags, hasSubscriber, subscriber, namespaces, nslen );
                
                // remove from any tags as well
                for ( t2 in pubsub.notopics.tags )
                {
                    if ( pubsub.notopics.tags[HAS](t2) )
                    {
                        remove_subscriber( pubsub.notopics.tags[ t2 ], hasSubscriber, subscriber, namespaces, nslen );
                        if ( !pubsub.notopics.tags[ t2 ].list.length )
                            delete pubsub.notopics.tags[ t2 ];
                    }
                }
                
                // remove from any topics and tags as well
                for ( t in pubsub.topics )
                {
                    if ( pubsub.topics[HAS](t) )
                    {
                        remove_subscriber( pubsub.topics[ t ].notags, hasSubscriber, subscriber, namespaces, nslen );
                        
                        for ( t2 in pubsub.topics[ t ].tags )
                        {
                            if ( pubsub.topics[ t ].tags[HAS](t2) )
                            {
                                remove_subscriber( pubsub.topics[ t ].tags[ t2 ], hasSubscriber, subscriber, namespaces, nslen );
                                if ( !pubsub.topics[ t ].tags[ t2 ].list.length )
                                    delete pubsub.topics[ t ].tags[ t2 ];
                            }
                        }
                    }
                }
            }
        }
    }
}

//
// PublishSubscribe (Interface)
var PublishSubscribe = function( ) { 
    if ( !(this instanceof PublishSubscribe) ) return new PublishSubscribe( );
    this.initPubSub( ); 
};
PublishSubscribe.VERSION = __version__;

PublishSubscribe.Event = PublishSubscribeEvent;

PublishSubscribe.Data = function( props ) {
    return new PublishSubscribeData(props);
};

PublishSubscribe[PROTO] = {
    constructor: PublishSubscribe
    
    ,_seps: null
    ,_pubsub$: null
    
    ,initPubSub: function( ) {
        var self = this;
        self._seps = [TOPIC_SEP, TAG_SEP, NS_SEP];
        self._pubsub$ = get_pubsub( );
        return self;
    }
    
    ,disposePubSub: function( ) {
        var self = this;
        self._seps = null;
        self._pubsub$ = null;
        return self;
    }
    
    ,setSeparators: function( seps ) {
        var self = this, l;
        if ( seps && (l=seps.length) )
        {
            if ( l > 0 && seps[0] ) self._seps[0] = seps[0];
            if ( l > 1 && seps[1] ) self._seps[1] = seps[1];
            if ( l > 2 && seps[2] ) self._seps[2] = seps[2];
        }
        return self;
    }
    
    ,trigger: function( message, data, delay ) {
        var self = this;
        if ( 3 > arguments.length ) delay = 0;
        delay = +delay;
        
        data = data || { };
        if ( delay > 0 )
        {
            setTimeout(function( ) {
                publish( self, self._seps, self._pubsub$, message, data );
            }, delay);
        }
        else
        {
            //console.log(JSON.stringify(self._pubsub$, null, 4));
            publish( self, self._seps, self._pubsub$, message, data );
            //console.log(JSON.stringify(self._pubsub$, null, 4));
        }
        return self;
    }
    
    ,pipeline: function( message, data, abort, delay ) {
        var self = this;
        if ( 4 > arguments.length ) delay = 0;
        delay = +delay;
        
        data = data || { };
        if ( delay > 0 )
        {
            setTimeout(function( ) {
                pipeline( self, self._seps, self._pubsub$, message, data, abort||null );
            }, delay);
        }
        else
        {
            //console.log(JSON.stringify(self._pubsub$, null, 4));
            pipeline( self, self._seps, self._pubsub$, message, data, abort||null );
            //console.log(JSON.stringify(self._pubsub$, null, 4));
        }
        return self;
    }
    
    ,on: function( message, callback ) {
        var self = this;
        if ( callback && "function" === typeof(callback) )
        {
            //console.log(JSON.stringify(self._pubsub$, null, 4));
            subscribe( self._seps, self._pubsub$, message, callback );
            //console.log(JSON.stringify(self._pubsub$, null, 4));
        }
        return self;
    }
    
    ,one: function( message, callback ) {
        var self = this;
        if ( callback && "function" === typeof(callback) )
        {
            //console.log(JSON.stringify(self._pubsub$, null, 4));
            subscribe( self._seps, self._pubsub$, message, callback, true );
            //console.log(JSON.stringify(self._pubsub$, null, 4));
        }
        return self;
    }
    
    ,on1: function( message, callback ) {
        var self = this;
        if ( callback && "function" === typeof(callback) )
        {
            //console.log(JSON.stringify(self._pubsub$, null, 4));
            subscribe( self._seps, self._pubsub$, message, callback, false, true );
            //console.log(JSON.stringify(self._pubsub$, null, 4));
        }
        return self;
    }
    
    ,one1: function( message, callback ) {
        var self = this;
        if ( callback && "function" === typeof(callback) )
        {
            //console.log(JSON.stringify(self._pubsub$, null, 4));
            subscribe( self._seps, self._pubsub$, message, callback, true, true );
            //console.log(JSON.stringify(self._pubsub$, null, 4));
        }
        return self;
    }
    
    ,off: function( message, callback ) {
        var self = this;
        //console.log(JSON.stringify(self._pubsub$, null, 4));
        unsubscribe( self._seps, self._pubsub$, message, callback || null );
        //console.log(JSON.stringify(self._pubsub$, null, 4));
        return self;
    }
};

// export it
return PublishSubscribe;
});