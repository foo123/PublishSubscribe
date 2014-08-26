/**
*  PublishSubscribe
*  A simple publish-subscribe implementation for PHP, Python, Node/JS
*
*  @version: 0.3.2
*  https://github.com/foo123/PublishSubscribe
*
**/
!function (root, moduleName, moduleDefinition) {

    //
    // export the module
    
    // node, CommonJS, etc..
    if ( 'object' === typeof(module) && module.exports ) module.exports = moduleDefinition();
    
    // AMD, etc..
    else if ( 'function' === typeof(define) && define.amd ) define( moduleDefinition );
    
    // browser, etc..
    else root[ moduleName ] = moduleDefinition();


}(this, 'PublishSubscribe', function( undef ) {
    
    "use strict";
    
    var __version__ = "0.3.2", 
        TOPIC_SEP = '/', TAG_SEP = '#', NS_SEP = '@',
        OTOPIC_SEP = '/', OTAG_SEP = '#', ONS_SEP = '@',
        KEYS = Object.keys;
    
    function PublishSubscribeEvent(topic, original, tags, namespaces)
    {
        var self = this;
        if ( topic )  self.topic = [].concat( topic );
        else self.topic = [ ];
        if ( original )  self.originalTopic = [].concat( original );
        if ( tags )  self.tags = [].concat( tags );
        else self.tags = [ ];
        if ( namespaces )  self.namespaces = [].concat( namespaces );
        else self.namespaces = [ ];
        self.data = { };
        self._stopPropagation = false;
        self._stopEvent = false;
    }
    PublishSubscribeEvent.prototype = {
        constructor: PublishSubscribeEvent,
        topic: null,
        originalTopic: null,
        tags: null,
        namespaces: null,
        data: null,
        _stopPropagation: false,
        _stopEvent: false,
        
        dispose: function( ) {
            var self = this;
            self.topic = null;
            self.originalTopic = null;
            self.tags = null;
            self.namespaces = null;
            self.data = null;
            self._stopPropagation = true;
            self._stopEvent = true;
            return self;
        },
        
        propagate: function( enable ) {
            if ( !arguments.length ) enable = true;
            this._stopPropagation = !enable;
            return this;
        },
        
        stop: function( enable ) {
            if ( !arguments.length ) enable = true;
            this._stopEvent = !!enable;
            return this;
        },
        
        propagationStopped: function( ) {
            return this._stopPropagation;
        },
        
        eventStopped: function( ) {
            return this._stopEvent;
        }
    };
    
    function getPubSub( ) { return { notopics: { notags: {namespaces: {}, list: []}, tags: {} }, topics: {} }; }
    
    function notEmpty( s ) { return s.length > 0; }
    
    function parseTopic( seps, topic )
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
                .filter( notEmpty )
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
                    .filter( notEmpty )
                    .sort( )
            ;
            topic = topic.slice( 0, tagspos );
        }
        else
        {
            tags = [ ];
        }
        topic = topic.split( seps[0] ).filter( notEmpty );
        return [topic, tags, namespaces];
    }
    
    function getAllTopics( seps, topic ) 
    { 
        var topics = [ ], tags = [ ], namespaces/* = [ ]*/, 
            ttags, tns, l, i, j, jj, tmp, combinations;
        
        topic = parseTopic( seps, topic );
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
    
    function updateNamespaces( pbns, namespaces, nl )
    {
        var n, ns;
        for (n=0; n<nl; n++)
        {
            ns = namespaces[n];
            if ( !(ns in pbns) )
            {
                pbns[ ns ] = 1;
            }
            else
            {
                pbns[ ns ]++;
            }
        }
    }
    
    function removeNamespaces( pbns, namespaces, nl )
    {
        var n, ns;
        for (n=0; n<nl; n++)
        {
            ns = namespaces[n];
            if ( ns in pbns )
            {
                pbns[ ns ]--;
                if ( pbns[ ns ] <=0 )
                    delete pbns[ ns ];
            }
        }
    }
    
    function matchNamespace( pbns, namespaces, nl )
    {
        var n, ns;
        for (n=0; n<nl; n++)
        {
            ns = namespaces[n];
            if ( !(ns in pbns) || (0 >= pbns[ ns ]) ) return false;
        }
        return true;
    }
    
    function checkIsSubscribed( pubsub, subscribedTopics, topic, tag, namespaces, nl )
    {
        if ( topic )
        {
            if ( tag )
            {
                if ( nl > 0 )
                {
                    if ( (tag in pubsub.topics[ topic ].tags) && 
                        pubsub.topics[ topic ].tags[ tag ].list.length &&
                        matchNamespace( pubsub.topics[ topic ].tags[ tag ].namespaces, namespaces, nl ) )
                    {
                        subscribedTopics.push( [topic, tag, true, pubsub.topics[ topic ].tags[ tag ]] );
                        return true;
                    }
                }
                else
                {
                    if ( (tag in pubsub.topics[ topic ].tags) && pubsub.topics[ topic ].tags[ tag ].list.length )
                    {
                        subscribedTopics.push( [topic, tag, null, pubsub.topics[ topic ].tags[ tag ]] );
                        return true;
                    }
                }
            }
            else
            {
                if ( nl > 0 )
                {
                    if ( pubsub.topics[ topic ].notags.list.length && 
                        matchNamespace( pubsub.topics[ topic ].notags.namespaces, namespaces, nl ) )
                    {
                        subscribedTopics.push( [topic, null, true, pubsub.topics[ topic ].notags] );
                        return true;
                    }
                }
                else
                {
                    if ( pubsub.topics[ topic ].notags.list.length )
                    {
                        subscribedTopics.push( [topic, null, null, pubsub.topics[ topic ].notags] );
                        return true;
                    }
                }
            }
        }
        else
        {
            if ( tag )
            {
                if ( nl > 0 )
                {
                    if ( (tag in pubsub.notopics.tags) && 
                        pubsub.notopics.tags[ tag ].list.length &&
                        matchNamespace( pubsub.notopics.tags[ tag ].namespaces, namespaces, nl ) )
                    {
                        subscribedTopics.push( [null, tag, true, pubsub.notopics.tags[ tag ]] );
                        return true;
                    }
                }
                else
                {
                    if ( (tag in pubsub.notopics.tags) && pubsub.notopics.tags[ tag ].list.length )
                    {
                        subscribedTopics.push( [null, tag, null, pubsub.notopics.tags[ tag ]] );
                        return true;
                    }
                }
            }
            else
            {
                if ( nl > 0 )
                {
                    if ( pubsub.notopics.notags.list.length &&
                        matchNamespace( pubsub.notopics.notags.namespaces, namespaces, nl ) )
                    {
                        subscribedTopics.push( [null, null, true, pubsub.notopics.notags] );
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
    
    function getSubscribedTopics( seps, pubsub, atopic )
    {
        var all = getAllTopics( seps, atopic ), l, topic, tag, ns,
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
                topic = topics[ 0 ];
                if ( pubsub.topics[ topic ] ) 
                {
                    if ( tl > 0 )
                    {
                        for (t=0; t<tl; t++)
                        {
                            tag = tags[ t ];
                            checkIsSubscribed( pubsub, subscribedTopics, topic, tag, namespaces, nl );
                        }
                    }
                    else
                    {
                        checkIsSubscribed( pubsub, subscribedTopics, topic, null, namespaces, nl );
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
                checkIsSubscribed( pubsub, subscribedTopics, null, tag, namespaces, nl );
            }
        }
        checkIsSubscribed( pubsub, subscribedTopics, null, null, namespaces, nl );
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
    
    function publish( seps, pubsub, topic, data )
    {
        if ( pubsub )
        {
            var topics = getSubscribedTopics( seps, pubsub, topic ), 
                t, s, tl, sl, subs, subscribers, subscriber, topTopic, subTopic,
                tags, namespaces, hasNamespace, nl, evt, oneOffs, res, pos, nskeys
            ;
            topTopic = topics[ 0 ];
            namespaces = topics[ 2 ];
            nl = namespaces.length;
            topics = topics[ 1 ];
            tl = topics.length;
            evt = null;
            
            if ( tl > 0 ) 
            {
                evt = new PublishSubscribeEvent( );
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
                oneOffs = [ ];
                sl = subscribers.list.length;
                for (s=0; s<sl; s++)
                {
                    if ( !hasNamespace || (subscribers.list[ s ][ 2 ] && matchNamespace(subscribers.list[ s ][ 2 ], namespaces, nl)) ) 
                    {
                        if ( subscribers.list[ s ][ 1 ] ) oneOffs.push( s );
                        subs.push( subscribers.list[ s ] );
                    }
                }
                
                // unsubscribeOneOffs
                while ( oneOffs.length )
                {
                    pos = oneOffs.pop( );
                    if ( subscribers.list[ pos ][ 2 ] )
                    {
                        nskeys = KEYS(subscribers.list[ pos ][ 2 ]);
                        removeNamespaces( subscribers.namespaces, nskeys, nskeys.length );
                    }
                    subscribers.list.splice( pos, 1 );
                }
                
                sl = subs.length;
                for (s=0; s<sl; s++)
                {
                    subscriber = subs[ s ];
                    if ( hasNamespace ) evt.namespaces = subscriber[ 3 ].slice( 0 );
                    else evt.namespaces = [ ];
                    res = subscriber[ 0 ]( evt, data );
                    // stop event propagation
                    if ( (false === res) || evt.eventStopped() ) break;
                }
                
                // stop event bubble propagation
                if ( evt.propagationStopped() ) break;
            }
            
            if ( evt ) 
            {
                evt.dispose( );
                evt = null;
            }
        }
    }
    
    function subscribe( seps, pubsub, topic, subscriber, oneOff, on1 )
    {
        if ( pubsub && "function" === typeof(subscriber) )
        {
            topic = parseTopic( seps, topic );
            var tags = topic[1].join( OTAG_SEP ), tagslen = tags.length,
                namespaces = topic[2], nshash, namespaces_ref, n, nslen = namespaces.length;
            topic = topic[0].join( OTOPIC_SEP );
            oneOff = (true === oneOff);
            on1 = (true === on1);
            
            nshash = { };
            if ( nslen )
            {
                for (n=0; n<nslen; n++)
                {
                    nshash[namespaces[n]] = 1;
                }
            }
            namespaces_ref = namespaces.slice( 0 );
            
            if ( topic.length )
            {
                if ( !(topic in pubsub.topics) ) 
                    pubsub.topics[ topic ] = { notags: {namespaces: {}, list: []}, tags: {} };
                if ( tagslen )
                {
                    if ( !(tags in pubsub.topics[ topic ].tags) ) 
                        pubsub.topics[ topic ].tags[ tags ] = {namespaces: {}, list: []};
                    if ( nslen )
                    {
                        if ( on1 )
                            pubsub.topics[ topic ].tags[ tags ].list.unshift( [subscriber, oneOff, nshash, namespaces_ref] );
                        else
                            pubsub.topics[ topic ].tags[ tags ].list.push( [subscriber, oneOff, nshash, namespaces_ref] );
                        updateNamespaces( pubsub.topics[ topic ].tags[ tags ].namespaces, namespaces, nslen );
                    }
                    else
                    {
                        if ( on1 )
                            pubsub.topics[ topic ].tags[ tags ].list.unshift( [subscriber, oneOff, false, []] );
                        else
                            pubsub.topics[ topic ].tags[ tags ].list.push( [subscriber, oneOff, false, []] );
                    }
                }
                else
                {
                    if ( nslen )
                    {
                        if ( on1 )
                            pubsub.topics[ topic ].notags.list.unshift( [subscriber, oneOff, nshash, namespaces_ref] );
                        else
                            pubsub.topics[ topic ].notags.list.push( [subscriber, oneOff, nshash, namespaces_ref] );
                        updateNamespaces( pubsub.topics[ topic ].notags.namespaces, namespaces, nslen );
                    }
                    else
                    {
                        if ( on1 )
                            pubsub.topics[ topic ].notags.list.unshift( [subscriber, oneOff, false, []] );
                        else
                            pubsub.topics[ topic ].notags.list.push( [subscriber, oneOff, false, []] );
                    }
                }
            }
            else
            {
                if ( tagslen )
                {
                    if ( !(tags in pubsub.notopics.tags) ) 
                        pubsub.notopics.tags[ tags ] = {namespaces: {}, list: []};
                    if ( nslen )
                    {
                        if ( on1 )
                            pubsub.notopics.tags[ tags ].list.unshift( [subscriber, oneOff, nshash, namespaces_ref] );
                        else
                            pubsub.notopics.tags[ tags ].list.push( [subscriber, oneOff, nshash, namespaces_ref] );
                        updateNamespaces( pubsub.notopics.tags[ tags ].namespaces, namespaces, nslen );
                    }
                    else
                    {
                        if ( on1 )
                            pubsub.notopics.tags[ tags ].list.unshift( [subscriber, oneOff, false, []] );
                        else
                            pubsub.notopics.tags[ tags ].list.push( [subscriber, oneOff, false, []] );
                    }
                }
                else if ( nslen )
                {
                    if ( on1 )
                        pubsub.notopics.notags.list.unshift( [subscriber, oneOff, nshash, namespaces_ref] );
                    else
                        pubsub.notopics.notags.list.push( [subscriber, oneOff, nshash, namespaces_ref] );
                    updateNamespaces( pubsub.notopics.notags.namespaces, namespaces, nslen );
                }
            }
        }
    }
    
    function removeSubscriber( pb, hasSubscriber, subscriber, namespaces, nslen )
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
                        if ( nslen && pb.list[pos][2] && matchNamespace( pb.list[pos][2], namespaces, nslen ) )
                        {
                            nskeys = KEYS(pb.list[pos][2]);
                            removeNamespaces( pb.namespaces, nskeys, nskeys.length );
                            pb.list.splice( pos, 1 );
                        }
                        else if ( !nslen )
                        {
                            if ( pb.list[pos][2] ) 
                            {
                                nskeys = KEYS(pb.list[pos][2]);
                                removeNamespaces( pb.namespaces, nskeys, nskeys.length );
                            }
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
                if ( pb.list[pos][2] && matchNamespace( pb.list[pos][2], namespaces, nslen ) )
                {
                    nskeys = KEYS(pb.list[pos][2]);
                    removeNamespaces( pb.namespaces, nskeys, nskeys.length );
                    pb.list.splice( pos, 1 );
                }
            }
        }
        else if ( !hasSubscriber && (pos > 0) )
        {
            pb.list = [ ];
            pb.namespaces = { };
        }
    }
    
    function unsubscribe( seps, pubsub, topic, subscriber )
    {
        if ( pubsub )
        {
            topic = parseTopic( seps, topic );
            var t, t2, tags = topic[1].join( OTAG_SEP ), namespaces = topic[2],
                tagslen = tags.length, nslen = namespaces.length, topiclen,
                hasSubscriber
            ;
            topic = topic[0].join( OTOPIC_SEP );
            topiclen = topic.length;
            hasSubscriber = !!(subscriber && ("function" === typeof( subscriber )));
            if ( !hasSubscriber ) subscriber = null;
            
            if ( topiclen && (topic in pubsub.topics) )
            {
                if ( tagslen && (tags in pubsub.topics[ topic ].tags) ) 
                {
                    removeSubscriber( pubsub.topics[ topic ].tags[ tags ], hasSubscriber, subscriber, namespaces, nslen );
                    if ( !pubsub.topics[ topic ].tags[ tags ].list.length )
                        delete pubsub.topics[ topic ].tags[ tags ];
                }
                else if ( !tagslen )
                {
                    removeSubscriber( pubsub.topics[ topic ].notags, hasSubscriber, subscriber, namespaces, nslen );
                }
                if ( !pubsub.topics[ topic ].notags.list.length && !KEYS(pubsub.topics[ topic ].tags).length )
                    delete pubsub.topics[ topic ];
            }
            else if ( !topiclen && (tagslen || nslen) )
            {
                if ( tagslen )
                {
                    if ( tags in pubsub.notopics.tags )
                    {
                        removeSubscriber( pubsub.notopics.tags[ tags ], hasSubscriber, subscriber, namespaces, nslen );
                        if ( !pubsub.notopics.tags[ tags ].list.length )
                            delete pubsub.notopics.tags[ tags ];
                    }
                    
                    // remove from any topics as well
                    for ( t in pubsub.topics )
                    {
                        if ( tags in pubsub.topics[ t ].tags )
                        {
                            removeSubscriber( pubsub.topics[ t ].tags[ tags ], hasSubscriber, subscriber, namespaces, nslen );
                            if ( !pubsub.topics[ t ].tags[ tags ].list.length )
                                delete pubsub.topics[ t ].tags[ tags ];
                        }
                    }
                }
                else
                {
                    removeSubscriber( pubsub.notopics.notags, hasSubscriber, subscriber, namespaces, nslen );
                    
                    // remove from any tags as well
                    for ( t2 in pubsub.notopics.tags )
                    {
                        removeSubscriber( pubsub.notopics.tags[ t2 ], hasSubscriber, subscriber, namespaces, nslen );
                        if ( !pubsub.notopics.tags[ t2 ].list.length )
                            delete pubsub.notopics.tags[ t2 ];
                    }
                    
                    // remove from any topics and tags as well
                    for ( t in pubsub.topics )
                    {
                        removeSubscriber( pubsub.topics[ t ].notags, hasSubscriber, subscriber, namespaces, nslen );
                        
                        for ( t2 in pubsub.topics[ t ].tags )
                        {
                            removeSubscriber( pubsub.topics[ t ].tags[ t2 ], hasSubscriber, subscriber, namespaces, nslen );
                            if ( !pubsub.topics[ t ].tags[ t2 ].list.length )
                                delete pubsub.topics[ t ].tags[ t2 ];
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
    PublishSubscribe.prototype = {
        constructor: PublishSubscribe
        
        ,_seps: null
        ,_pubsub$: null
        
        ,initPubSub: function( ) {
            var self = this;
            self._seps = [TOPIC_SEP, TAG_SEP, NS_SEP];
            self._pubsub$ = getPubSub( );
            return self;
        }
        
        ,disposePubSub: function( ) {
            var self = this;
            self._seps = null;
            self._pubsub$ = null;
            return self;
        }
        
        ,setSeparators: function( seps ) {
            if ( seps )
            {
                var l = seps.length;
                if ( l > 0 && seps[0] ) this._seps[0] = seps[0];
                if ( l > 1 && seps[1] ) this._seps[1] = seps[1];
                if ( l > 2 && seps[2] ) this._seps[2] = seps[2];
            }
            return this;
        }
        
        ,trigger: function( message, data, delay ) {
            var self = this;
            if ( 3 > arguments.length ) delay = 0;
            delay = +delay;
            
            data = data || { };
            if ( delay > 0 )
            {
                setTimeout(function( ) {
                    publish( self._seps, self._pubsub$, message, data );
                }, delay);
            }
            else
            {
                //console.log(JSON.stringify(self._pubsub$, null, 4));
                publish( self._seps, self._pubsub$, message, data );
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