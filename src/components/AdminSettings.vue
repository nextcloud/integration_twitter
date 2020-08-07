<template>
    <div id="twitter_prefs" class="section">
            <h2>
                <a class="icon icon-twitter"></a>
                {{ t('twitter', 'Twitter') }}
            </h2>
            <p class="settings-hint">
                {{ t('twitter', 'If you want to allow your Nextcloud users to use OAuth to authenticate to https://twitter.com, create a Twitter application in your Twitter settings and set the ID and secret here.') }}
                <br/>
                {{ t('twitter', 'Make sure you set the "redirect_uri" to') }}
                <br/><b> {{ redirect_uri }} </b>
            </p>
            <div class="grid-form">
                <label for="twitter-client-id">
                    <a class="icon icon-category-auth"></a>
                    {{ t('twitter', 'Twitter application consumer key') }}
                </label>
                <input id="twitter-client-id" type="password" v-model="state.consumer_key" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('twitter', 'Consumer key of your Twitter application')" />
                <label for="twitter-client-secret">
                    <a class="icon icon-category-auth"></a>
                    {{ t('twitter', 'Twitter application consumer secret') }}
                </label>
                <input id="twitter-client-secret" type="password" v-model="state.consumer_secret" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('twitter', 'Consumer secret of your Twitter application')" />
                <label for="twitter-oauth-token">
                    <a class="icon icon-category-auth"></a>
                    {{ t('twitter', 'Twitter OAuth access token') }}
                </label>
                <input id="twitter-oauth-token" type="password" v-model="state.oauth_token" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('twitter', 'OAuth access token of your Twitter application')" />
                <label for="twitter-oauth-token-secret">
                    <a class="icon icon-category-auth"></a>
                    {{ t('twitter', 'Twitter OAuth token secret') }}
                </label>
                <input id="twitter-oauth-token-secret" type="password" v-model="state.oauth_token_secret" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('twitter', 'OAuth token secret your Twitter application')" />
            </div>
    </div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
    name: 'AdminSettings',

    props: [],
    components: {
    },

    mounted() {
    },

    data() {
        return {
            state: loadState('twitter', 'admin-config'),
            // to prevent some browsers to fill fields with remembered passwords
            readonly: true,
            redirect_uri: 'web+nextcloudtwitter://',
        }
    },

    watch: {
    },

    methods: {
        onInput() {
            const that = this
            delay(function() {
                that.saveOptions()
            }, 2000)()
        },
        saveOptions() {
            const req = {
                values: {
                    consumer_key: this.state.consumer_key,
                    consumer_secret: this.state.consumer_secret,
                    oauth_token: this.state.oauth_token,
                    oauth_token_secret: this.state.oauth_token_secret,
                }
            }
            const url = generateUrl('/apps/twitter/admin-config')
            axios.put(url, req)
                .then(function (response) {
                    showSuccess(t('twitter', 'Twitter admin options saved.'))
                })
                .catch(function (error) {
                    showError(t('twitter', 'Failed to save Twitter admin options') +
                        ': ' + error.response.request.responseText
                    )
                })
                .then(function () {
                })
        },
    }
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
body.dark .icon-twitter {
    background-image: url(./../../img/app.svg);
}
</style>