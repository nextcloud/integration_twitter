/* jshint esversion: 6 */

/**
 * Nextcloud - twitter
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

import Vue from 'vue'
import './bootstrap'
import DashboardHomeTimeline from './views/DashboardHomeTimeline'

document.addEventListener('DOMContentLoaded', function() {

	OCA.Dashboard.register('twitter_home_timeline', (el, { widget }) => {
		const View = Vue.extend(DashboardHomeTimeline)
		new View({
			propsData: { title: widget.title },
		}).$mount(el)
	})

})
