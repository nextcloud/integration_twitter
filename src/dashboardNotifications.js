/**
 * Nextcloud - twitter
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2020
 */

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('integration_twitter', 'js/') // eslint-disable-line

OCA.Dashboard.register('twitter_notifications', async (el, { widget }) => {
	const { default: Vue } = await import(/* webpackChunkName: "dashboard-notif-lazy" */'vue')
	const { default: DashboardNotifications } = await import(/* webpackChunkName: "dashboard-notif-lazy" */'./views/DashboardNotifications.vue')
	Vue.mixin({ methods: { t, n } })
	const View = Vue.extend(DashboardNotifications)
	new View({
		propsData: { title: widget.title },
	}).$mount(el)
})
