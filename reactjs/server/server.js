'use strict';
var express = require('express');
var path = require('path');
var fs = require('fs');
var fetch = require('node-fetch');
const NODE_PAGE = process.env.NODE_PAGE || 'condensed';
console.log('express NODE_PAGE=',NODE_PAGE);
var config = require('../webpack.config');
var webpack = require('webpack');
var webpackDevMiddleware = require('webpack-dev-middleware');
var webpackHotMiddleware = require('webpack-hot-middleware');

var app = express();

var compiler = webpack(config);

app.use(webpackDevMiddleware(compiler, {noInfo: false, publicPath: config.output.publicPath}));
app.use(webpackHotMiddleware(compiler));

app.use(express.static('./dist'));

function fetch_data(req,res){
    var page = 'http://prolodev.prologistics.info'+req.url,
        headers = req.headers;
        headers.host = 'proloissue.prologistics.info';

    var data = fetch(page,
                {
                    headers:headers,
                    mode:'cors',
                    cache:'default',
                    credentials:'include'
                })
                    .then(data => {
                        console.log('data',data.headers.get('Content-Type'));
                        if(data.headers.get('Content-Type').indexOf('html') !== -1){
                            return data.text();
                        }
                        return data.json()
                    })
                    .then((data)=>{res.send(data)});
}

app.get('/api/filtersOptions/', fetch_data);

app.get('/api/comments/list/', fetch_data);

app.get('/api/reactPagesLog/setLog', fetch_data);
app.get(/\/api\/issueLog\//, fetch_data);

app.get('/api/multiSA/get/', fetch_data);

app.get(/\/api\/suggest\//, fetch_data);

app.get(/cache/, function (req, res) {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.redirect('http://prolodev.prologistics.info'+req.url);
});

app.get('/api/langs/', fetch_data);



app.get('*', function (request, response){
    response.sendFile(path.resolve(__dirname, '../frontend/logs/', 'index.html'));
});

var port = 3001;

app.listen(port, function(error) {
  if (error) throw error;
  console.log("Express server listening on port", port);
});
