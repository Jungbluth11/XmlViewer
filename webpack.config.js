const path = require('path')

module.exports = {
	entry: {
		main: './src/main.js',
		style: './src/style.js',
	},
	output: {
		path: path.resolve(__dirname, 'js'),
		filename: '[name].js',
	},
}
