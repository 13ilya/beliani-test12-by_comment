/**
 * Entry point of node script
 * This script used to work with socket.io to share messages with clients
 * Used only on docker
 * @todo combine with socket.js and socket.prod.js
 */
global.countToCache = 4;

var app = require('express');
var http = require('http').Server(app);

var io = require('socket.io')(http);

var Redis = require('ioredis');
var redis = new Redis({
    port: 6379, //@todo use env var instead
    host: 'db', //@todo use env var instead
    db: 1 //@todo use env var instead
});

redis.subscribe('prolo-channel', function(err, count) {
    console.log('error:' + err + '|count:' + count);
});

redis.subscribe('live', function(err, count){
    console.log('error:' + err + '|count:' + count);
});

/**
 * Cache of last several (depending on countToCache) notifications
 * @type {Array}
 */
global.cacheSites = [];

/**
 * Push to cache notification.
 * Delete the oldest notifications if notifications count is more than enough.
 * @param site
 * @param message
 */
function cachePush(site, message)
{
    if (global.cacheSites[site]) {
        var cache = global.cacheSites[site];
    } else {
        var cache = [];
    }
    for (var i = 0; i < cache.length; ++i) {
        if (cache[i]['id'] === message['id']) {
            cache.splice(i--, 1);
        }
    }
    message['timestamp'] = Date.now();
    cache.push(message);
    if (cache.length > global.countToCache) {
        cache.shift();
    }
    global.cacheSites[site] = cache;
}

/**
 * Pop last notifications from cache.
 * @param site
 */
function cacheGet(site)
{
    var lenght = 0;
    var cache = global.cacheSites[site];
    if (cache) {
        var length = cache.length;
    }
    for (var i = 0; i < length; ++i) {
        cache[i]['timeago'] = Math.round((Date.now() - cache[i]['timestamp'])/1000);
    }
    return cache;
}

redis.on('message', function(channel, message) {
    console.log('channel:' + channel + '|message:' + message);
    if (channel == 'live') {
        var messageParsed = JSON.parse(message);
        var channelMobile = 'live-mobile#' + messageParsed['site'];
        var messageMobile = JSON.stringify(messageParsed['message']);

        console.log('send-to:' + channelMobile + '|message:' + messageMobile);
        io.emit(channelMobile, messageMobile);

        cachePush(messageParsed['site'], messageParsed['message']);
    } else {
        io.emit(channel, message);
    }
});

io.on('connection', function (socket) {
    var host = socket.handshake.headers.host.split(':');
    host = host[0];
    /**
     * @todo use normal way to recognize channel
     */
    if (host.search('prologistics') === -1) {//sends only on live channels
        console.log('new client on ' + host);
        socket.send(JSON.stringify(cacheGet(host)));
        console.log('send:');
        console.log(cacheGet(host));
    }
});

http.listen(3000, function(){
    console.log('Listening on Port 3000');
});
