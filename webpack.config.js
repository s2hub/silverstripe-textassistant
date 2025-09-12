const Path = require('path');
const webpack = require('webpack');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');

const ENV = process.env.NODE_ENV;
const PATHS = {
  // the root path, where your webpack.config.js is located.
  ROOT: Path.resolve(),
  // your node_modules folder name, or full path
  MODULES: 'node_modules',
  // relative path from your css files to your other files, such as images and fonts
  FILES_PATH: '../',
  // thirdparty folder containing copies of packages which wouldn't be available on NPM
  THIRDPARTY: 'thirdparty',
  // the root path to your javascript source files
  SRC: Path.resolve('client/src'),
  DIST: Path.resolve('client/dist'),
};

const config = [
  {
    name: 'js',
    entry: {
      bundle: `${PATHS.SRC}/js/bundle.js`,
    },
    output: {
      path: PATHS.DIST,
      filename: 'js/[name].js'
    },
    devtool: (ENV !== 'production') ? 'source-map' : false,
    resolve: {
      modules: [PATHS.ROOT, PATHS.SRC, PATHS.MODULES]
    },
    externals: {
      jquery: 'jQuery'
    },
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: new RegExp(`(${PATHS.MODULES}|${PATHS.THIRDPARTY})`),
          loader: 'babel-loader',
          options: {
            comments: false,
            cacheDirectory: (ENV !== 'production'),
          }
        }
      ]
    },
    plugins: [
      new webpack.DefinePlugin({
        'process.env': {
          NODE_ENV: JSON.stringify(ENV || 'development'),
        }
      })
    ],
    optimization: {
      minimize: (ENV === 'production'),
      moduleIds: 'named'
    }
  },
  {
    name: 'css',
    entry: {
      bundle: `${PATHS.SRC}/styles/bundle.scss`,
    },
    output: {
      path: PATHS.DIST
    },
    devtool: (ENV !== 'production') ? 'source-map' : false,
    module: {
      rules: [
        {
          test: /\.s[ac]ss$/,
          use: [
            MiniCssExtractPlugin.loader,
            {loader: 'css-loader', options: {url: false}},
            "sass-loader",
          ],
        },
        {
          test: /\.css$/,
          use: [
            MiniCssExtractPlugin.loader,
            'css-loader'
          ]
        }
      ]
    },
    plugins: [
      new RemoveEmptyScriptsPlugin(),
      new MiniCssExtractPlugin({
        filename: "styles/[name].css",
        chunkFilename: "styles/[id].css",
      })
    ],
    optimization: {
      minimize: (ENV === 'production'),
      minimizer: [
        new CssMinimizerPlugin()
      ]
    }
  }
];

//Use WEBPACK_CHILD=js or WEBPACK_CHILD=css env var to run a single config
module.exports = (process.env.WEBPACK_CHILD)
  ? config.find((entry) => entry.name === process.env.WEBPACK_CHILD)
  : module.exports = config;
