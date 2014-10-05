# -*- coding: UTF-8 -*-
##
#  PublishSubscribe
#  A simple publish-subscribe implementation for PHP, Python, Node/JS
#
#  @version: 0.3.4
#  https://github.com/foo123/PublishSubscribe
#
##
    
#import pprint

TOPIC_SEP = '/' 
TAG_SEP = '#'
NS_SEP = '@'
OTOPIC_SEP = '/' 
OTAG_SEP = '#'
ONS_SEP = '@'

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
        self.data = {}
        self._stopPropagation = False
        self._stopEvent = False
    
    def dispose( self ):
        self.target = None
        self.topic = None
        self.originalTopic = None
        self.tags = None
        self.namespaces = None
        self.data = None
        self._stopPropagation = True
        self._stopEvent = True
        return self
    
    def propagate( self, enable=True ):
        self._stopPropagation = not bool(enable)
        return self
    
    def stop( self, enable=True ):
        self._stopEvent = bool(enable)
        return self
    
    def propagationStopped( self ):
        return self._stopPropagation
    
    def eventStopped( self ):
        return self._stopEvent


def getPubSub( ): 
    return { 'notopics': { 'notags': {'namespaces': {}, 'list': [], 'oneOffs': 0}, 'tags': {} }, 'topics': {} }
    
def notEmpty( s ): 
    return len(s) > 0
    

def parseTopic( seps, topic ):
    nspos = topic.find( seps[2] )
    tagspos = topic.find( seps[1] )
    
    if -1 < nspos:
        namespaces = [x for x in topic[nspos:].split( seps[2] ) if notEmpty(x)]
        namespaces = sorted( namespaces )
        topic = topic[0:nspos]
    else:
        namespaces = [ ]
    
    if -1 < tagspos:
        tags = [x for x in topic[tagspos:].split( seps[1] ) if notEmpty(x)]
        tags = sorted( tags )
        topic = topic[0:tagspos]
    else:
        tags = [ ]
    
    topic = [x for x in topic.split( seps[0] ) if notEmpty(x)]
    return [topic, tags, namespaces]


def getAllTopics( seps, topic ): 
    topics = [ ]
    tags = [ ]
    #namespaces = [ ] 
    
    topic = parseTopic( seps, topic )
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


def updateNamespaces( pbns, namespaces, nl=0 ):
    for ns in namespaces:
        if not (ns in pbns):
            pbns[ ns ] = 1
        else:
            pbns[ ns ] += 1


def removeNamespaces( pbns, namespaces, nl=0 ):
    for ns in namespaces:
        if ns in pbns:
            pbns[ ns ] -= 1
            if pbns[ ns ] <=0:
                del pbns[ ns ]


def matchNamespace( pbns, namespaces, nl=0 ):
    for ns in namespaces:
        if (ns not in pbns) or (0 >= pbns[ ns ]): return False
    return True


def checkIsSubscribed( pubsub, subscribedTopics, topic, tag, namespaces, nl ):
    if topic:
        if tag:
            if nl > 0:
                if (tag in pubsub['topics'][ topic ]['tags']) and notEmpty(pubsub['topics'][ topic ]['tags'][ tag ]['list']) and matchNamespace( pubsub['topics'][ topic ]['tags'][ tag ]['namespaces'], namespaces, nl ):
                    subscribedTopics.append( [topic, tag, True, pubsub['topics'][ topic ]['tags'][ tag ]] )
                    return True
            else:
                if (tag in pubsub['topics'][ topic ]['tags']) and notEmpty(pubsub['topics'][ topic ]['tags'][ tag ]['list']):
                    subscribedTopics.append( [topic, tag, None, pubsub['topics'][ topic ]['tags'][ tag ]] )
                    return True
        
        else:
            if nl > 0:
                if notEmpty(pubsub['topics'][ topic ]['notags']['list']) and matchNamespace( pubsub['topics'][ topic ]['notags']['namespaces'], namespaces, nl ):
                    subscribedTopics.append( [topic, None, True, pubsub['topics'][ topic ]['notags']] )
                    return True
            else:
                if notEmpty(pubsub['topics'][ topic ]['notags']['list']):
                    subscribedTopics.append( [topic, None, None, pubsub['topics'][ topic ]['notags']] )
                    return True
    
    else:
        if tag:
            if nl > 0:
                if (tag in pubsub['notopics']['tags']) and notEmpty(pubsub['notopics']['tags'][ tag ]['list']) and matchNamespace( pubsub['notopics']['tags'][ tag ]['namespaces'], namespaces, nl ):
                    subscribedTopics.append( [None, tag, True, pubsub['notopics']['tags'][ tag ]] )
                    return True
            else:
                if (tag in pubsub['notopics']['tags']) and notEmpty(pubsub['notopics']['tags'][ tag ]['list']):
                    subscribedTopics.append( [None, tag, None, pubsub['notopics']['tags'][ tag ]] )
                    return True
        
        else:
            if nl > 0:
                if notEmpty(pubsub['notopics']['notags']['list']) and matchNamespace( pubsub['notopics']['notags']['namespaces'], namespaces, nl ):
                    subscribedTopics.append( [None, None, True, pubsub['notopics']['notags']] )
                    return True
            #else:
                # no topics no tags no namespaces, do nothing
    
    return False


def getSubscribedTopics( seps, pubsub, atopic ):
    all = getAllTopics( seps, atopic )
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
            if topic in  pubsub['topics']:
                if tl > 0:
                    for tag in tags:
                        checkIsSubscribed( pubsub, subscribedTopics, topic, tag, namespaces, nl )
                else:
                    checkIsSubscribed( pubsub, subscribedTopics, topic, None, namespaces, nl )
            topics.pop( 0 )
            l -= 1
    
    if tl > 0:
        for tag in tags:
            checkIsSubscribed( pubsub, subscribedTopics, None, tag, namespaces, nl )
    
    checkIsSubscribed( pubsub, subscribedTopics, None, None, namespaces, nl )
    
    return [topTopic, subscribedTopics, namespaces]


def publish( target, seps, pubsub, topic, data ):
    if pubsub:
        topics = getSubscribedTopics( seps, pubsub, topic )
        topTopic = topics[ 0 ]
        namespaces = topics[ 2 ]
        topics = topics[ 1 ]
        tl = len(topics)
        evt = None
        
        if tl > 0:
            evt = PublishSubscribeEvent( target )
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
                if (not hasNamespace) or (subscribers['list'][ s ][ 2 ] and matchNamespace(subscribers['list'][ s ][ 2 ], namespaces)):
                    subs.append( subscribers['list'][ s ] )
            
            for subscriber in subs:
                if hasNamespace: evt.namespaces = subscriber[ 3 ][:]
                else: evt.namespaces = []
                subscriber[ 4 ] = 1 # subscriber called
                res = subscriber[ 0 ]( evt, data )
                # stop event propagation
                if (False == res) or evt.eventStopped(): break
            
            # unsubscribeOneOffs
            if ('list' in subscribers) and len(subscribers['list']) > 0:
                if subscribers['oneOffs'] > 0:
                    subs = subscribers['list']
                    for s in range(len(subs)-1,-1,-1):
                        subscriber = subs[ s ]
                        if subscriber[1] and subscriber[4] > 0:
                            del subs[s:s+1]
                            subscribers['oneOffs'] = subscribers['oneOffs']-1 if subscribers['oneOffs'] > 0 else 0
                else: subscribers['oneOffs'] = 0
                    
            # stop event bubble propagation
            if evt.propagationStopped(): break
        
        if evt:
            evt.dispose( )
            evt = None


def subscribe( seps, pubsub, topic, subscriber, oneOff=False, on1=False ):
    if pubsub and callable(subscriber):
        topic = parseTopic( seps, topic )
        tags = OTAG_SEP.join( topic[1] ) 
        tagslen = len(tags)
        namespaces = topic[2]
        nslen = len(namespaces)
        topic = OTOPIC_SEP.join( topic[0] )
        oneOff = (True == oneOff)
        on1 = (True == on1)
        nshash = { }
        if nslen:
            for ns in namespaces: nshash[ns] = 1
        namespaces_ref = namespaces[:]
        
        queue = None
        if len(topic):
            if not topic in pubsub['topics']: 
                pubsub['topics'][ topic ] = { 'notags': {'namespaces': {}, 'list': [], 'oneOffs': 0}, 'tags': {} }
            if tagslen:
                if not tags in pubsub['topics'][ topic ]['tags']: 
                    pubsub['topics'][ topic ]['tags'][ tags ] = {'namespaces': {}, 'list': [], 'oneOffs': 0}
                
                queue = pubsub['topics'][ topic ]['tags'][ tags ]
            else:
                queue = pubsub['topics'][ topic ]['notags']
        
        else:
            if tagslen:
                if not tags in pubsub['notopics']['tags']: 
                    pubsub['notopics']['tags'][ tags ] = {'namespaces': {}, 'list': [], 'oneOffs': 0}
                
                queue = pubsub['notopics']['tags'][ tags ]
                    
            elif nslen:
                queue = pubsub['notopics']['notags']

        if queue is not None:
            entry = [subscriber, oneOff, nshash, namespaces_ref, 0] if nslen else [subscriber, oneOff, False, [], 0]
            if on1: queue['list'].insert( 0, entry )
            else: queue['list'].append( entry )
            if oneOff: queue['oneOffs'] += 1
            if nslen: updateNamespaces( queue['namespaces'], namespaces, nslen )


def removeSubscriber( pb, hasSubscriber, subscriber, namespaces, nslen ):
    pos = len(pb['list'])
    
    if hasSubscriber:
        if (None != subscriber) and (pos > 0):
            pos -= 1
            while pos >= 0:
                if subscriber == pb.list[pos][0]:
                    if nslen and pb.list[pos][2] and matchNamespace( pb['list'][pos][2], namespaces, nslen ):
                        removeNamespaces( pb['namespaces'], pb['list'][pos][2].keys() )
                        if pb['list'][pos][1]: 
                            pb['oneOffs'] = pb['oneOffs']-1 if pb['oneOffs'] > 0 else 0
                        del pb['list'][pos:pos+1]
                    elif not nslen:
                        if pb['list'][pos][2]: removeNamespaces( pb['namespaces'], pb['list'][pos][2].keys() )
                        if pb['list'][pos][1]: 
                            pb['oneOffs'] = pb['oneOffs']-1 if pb['oneOffs'] > 0 else 0
                        del pb['list'][pos:pos+1]
                pos -= 1
    
    elif not hasSubscriber and (nslen > 0) and (pos > 0):
        pos -= 1
        while pos >= 0:
            if pb['list'][pos][2] and matchNamespace( pb['list'][pos][2], namespaces, nslen ):
                removeNamespaces( pb['namespaces'], pb['list'][pos][2].keys() )
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
        
        topic = parseTopic( seps, topic )
        tags = OTAG_SEP.join( topic[1] ) 
        namespaces = topic[2]
        tagslen = len(tags)
        nslen = len(namespaces)
        hasSubscriber = bool(subscriber and callable( subscriber ))
        if not hasSubscriber: subscriber = None
        
        topic = OTOPIC_SEP.join( topic[0] )
        topiclen = len(topic)
        
        if topiclen and (topic in pubsub['topics']):
            if tagslen and (tags in pubsub['topics'][ topic ]['tags']):
                removeSubscriber( pubsub['topics'][ topic ]['tags'][ tags ], hasSubscriber, subscriber, namespaces, nslen )
                if not pubsub['topics'][ topic ]['tags'][ tags ]['list']:
                    del pubsub['topics'][ topic ]['tags'][ tags ]
            elif not tagslen:
                removeSubscriber( pubsub['topics'][ topic ]['notags'], hasSubscriber, subscriber, namespaces, nslen )
            if not pubsub['topics'][ topic ]['notags']['list'] and not pubsub['topics'][ topic ]['tags']:
                del pubsub['topics'][ topic ]
        
        elif not topiclen and (tagslen or nslen):
            if tagslen:
                if tags in pubsub['notopics']['tags']:
                    removeSubscriber( pubsub['notopics']['tags'][ tags ], hasSubscriber, subscriber, namespaces, nslen )
                    if not pubsub['notopics']['tags'][ tags ]['list']:
                        del pubsub['notopics']['tags'][ tags ]
                
                # remove from any topics as well
                for t in pubsub['topics']:
                    if tags in pubsub['topics'][ t ]['tags']:
                        removeSubscriber( pubsub['topics'][ t ]['tags'][ tags ], hasSubscriber, subscriber, namespaces, nslen )
                        if not pubsub['topics'][ t ]['tags'][ tags ]['list']:
                            del pubsub['topics'][ t ]['tags'][ tags ]
            
            else:
                removeSubscriber( pubsub['notopics']['notags'], hasSubscriber, subscriber, namespaces, nslen )
                
                # remove from any tags as well
                for t2 in pubsub['notopics']['tags']:
                    removeSubscriber( pubsub['notopics']['tags'][ t2 ], hasSubscriber, subscriber, namespaces, nslen )
                    if not pubsub['notopics']['tags'][ t2 ]['list']:
                        del pubsub['notopics']['tags'][ t2 ]
                
                # remove from any topics and tags as well
                for t in pubsub['topics']:
                    removeSubscriber( pubsub['topics'][ t ]['notags'], hasSubscriber, subscriber, namespaces, nslen )
                    
                    for t2 in pubsub['topics'][ t ]['tags']:
                        removeSubscriber( pubsub['topics'][ t ]['tags'][ t2 ], hasSubscriber, subscriber, namespaces, nslen )
                        if not pubsub['topics'][ t ]['tags'][ t2 ]['list']:
                            del pubsub['topics'][ t ]['tags'][ t2 ]


    
#
# PublishSubscribe (Interface)
class PublishSubscribe:
    """
    PublishSubscribe,
    https://github.com/foo123/PublishSubscribe
    """
    
    VERSION = "0.3.4"
    
    Event = PublishSubscribeEvent
    
    def __init__( self ):
        self.initPubSub( )
    
    def initPubSub( self ):
        self._seps = [TOPIC_SEP, TAG_SEP, NS_SEP]
        self._pubsub = getPubSub( )
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
