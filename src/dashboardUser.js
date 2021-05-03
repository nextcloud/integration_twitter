/* jshint esversion: 6 */

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

import Vue from 'vue'
import './bootstrap'
import DashboardUser from './views/DashboardUser'

document.addEventListener('DOMContentLoaded', function() {

	OCA.Dashboard.register('twitter_user_timeline', (el, { widget }) => {
		const View = Vue.extend(DashboardUser)
		new View({
			propsData: { title: widget.title },
		}).$mount(el)
	})

})
