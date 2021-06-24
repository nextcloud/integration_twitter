const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
    colors: true,
    modules: false,
}

webpackConfig.entry = {
    personalSettings: { import: path.join(__dirname, 'src', 'personalSettings.js'), filename: 'integration_twitter-personalSettings.js' },
    adminSettings: { import: path.join(__dirname, 'src', 'adminSettings.js'), filename: 'integration_twitter-adminSettings.js' },
    dashboardNotifications: { import: path.join(__dirname, 'src', 'dashboardNotifications.js'), filename: 'integration_twitter-dashboardNotifications.js' },
    dashboardHomeTimeline: { import: path.join(__dirname, 'src', 'dashboardHomeTimeline.js'), filename: 'integration_twitter-dashboardHomeTimeline.js' },
    dashboardUserTimeline: { import: path.join(__dirname, 'src', 'dashboardUserTimeline.js'), filename: 'integration_twitter-dashboardUserTimeline.js' },
}

module.exports = webpackConfig
