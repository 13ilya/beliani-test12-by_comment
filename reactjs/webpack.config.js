const NODE_ENV = process.env.NODE_ENV || 'development';
const NODE_PAGE = process.env.NODE_PAGE || 'condensed';
const NODE_BUILD = process.env.NODE_BUILD || false;
const webpack = require('webpack');
const path = require('path');
const dir ='./dist/';
const main = 'main_'+NODE_PAGE;
var entryArr = [];
if(NODE_BUILD) entryArr=[
        'bootstrap-loader',
        './frontend/'+NODE_PAGE+'/index.js'
    ]
else entryArr=[
    'webpack-hot-middleware/client',
    'bootstrap-loader',
    './frontend/'+NODE_PAGE+'/index.js'
]

const common = 'common_'+NODE_PAGE;
module.exports = {
    devtool: 'cheap-source-map',
    entry: {
        [main]:entryArr
    },
    output: {
        path: path.resolve(dir),
        filename: '[name].js',
        publicPath: '/',
        library: '[name]'
    },
    plugins: [
        new webpack.optimize.OccurrenceOrderPlugin(),
        new webpack.HotModuleReplacementPlugin(),
        new webpack.NoErrorsPlugin(),
        //new webpack.optimize.CommonsChunkPlugin(common,common+'.js',Infinity),
        new webpack.ProvidePlugin({
            $: "jquery",
            jQuery: "jquery",
            _: "underscore"
        })
    ],
    resolve: {
        modulesDirectories: ['node_modules'],
        extensions: ['', '.js', '.jsx']
    },
    resolveLoader: {
        modulesDirectories: ['node_modules'],
        moduleTemplates: ['*-loader', '*'],
        extensions: ['', '.js']
    },
    module: {
    preLoaders: [ //добавили ESlint в preloaders
      {
      test: /\.js$/,
      loaders: ['eslint'],
      include: [
        path.resolve(__dirname, "/"),
      ],
      }
    ],
        loaders: [
            {
                test: /\.js$/,
                loader: 'babel',
                exclude: /\/node_modules\//,
                query: {
                    presets: ['react', 'es2015', 'stage-0'],
                    plugins: ['transform-runtime'],
                    env: {
                        development: {
                            presets: ['react-hmre']
                        }
                    }
                }
            },
            {
                test: /\.js$/,
                loader: 'imports?jQuery=jquery',
                exclude: /\/node_modules\//
            },
            {
                test: /\.css$/,
                loaders: [ 'style', 'css', 'postcss' ]
            },
            {
                test: /\.scss$/,
                loaders: [ 'style', 'css', 'postcss', 'sass' ]
            },
            {
                test: /\.(ttf|eot|svg|woff|woff2|png|jpg)$/,
                loader: 'file?name=[path][name].[ext]',
            },

            // Bootstrap 3
            {
                test: /bootstrap-sass\/assets\/javascripts\//,
                loader: 'imports?jQuery=jquery'
            }
        ]
    }
};

if (NODE_ENV == 'production') {
    module.exports.output.publicPath = '/reactjs/dist/';
    module.exports.plugins.push(
        new webpack.optimize.UglifyJsPlugin({
            compress: {
                warnings: true,
                drop_console: true,
                unsafe: true,
                screw_ie8: true
            },
            comments: false,
            sourceMap: false
        })
    );
    module.exports.plugins.push(
        new webpack.DefinePlugin({
            'process.env.NODE_ENV': '"production"'
        })
    );

}
