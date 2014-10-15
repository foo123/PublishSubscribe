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

def _log( evt, data):
    print( pprint.pformat({'topic': evt.topic, 'originalTopic': evt.originalTopic, 'tags': evt.tags, 'namespaces': evt.namespaces, 'timestamp': evt.timestamp}, 4) )
    print( pprint.pformat(data, 4) )
    
def handler1(evt, data):
    print('Handler1' + "\n")
    _log( evt, data)
    # stop bubble propagation
    #evt.propagate( False )
    # stop propagation on same event
    #evt.stop( )
    #return False

def handler2(evt, data):
    print('Handler2' + "\n")
    _log( evt, data)

def handler3(evt, data):
    print('Handler3' + "\n")
    _log( evt, data)

def handler4(evt, data):
    print('Handler4' + "\n")
    _log( evt, data)

pb = PublishSubscribe( )
pb.on('Topic1/SubTopic11#Tag1#Tag2', handler1).on1('Topic1/SubTopic11#Tag1#Tag2@NS1', handler2).on('Topic1/SubTopic11#Tag1#Tag2@NS1@NS2', handler3).off('@NS1@NS2').trigger('Topic1/SubTopic11#Tag2#Tag1', {'key1': 'value1'}).trigger('Topic1/SubTopic11#Tag2#Tag1@NS1', {'key1': 'value1'})
