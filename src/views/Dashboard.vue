<template>
    <DashboardWidget :items="items"
        :showMoreUrl="showMoreUrl"
        @unsubscribe="onUnsubscribe"
        @markRead="onMarkRead"
        :loading="state === 'loading'"
        :itemMenu="itemMenu">
        <template v-slot:empty-content>
            <div v-if="state === 'no-token'">
                <a :href="settingsUrl">
                    {{ t('twitter', 'Click here to configure the access to your Twitter account.')}}
                </a>
            </div>
            <div v-else-if="state === 'error'">
                <a :href="settingsUrl">
                    {{ t('twitter', 'Incorrect access token.') }}
                    {{ t('twitter', 'Click here to configure the access to your Twitter account.')}}
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
import { showSuccess, showError } from '@nextcloud/dialogs'
import { getLocale } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { DashboardWidget } from '@nextcloud/vue-dashboard'

export default {
    name: 'Dashboard',

    props: [],
    components: {
        DashboardWidget,
    },

    beforeMount() {
        this.fetchNotifications()
        this.loop = setInterval(() => this.fetchNotifications(), 15000)
    },

    mounted() {
    },

    data() {
        return {
            notifications: [],
            showMoreUrl: 'https://twitter.com/notifications',
            // lastDate could be computed but we want to keep the value when first notification is removed
            // to avoid getting it again on next request
            lastDate: null,
            locale: getLocale(),
            loop: null,
            state: 'loading',
            settingsUrl: generateUrl('/settings/user/linked-accounts'),
            darkThemeColor: OCA.Accessibility.theme === 'dark' ? '181818' : 'ffffff',
            itemMenu: {
                'markRead': {
                    text: t('twitter', 'Mark as read'),
                    icon: 'icon-checkmark',
                },
                'unsubscribe': {
                    text: t('twitter', 'Unsubscribe'),
                    icon: 'icon-twitter-unsubscribe',
                }
            },
        }
    },

    computed: {
        items() {
            return this.notifications.map((n) => {
                return {
                    id: n.id,
                    targetUrl: this.getNotificationTarget(n),
                    avatarUrl: this.getRepositoryAvatarUrl(n),
                    //avatarUsername: '',
                    overlayIconUrl: this.getNotificationTypeImage(n),
                    mainText: n.subject.title,
                    subText: this.getSubline(n),
                }
            })
        },
        lastMoment() {
            return moment(this.lastDate)
        },
    },

    methods: {
        fetchNotifications() {
            const req = {}
            if (this.lastDate) {
                req.params = {
                    since: this.lastDate
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
                    console.log(error)
                }
            })
        },
        processNotifications(newNotifications) {
            if (this.lastDate) {
                // just add those which are more recent than our most recent one
                let i = 0;
                while (i < newNotifications.length && this.lastMoment.isBefore(newNotifications[i].updated_at)) {
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
            this.lastDate = (nbNotif > 0) ? this.notifications[0].updated_at : null
        },
        filter(notifications) {
            // only keep the unread ones with specific reasons
            return notifications.filter((n) => {
                return (n.unread && ['assign', 'mention', 'review_requested'].includes(n.reason))
            })
        },
        onUnsubscribe(item) {
            const i = this.notifications.findIndex((n) => n.id === item.id)
            if (i !== -1) {
                this.notifications.splice(i, 1)
            }
            this.editNotification(item, 'unsubscribe')
        },
        onMarkRead(item) {
            const i = this.notifications.findIndex((n) => n.id === item.id)
            if (i !== -1) {
                this.notifications.splice(i, 1)
            }
            this.editNotification(item, 'mark-read')
        },
        editNotification(item, action) {
            axios.put(generateUrl('/apps/twitter/notifications/' + item.id + '/' + action)).then((response) => {
            }).catch((error) => {
                showError(t('twitter', 'Failed to edit Twitter notification.'))
            })
        },
        getRepositoryAvatarUrl(n) {
            return (n.repository && n.repository.owner && n.repository.owner.avatar_url) ?
                    generateUrl('/apps/twitter/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(n.repository.owner.avatar_url) :
                    ''
        },
        getNotificationTarget(n) {
            return n.subject.url
                .replace('api.twitter.com', 'twitter.com')
                .replace('/repos/', '/')
                .replace('/pulls/', '/pull/')
        },
        getNotificationContent(n) {
            // reason : mention, comment, review_requested, state_change
            if (n.reason === 'mention') {
                if (n.subject.type === 'PullRequest') {
                    return t('twitter', 'You were mentioned in a pull request')
                } else if (n.subject.type === 'Issue') {
                    return t('twitter', 'You were mentioned in an issue')
                }
            } else if (n.reason === 'comment') {
                return t('twitter', 'Comment')
            } else if (n.reason === 'review_requested') {
                return t('twitter', 'Your review was requested')
            } else if (n.reason === 'state_change') {
                if (n.subject.type === 'PullRequest') {
                    return t('twitter', 'Pull request state changed')
                } else if (n.subject.type === 'Issue') {
                    return t('twitter', 'Issue state changed')
                }
            } else if (n.reason === 'assign') {
                return t('twitter', 'You are assigned')
            }
            return ''
        },
        getNotificationActionChar(n) {
            if (['review_requested', 'assign'].includes(n.reason)) {
                return '👁 '
            } else if (['comment', 'mention'].includes(n.reason)) {
                return '🗨 '
            }
            return ''
        },
        getSubline(n) {
            return this.getNotificationActionChar(n) + ' ' + n.repository.name + this.getTargetIdentifier(n)
        },
        getNotificationTypeImage(n) {
            if (n.subject.type === 'PullRequest') {
                return generateUrl('/svg/twitter/pull_request?color=ffffff')
            } else if (n.subject.type === 'Issue') {
                return generateUrl('/svg/twitter/issue?color=ffffff')
            }
            return ''
        },
        getTargetIdentifier(n) {
            if (['PullRequest', 'Issue'].includes(n.subject.type)) {
                const parts = n.subject.url.split('/')
                return '#' + parts[parts.length - 1]
            }
            return ''
        },
        getFormattedDate(n) {
            return moment(n.updated_at).locale(this.locale).format('LLL')
        },
    },
}
</script>

<style scoped lang="scss">
::v-deep .icon-twitter-unsubscribe {
    background-color: var(--color-main-text);
    padding: 0 !important;
    mask: url(./../../img/unsub.svg) no-repeat;
    mask-size: 18px 18px;
    mask-position: center;
    -webkit-mask: url(./../../img/unsub.svg) no-repeat;
    -webkit-mask-size: 18px 18px;
    -webkit-mask-position: center;
    min-width: 44px !important;
    min-height: 44px !important;
}
</style>
