<template>
	<div id="twitter_prefs" class="section">
		<h2>
			<TwitterIcon class="icon" />
			{{ t('integration_twitter', 'Twitter integration') }}
		</h2>
		<div v-if="!state.oauth_token">
			<p class="settings-hint">
				<InformationOutlineIcon :size="20" class="icon" />
				{{ t('integration_twitter', 'Make sure you accepted the protocol registration on top of this page if you want to authenticate to Twitter.') }}
			</p>
			<span v-if="isChromium">
				<p class="settings-hint">
					{{ t('integration_twitter', 'With Chrome/Chromium, you should see a popup on browser top-left to authorize this page to open "web+nextcloudtwitter" links.') }}
					<br>
					{{ t('integration_twitter', 'If you don\'t see the popup, you can still click on this icon in the address bar.') }}
				</p>
				<img :src="chromiumImagePath">
				<br><br>
				<p class="settings-hint">
					{{ t('integration_twitter', 'Then authorize this page to open "web+nextcloudtwitter" links.') }}
					<br>
					{{ t('integration_twitter', 'If you still don\'t manage to get the protocol registered, check your settings on this page:') }}
				</p>
				<strong>chrome://settings/handlers</strong>
			</span>
			<span v-else-if="isFirefox">
				<p class="settings-hint">
					{{ t('integration_twitter', 'With Firefox, you should see a bar on top of this page to authorize this page to open "web+nextcloudtwitter" links.') }}
				</p>
				<img :src="firefoxImagePath">
			</span>
			<br><br>
		</div>
		<div v-if="showOAuth" id="twitter-content">
			<NcButton v-if="!state.oauth_token" @click="onOAuthClick">
				<template #icon>
					<OpenInNewIcon :size="20" />
				</template>
				{{ t('integration_twitter', 'Connect to Twitter') }}
			</NcButton>
			<div v-else>
				<div class="line">
					<label>
						<CheckIcon :size="20" class="icon" />
						{{ t('integration_twitter', 'Connected as {user}', { user: userName }) }}
					</label>
					<NcButton @click="onLogoutClick">
						<template #icon>
							<CloseIcon :size="20" />
						</template>
						{{ t('integration_twitter', 'Disconnect from Twitter') }}
					</NcButton>
				</div>
				<div class="line">
					<label for="twitter-followed-user">
						<AccountIcon :size="20" class="icon" />
						{{ t('integration_twitter', 'User to follow') }}
					</label>
					<span v-if="state.followed_user_admin">
						{{ followedUserAdminString }}
					</span>
					<input v-else
						id="twitter-followed-user"
						v-model="state.followed_user"
						type="text"
						:title="followedUserTitle"
						:placeholder="followedUserTitle"
						@input="onFollowedUserInput">
				</div>
			</div>
		</div>
		<p v-else class="settings-hint">
			{{ t('integration_twitter', 'You must access this page with HTTPS to be able to authenticate to Twitter.') }}
		</p>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'

import TwitterIcon from './icons/TwitterIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { detectBrowser, delay } from '../utils.js'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'PersonalSettings',

	components: {
		NcButton,
		TwitterIcon,
		InformationOutlineIcon,
		CloseIcon,
		CheckIcon,
		OpenInNewIcon,
		AccountIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_twitter', 'user-config'),
			readonly: true,
			chromiumImagePath: imagePath('integration_twitter', 'chromium.png'),
			firefoxImagePath: imagePath('integration_twitter', 'firefox.png'),
			isChromium: detectBrowser() === 'chrome',
			isFirefox: detectBrowser() === 'firefox',
			followedUserTitle: t('integration_twitter', 'Display name of Twitter user to follow in "User timeline" widget'),
		}
	},

	computed: {
		followedUserAdminString() {
			return t('integration_twitter', 'Defined by an administrator: "@{name}"', { name: this.state.followed_user_admin })
		},
		showOAuth() {
			return window.location.protocol === 'https:' && this.state.consumer_key && this.state.consumer_secret
		},
		userName() {
			return this.state.name + ' (@' + this.state.screen_name + ')'
		},
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const twToken = urlParams.get('twitterToken')
		if (twToken === 'success') {
			showSuccess(t('integration_twitter', 'Successfully connected to Twitter!'))
		} else if (twToken === 'error') {
			showError(t('integration_twitter', 'Twitter OAuth error:') + ' ' + urlParams.get('message'))
		}

		// register protocol handler
		if (window.isSecureContext && window.navigator.registerProtocolHandler) {
			const ncUrl = window.location.protocol
				+ '//' + window.location.hostname
				+ window.location.pathname.replace('settings/user/connected-accounts', '').replace('/index.php/', '')
			window.navigator.registerProtocolHandler(
				'web+nextcloudtwitter',
				generateUrl('/apps/integration_twitter/oauth-redirect') + '?url=%s',
				t('integration_twitter', 'Nextcloud Twitter integration on {ncUrl}', { ncUrl })
			)
		}
	},

	methods: {
		onFollowedUserInput() {
			delay(() => {
				if (this.state.followed_user.match(/^@/)) {
					this.state.followed_user = this.state.followed_user.replace(/^@/, '')
				}
				this.saveOptions({ followed_user: this.state.followed_user })
			}, 2000)()
		},
		onLogoutClick() {
			this.state.oauth_token = ''
			this.saveOptions({ oauth_token: this.state.oauth_token })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_twitter/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_twitter', 'Twitter options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_twitter', 'Failed to save Twitter options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		onOAuthClick() {
			const url = generateUrl('/apps/integration_twitter/oauth-step1')
			axios.get(url)
				.then((response) => {
					this.step2(response.data)
				})
				.catch((error) => {
					showError(
						t('integration_twitter', 'Failed to request Twitter 1st step OAuth token')
						+ ': ' + error.response.request.responseText
					)
					console.debug(error)
				})
				.then(() => {
				})
		},
		step2(data) {
			if (!data.startsWith('http')) {
				showError(
					t('integration_twitter', 'OAuth failure')
					+ ': ' + data
				)
			} else {
				window.location.replace(data)
			}
		},
	},
}
</script>

<style scoped lang="scss">
#twitter_prefs {
	#twitter-content {
		margin-left: 40px;
	}
	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 250px;
		}
	}
}
</style>
