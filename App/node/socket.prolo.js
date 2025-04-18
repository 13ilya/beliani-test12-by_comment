/**
 * Entry point of node script
 * This script used to work with socket.io to share messages with clients
 * Used only on production server
 * @todo combine with socket.js
 */

var fs = require('fs');

var options = {
    key: fs.readFileSync('/etc/nginx/ssl/prologistics.info.key'),
    cert: fs.readFileSync('/etc/nginx/ssl/prologistics.info.crt')
};

var app = require('express');
var http = require('http').Server(app);
var https = require('https').Server(options, app);

var io = require('socket.io')(http);
var io_https = require('socket.io')(https);

var Redis = require('ioredis');
var redis = new Redis({
    port: 6379, //@todo use env var instead
    host: '148.251.40.98', //@todo use env var instead
    db: 1 //@todo use env var instead
});

redis.subscribe('prolo-channel', function(err, count) {
    console.log('error:' + err + '|count:' + count);
});

redis.on('message', function(channel, message) {
    io.emit(channel, message);
    io_https.emit(channel, message);
});

http.listen(3000, function(){
    console.log('Listening on Port 3000');
});

https.listen(3043, function(){
    console.log('Listening on Port 3043');
});
