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
	name: 'DashboardHome',

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
			showMoreUrl: 'https://twitter.com/home',
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts#twitter_prefs'),
			darkThemeColor: OCA.Accessibility.theme === 'dark' ? 'ffffff' : '000000',
		}
	},

	computed: {
		items() {
			return this.notifications.map((n) => {
				return {
					id: n.id_str,
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getUserAvatarUrl(n),
					avatarUsername: this.getAvatarText(n),
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getMainText(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastId() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].id_str : null
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
		this.launchLoop()
	},

	mounted() {
	},

	methods: {
		async launchLoop() {
			// get my user name first
			try {
				const response = await axios.get(generateUrl('/apps/integration_twitter/username'))
				this.myUsername = response.data
			} catch (error) {
				console.debug(error)
			}
			// then launch the loop
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 120000)
		},
		fetchNotifications() {
			const req = {}
			if (this.lastId) {
				req.params = {
					since: this.lastId,
				}
			}
			axios.get(generateUrl('/apps/integration_twitter/home'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_twitter', 'Failed to get Twitter home timeline.'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			if (this.lastId) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && BigInt(this.lastId) < BigInt(newNotifications[i].id_str)) {
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
		},
		filter(notifications) {
			return notifications
		},
		getUserAvatarUrl(n) {
			if (n.retweeted_status && n.retweeted_status.user && n.retweeted_status.user.profile_image_url_https) {
				return generateUrl('/apps/integration_twitter/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(n.retweeted_status.user.profile_image_url_https)
			} else if (n.user && n.user.profile_image_url_https) {
				return generateUrl('/apps/integration_twitter/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(n.user.profile_image_url_https)
			} else {
				return ''
			}
		},
		getNotificationTypeImage(n) {
			if (n.retweeted_status) {
				return generateUrl('/svg/integration_twitter/retweet?color=ffffff')
			} else if (n.in_reply_to_screen_name) {
				return generateUrl('/svg/integration_twitter/reply?color=ffffff')
			}
			return ''
		},
		getAvatarText(n) {
			return n.user && n.sender_screen_name
				? n.sender_screen_name
				: ''
		},
		getNotificationTarget(n) {
			return n.user && n.user.screen_name && n.id_str
				? 'https://twitter.com/' + n.user.screen_name + '/status/' + n.id_str
				: ''
		},
		getMainText(n) {
			let text = n.retweeted_status && n.retweeted_status.text
				? n.retweeted_status.text
				: n.text

			while (text.startsWith('@')) {
				text = text.replace(/^@[^\s]*\s/, '')
			}
			return text
		},
		getSubline(n) {
			return n.retweeted_status && n.retweeted_status.user && n.retweeted_status.user.screen_name
				? '@' + n.retweeted_status.user.screen_name + ' (⮔@' + n.user.screen_name + ')'
				: n.in_reply_to_screen_name
					? n.in_reply_to_screen_name === this.myUsername
						? t('integration_twitter', '{user} to you', { user: '@' + n.user.screen_name })
						: t('integration_twitter', '{user1} to {user2}', { user1: '@' + n.user.screen_name, user2: n.in_reply_to_screen_name })
					: '@' + n.user.screen_name
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
