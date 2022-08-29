<template>
	<div id="twitter_prefs" class="section">
		<h2>
			<TwitterIcon class="icon" />
			{{ t('integration_twitter', 'Twitter integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_twitter', 'Leave all fields empty to use the default Nextcloud Twitter OAuth app.') }}
		</p>
		<p class="settings-hint">
			{{ t('integration_twitter', 'If you want to use your own Twitter OAuth app to authenticate to Twitter, create a Twitter application in your Twitter developer settings and put the "Consumer key" and "Consumer secret" below.') }}
		</p>
		<a href="https://developer.twitter.com/en/portal/projects-and-apps" target="_blank" class="external">
			{{ t('integration_twitter', 'Twitter developer app settings') }}
		</a>
		<br><br>
		<p class="settings-hint">
			<InformationOutlineIcon :size="20" class="icon" />
			{{ t('integration_twitter', 'Make sure you set this "callback URL" to your Twitter OAuth app:') }}
		</p>
		<strong>{{ redirect_uri }}</strong>
		<br><br>
		<p class="settings-hint">
			{{ t('integration_twitter', 'And give it "Read + Write + Direct Messages" permissions.') }}
		</p>
		<div id="twitter-content">
			<div class="line">
				<label for="twitter-client-id">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_twitter', 'Consumer key') }}
				</label>
				<input id="twitter-client-id"
					v-model="state.consumer_key"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_twitter', 'Consumer key of your Twitter application')"
					@focus="readonly = false"
					@input="onInput">
			</div>
			<div class="line">
				<label for="twitter-client-secret">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_twitter', 'Consumer secret') }}
				</label>
				<input id="twitter-client-secret"
					v-model="state.consumer_secret"
					type="password"
					:readonly="readonly"
					:placeholder="t('integration_twitter', 'Consumer secret of your Twitter application')"
					@input="onInput"
					@focus="readonly = false">
			</div>
			<div class="line">
				<label for="twitter-followed-user">
					<AccountIcon :size="20" class="icon" />
					{{ t('integration_twitter', 'User to follow') }}
				</label>
				<input id="twitter-followed-user"
					v-model="state.followed_user"
					type="text"
					:placeholder="followedUserString"
					@input="onInput">
			</div>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'

import TwitterIcon from './icons/TwitterIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
		TwitterIcon,
		KeyIcon,
		InformationOutlineIcon,
		AccountIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_twitter', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: 'web+nextcloudtwitter://',
			followedUserString: t('integration_twitter', 'Twitter user to follow in "User timeline" widget'),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			delay(() => {
				if (this.state.followed_user.match(/^@/)) {
					this.state.followed_user = this.state.followed_user.replace(/^@/, '')
				}
				this.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			const req = {
				values: {
					consumer_key: this.state.consumer_key,
					consumer_secret: this.state.consumer_secret,
					followed_user: this.state.followed_user,
				},
			}
			const url = generateUrl('/apps/integration_twitter/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_twitter', 'Twitter admin options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_twitter', 'Failed to save Twitter admin options')
						+ ': ' + error.response.request.responseText
					)
					console.debug(error)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
#twitter_prefs {
	#twitter-content{
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
			width: 300px;
		}
	}
}
</style>
