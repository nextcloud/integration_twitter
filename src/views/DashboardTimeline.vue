<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="showMoreText"
		:loading="state === 'loading'">
		<template #empty-content>
			<EmptyContent
				v-if="emptyContentMessage">
				<template #icon>
					<component :is="emptyContentIcon" />
				</template>
				<template #desc>
					{{ emptyContentMessage }}
					<div v-if="state === 'no-token' || state === 'error' || state === 'nothing-to-show'"
						class="connect-button">
						<a :href="settingsUrl">
							<NcButton>
								<template #icon>
									<LoginVariantIcon />
								</template>
								{{ errorButtonText }}
							</NcButton>
						</a>
					</div>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import LoginVariantIcon from 'vue-material-design-icons/LoginVariant.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import TwitterIcon from '../components/icons/TwitterIcon.vue'

import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent.js'
import NcButton from '@nextcloud/vue/dist/Components/Button.js'

import { convert } from 'html-to-text'

export default {
	name: 'DashboardTimeline',

	components: {
		DashboardWidget,
		EmptyContent,
		NcButton,
		LoginVariantIcon,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
		mode: {
			type: String,
			default: 'home',
		},
	},

	data() {
		return {
			notifications: [],
			showMoreUrl: 'https://twitter.com/home',
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts#twitter_prefs'),
			windowVisibility: true,
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
				return t('integration_twitter', 'No tweets!')
			} else if (this.state === 'nothing-to-show') {
				return t('integration_twitter', 'No Twitter user timeline to show')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return TwitterIcon
			} else if (this.state === 'error') {
				return CloseIcon
			} else if (this.state === 'ok') {
				return CheckIcon
			} else if (this.state === 'nothing-to-show') {
				return CloseIcon
			}
			return CheckIcon
		},
		errorButtonText() {
			if (this.state === 'nothing-to-show') {
				return t('integration_twitter', 'Configure Twitter connected account')
			} else {
				return t('integration_twitter', 'Connect to Twitter')
			}
		},
		notificationsAPIUrl() {
			return generateUrl('/apps/integration_twitter/' + this.mode)
		},
		showMoreText() {
			if (this.mode === 'home') {
				return this.title
			} else {
				return t('integration_twitter', 'about {name}', { name: this.title })
			}
		},
	},

	watch: {
		windowVisibility(newValue) {
			if (newValue) {
				this.launchLoop()
			} else {
				this.stopLoop()
			}
		},
	},

	beforeDestroy() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		console.debug('in dashboardTIMELINE')
		this.launchLoop()
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	mounted() {
	},

	methods: {
		changeWindowVisibility() {
			this.windowVisibility = !document.hidden
		},
		stopLoop() {
			clearInterval(this.loop)
		},
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
			axios.get(this.notificationsAPIUrl, req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_twitter', 'Failed to get Twitter home timeline'))
					this.state = 'error'
				} else if (error.response && error.response.status === 418) {
					showError(t('integration_twitter', 'No Twitter user timeline to show'))
					this.state = 'nothing-to-show'
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
			if (n.retweeted_status && n.retweeted_status.user && n.retweeted_status.user.profile_image_url_https && n.retweeted_status.user.id_str) {
				return generateUrl('/apps/integration_twitter/avatar?') + encodeURIComponent('userId') + '=' + encodeURIComponent(n.retweeted_status.user.id_str)
			} else if (n.user && n.user.profile_image_url_https && n.user.id_str) {
				return generateUrl('/apps/integration_twitter/avatar?') + encodeURIComponent('userId') + '=' + encodeURIComponent(n.user.id_str)
			} else {
				return ''
			}
		},
		getNotificationTypeImage(n) {
			if (n.retweeted_status) {
				return imagePath('integration_twitter', 'retweet.svg')
			} else if (n.in_reply_to_screen_name) {
				return imagePath('integration_twitter', 'reply.svg')
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
				text = text.replace(/^@[^\s]*\s?/, '')
			}
			return convert(text)
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
