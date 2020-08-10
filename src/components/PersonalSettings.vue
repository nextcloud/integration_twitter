<template>
	<div v-if="showOAuth" id="twitter_prefs" class="section">
		<h2>
			<a class="icon icon-twitter" />
			{{ t('twitter', 'Twitter') }}
		</h2>
		<p class="settings-hint">
			{{ t('twitter', 'Make sure you accepted the protocol registration on top of this page if you want to authenticate to Twitter.') }}
			<br><b> {{ redirect_uri }} </b>
		</p>
		<div class="twitter-grid-form">
			<label for="twitter-token">
				<a class="icon icon-category-auth" />
				{{ t('twitter', 'Twitter access token') }}
			</label>
			<input id="twitter-token"
				v-model="state.oauth_token"
				type="password"
				:readonly="readonly"
				:placeholder="t('twitter', 'Token obtained with OAuth')"
				@focus="readonly = false"
				@input="onInput">
			<button id="twitter-oauth" @click="onOAuthClick">
				<span class="icon icon-external" />
				{{ t('twitter', 'Get access with OAuth') }}
			</button>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('twitter', 'user-config'),
			readonly: true,
		}
	},

	computed: {
		showOAuth() {
			return this.state.consumer_key && this.state.consumer_secret
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		const urlParams = new URLSearchParams(paramString)
		const twToken = urlParams.get('twitterToken')
		if (twToken === 'success') {
			showSuccess(t('twitter', 'Twitter OAuth access token successfully retrieved!'))
		} else if (twToken === 'error') {
			showError(t('twitter', 'Twitter OAuth error:') + ' ' + urlParams.get('message'))
		}

		// register protocol handler
		if (window.isSecureContext && window.navigator.registerProtocolHandler) {
			window.navigator.registerProtocolHandler('web+nextcloudtwitter', generateUrl('/apps/twitter/oauth-redirect') + '?url=%s', 'Nextcloud Twitter integration')
		}
	},

	methods: {
		onInput() {
			const that = this
			delay(() => {
				that.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			const req = {
				values: {
					oauth_token: this.state.oauth_token,
				},
			}
			const url = generateUrl('/apps/twitter/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('twitter', 'Twitter options saved.'))
				})
				.catch((error) => {
					showError(
						t('twitter', 'Failed to save Twitter options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		onOAuthClick() {
			const url = generateUrl('/apps/twitter/oauth-step1')
			axios.get(url)
				.then((response) => {
					this.step2(response.data)
				})
				.catch((error) => {
					showError(
						t('twitter', 'Failed to request Twitter 1st step OAuth token')
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
					t('twitter', 'OAuth failure')
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
.twitter-grid-form label {
	line-height: 38px;
}
.twitter-grid-form input {
	width: 100%;
}
.twitter-grid-form {
	max-width: 900px;
	display: grid;
	grid-template: 1fr / 1fr 1fr 1fr;
	margin-left: 30px;
	button .icon {
		margin-bottom: -1px;
	}
}
#twitter_prefs .icon {
	display: inline-block;
	width: 32px;
}
#twitter_prefs .grid-form .icon {
	margin-bottom: -3px;
}
.icon-twitter {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.dark .icon-twitter {
	background-image: url(./../../img/app.svg);
}
</style>
