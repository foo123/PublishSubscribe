# -*- coding: UTF-8 -*-
##
#  PublishSubscribe
#  A simple publish-subscribe implementation for PHP, Python, Node/JS
#
#  @version: 0.4.1
#  https://github.com/foo123/PublishSubscribe
#
##

import time    
#import pprint

TOPIC_SEP = '/' 
TAG_SEP = '#'
NS_SEP = '@'
OTOPIC_SEP = '/' 
OTAG_SEP = '#'
ONS_SEP = '@'

class PublishSubscribeData:
    
    def __init__(self, props=None):
        if props:
            for k,v in props.items(): setattr(self, k, v)
    
    def __del__(self):
        self.dispose()
        
    def dispose(self, props=None):
        if props:
            for k in props: setattr(self, k, None)
        return self
            
            
class PublishSubscribeEvent:
    
    def __init__( self, target=None, topic=None, original=None, tags=None, namespaces=None ):
        self.target = target
        if topic: self.topic = topic
        else: self.topic = []
        if original: self.originalTopic = original
        else: self.originalTopic = []
        if tags: self.tags = tags
        else: self.tags = []
        if namespaces: self.namespaces = namespaces
        else: self.namespaces = []
        self.data = PublishSubscribeData()
        self.timestamp = int(round(time.time() * 1000))
        self._propagates = True
        self._stopped = False
        self._aborted = False
        self.is_pipelined = False
        self._next = None
    
    def __del__(self):
        self.dispose()
        
    def dispose( self ):
        self.target = None
        self.topic = None
        self.originalTopic = None
        self.tags = None
        self.namespaces = None
        if isinstance(self.data, PublishSubscribeData): self.data.dispose()
        self.data = None
        self.timestamp = None
        self.is_pipelined = False
        self._propagates = False
        self._stopped = True
        self._aborted = False
        self._next = None
        return self
    
    def next( self ):
        if callable(self._next): self._next(self)
        return self
    
    def pipeline( self, next=None ):
        if callable(next):
            self._next = next
            self.is_pipelined = True
        else:
            self._next = None
            self.is_pipelined = False
        return self
    
    def propagate( self, enable=True ):
        self._propagates = bool(enable)
        return self
    
    def stop( self, enable=True ):
        self._stopped = bool(enable)
        return self
    
    def abort( self, enable=True ):
        self._aborted = bool(enable)
        return self
    
    def propagates( self ):
        return self._propagates
    
    def aborted( self ):
        return self._aborted
    
    def stopped( self ):
        return self._stopped


def get_pubsub( ): 
    return { 'notopics': { 'notags': {'namespaces': {}, 'list': [], 'oneOffs': 0}, 'tags': {} }, 'topics': {} }
    
def not_empty( s ): 
    return len(s) > 0
    

def parse_topic( seps, topic ):
    nspos = topic.find( seps[2] )
    tagspos = topic.find( seps[1] )
    
    if -1 < nspos:
        namespaces = [x for x in topic[nspos:].split( seps[2] ) if not_empty(x)]
        namespaces = sorted( namespaces )
        topic = topic[0:nspos]
    else:
        namespaces = [ ]
    
    if -1 < tagspos:
        tags = [x for x in topic[tagspos:].split( seps[1] ) if not_empty(x)]
        tags = sorted( tags )
        topic = topic[0:tagspos]
    else:
        tags = [ ]
    
    topic = [x for x in topic.split( seps[0] ) if not_empty(x)]
    return [topic, tags, namespaces]


def get_all_topics( seps, topic ): 
    topics = [ ]
    tags = [ ]
    #namespaces = [ ] 
    
    topic = parse_topic( seps, topic )
    #tns = topic[2]
    namespaces = topic[2]
    ttags = topic[1]
    topic = topic[0]
    
    l = len(topic)
    while l:
        topics.append( OTOPIC_SEP.join(topic) )
        topic.pop( )
        l -= 1
    
    l = len(ttags)
    if l > 1:
        combinations = (1 << l)
        combrange = range(combinations-1, 1, -1)
        lrange = range(l)
        for i in combrange:
            tmp = [ ]
            for j in lrange:
                jj = (1 << j)
                if (i != jj) and (i & jj):
                    tmp.append( ttags[ j ] )
            if len(tmp):
                tags.append( OTAG_SEP.join( tmp ) )
        tags = tags + ttags
    elif l: tags.append( ttags[ 0 ] )
    
    #l = len(tns)
    #if l > 1:
    #    combinations = (1 << l)
    #    combrange = range(combinations-1, 1, -1)
    #    lrange = range(l)
    #    for i in combrange:
    #        tmp = [ ]
    #        for j in lrange:
    #            jj = (1 << j)
    #            if (i != jj) and (i & jj):
    #                tmp.append( tns[ j ] )
    #        if len(tmp):
    #            namespaces.append( ONS_SEP.join( tmp ) )
    #    namespaces = namespaces + tns
    #elif l and len(tns[0]): namespaces.append( tns[ 0 ] )
    
    topTopic = topics[0] if len(topics) else ''
    return [topTopic, topics, tags, namespaces]


def update_namespaces( pbns, namespaces, nl=0 ):
    for ns in namespaces:
        ns = 'ns_' + ns
        if not (ns in pbns):
            pbns[ ns ] = 1
        else:
            pbns[ ns ] += 1


def remove_namespaces( pbns, namespaces, nl=0 ):
    for ns in namespaces:
        ns = 'ns_' + ns
        if ns in pbns:
            pbns[ ns ] -= 1
            if pbns[ ns ] <=0:
                del pbns[ ns ]


def match_namespace( pbns, namespaces, nl=0 ):
    for ns in namespaces:
        ns = 'ns_' + ns
        if (ns not in pbns) or (0 >= pbns[ ns ]): return False
    return True


def check_is_subscribed( pubsub, subscribedTopics, topic, tag, namespaces, nl ):
    _topic = 'tp_' + topic if topic else False
    _tag = 'tg_' + tag if tag else False
    
    if _topic and (_topic in pubsub['topics']):
        if _tag and (_tag in pubsub['topics'][ _topic ]['tags']):
            if not_empty(pubsub['topics'][ _topic ]['tags'][ _tag ]['list']) and (nl <= 0 or match_namespace( pubsub['topics'][ _topic ]['tags'][ _tag ]['namespaces'], namespaces, nl )):
                subscribedTopics.append( [topic, tag, nl > 0, pubsub['topics'][ _topic ]['tags'][ _tag ]] )
                return True
        
        else:
            if not_empty(pubsub['topics'][ _topic ]['notags']['list']) and (nl <= 0 or match_namespace( pubsub['topics'][ _topic ]['notags']['namespaces'], namespaces, nl )):
                subscribedTopics.append( [topic, None, nl > 0, pubsub['topics'][ +topic ]['notags']] )
                return True
    
    else:
        if _tag and (_tag in pubsub['notopics']['tags']):
            if not_empty(pubsub['notopics']['tags'][ _tag ]['list']) and (nl <= 0 or match_namespace( pubsub['notopics']['tags'][ _tag ]['namespaces'], namespaces, nl )):
                subscribedTopics.append( [None, tag, nl > 0, pubsub['notopics']['tags'][ _tag ]] )
                return True
        
        else:
            if not_empty(pubsub['notopics']['notags']['list']) and (nl > 0 and match_namespace( pubsub['notopics']['notags']['namespaces'], namespaces, nl )):
                subscribedTopics.append( [None, None, True, pubsub['notopics']['notags']] )
                return True
            # else no topics no tags no namespaces, do nothing
    
    return False


def get_subscribed_topics( seps, pubsub, atopic ):
    all = get_all_topics( seps, atopic )
    topics = all[ 1 ]
    tags = all[ 2 ]
    namespaces = all[ 3 ] 
    topTopic = all[ 0 ] 
    subscribedTopics = [ ]
    tl = len(tags)
    nl = len(namespaces)
    l = len(topics)
    
    if l:
        while l:
            topic = topics[ 0 ]
            if ('tp_'+topic) in  pubsub['topics']:
                if tl > 0:
                    for tag in tags:
                        check_is_subscribed( pubsub, subscribedTopics, topic, tag, namespaces, nl )
                else:
                    check_is_subscribed( pubsub, subscribedTopics, topic, None, namespaces, nl )
            topics.pop( 0 )
            l -= 1
    
    if tl > 0:
        for tag in tags:
            check_is_subscribed( pubsub, subscribedTopics, None, tag, namespaces, nl )
    
    check_is_subscribed( pubsub, subscribedTopics, None, None, namespaces, nl )
    
    return [topTopic, subscribedTopics, namespaces]

def unsubscribe_oneoffs( subscribers ):
    if subscribers and ('list' in subscribers) and len(subscribers['list']) > 0:
        if len(subscribers['list']) > 0:
            if subscribers['oneOffs'] > 0:
                subs = subscribers['list']
                for s in range(len(subs)-1,-1,-1):
                    subscriber = subs[ s ]
                    if subscriber[1] and subscriber[4] > 0:
                        del subs[s:s+1]
                        subscribers['oneOffs'] = subscribers['oneOffs']-1 if subscribers['oneOffs'] > 0 else 0
            else: subscribers['oneOffs'] = 0
    return subscribers


def publish( target, seps, pubsub, topic, data ):
    if pubsub:
        topics = get_subscribed_topics( seps, pubsub, topic )
        topTopic = topics[ 0 ]
        namespaces = topics[ 2 ]
        topics = topics[ 1 ]
        tl = len(topics)
        evt = None
        res = False
        
        if tl > 0:
            evt = PublishSubscribeEvent( target )
            evt.data.data = data
            evt.originalTopic = topTopic.split(OTOPIC_SEP) if topTopic else []
            
        for t in topics:
            subTopic = t[ 0 ]
            tags = t[ 1 ]
            evt.topic = subTopic.split(OTOPIC_SEP) if subTopic else []
            evt.tags = tags.split(OTAG_SEP) if tags else []
            hasNamespace = t[ 2 ]
            subscribers = t[ 3 ]
            # create a copy avoid mutation of pubsub during notifications
            subs = [ ]
            sl = len(subscribers['list'])
            slr = range(sl)
            for s in slr:
                subscriber = subscribers['list'][ s ]
                if ((not subscriber[ 1 ]) or (not subscriber[ 4 ])) and ((not hasNamespace) or (subscriber[ 2 ] and match_namespace(subscriber[ 2 ], namespaces))):
                    subs.append( subscriber )
            
            for subscriber in subs:
                #if subscriber[ 1 ] and subscriber[ 4 ] > 0: continue # oneoff subscriber already called
                
                if hasNamespace: evt.namespaces = subscriber[ 3 ][:]
                else: evt.namespaces = []
                
                subscriber[ 4 ] = 1 # subscriber called
                
                res = subscriber[ 0 ]( evt )
                
                # stop event propagation
                if (False == res) or evt.stopped() or evt.aborted(): break
            
            # unsubscribeOneOffs
            unsubscribe_oneoffs( subscribers )
                    
            # stop event bubble propagation
            if evt.aborted() or not evt.propagates(): break
        
        if evt:
            evt.dispose( )
            evt = None
        


def create_pipeline_loop(evt, topics, abort):
    topTopic = topics[ 0 ]
    namespaces = topics[ 2 ]
    topics = topics[ 1 ]
    evt.non_local = PublishSubscribeData({
        't': 0,
        's': 0,
        'start_topic': True,
        'subscribers': None,
        'topics': topics,
        'namespaces': namespaces,
        'hasNamespace': False,
        'abort': abort
    })
    evt.originalTopic = topTopic.split(OTOPIC_SEP) if topTopic else []
    
    def pipeline_loop( evt ):
        non_local = evt.non_local
        
        if non_local.t < len(non_local.topics):
            if non_local.start_topic:
                
                # unsubscribeOneOffs
                unsubscribe_oneoffs( non_local.subscribers )
                
                # stop event propagation
                if evt.aborted() or not evt.propagates():
                    if evt.aborted() and callable(non_local.abort): non_local.abort( evt )
                    return False
                    
                subTopic = non_local.topics[non_local.t][ 0 ]
                tags = non_local.topics[non_local.t][ 1 ]
                evt.topic = subTopic.split(OTOPIC_SEP) if subTopic else []
                evt.tags = tags.split(OTAG_SEP) if tags else []
                non_local.hasNamespace = non_local.topics[non_local.t][ 2 ]
                non_local.subscribers = non_local.topics[non_local.t][ 3 ]
                non_local.s = 0
                non_local.start_topic = False
            
            #if non_local['subscribers']: non_local['sl'] = len(non_local['subscribers']['list'])
            if non_local.s<len(non_local.subscribers['list']):
                
                # stop event propagation
                if evt.aborted() or evt.stopped():
                    # unsubscribeOneOffs
                    unsubscribe_oneoffs( non_local.subscribers )
                    
                    if evt.aborted() and callable(non_local.abort): non_local.abort( evt )
                    return False
                    
                done = False
                while non_local.s<len(non_local.subscribers['list']) and not done:
                    subscriber = non_local.subscribers['list'][ non_local.s ]
                    
                    if ((not subscriber[ 1 ]) or (not subscriber[ 4 ])) and ((not non_local.hasNamespace) or (subscriber[ 2 ] and match_namespace(subscriber[ 2 ], non_local.namespaces))):
                        
                        done = True
                    
                    non_local.s += 1
                if done:
                    if non_local.hasNamespace: evt.namespaces = subscriber[ 3 ][:]
                    else: evt.namespaces = []
                    
                    subscriber[ 4 ] = 1 # subscriber called
                    res = subscriber[ 0 ]( evt )
                    
            if non_local.s>=len(non_local.subscribers['list']):
                non_local.t += 1
                non_local.start_topic = True
            
        else:
            # unsubscribeOneOffs
            unsubscribe_oneoffs( non_local.subscribers )
            
            if evt:
                evt.non_local.dispose()
                evt.non_local = None
                evt.dispose()
                evt = None
    
    return pipeline_loop
    
    
def pipeline( target, seps, pubsub, topic, data, abort=None ):
    if pubsub:
        topics = get_subscribed_topics( seps, pubsub, topic )
        
        if len(topics[ 1 ]) > 0:
            evt = PublishSubscribeEvent( target )
            evt.data.data = data
            pipeline_loop = create_pipeline_loop(evt, topics, abort)
            evt.pipeline( pipeline_loop )
            pipeline_loop( evt )
        

def subscribe( seps, pubsub, topic, subscriber, oneOff=False, on1=False ):
    if pubsub and callable(subscriber):
        topic = parse_topic( seps, topic )
        tags = OTAG_SEP.join( topic[1] ) 
        tagslen = len(tags)
        namespaces = topic[2]
        nslen = len(namespaces)
        topic = OTOPIC_SEP.join( topic[0] )
        oneOff = (True == oneOff)
        on1 = (True == on1)
        nshash = { }
        if nslen:
            for ns in namespaces: nshash['ns_'+ns] = 1
        namespaces_ref = namespaces[:]
        
        queue = None
        if len(topic):
            _topic = 'tp_' + topic
            if not _topic in pubsub['topics']: 
                pubsub['topics'][ _topic ] = { 'notags': {'namespaces': {}, 'list': [], 'oneOffs': 0}, 'tags': {} }
            if tagslen:
                _tag = 'tg_' + tags
                if not _tag in pubsub['topics'][ _topic ]['tags']: 
                    pubsub['topics'][ _topic ]['tags'][ _tag ] = {'namespaces': {}, 'list': [], 'oneOffs': 0}
                
                queue = pubsub['topics'][ _topic ]['tags'][ _tag ]
            else:
                queue = pubsub['topics'][ _topic ]['notags']
        
        else:
            if tagslen:
                _tag = 'tg_' + tags
                if not _tag in pubsub['notopics']['tags']: 
                    pubsub['notopics']['tags'][ _tag ] = {'namespaces': {}, 'list': [], 'oneOffs': 0}
                
                queue = pubsub['notopics']['tags'][ _tag ]
                    
            elif nslen:
                queue = pubsub['notopics']['notags']

        if queue is not None:
            entry = [subscriber, oneOff, nshash, namespaces_ref, 0] if nslen else [subscriber, oneOff, False, [], 0]
            if on1: queue['list'].insert( 0, entry )
            else: queue['list'].append( entry )
            if oneOff: queue['oneOffs'] += 1
            if nslen: update_namespaces( queue['namespaces'], namespaces, nslen )


def remove_subscriber( pb, hasSubscriber, subscriber, namespaces, nslen ):
    pos = len(pb['list'])
    
    if hasSubscriber:
        if (None != subscriber) and (pos > 0):
            pos -= 1
            while pos >= 0:
                if subscriber == pb.list[pos][0]:
                    if nslen and pb.list[pos][2] and match_namespace( pb['list'][pos][2], namespaces, nslen ):
                        remove_namespaces( pb['namespaces'], pb['list'][pos][2].keys() )
                        if pb['list'][pos][1]: 
                            pb['oneOffs'] = pb['oneOffs']-1 if pb['oneOffs'] > 0 else 0
                        del pb['list'][pos:pos+1]
                    elif not nslen:
                        if pb['list'][pos][2]: remove_namespaces( pb['namespaces'], pb['list'][pos][2].keys() )
                        if pb['list'][pos][1]: 
                            pb['oneOffs'] = pb['oneOffs']-1 if pb['oneOffs'] > 0 else 0
                        del pb['list'][pos:pos+1]
                pos -= 1
    
    elif not hasSubscriber and (nslen > 0) and (pos > 0):
        pos -= 1
        while pos >= 0:
            if pb['list'][pos][2] and match_namespace( pb['list'][pos][2], namespaces, nslen ):
                remove_namespaces( pb['namespaces'], pb['list'][pos][2].keys() )
                if pb['list'][pos][1]: 
                    pb['oneOffs'] = pb['oneOffs']-1 if pb['oneOffs'] > 0 else 0
                del pb['list'][pos:pos+1]
            pos -= 1
            
    elif not hasSubscriber and (pos > 0):
        pb['list'] = [ ]
        pb['oneOffs'] = 0
        pb['namespaces'] = { }


def unsubscribe( seps, pubsub, topic, subscriber=None ):
    if pubsub:
        
        topic = parse_topic( seps, topic )
        tags = OTAG_SEP.join( topic[1] ) 
        namespaces = topic[2]
        tagslen = len(tags)
        nslen = len(namespaces)
        hasSubscriber = bool(subscriber and callable( subscriber ))
        if not hasSubscriber: subscriber = None
        
        topic = OTOPIC_SEP.join( topic[0] )
        topiclen = len(topic)
        _topic = 'tp_'+topic if topiclen else False
        _tag = 'tg_'+tags if tagslen else False
        
        if topiclen and (_topic in pubsub['topics']):
            if tagslen and (_tag in pubsub['topics'][ _topic ]['tags']):
                remove_subscriber( pubsub['topics'][ _topic ]['tags'][ _tag ], hasSubscriber, subscriber, namespaces, nslen )
                if not pubsub['topics'][ _topic ]['tags'][ _tag ]['list']:
                    del pubsub['topics'][ _topic ]['tags'][ _tag ]
            elif not tagslen:
                remove_subscriber( pubsub['topics'][ _topic ]['notags'], hasSubscriber, subscriber, namespaces, nslen )
            if not pubsub['topics'][ _topic ]['notags']['list'] and not pubsub['topics'][ _topic ]['tags']:
                del pubsub['topics'][ _topic ]
        
        elif not topiclen and (tagslen or nslen):
            if tagslen:
                if _tag in pubsub['notopics']['tags']:
                    remove_subscriber( pubsub['notopics']['tags'][ _tag ], hasSubscriber, subscriber, namespaces, nslen )
                    if not pubsub['notopics']['tags'][ _tag ]['list']:
                        del pubsub['notopics']['tags'][ _tag ]
                
                # remove from any topics as well
                for t in pubsub['topics']:
                    if _tag in pubsub['topics'][ t ]['tags']:
                        remove_subscriber( pubsub['topics'][ t ]['tags'][ _tag ], hasSubscriber, subscriber, namespaces, nslen )
                        if not pubsub['topics'][ t ]['tags'][ _tag ]['list']:
                            del pubsub['topics'][ t ]['tags'][ _tag ]
            
            else:
                remove_subscriber( pubsub['notopics']['notags'], hasSubscriber, subscriber, namespaces, nslen )
                
                # remove from any tags as well
                for t2 in pubsub['notopics']['tags']:
                    remove_subscriber( pubsub['notopics']['tags'][ t2 ], hasSubscriber, subscriber, namespaces, nslen )
                    if not pubsub['notopics']['tags'][ t2 ]['list']:
                        del pubsub['notopics']['tags'][ t2 ]
                
                # remove from any topics and tags as well
                for t in pubsub['topics']:
                    remove_subscriber( pubsub['topics'][ t ]['notags'], hasSubscriber, subscriber, namespaces, nslen )
                    
                    for t2 in pubsub['topics'][ t ]['tags']:
                        remove_subscriber( pubsub['topics'][ t ]['tags'][ t2 ], hasSubscriber, subscriber, namespaces, nslen )
                        if not pubsub['topics'][ t ]['tags'][ t2 ]['list']:
                            del pubsub['topics'][ t ]['tags'][ t2 ]


    
#
# PublishSubscribe (Interface)
class PublishSubscribe:
    """
    PublishSubscribe,
    https://github.com/foo123/PublishSubscribe
    """
    
    VERSION = "0.4.1"
    
    Event = PublishSubscribeEvent
    
    def Data( props=None ):
        return PublishSubscribeData(props)
        
    
    def __init__( self ):
        self.initPubSub( )
    
    def __del__(self):
        self.disposePubSub()
        
    def initPubSub( self ):
        self._seps = [TOPIC_SEP, TAG_SEP, NS_SEP]
        self._pubsub = get_pubsub( )
        return self
    
    def disposePubSub( self ):
        self._seps = None
        self._pubsub = None
        return self
    
    def setSeparators( self, seps ):
        if seps:
            l = len(seps)
            if l > 0 and seps[0]: self._seps[0] = seps[0]
            if l > 1 and seps[1]: self._seps[1] = seps[1]
            if l > 2 and seps[2]: self._seps[2] = seps[2]
        return self
    
    def trigger( self, message, data=None ):
        if not data: data = { }
        #print( pprint.pformat(self._pubsub, 4) )
        publish( self, self._seps, self._pubsub, message, data )
        #print( pprint.pformat(self._pubsub, 4) )
        return self
    
    def pipeline( self, message, data=None, abort=None ):
        if not data: data = { }
        #print( pprint.pformat(self._pubsub, 4) )
        pipeline( self, self._seps, self._pubsub, message, data, abort )
        #print( pprint.pformat(self._pubsub, 4) )
        return self
    
    def on( self, message, callback ):
        if callback and callable(callback):
            #print( pprint.pformat(self._pubsub, 4) )
            subscribe( self._seps, self._pubsub, message, callback )
            #print( pprint.pformat(self._pubsub, 4) )
        return self
    
    def one( self, message, callback ):
        if callback and callable(callback):
            #print( pprint.pformat(self._pubsub, 4) )
            subscribe( self._seps, self._pubsub, message, callback, True )
            #print( pprint.pformat(self._pubsub, 4) )
        return self
    
    def on1( self, message, callback ):
        if callback and callable(callback):
            #print( pprint.pformat(self._pubsub, 4) )
            subscribe( self._seps, self._pubsub, message, callback, False, True )
            #print( pprint.pformat(self._pubsub, 4) )
        return self
    
    def one1( self, message, callback ):
        if callback and callable(callback):
            #print( pprint.pformat(self._pubsub, 4) )
            subscribe( self._seps, self._pubsub, message, callback, True, True )
            #print( pprint.pformat(self._pubsub, 4) )
        return self
    
    def off( self, message, callback=None ):
        #print( pprint.pformat(self._pubsub, 4) )
        unsubscribe( self._seps, self._pubsub, message, callback )
        #print( pprint.pformat(self._pubsub, 4) )
        return self
    

# if used with 'import *'
__all__ = ['PublishSubscribe']
