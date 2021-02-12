<template>
	<div id="twitter_prefs" class="section">
		<h2>
			<a class="icon icon-twitter" />
			{{ t('integration_twitter', 'Twitter integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_twitter', 'Leave all fields empty to use the default Nextcloud Twitter OAuth app.') }}
			<br><br>
			{{ t('integration_twitter', 'If you want to use your own Twitter OAuth app to authenticate to Twitter, create a Twitter application in your Twitter developer settings and put the "Consumer key" and "Consumer secret" below.') }}
			<a href="https://developer.twitter.com/en/portal/projects-and-apps" target="_blank" class="external">
				{{ t('integration_twitter', 'Twitter developer app settings') }}
			</a>
			<br><br>
			<span class="icon icon-details" />
			{{ t('integration_twitter', 'Make sure you set this "callback URL" to your Twitter OAuth app:') }}
			<b> {{ redirect_uri }} </b>
			<br>
			{{ t('integration_twitter', 'And give it "Read + Write + Direct Messages" permissions.') }}
		</p>
		<div class="grid-form">
			<label for="twitter-client-id">
				<a class="icon icon-category-auth" />
				{{ t('integration_twitter', 'Consumer key') }}
			</label>
			<input id="twitter-client-id"
				v-model="state.consumer_key"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_twitter', 'Consumer key of your Twitter application')"
				@focus="readonly = false"
				@input="onInput">
			<label for="twitter-client-secret">
				<a class="icon icon-category-auth" />
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
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'AdminSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_twitter', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: 'web+nextcloudtwitter://',
		}
	},

	watch: {
	},

	mounted() {
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
					consumer_key: this.state.consumer_key,
					consumer_secret: this.state.consumer_secret,
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
.grid-form label {
	line-height: 38px;
}

.grid-form input {
	width: 100%;
}

.grid-form {
	width: 500px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	margin-left: 30px;
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

body.theme--dark .icon-twitter {
	background-image: url(./../../img/app.svg);
}

</style>
