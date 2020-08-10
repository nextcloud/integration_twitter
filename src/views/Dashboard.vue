<template>
	<DashboardWidget :items="items"
		:showMoreUrl="showMoreUrl"
		:loading="state === 'loading'">
		<template v-slot:empty-content>
			<div v-if="state === 'no-token'">
				<a :href="settingsUrl">
					{{ t('twitter', 'Click here to configure the access to your Twitter account.') }}
				</a>
			</div>
			<div v-else-if="state === 'error'">
				<a :href="settingsUrl">
					{{ t('twitter', 'Incorrect access token.') }}
					{{ t('twitter', 'Click here to configure the access to your Twitter account.') }}
				</a>
			</div>
			<div v-else-if="state === 'ok'">
				{{ t('twitter', 'Nothing to show') }}
			</div>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { DashboardWidget } from '@nextcloud/vue-dashboard'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
	},

	props: [],

	data() {
		return {
			notifications: [],
			showMoreUrl: 'https://twitter.com/notifications',
			// lastDate could be computed but we want to keep the value when first notification is removed
			// to avoid getting it again on next request
			lastDate: null,
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/linked-accounts'),
			darkThemeColor: OCA.Accessibility.theme === 'dark' ? 'ffffff' : '000000',
		}
	},

	computed: {
		items() {
			// get rid of follow request counts
			const items = this.notifications.filter((n) => {
				return n.type !== 'follow_request'
			})

			// find the last follow request notif
			const fr = this.notifications.find((n) => {
				return n.type === 'follow_request'
			})

			// if we have follow requests, show them in first
			if (fr && fr.number > 0) {
				items.unshift(fr)
			}

			return items.map((n) => {
				return {
					id: n.id,
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getUserAvatarUrl(n),
					avatarUsername: this.getAvatarText(n),
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getMainText(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastMoment() {
			return moment(this.lastDate)
		},
	},

	beforeMount() {
		this.fetchNotifications()
		this.loop = setInterval(() => this.fetchNotifications(), 120000)
	},

	mounted() {
	},

	methods: {
		fetchNotifications() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/twitter/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('twitter', 'Failed to get Twitter notifications.'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastDate < newNotifications[i].timestamp) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
			// update lastDate manually (explained in data)
			const nbNotif = this.notifications.length
			this.lastDate = (nbNotif > 0) ? this.notifications[0].timestamp : null
		},
		filter(notifications) {
			return notifications
		},
		getUserAvatarUrl(n) {
			return n.profile_image_url_https
				? generateUrl('/apps/twitter/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(n.profile_image_url_https)
				: ''
		},
		getAvatarText(n) {
			if (['follow_request'].includes(n.type)) {
				return '!'
			}
			return ''
		},
		getNotificationTarget(n) {
			if (['retweet', 'mention'].includes(n.type)) {
				return 'https://twitter.com/' + n.sender_screen_name + '/status/' + n.id_str
			} else if (['message'].includes(n.type)) {
				return 'https://twitter.com/messages'
			} else if (['follow_request'].includes(n.type)) {
				return 'https://twitter.com/follower_requests'
			}
			return ''
		},
		getMainText(n) {
			if (['follow_request'].includes(n.type)) {
				return t('twitter', '{nb} follow requests', { nb: n.number })
			}
			return n.text
		},
		getSubline(n) {
			if (['follow_request'].includes(n.type)) {
				return t('twitter', 'System')
			}
			return '@' + n.sender_screen_name
		},
		getNotificationTypeImage(n) {
			if (n.type === 'mention') {
				return generateUrl('/svg/twitter/arobase?color=ffffff')
			} else if (n.type === 'message') {
				return generateUrl('/svg/twitter/message?color=ffffff')
			} else if (n.type === 'retweet') {
				return generateUrl('/svg/twitter/retweet?color=ffffff')
			} else if (n.type === 'follow_request') {
				return generateUrl('/svg/twitter/sound?color=ffffff')
			}
			return ''
		},
		getFormattedDate(n) {
			return moment(n.timestamp).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
</style>
