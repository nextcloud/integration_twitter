<template>
	<div id="twitter_prefs" class="section">
		<h2>
			<a class="icon icon-twitter" />
			{{ t('integration_twitter', 'Twitter integration') }}
		</h2>
		<div v-if="showOAuth" class="twitter-content">
			<div v-if="!state.oauth_token">
				<p class="settings-hint">
					{{ t('integration_twitter', 'Make sure you accepted the protocol registration on top of this page if you want to authenticate to Twitter.') }}
				</p>
				<button v-if="!state.oauth_token" id="twitter-oauth" @click="onOAuthClick">
					<span class="icon icon-external" />
					{{ t('integration_twitter', 'Connect to Twitter') }}
				</button>
			</div>
			<div v-else class="twitter-grid-form">
				<label>
					<a class="icon icon-checkmark-color" />
					{{ t('integration_twitter', 'Connected as {user}', { user: userName }) }}
				</label>
				<button id="twitter-rm-cred" @click="onLogoutClick">
					<span class="icon icon-close" />
					{{ t('integration_twitter', 'Disconnect from Twitter') }}
				</button>
			</div>
		</div>
		<p v-else class="settings-hint">
			{{ t('integration_twitter', 'You must access this page with HTTPS to be able to authenticate to Twitter.') }}
		</p>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_twitter', 'user-config'),
			readonly: true,
		}
	},

	computed: {
		showOAuth() {
			return window.location.protocol === 'https:' && this.state.consumer_key && this.state.consumer_secret
		},
		userName() {
			return this.state.name + ' (@' + this.state.screen_name + ')'
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const twToken = urlParams.get('twitterToken')
		if (twToken === 'success') {
			showSuccess(t('integration_twitter', 'Twitter OAuth access token successfully retrieved!'))
		} else if (twToken === 'error') {
			showError(t('integration_twitter', 'Twitter OAuth error:') + ' ' + urlParams.get('message'))
		}

		// register protocol handler
		if (window.isSecureContext && window.navigator.registerProtocolHandler) {
			window.navigator.registerProtocolHandler('web+nextcloudtwitter', generateUrl('/apps/integration_twitter/oauth-redirect') + '?url=%s', 'Nextcloud Twitter integration')
		}
	},

	methods: {
		onLogoutClick() {
			this.state.oauth_token = ''
			this.saveOptions()
		},
		saveOptions() {
			const req = {
				values: {
					oauth_token: this.state.oauth_token,
				},
			}
			const url = generateUrl('/apps/integration_twitter/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_twitter', 'Twitter options saved.'))
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
#twitter_prefs .icon {
	display: inline-block;
	width: 32px;
}
.icon-twitter {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-twitter {
	background-image: url(./../../img/app.svg);
}
.twitter-content {
	margin-left: 40px;
}
.twitter-grid-form {
    max-width: 600px;
    display: grid;
    grid-template: 1fr / 1fr 1fr;
    button .icon {
        margin-bottom: -1px;
    }
}
.twitter-grid-form label {
    line-height: 38px;
}
#twitter-rm-cred {
	max-height: 34px;
}
</style>
