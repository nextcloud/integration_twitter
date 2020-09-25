<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template v-slot:empty-content>
			<EmptyContent
				v-if="emptyContentMessage"
				:icon="emptyContentIcon">
				<template #desc>
					{{ emptyContentMessage }}
					<div v-if="state === 'no-token' || state === 'error'" class="connect-button">
						<a class="button" :href="settingsUrl">
							{{ t('integration_twitter', 'Connect to Twitter') }}
						</a>
					</div>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget, EmptyContent,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			notifications: [],
			showMoreUrl: 'https://twitter.com/notifications',
			// lastDate could be computed but we want to keep the value when first notification is removed
			// to avoid getting it again on next request
			lastDate: null,
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts#twitter_prefs'),
			darkThemeColor: OCA.Accessibility.theme === 'dark' ? 'ffffff' : '000000',
		}
	},

	computed: {
		items() {
			// get rid of follow request counts
			let items = this.notifications.filter((n) => {
				return (!['follow_request'].includes(n.type))
			})

			// if we have follow requests, show them in first
			if (this.followRequestItem && this.followRequestItem.number > 0) {
				items.unshift(this.followRequestItem)
			}

			// then filter out unnecessary private messages
			const privMsgAuthorIds = []
			items = items.filter((n) => {
				if (n.type !== 'message') {
					return true
				} else if (privMsgAuthorIds.includes(n.sender_screen_name)) {
					return false
				} else {
					privMsgAuthorIds.push(n.sender_screen_name)
					return true
				}
			})

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
		followRequestItem() {
			// find the last follow request notif
			return this.notifications.find((n) => {
				return n.type === 'follow_request'
			})
		},
		lastMoment() {
			return moment(this.lastDate)
		},
		emptyContentMessage() {
			if (this.state === 'no-token') {
				return t('integration_twitter', 'No Twitter account connected')
			} else if (this.state === 'error') {
				return t('integration_twitter', 'Error connecting to Twitter')
			} else if (this.state === 'ok') {
				return t('integration_twitter', 'No Twitter notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return 'icon-twitter'
			} else if (this.state === 'error') {
				return 'icon-close'
			} else if (this.state === 'ok') {
				return 'icon-checkmark'
			}
			return 'icon-checkmark'
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
			axios.get(generateUrl('/apps/integration_twitter/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_twitter', 'Failed to get Twitter notifications.'))
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
				? generateUrl('/apps/integration_twitter/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(n.profile_image_url_https)
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
				return t('integration_twitter', '{nb} follow requests', { nb: n.number })
			} else if (['retweet', 'mention'].includes(n.type)) {
				let text = n.text
				while (text.startsWith('@')) {
					text = text.replace(/^@[^\s]*\s/, '')
				}
				return text
			}
			return n.text
		},
		getSubline(n) {
			if (['follow_request'].includes(n.type)) {
				return t('integration_twitter', 'System')
			}
			return '@' + n.sender_screen_name
		},
		getNotificationTypeImage(n) {
			if (n.type === 'mention') {
				return generateUrl('/svg/integration_twitter/arobase?color=ffffff')
			} else if (n.type === 'message') {
				return generateUrl('/svg/integration_twitter/message?color=ffffff')
			} else if (n.type === 'retweet') {
				return generateUrl('/svg/integration_twitter/retweet?color=ffffff')
			} else if (n.type === 'follow_request') {
				return generateUrl('/svg/integration_twitter/sound?color=ffffff')
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
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
