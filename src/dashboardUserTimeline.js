/**
 * Nextcloud - twitter
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('integration_twitter', 'js/') // eslint-disable-line

OCA.Dashboard.register('twitter_user_timeline', async (el, { widget }) => {
	const { default: Vue } = await import(/* webpackChunkName: "dashboard-user-lazy" */'vue')
	const { default: DashboardUserTimeline } = await import(/* webpackChunkName: "dashboard-user-lazy" */'./views/DashboardUserTimeline.vue')
	Vue.mixin({ methods: { t, n } })
	const View = Vue.extend(DashboardUserTimeline)
	new View({
		propsData: { title: widget.title },
	}).$mount(el)
})
