#!/usr/bin/env python

import os, sys
import pprint

# import the Dromeo.py engine (as a) module, probably you will want to place this in another dir/package
import imp
PBSModulePath = os.path.join(os.path.dirname(__file__), '../src/python/')
try:
    PBSFp, PBSPath, PBSDesc  = imp.find_module('PublishSubscribe', [PBSModulePath])
    PublishSubscribe = getattr( imp.load_module('PublishSubscribe', PBSFp, PBSPath, PBSDesc), 'PublishSubscribe' )
except ImportError as exc:
    PublishSubscribe = None
    sys.stderr.write("Error: failed to import module ({})".format(exc))
finally:
    if PBSFp: PBSFp.close()

if not PublishSubscribe:
    print ('Could not load the PublishSubscribe Module')
    sys.exit(1)
else:    
    print ('PublishSubscribe Module loaded succesfully')

print('PublishSubscribe.VERSION = ' + PublishSubscribe.VERSION + "\n")

def _log( evt):
    print( pprint.pformat({'topic': evt.topic, 'originalTopic': evt.originalTopic, 'tags': evt.tags, 'namespaces': evt.namespaces, 'timestamp': evt.timestamp}, 4) )
    print( pprint.pformat(evt.data, 4) )
    
def handler1(evt):
    print('Handler1' + "\n")
    _log( evt)
    evt.next()
    # event abort
    #evt.abort( )
    # stop bubble propagation
    #evt.propagate( False )
    # stop propagation on same event
    #evt.stop( )
    #return False

def handler2(evt):
    print('Handler2' + "\n")
    _log( evt)
    evt.next()

def handler3(evt):
    print('Handler3' + "\n")
    _log( evt)
    evt.next()

def handler4(evt):
    print('Handler4' + "\n")
    _log( evt)
    evt.next()

pb = PublishSubscribe( )
pb.on('Topic1/SubTopic11#Tag1#Tag2', handler1).on1('Topic1/SubTopic11#Tag1#Tag2@NS1', handler2).on('Topic1/SubTopic11#Tag1#Tag2@NS1@NS2', handler3).off('@NS1@NS2').pipeline('Topic1/SubTopic11#Tag2#Tag1', {'key1': 'value1'}).pipeline('Topic1/SubTopic11#Tag2#Tag1@NS1', {'key1': 'value1'})
