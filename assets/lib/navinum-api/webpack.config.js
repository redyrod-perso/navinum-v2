const path = require('path');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';

    return {
        entry: './src/index.js',
        output: {
            path: path.resolve(__dirname, 'dist'),
            filename: isProduction ? 'navinum-api.min.js' : 'navinum-api.js',
            library: {
                name: 'NavinumAPI',
                type: 'umd',
                export: 'default'
            },
            globalObject: 'this'
        },
        module: {
            rules: [
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: [
                                ['@babel/preset-env', {
                                    targets: {
                                        browsers: ['> 1%', 'last 2 versions', 'not dead']
                                    },
                                    modules: false
                                }]
                            ]
                        }
                    }
                }
            ]
        },
        resolve: {
            extensions: ['.js']
        },
        devtool: isProduction ? 'source-map' : 'eval-source-map',
        optimization: {
            minimize: isProduction
        }
    };
};
