/**
 * Entry point of node script
 * This script used to work with socket.io to share messages with clients
 * Used only on production server
 * @todo combine with socket.js
 */
global.countToCache = 4;
global.cacheTime = 60*30;

var fs = require('fs');
var url = require('url');

function getSecureContext (domain) {
    return require('crypto').createCredentials({
        key: fs.readFileSync('/etc/nginx/ssl/' + domain + '.key'),
        cert: fs.readFileSync('/etc/nginx/ssl/' + domain + '.crt')
    }).context;
}

var secureContext = {
    'beliani.ch': getSecureContext('www.beliani.ch'),
    'www.beliani.ch': getSecureContext('www.beliani.ch'),
    'www.beliani.co.uk': getSecureContext('www.beliani.co.uk'),
    'beliani.co.uk': getSecureContext('www.beliani.co.uk'),
    'www.beliani.de': getSecureContext('www.beliani.de'),
    'beliani.de': getSecureContext('www.beliani.de'),
    'www.beliani.com': getSecureContext('www.beliani.com'),
    'beliani.com': getSecureContext('www.beliani.com'),
    'www.beliani.fr': getSecureContext('www.beliani.fr'),
    'beliani.fr': getSecureContext('www.beliani.fr'),
    'www.beliani.at': getSecureContext('www.beliani.at'),
    'beliani.at': getSecureContext('www.beliani.at'),
    'www.beliani.ca': getSecureContext('www.beliani.ca'),
    'beliani.ca': getSecureContext('www.beliani.ca'),
    'www.beliani.es': getSecureContext('www.beliani.es'),
    'beliani.es': getSecureContext('www.beliani.es'),
    'www.beliani.pl': getSecureContext('www.beliani.pl'),
    'beliani.pl': getSecureContext('www.beliani.pl'),
    'www.artehome.org': getSecureContext('www.artehome.org'),
    'artehome.org': getSecureContext('www.artehome.org'),
    'www.artehome.de': getSecureContext('www.artehome.de'),
    'artehome.de': getSecureContext('www.artehome.de'),
    'www.beliani.nl': getSecureContext('www.beliani.nl'),
    'beliani.nl': getSecureContext('www.beliani.nl'),
    'www.beliani.lu': getSecureContext('www.beliani.lu'),
    'beliani.lu': getSecureContext('www.beliani.lu'),
    'www.beliani.be': getSecureContext('www.beliani.be'),
    'beliani.be': getSecureContext('www.beliani.be'),
    'www.avandeo.de': getSecureContext('www.avandeo.de'),
    'avandeo.de': getSecureContext('www.avandeo.de'),
    'www.beliani.it': getSecureContext('www.beliani.it'),
    'beliani.it': getSecureContext('www.beliani.it'),
    'www.beliani.pt': getSecureContext('www.beliani.pt'),
    'beliani.pt': getSecureContext('www.beliani.pt'),
    'www.beliani.se': getSecureContext('www.beliani.se'),
    'beliani.se': getSecureContext('www.beliani.se'),
    'www.beliani.hu': getSecureContext('www.beliani.hu'),
    'beliani.hu': getSecureContext('www.beliani.hu'),
    'www.beliani.dk': getSecureContext('www.beliani.dk'),
    'beliani.dk': getSecureContext('www.beliani.dk'),
    'www.beliani.cz': getSecureContext('www.beliani.cz'),
    'beliani.cz': getSecureContext('www.beliani.cz'),
    'www.beliani.fi': getSecureContext('www.beliani.fi'),
    'beliani.fi': getSecureContext('www.beliani.fi'),
    'www.beliani.no': getSecureContext('www.beliani.no'),
    'beliani.no': getSecureContext('www.beliani.no')
};

var options = {
    SNICallback: function (domain) {
        return secureContext[domain];
    },
    key: fs.readFileSync('/etc/nginx/ssl/prologistics.info.key'),
    cert: fs.readFileSync('/etc/nginx/ssl/prologistics.info.crt')
};

var app = require('express');
var http = require('http').Server(app);
var https = require('https').Server(options, app);

require("http").globalAgent.maxSockets = Infinity;
require("https").globalAgent.maxSockets = Infinity;

var io = require('socket.io')(http);
var io_https = require('socket.io')(https);

var Redis = require('ioredis');
var redis = new Redis({
    port: 6379, //@todo use env var instead
    host: '148.251.40.98', //@todo use env var instead
    db: 1 //@todo use env var instead
});

//redis.subscribe('prolo-channel', function(err, count) {
//    console.log('error:' + err + '|count:' + count);
//});

redis.subscribe('live', function(err, count){//when somebody will visit the product page, the redis will ask the server send the message
    console.log('error:' + err + '|count:' + count);
});

redis.subscribe('arrivals', function(err, count){//when somebody will change the SA, the redis will ask the server send the message
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
    var length = 0;
    var cache = global.cacheSites[site];
    if (cache) {
        length = cache.length;
    }
    for (var i = 0; i < length; ++i) {
        cache[i]['timeago'] = Math.round((Date.now() - cache[i]['timestamp'])/1000);
        if (cache[i]['timeago'] > global.cacheTime) {
            cache.splice(i--, 1);
            length--;
        }
    }
    return cache;
}

redis.on('message', function(channel, message) {
    var messageParsed = {site: '', message: ''};
    var block ='';
    try {
        messageParsed = JSON.parse(message);
    } catch (e) {
        console.log('malformed request', message);
    }
    
    console.log("channel: " + channel);
    
    var messageMobile = JSON.stringify(messageParsed['message']);
    switch(channel){
        case 'live':
            block ='live-mobile#';
            cachePush(messageParsed['site'], messageParsed['message']);
            break;
        case 'arrivals': 
            block ='arrivals#'; 
            break;
    }
    var channelMobile = block + messageParsed['site'];
    if (block) {
        console.log("channelMobile: " + channelMobile);
        
        io.emit(channelMobile, messageMobile);
        io_https.emit(channelMobile, messageMobile);
    }
    else {
        io.emit(channel, message);
        io_https.emit(channel, message);
    }
});

var processConnection = function (socket) {
    var host = socket.handshake.headers.referer || socket.handshake.headers.host;
    host = url.parse(host);

    /**
     * @todo use normal way to recognize channel
     */
    try {
        if (host && host.host.search('prologistics') === -1) {//sends only on live channels
            console.log('new client on ' + host.host + '; secure ' + socket.handshake.secure);
            socket.send(JSON.stringify(cacheGet(host.host)));
            console.log('send:', cacheGet(host.host));
        }
    } catch (e) {
        console.log('processConnection');
    }
};

io.on('connection', processConnection);
io_https.on('connection', processConnection);

http.listen(3000, function(){
    console.log('Listening on Port 3000');
});

https.listen(3043, function(){
    console.log('Listening on Port 3043');
});
